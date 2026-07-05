<?php

declare(strict_types=1);

class DockercartExtensionStore {
	const YML_ENDPOINT = 'http://licensing.docker.localhost:8080/api/v1/yml/';
	const STORE_DOMAIN = 'https://dockercart.net/extensions';
	const CACHE_TTL = 1800;
	const LICENSING_CACHE_TTL = 1800;

	private $registry;
	private $cache_dir;
	private $api_url;

	private $module_info_cache = array();

	public function __construct($registry) {
		$this->registry = $registry;
		$this->cache_dir = DIR_STORAGE . 'dockercart/extension_store/';
		$this->api_url = defined('LICENSING_API_URL')
			? LICENSING_API_URL
			: 'http://licensing.docker.localhost:8080';
	}

	public function resolveLanguage(): string {
		$session = $this->registry->get('session');
		$config = $this->registry->get('config');

		$code = $session->data['language'] ?? $config->get('config_admin_language') ?? 'en-gb';

		$map = array(
			'en-gb' => 'en',
			'ru-ua' => 'ru',
			'uk-ua' => 'uk',
		);

		return $map[$code] ?? 'en';
	}

	public function getYml(string $lang): SimpleXMLElement {
		$lang = preg_replace('/[^a-z]/', '', $lang) ?: 'en';
		$cache_file = $this->cache_dir . 'yml_' . $lang . '.xml';

		if (is_file($cache_file) && (time() - filemtime($cache_file)) < self::CACHE_TTL) {
			$xml = simplexml_load_file($cache_file);

			if ($xml !== false) {
				return $xml;
			}
		}

		$url = self::YML_ENDPOINT . $lang;

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_ENCODING, '');

		$parsed = parse_url($url);
		$gateway_ip = @gethostbyname('host.docker.internal');

		if ($gateway_ip !== 'host.docker.internal') {
			curl_setopt($ch, CURLOPT_RESOLVE, array(
				$parsed['host'] . ':' . ($parsed['port'] ?? 80) . ':' . $gateway_ip
			));
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code !== 200 || empty($response)) {
			if ($lang !== 'en') {
				return $this->getYml('en');
			}

			throw new RuntimeException('Failed to fetch YML catalog. HTTP ' . $http_code);
		}

		$xml = simplexml_load_string($response);

		if ($xml === false) {
			throw new RuntimeException('Failed to parse YML catalog XML');
		}

		if (!is_dir($this->cache_dir)) {
			mkdir($this->cache_dir, 0750, true);
		}

		file_put_contents($cache_file, $response);

