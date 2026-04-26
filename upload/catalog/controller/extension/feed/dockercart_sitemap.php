<?php
class ControllerExtensionFeedDockercartSitemap extends Controller {



    public function index() {

        if (!$this->config->get('dockercart_sitemap_status')) {
            return new Action('error/not_found');
        }


        $this->load->model('setting/setting');
        $module_settings = $this->model_setting_setting->getSetting('module_dockercart_sitemap');
        if (!empty($module_settings['module_dockercart_sitemap_license_key'])) {
            $this->config->set('module_dockercart_sitemap_license_key', $module_settings['module_dockercart_sitemap_license_key']);
        }
        if (!empty($module_settings['module_dockercart_sitemap_public_key'])) {
            $this->config->set('module_dockercart_sitemap_public_key', $module_settings['module_dockercart_sitemap_public_key']);
        }


        $license_from_admin = isset($this->request->get['admin_request']) && $this->request->get['admin_request'] == '1';

        if (!$license_from_admin) {
            $license_key = $this->config->get('module_dockercart_sitemap_license_key');
            if (!empty($license_key)) {
                if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {

                    http_response_code(403);
                    header('Content-Type: application/xml; charset=UTF-8');
                    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
                    echo '  <!-- Sitemap generation is disabled: License library not found -->' . "\n";
                    echo '</urlset>' . "\n";
                    exit;
                }

                require_once(DIR_SYSTEM . 'library/dockercart_license.php');
                if (class_exists('DockercartLicense')) {
                    $license = new DockercartLicense($this->registry);
                    $result = $license->verify($license_key, 'dockercart_sitemap');

                    if (!$result['valid']) {

                        http_response_code(403);
                        header('Content-Type: application/xml; charset=UTF-8');
                        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
                        echo '  <!-- Sitemap generation is disabled: ' . htmlspecialchars($result['error'] ?? 'Invalid license', ENT_XML1, 'UTF-8') . ' -->' . "\n";
                        echo '</urlset>' . "\n";
                        exit;
                    }
                }
            } else {

                http_response_code(403);
                header('Content-Type: application/xml; charset=UTF-8');
                echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
                echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
                echo '  <!-- Sitemap generation is disabled: License key not configured -->' . "\n";
                echo '</urlset>' . "\n";
                exit;
            }
        }

    $sitemap_file = DIR_APPLICATION . '../sitemap.xml';

    $cache_duration = (int)($this->config->get('dockercart_sitemap_cache_seconds') ?: 86400);


        if (!isset($this->request->get['regenerate'])) {
            if (file_exists($sitemap_file) && (time() - filemtime($sitemap_file) < $cache_duration)) {

                header('Content-Type: application/xml; charset=UTF-8');
                header('Cache-Control: public, max-age=86400');
                readfile($sitemap_file);
                exit;
            }
        }


        $this->generate();


        if (file_exists($sitemap_file)) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('Cache-Control: public, max-age=86400');
            readfile($sitemap_file);
            exit;
        }

