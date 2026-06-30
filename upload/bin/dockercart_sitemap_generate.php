#!/usr/bin/env php
<?php
/**
 * DockerCart Sitemap Generate — CLI Worker
 *
 * Bootstraps OpenCart and regenerates sitemap XML files directly
 * (no HTTP/curl bridge, no catalog controller dependency).
 * Called by the scheduler daemon or manually.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_sitemap_generate.php
 *
 * Exit codes:
 *   0 — success
 *   1 — failure (config missing, generation error, etc.)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

// Fake minimal HTTP environment so OpenCart internals don't choke.
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[dockercart-sitemap] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[dockercart-sitemap] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

try {
	$registry = new Registry();

	// Config
	$config = new Config();
	$config->load('default');
	$config->load('catalog');
	$registry->set('config', $config);

	// Log
	$log = new Log($config->get('error_filename') ?: 'error.log');
	$registry->set('log', $log);

	// Event
	$event = new Event($registry);
	$registry->set('event', $event);

	// Loader
	$loader = new Loader($registry);
	$registry->set('load', $loader);

	// Database
	$db = new DB(
		$config->get('db_engine')    ?: 'mysqli',
		$config->get('db_hostname')  ?: 'mariadb',
		$config->get('db_username')  ?: 'dockercart',
		$config->get('db_password')  ?: 'dockercart_password',
		$config->get('db_database')  ?: 'dockercart',
		$config->get('db_port')      ?: '3306'
	);
	$registry->set('db', $db);

	// Cache
	$cache = new Cache($config->get('cache_engine') ?: 'file', (int)($config->get('cache_expire') ?: 3600));
	$registry->set('cache', $cache);

	// Defaults required by catalog
	$config->set('config_store_id',    0);
	$config->set('config_language_id', 1);

	// Load all settings from DB (same as catalog startup controller)
	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
	foreach ($query->rows as $result) {
		if (!$result['serialized']) {
			$config->set($result['key'], $result['value']);
		} else {
			$config->set($result['key'], json_decode($result['value'], true));
		}
	}

	// URL Router — required by buildAlternateUrls()
	$url = new Url($config->get('config_url') ?: HTTP_SERVER, $config->get('config_ssl') ?: HTTPS_SERVER);
	$registry->set('url', $url);

	// Register SEO URL rewriter so $url->link() returns clean SEO URLs
	if ($config->get('config_seo_url')) {
		require_once DIR_APPLICATION . 'controller/startup/seo_url.php';
		$seoUrl = new ControllerStartupSeoUrl($registry);
		$seoUrl->initializeRequestState();
		$url->addRewrite($seoUrl);
	}

	// Session mock — buildAlternateUrls() writes to $this->session->data['language']
	// In CLI there is no real session; provide a minimal object.
	$session = new stdClass();
	$session->data = ['language' => $config->get('config_language') ?: 'en-gb'];
	$registry->set('session', $session);

	echo "[dockercart-sitemap] Starting sitemap generation...\n";

	// ── Helper: build alternate hreflang URLs for a route ──────────
	$buildAlternateUrls = function(string $route, string $args, array $languages) use ($config, $url, $session) {
		$old_config_language_id = $config->get('config_language_id');
		$old_language = $session->data['language'] ?? null;

		$urls = [];
		foreach ($languages as $l) {
			$config->set('config_language_id', (int)$l['language_id']);
			$session->data['language'] = $l['code'];

			$u = $url->link($route, $args !== '' ? $args : '', false);

			$urls[] = [
				'loc' => $u,
				'hreflang' => $l['code']
			];
		}

		// Restore original state
		$config->set('config_language_id', $old_config_language_id);
		if ($old_language !== null) {
			$session->data['language'] = $old_language;
		}

		return $urls;
	};

	// ── Generate sitemap XML ───────────────────────────────────────

	$query = $db->query("SELECT language_id, code FROM " . DB_PREFIX . "language WHERE status = 1 ORDER BY language_id");
	$languages = $query->rows;

	if (empty($languages)) {
		$languages = [];
		$code = $config->get('config_language') ?: 'en-gb';
		$languages[] = [
			'language_id' => (int)$config->get('config_language_id'),
			'code' => $code
		];
	}

	// Remove any previous sitemap files (xml + optional gz)
	@array_map('unlink', glob(DIR_APPLICATION . '../sitemap*.xml'));
	@array_map('unlink', glob(DIR_APPLICATION . '../sitemap*.xml.gz'));

	$create_gzip = !empty($config->get('dockercart_sitemap_create_gzip'));

	$max_urls = (int)($config->get('dockercart_sitemap_max_urls') ?: 50000);

	$max_file_size_mb = (int)($config->get('dockercart_sitemap_max_file_size_mb') ?: 50);
	$max_file_bytes = (int)($max_file_size_mb * 1024 * 1024);

	$priority_product = (float)($config->get('dockercart_sitemap_product_priority') ?: 0.8);
	$changefreq_product = $config->get('dockercart_sitemap_product_changefreq') ?: 'weekly';
	$priority_category = (float)($config->get('dockercart_sitemap_category_priority') ?: 0.9);
	$changefreq_category = $config->get('dockercart_sitemap_category_changefreq') ?: 'weekly';
	$priority_manufacturer = (float)($config->get('dockercart_sitemap_manufacturer_priority') ?: 0.7);
	$changefreq_manufacturer = $config->get('dockercart_sitemap_manufacturer_changefreq') ?: 'monthly';

	$sitemap_files = [];
	$file_index = 0;
	$url_count_in_file = 0;
	$current_writer = null;

	// File lock to prevent concurrent generation
	$lock_file = DIR_APPLICATION . '../sitemap.lock';
	$lock_fp = @fopen($lock_file, 'c');
	if ($lock_fp === false) {
		throw new \RuntimeException('Cannot create lock file: ' . $lock_file);
	}

	$lock_acquired = false;
	$lock_start = time();
	while (!$lock_acquired) {
		$lock_acquired = @flock($lock_fp, LOCK_EX | LOCK_NB);
		if ($lock_acquired) break;
		if ((time() - $lock_start) > 10) {
			fclose($lock_fp);
			throw new \RuntimeException('Could not acquire lock within 10 seconds');
		}
		usleep(200000);
	}

	$open_new_writer = function() use (&$file_index) {
		$file_index++;
		$final = DIR_APPLICATION . '../sitemap' . $file_index . '.xml';
		$tmp = $final . '.tmp';

		$w = new XMLWriter();
		$w->openURI($tmp);
		$w->startDocument('1.0', 'UTF-8');
		$w->startElement('urlset');
		$w->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
		$w->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');

		return [$w, $tmp, $final];
	};

	$close_writer = function($w, $tmp = null, $final = null) use ($create_gzip) {
		if (!$w) return;
		$w->endElement();
		$w->endDocument();
		$w->flush();

		if ($tmp !== null && $final !== null) {
			@rename($tmp, $final);
			@chmod($final, 0644);
			if ($create_gzip) {
				$gzfile = $final . '.gz';
				$content = @file_get_contents($final);
				if ($content !== false) {
					$gzdata = gzencode($content, 9);
					@file_put_contents($gzfile, $gzdata, LOCK_EX);
					@chmod($gzfile, 0644);
				}
			}
		}
	};

	$write_entry = function($urls, $lastmod, $changefreq, $priority) use (&$current_writer, &$url_count_in_file, &$sitemap_files, $open_new_writer, $close_writer, $max_urls, $max_file_bytes) {
		if ($current_writer === null || $url_count_in_file >= $max_urls) {
			if ($current_writer !== null) {
				$close_writer($current_writer['writer'], $current_writer['tmp'], $current_writer['final']);
				$sitemap_files[] = $current_writer['final'];
			}

			[$w, $tmp, $final] = $open_new_writer();
			$current_writer = ['writer' => $w, 'tmp' => $tmp, 'final' => $final];
			$url_count_in_file = 0;
		}

		$w = $current_writer['writer'];

		$w->startElement('url');
		$loc_value = html_entity_decode($urls[0]['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8');
		$w->writeElement('loc', $loc_value);
		$w->writeElement('lastmod', $lastmod);
		$w->writeElement('changefreq', $changefreq);
		$w->writeElement('priority', number_format((float)$priority, 1, '.', ''));

		if (count($urls) > 1) {
			foreach ($urls as $url_entry) {
				$w->startElementNS('xhtml', 'link', null);
				$w->writeAttribute('rel', 'alternate');
				$w->writeAttribute('hreflang', $url_entry['hreflang']);
				$href_value = html_entity_decode($url_entry['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8');
				$w->writeAttribute('href', $href_value);
				$w->endElement();
			}
		}

		$w->endElement();

		if (method_exists($w, 'flush')) {
			$w->flush();
		}

		$url_count_in_file++;

		$current_size = @filesize($current_writer['tmp']);
		if ($current_size !== false && $current_size >= $max_file_bytes) {
			$close_writer($current_writer['writer'], $current_writer['tmp'], $current_writer['final']);
			$sitemap_files[] = $current_writer['final'];
			$current_writer = null;
			$url_count_in_file = 0;
		}
	};

	// Home page
	$urls_home = $buildAlternateUrls('common/home', '', $languages);
	$write_entry($urls_home, date('Y-m-d'), 'daily', '1.0');

	// Products
	if ($config->get('dockercart_sitemap_products')) {
		$query = $db->query("SELECT product_id, date_modified FROM " . DB_PREFIX . "product WHERE status = 1 ORDER BY product_id");
		$products = $query->rows;

		foreach ($products as $product) {
			$urls = $buildAlternateUrls('product/product', 'product_id=' . $product['product_id'], $languages);

			$lastmod = $product['date_modified'] ?? date('Y-m-d');
			if (strlen($lastmod) > 10) {
				$lastmod = date('Y-m-d', strtotime($lastmod));
			}

			$write_entry($urls, $lastmod, $changefreq_product, $priority_product);
		}
	}

	// Categories
	if ($config->get('dockercart_sitemap_categories')) {
		$query = $db->query("SELECT category_id, date_modified FROM " . DB_PREFIX . "category WHERE status = 1 ORDER BY category_id");
		$categories = $query->rows;

		foreach ($categories as $category) {
			$urls = $buildAlternateUrls('product/category', 'path=' . $category['category_id'], $languages);

			$lastmod = $category['date_modified'] ?? date('Y-m-d');
			if (strlen($lastmod) > 10) {
				$lastmod = date('Y-m-d', strtotime($lastmod));
			}

			$write_entry($urls, $lastmod, $changefreq_category, $priority_category);
		}
	}

	// Manufacturers
	if ($config->get('dockercart_sitemap_manufacturers')) {
		$query = $db->query("SELECT manufacturer_id FROM " . DB_PREFIX . "manufacturer ORDER BY manufacturer_id");
		$manufacturers = $query->rows;

		foreach ($manufacturers as $manufacturer) {
			$urls = $buildAlternateUrls('product/manufacturer/info', 'manufacturer_id=' . $manufacturer['manufacturer_id'], $languages);
			$write_entry($urls, date('Y-m-d'), $changefreq_manufacturer, $priority_manufacturer);
		}
	}

	// Information pages
	if ($config->get('dockercart_sitemap_information')) {
		$query = $db->query("SELECT information_id FROM " . DB_PREFIX . "information WHERE status = 1 ORDER BY information_id");
		$informations = $query->rows;

		$info_priority = (float)($config->get('dockercart_sitemap_information_priority') ?: 0.5);
		$info_changefreq = $config->get('dockercart_sitemap_information_changefreq') ?: 'monthly';

		foreach ($informations as $info) {
			$urls = $buildAlternateUrls('information/information', 'information_id=' . $info['information_id'], $languages);
			$write_entry($urls, date('Y-m-d'), $info_changefreq, $info_priority);
		}
	}

	// Close last writer
	if ($current_writer !== null) {
		$close_writer($current_writer['writer'], $current_writer['tmp'], $current_writer['final']);
		$sitemap_files[] = $current_writer['final'];
	}

	// Build sitemap index
	$sitemap_url = rtrim($config->get('config_url') ?: HTTP_SERVER, '/');

	if (count($sitemap_files) === 1) {
		$single = $sitemap_files[0];
		$target = DIR_APPLICATION . '../sitemap.xml';

		@unlink($target);
		rename($single, $target);
		chmod($target, 0644);

		if ($create_gzip) {
			$gz = $target . '.gz';
			$content = @file_get_contents($target);
			if ($content !== false) {
				@file_put_contents($gz, gzencode($content, 9), LOCK_EX);
				@chmod($gz, 0644);
			}

			$index_writer = new XMLWriter();
			$index_writer->openMemory();
			$index_writer->startDocument('1.0', 'UTF-8');
			$index_writer->startElement('sitemapindex');
			$index_writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

			$name = basename($target) . '.gz';
			$index_writer->startElement('sitemap');
			$index_writer->writeElement('loc', $sitemap_url . '/' . $name);
			$index_writer->writeElement('lastmod', date('c', filemtime($gz ?: $target)));
			$index_writer->endElement();

			$index_writer->endElement();
			$index_writer->endDocument();
			$xmlindex = $index_writer->outputMemory();

			file_put_contents(DIR_APPLICATION . '../sitemap.xml', $xmlindex);
			chmod(DIR_APPLICATION . '../sitemap.xml', 0644);

			$sitemap_files = [$target, $gz];
		} else {
			$sitemap_files = [$target];
		}
	} elseif (count($sitemap_files) > 1) {
		$index_writer = new XMLWriter();
		$index_writer->openMemory();
		$index_writer->startDocument('1.0', 'UTF-8');
		$index_writer->startElement('sitemapindex');
		$index_writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

		foreach ($sitemap_files as $f) {
			$index_writer->startElement('sitemap');
			$name = basename($f);
			if ($create_gzip && file_exists($f . '.gz')) {
				$index_writer->writeElement('loc', $sitemap_url . '/' . $name . '.gz');
				$index_writer->writeElement('lastmod', date('c', filemtime($f . '.gz')));
			} else {
				$index_writer->writeElement('loc', $sitemap_url . '/' . $name);
				$index_writer->writeElement('lastmod', date('c', filemtime($f)));
			}
			$index_writer->endElement();
		}

		$index_writer->endElement();
		$index_writer->endDocument();
		$xmlindex = $index_writer->outputMemory();

		file_put_contents(DIR_APPLICATION . '../sitemap.xml', $xmlindex);
		chmod(DIR_APPLICATION . '../sitemap.xml', 0644);
	} else {
		@unlink(DIR_APPLICATION . '../sitemap.xml');
	}

	// Release lock
	if (is_resource($lock_fp)) {
		@flock($lock_fp, LOCK_UN);
		@fclose($lock_fp);
		@unlink(DIR_APPLICATION . '../sitemap.lock');
	}

	echo "[dockercart-sitemap] Done.\n";
	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[dockercart-sitemap] ERROR: " . $e->getMessage() . "\n");
	exit(1);
}