		return $xml;
	}

	public function clearCache(): void {
		if (!is_dir($this->cache_dir)) {
			return;
		}

		$files = glob($this->cache_dir . '{yml_*.xml,licensed_*.json}', GLOB_BRACE);

		if ($files === false) {
			return;
		}

		foreach ($files as $file) {
			unlink($file);
		}
	}

	public function buildCategoriesTree(SimpleXMLElement $yml): array {
		$categories = array();
		$children = array();

		foreach ($yml->shop->categories->category as $cat) {
			$id = (string) $cat['id'];
			$name = (string) $cat;
			$parent = isset($cat['parentId']) ? (string) $cat['parentId'] : null;

			$categories[$id] = array(
				'id' => $id,
				'name' => $name,
				'parent_id' => $parent,
				'children' => array(),
			);

			if ($parent !== null) {
				$children[$parent][] = $id;
			}
		}

		$tree = array();

		foreach ($categories as $id => &$cat) {
			if ($cat['parent_id'] === null) {
				$tree[] = &$cat;
			} elseif (isset($categories[$cat['parent_id']])) {
				$categories[$cat['parent_id']]['children'][] = &$cat;
			} else {
				$tree[] = &$cat;
			}
		}
		unset($cat);

		return $tree;
	}

	public function getCategoryDescendants(SimpleXMLElement $yml, string $category_id): array {
		$ids = array($category_id);
		$all_categories = array();

		foreach ($yml->shop->categories->category as $cat) {
			$all_categories[(string) $cat['id']] = array(
				'id' => (string) $cat['id'],
				'parent_id' => isset($cat['parentId']) ? (string) $cat['parentId'] : null,
			);
		}

		$stack = array($category_id);

		while (!empty($stack)) {
			$current = array_pop($stack);

			foreach ($all_categories as $id => $cat) {
				if ($cat['parent_id'] === $current && !in_array($id, $ids, true)) {
					$ids[] = $id;
					$stack[] = $id;
				}
			}
		}

		return $ids;
	}

	public function getOffers(SimpleXMLElement $yml, ?array $category_ids = null): array {
		$offers = array();

		foreach ($yml->shop->offers->offer as $offer) {
			$offer_category = (string) $offer->categoryId;

			if ($category_ids !== null && !in_array($offer_category, $category_ids, true)) {
				continue;
			}

			$pictures = array();

			foreach ($offer->picture as $pic) {
				$pictures[] = (string) $pic;
			}

			$params = array();

			foreach ($offer->param as $param) {
				$params[(string) $param['code']] = array(
					'name' => (string) $param['name'],
					'value' => (string) $param,
				);
			}

			$buy_url = '';

			if (!empty((string) $offer->url)) {
				$raw_url = (string) $offer->url;

				if (preg_match('#^https?://#', $raw_url)) {
					$buy_url = $raw_url;
				} else {
					$buy_url = self::STORE_DOMAIN . '/' . ltrim($raw_url, '/');
				}
			}

			if (empty($buy_url)) {
				$buy_url = self::STORE_DOMAIN;
			}

			$name = (string) $offer->name;

			if (empty($name)) {
				$name = (string) $offer->model;
			}

			if (empty($name)) {
				$name = 'Extension #' . (string) $offer['id'];
			}

			$description = '';

			if (isset($offer->description)) {
				$description = (string) $offer->description;
			}

			$offers[] = array(
				'id' => (string) $offer['id'],
				'sku' => (string) $offer['id'],
				'name' => $name,
				'description' => $description,
				'price' => (float) $offer->price,
				'currency' => (string) $offer->currencyId,
				'category_id' => $offer_category,
				'pictures' => $pictures,
				'params' => $params,
				'buy_url' => $buy_url,
				'available' => ((string) $offer['available']) === 'true',
				'version' => $params['version']['value'] ?? '',
				'price_type' => $params['price_type']['value'] ?? '',
			);
		}

		return $offers;
	}

	/**
	 * Merge YML catalog offers with licensed-module data from the licensing server.
	 * Returns offers enriched with state, license_key, and version/status info.
	 */
	public function getMergedOffers(SimpleXMLElement $yml, ?array $category_ids = null): array {
		$offers = $this->getOffers($yml, $category_ids);
		$licensed_modules = $this->getLicensedModules();
		$licensed_map = array();

		foreach ($licensed_modules as $mod) {
			if (!empty($mod['sku'])) {
				$licensed_map[$mod['sku']] = $mod;
			}
		}

		foreach ($offers as &$offer) {
			$offer['state'] = 'buy';
			$offer['license_key'] = null;
			$offer['license_status'] = null;
			$offer['is_licensed'] = false;
			$offer['installed_version'] = null;
			$offer['update_available'] = false;

			$sku = $offer['sku'];

			if (isset($licensed_map[$sku])) {
				$offer['is_licensed'] = true;
				$offer['license_key'] = $licensed_map[$sku]['licenseKey'] ?? null;
				$offer['license_status'] = $licensed_map[$sku]['licenseStatus'] ?? null;
			}

			$meta = $this->getInstalledMeta($sku);
			if ($meta) {
				$offer['installed_version'] = $meta['installed_version'];
			}

			if ($offer['installed_version'] && $offer['version']) {
				if (version_compare($offer['version'], $offer['installed_version'], '>')) {
					$offer['update_available'] = true;
				}
			}

			$offer['state'] = $this->resolveState($offer);

			if (!$offer['is_licensed'] && $offer['installed_version'] === null) {
				$offer['state'] = 'buy';
			}
		}
		unset($offer);

		return $offers;
	}

	public function resolveState(array $offer): string {
		$installed = $offer['installed_version'] !== null;
		$licensed = !empty($offer['is_licensed']);
		$update_available = !empty($offer['update_available']);
		$license_status = $offer['license_status'] ?? null;

		if (in_array($license_status, ['REVOKED', 'EXPIRED'], true)) {
			return strtolower($license_status);
		}

		if ($licensed && !$installed) {
			return 'install';
		}

		if ($installed && $update_available && $licensed) {
			return 'update';
		}

		if ($installed) {
			return 'up_to_date';
		}

		return 'buy';
	}

	public function getDomain(): string {
		if (defined('LICENSING_DOMAIN') && LICENSING_DOMAIN !== '') {
			return LICENSING_DOMAIN;
		}

		$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$domain = strtolower($domain);
		$domain = preg_replace('/^www\./', '', $domain);
		$domain = preg_replace('/:.*$/', '', $domain);

		return $domain;
	}

	public function getLicensedModules(): array {
		$domain = $this->getDomain();
		$cache_file = $this->cache_dir . 'licensed_' . md5($domain) . '.json';

		if (is_file($cache_file) && (time() - filemtime($cache_file)) < self::LICENSING_CACHE_TTL) {
			$cached = json_decode(file_get_contents($cache_file), true);

			if (is_array($cached)) {
				return $cached;
			}
		}

		$url = $this->api_url . '/api/v1/modules/by-domain?domain=' . urlencode($domain);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

		$parsed = parse_url($url);
		$gateway_ip = @gethostbyname('host.docker.internal');

		if ($gateway_ip !== 'host.docker.internal') {
			curl_setopt($ch, CURLOPT_RESOLVE, array(
				$parsed['host'] . ':' . ($parsed['port'] ?? 80) . ':' . $gateway_ip
			));
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code !== 200 || $response === false) {
			return array();
		}

		$body = json_decode($response, true);

		if (!is_array($body) || empty($body['success']) || !isset($body['data'])) {
			return array();
		}

		if (!is_dir($this->cache_dir)) {
			mkdir($this->cache_dir, 0755, true);
		}

		file_put_contents($cache_file, json_encode($body['data']));

		return $body['data'];
	}

	public function getModuleInfo(string $sku): ?array {
		if (isset($this->module_info_cache[$sku])) {
			return $this->module_info_cache[$sku];
		}

		$url = $this->api_url . '/api/v1/modules/' . urlencode($sku);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

		$parsed = parse_url($url);
		$gateway_ip = @gethostbyname('host.docker.internal');

		if ($gateway_ip !== 'host.docker.internal') {
			curl_setopt($ch, CURLOPT_RESOLVE, array(
				$parsed['host'] . ':' . ($parsed['port'] ?? 80) . ':' . $gateway_ip,
			));
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code !== 200 || $response === false) {
			return null;
		}

		$body = json_decode($response, true);

		if (!is_array($body) || empty($body['success']) || !isset($body['data'])) {
			return null;
		}

		$this->module_info_cache[$sku] = $body['data'];

		return $body['data'];
	}

	public function getModuleVersions(string $sku): array {
		$data = $this->getModuleInfo($sku);

		return $data['versions'] ?? array();
	}

	public function getDownloadUrl(string $sku, string $version_id, string $license_key): ?array {
		$domain = $this->getDomain();

		$url = $this->api_url . '/api/v1/modules/' . urlencode($sku)
			. '/versions/' . urlencode($version_id)
			. '/download?domain=' . urlencode($domain)
			. '&key=' . urlencode($license_key);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

		$parsed = parse_url($url);
		$gateway_ip = @gethostbyname('host.docker.internal');

		if ($gateway_ip !== 'host.docker.internal') {
			curl_setopt($ch, CURLOPT_RESOLVE, array(
				$parsed['host'] . ':' . ($parsed['port'] ?? 80) . ':' . $gateway_ip
			));
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code !== 200 || $response === false) {
			return null;
		}

		$body = json_decode($response, true);

		if (!is_array($body) || empty($body['success']) || !isset($body['data']['downloadUrl'])) {
			return null;
		}

		return $body['data'];
	}

	public function getInstalledMeta(string $sku): ?array {
		$db = $this->registry->get('db');

		$result = $db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_extension_meta`
			 WHERE `sku` = '" . $db->escape($sku) . "'"
		);

		return $result->num_rows ? $result->row : null;
	}

	public function getInstalledMetaByCode(string $code): ?array {
		$db = $this->registry->get('db');

		$result = $db->query(
			"SELECT * FROM `" . DB_PREFIX . "dockercart_extension_meta`
			 WHERE `code` = '" . $db->escape($code) . "'"
		);

		return $result->num_rows ? $result->row : null;
	}

	public function setInstalledMeta(
		string $code,
		string $sku,
		string $version,
		string $source = 'store',
		?int $extension_install_id = null,
		?string $name = null,
		?string $author = null,
		?string $author_email = null,
		?string $license_type = null,
		?string $link = null
	): void {
		$db = $this->registry->get('db');

		$column_sql = '';
		$update_sql = '';

		if ($name !== null) {
			$column_sql .= "`name` = '" . $db->escape($name) . "', ";
			$update_sql .= "`name` = VALUES(`name`), ";
		}
		if ($author !== null) {
			$column_sql .= "`author` = '" . $db->escape($author) . "', ";
			$update_sql .= "`author` = VALUES(`author`), ";
		}
		if ($author_email !== null) {
			$column_sql .= "`author_email` = '" . $db->escape($author_email) . "', ";
			$update_sql .= "`author_email` = VALUES(`author_email`), ";
		}
		if ($license_type !== null) {
			$column_sql .= "`license_type` = '" . $db->escape($license_type) . "', ";
			$update_sql .= "`license_type` = VALUES(`license_type`), ";
		}
		if ($link !== null) {
			$column_sql .= "`link` = '" . $db->escape($link) . "', ";
			$update_sql .= "`link` = VALUES(`link`), ";
		}

		$db->query(
			"INSERT INTO `" . DB_PREFIX . "dockercart_extension_meta`
			 SET `code` = '" . $db->escape($code) . "',
			     `sku` = '" . $db->escape($sku) . "',
			     `installed_version` = '" . $db->escape($version) . "',
			     `source` = '" . $db->escape($source) . "',
			     `extension_type` = 'module',
			     `extension_install_id` = " . ($extension_install_id !== null ? (int)$extension_install_id : 'NULL') . ",
			     " . $column_sql . "
			     `date_added` = NOW(),
			     `date_modified` = NOW()
			 ON DUPLICATE KEY UPDATE
			     `installed_version` = VALUES(`installed_version`),
			     `sku` = VALUES(`sku`),
			     `extension_install_id` = VALUES(`extension_install_id`),
			     " . $update_sql . "
			     `date_modified` = NOW()"
		);
	}

	public function updateInstalledMetaVersion(string $code, string $version): void {
		$db = $this->registry->get('db');

		$db->query(
			"UPDATE `" . DB_PREFIX . "dockercart_extension_meta`
			 SET `installed_version` = '" . $db->escape($version) . "',
			     `date_modified` = NOW()
			 WHERE `code` = '" . $db->escape($code) . "'"
		);
	}

	public function removeInstalledMeta(string $code): void {
		$db = $this->registry->get('db');

		$db->query(
			"DELETE FROM `" . DB_PREFIX . "dockercart_extension_meta`
			 WHERE `code` = '" . $db->escape($code) . "'"
		);
	}

	public function getChangelogHtml(array $versions, string $from_version): string {
		$html = '';
		$from_found = $from_version === '' || $from_version === '0.0.0';

		foreach ($versions as $ver) {
			$v = $ver['version'] ?? '';

			if (!$from_found) {
				if (version_compare($v, $from_version, '<=')) {
					$from_found = true;
				}

				continue;
			}

			if ($v === $from_version) {
				continue;
			}

			$changelog = $ver['changelog'] ?? '';

			if ($changelog !== '') {
				$html .= '<div class="store-changelog-version">';
				$html .= '<strong>v' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '</strong>';
				$html .= '<div class="store-changelog-content">' . htmlspecialchars($changelog, ENT_QUOTES, 'UTF-8') . '</div>';
				$html .= '</div>';
			}
		}

		return $html;
	}
}