        exit('Sitemap generation failed');
    }



    public function generate() {



        $query = $this->db->query("SELECT language_id, code FROM " . DB_PREFIX . "language ORDER BY language_id");
        $languages = $query->rows;

        if (empty($languages)) {

            $languages = array();
            $code = $this->config->get('config_language') ?: 'en-gb';
            $languages[] = array(
                'language_id' => (int)$this->config->get('config_language_id'),
                'code' => $code
            );
        }


    // Remove any previous sitemap files (xml + optional gz)
    @array_map('unlink', glob(DIR_APPLICATION . '../sitemap*.xml'));
    @array_map('unlink', glob(DIR_APPLICATION . '../sitemap*.xml.gz'));

    $create_gzip = !empty($this->config->get('dockercart_sitemap_create_gzip'));


    $max_urls = (int)($this->config->get('dockercart_sitemap_max_urls') ?: 50000);

    $max_file_size_mb = (int)($this->config->get('dockercart_sitemap_max_file_size_mb') ?: 50);
    $max_file_bytes = (int)($max_file_size_mb * 1024 * 1024);

    $priority_product = (float)($this->config->get('dockercart_sitemap_product_priority') ?: 0.8);
        $changefreq_product = $this->config->get('dockercart_sitemap_product_changefreq') ?: 'weekly';
        $priority_category = (float)($this->config->get('dockercart_sitemap_category_priority') ?: 0.9);
        $changefreq_category = $this->config->get('dockercart_sitemap_category_changefreq') ?: 'weekly';
        $priority_manufacturer = (float)($this->config->get('dockercart_sitemap_manufacturer_priority') ?: 0.7);
        $changefreq_manufacturer = $this->config->get('dockercart_sitemap_manufacturer_changefreq') ?: 'monthly';



        try {
            if (file_exists(DIR_SYSTEM . 'library/dockercart_sitemap_license_helper.php')) {
                require_once(DIR_SYSTEM . 'library/dockercart_sitemap_license_helper.php');
                $license_key = $this->config->get('module_dockercart_sitemap_license_key');
                $public_key = $this->config->get('module_dockercart_sitemap_public_key');
                $helper_class = 'DockercartSitemapLicenseHelper';

                if (class_exists($helper_class)) {
                    $res = $helper_class::verify($this->registry, (string)$license_key, (string)$public_key);
                    $license_valid = !empty($res['valid']);
                    $license_message = $res['error'] ?? '';
                } else {
                    $license_valid = false;
                    $license_message = 'License helper class not found';
                }
            } else {
                $license_valid = true;
                $license_message = '';
            }
        } catch (Throwable $e) {
            $license_valid = false;
            $license_message = 'License check error: ' . $e->getMessage();
        }


        $sitemap_files = array();
        $file_index = 0;
        $url_count_in_file = 0;
        $current_writer = null;


        $lock_file = DIR_APPLICATION . '../sitemap.lock';
        $lock_fp = @fopen($lock_file, 'c');
        if ($lock_fp === false) {

            return;
        }

        $lock_acquired = false;
        $lock_start = time();
        while (! $lock_acquired) {
            $lock_acquired = @flock($lock_fp, LOCK_EX | LOCK_NB);
            if ($lock_acquired) break;
            if ((time() - $lock_start) > 10) {
                fclose($lock_fp);
                return;
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


            global $license_valid, $license_message;
            if (isset($license_valid) && !$license_valid) {
                $comment = 'DockerCart Sitemap: License not verified';
                if (!empty($license_message)) $comment .= ' - ' . $license_message;
                if (method_exists($w, 'writeComment')) {
                    $w->writeComment($comment);
                }
            }

            return array($w, $tmp, $final);
        };

        $close_writer = function($w, $tmp = null, $final = null) use ($create_gzip) {
            if (!$w) return;
            $w->endElement();
            $w->endDocument();
            $w->flush();

            if ($tmp !== null && $final !== null) {
                @rename($tmp, $final);
                @chmod($final, 0644);
                // Create gzipped version if requested
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


        $write_entry = function($urls, $lastmod, $changefreq, $priority) use (&$current_writer, &$url_count_in_file, &$sitemap_files, &$open_new_writer, &$close_writer, $max_urls, $max_file_bytes) {

            if ($current_writer === null || $url_count_in_file >= $max_urls) {

                if ($current_writer !== null) {
                    $close_writer($current_writer['writer'], $current_writer['tmp'], $current_writer['final']);
                    $sitemap_files[] = $current_writer['final'];
                }

                list($w, $tmp, $final) = $open_new_writer();
                $current_writer = array('writer' => $w, 'tmp' => $tmp, 'final' => $final);
                $url_count_in_file = 0;
            }

            $w = $current_writer['writer'];


            $w->startElement('url');

            $loc_value = html_entity_decode($urls[0]['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $w->writeElement('loc', $loc_value);
            $w->writeElement('lastmod', $lastmod);
            $w->writeElement('changefreq', $changefreq);
            $w->writeElement('priority', number_format((float)$priority, 1, '.', ''));

            foreach ($urls as $url_entry) {
                $w->startElementNS('xhtml', 'link', null);
                $w->writeAttribute('rel', 'alternate');
                $w->writeAttribute('hreflang', $url_entry['hreflang']);
                $href_value = html_entity_decode($url_entry['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8');
                $w->writeAttribute('href', $href_value);
                $w->endElement();
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


        $urls_home = $this->buildAlternateUrls('common/home', '', $languages, false);
        $write_entry($urls_home, date('Y-m-d'), 'daily', '1.0');


        if ($this->config->get('dockercart_sitemap_products')) {
            $query = $this->db->query("SELECT product_id, date_modified FROM " . DB_PREFIX . "product WHERE status = 1 ORDER BY product_id");
            $products = $query->rows;

            foreach ($products as $product) {
                $urls = $this->buildAlternateUrls('product/product', 'product_id=' . $product['product_id'], $languages, true);

                $lastmod = $product['date_modified'] ?? date('Y-m-d');
                if (strlen($lastmod) > 10) {
                    $lastmod = date('Y-m-d', strtotime($lastmod));
                }

                $write_entry($urls, $lastmod, $changefreq_product, $priority_product);
            }
        }


        if ($this->config->get('dockercart_sitemap_categories')) {
            $query = $this->db->query("SELECT category_id, date_modified FROM " . DB_PREFIX . "category WHERE status = 1 ORDER BY category_id");
            $categories = $query->rows;

            foreach ($categories as $category) {
                $urls = $this->buildAlternateUrls('product/category', 'path=' . $category['category_id'], $languages, true);

                $lastmod = $category['date_modified'] ?? date('Y-m-d');
                if (strlen($lastmod) > 10) {
                    $lastmod = date('Y-m-d', strtotime($lastmod));
                }

                $write_entry($urls, $lastmod, $changefreq_category, $priority_category);
            }
        }


        if ($this->config->get('dockercart_sitemap_manufacturers')) {
            $query = $this->db->query("SELECT manufacturer_id FROM " . DB_PREFIX . "manufacturer ORDER BY manufacturer_id");
            $manufacturers = $query->rows;

            foreach ($manufacturers as $manufacturer) {
                $urls = $this->buildAlternateUrls('product/manufacturer/info', 'manufacturer_id=' . $manufacturer['manufacturer_id'], $languages, true);

                $write_entry($urls, date('Y-m-d'), $changefreq_manufacturer, $priority_manufacturer);
            }
        }


        if ($this->config->get('dockercart_sitemap_information')) {
            $query = $this->db->query("SELECT information_id FROM " . DB_PREFIX . "information WHERE status = 1 ORDER BY information_id");
            $informations = $query->rows;

            $info_priority = (float)($this->config->get('dockercart_sitemap_information_priority') ?: 0.5);
            $info_changefreq = $this->config->get('dockercart_sitemap_information_changefreq') ?: 'monthly';

            foreach ($informations as $info) {
                $urls = $this->buildAlternateUrls('information/information', 'information_id=' . $info['information_id'], $languages, true);

                $write_entry($urls, date('Y-m-d'), $info_changefreq, $info_priority);
            }
        }


        if ($current_writer !== null) {
            $close_writer($current_writer['writer'], $current_writer['tmp'], $current_writer['final']);
            $sitemap_files[] = $current_writer['final'];
        }


        if (count($sitemap_files) === 1) {
            $single = $sitemap_files[0];
            $target = DIR_APPLICATION . '../sitemap.xml';

            @unlink($target);
            rename($single, $target);
            chmod($target, 0644);


            // If gzip enabled, create a compressed master file and make sitemap.xml an index pointing to the .gz
            if ($create_gzip) {
                $gz = $target . '.gz';
                $content = @file_get_contents($target);
                if ($content !== false) {
                    @file_put_contents($gz, gzencode($content, 9), LOCK_EX);
                    @chmod($gz, 0644);
                }

                // Create a sitemapindex that references the gzip file
                $index_writer = new XMLWriter();
                $index_writer->openMemory();
                $index_writer->startDocument('1.0', 'UTF-8');
                $index_writer->startElement('sitemapindex');
                $index_writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

                $base = (defined('HTTP_CATALOG') ? rtrim(HTTP_CATALOG, '/') : rtrim($this->config->get('config_url'), '/')) . '/';
                $name = basename($target) . '.gz';
                $index_writer->startElement('sitemap');
                $index_writer->writeElement('loc', $base . $name);
                $index_writer->writeElement('lastmod', date('c', filemtime($gz ?: $target)));
                $index_writer->endElement();

                $index_writer->endElement();
                $index_writer->endDocument();
                $xmlindex = $index_writer->outputMemory();

                file_put_contents(DIR_APPLICATION . '../sitemap.xml', $xmlindex);
                chmod(DIR_APPLICATION . '../sitemap.xml', 0644);

                $sitemap_files = array($target, $gz);
            } else {
                $sitemap_files = array($target);
            }
        } elseif (count($sitemap_files) > 1) {

            $index_writer = new XMLWriter();
            $index_writer->openMemory();
            $index_writer->startDocument('1.0', 'UTF-8');
            $index_writer->startElement('sitemapindex');
            $index_writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            foreach ($sitemap_files as $f) {
                $index_writer->startElement('sitemap');

                $base = (defined('HTTP_CATALOG') ? rtrim(HTTP_CATALOG, '/') : rtrim($this->config->get('config_url'), '/')) . '/';
                $name = basename($f);
                // If gzip is enabled, reference compressed counterpart when available
                if ($create_gzip && file_exists($f . '.gz')) {
                    $index_writer->writeElement('loc', $base . $name . '.gz');
                    $index_writer->writeElement('lastmod', date('c', filemtime($f . '.gz')));
                } else {
                    $index_writer->writeElement('loc', $base . $name);
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


		if (is_resource($lock_fp)) {
            @flock($lock_fp, LOCK_UN);
            @fclose($lock_fp);
            @unlink(DIR_APPLICATION . '../sitemap.lock');
        }
    }



    private function addUrlEntry(&$writer, $urls, $lastmod, $changefreq, $priority) {
        if (empty($urls)) {
            return;
        }

        $writer->startElement('url');
        $writer->writeElement('loc', $urls[0]['loc']);
        $writer->writeElement('lastmod', $lastmod);
        $writer->writeElement('changefreq', $changefreq);
        $writer->writeElement('priority', number_format((float)$priority, 1, '.', ''));


        foreach ($urls as $url_entry) {
            $writer->startElementNS('xhtml', 'link', null);
            $writer->writeAttribute('rel', 'alternate');
            $writer->writeAttribute('hreflang', $url_entry['hreflang']);
            $writer->writeAttribute('href', $url_entry['loc']);
            $writer->endElement();
        }

        $writer->endElement();
    }



    private function buildAlternateUrls($route, $args = '', $languages = array(), $secure = false) {
        $old_config_language_id = $this->config->get('config_language_id');

        $urls = array();
        foreach ($languages as $l) {
            // Устанавливаем только config_language_id, не трогаем config_language
            $this->config->set('config_language_id', (int)$l['language_id']);

            $url = $this->url->link($route, $args !== '' ? $args : '', $secure);

            $urls[] = array(
            'loc' => $url,
            'hreflang' => $l['code']
            );
        }

        // Восстанавливаем прежний config_language_id
        if ($old_config_language_id !== null) {
            $this->config->set('config_language_id', $old_config_language_id);
        } else {
            $this->config->set('config_language_id', null);
        }

        return $urls;
    }
}
?>
