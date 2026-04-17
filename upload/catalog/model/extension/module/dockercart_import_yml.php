<?php
class ModelExtensionModuleDockercartImportYml extends Model {

    private $category_map_schema_checked = false;

    public function getProfile($profile_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_import_yml_profile` WHERE `profile_id` = '" . (int)$profile_id . "'");

        if (!$query->num_rows) {
            return null;
        }

        $row = $query->row;

        // Decode last_result JSON if present
        if (!empty($row['last_result'])) {
            $last_result = json_decode($row['last_result'], true);
            $row['last_result'] = is_array($last_result) ? $last_result : array();
        } else {
            $row['last_result'] = array();
        }

        return $row;
    }

    public function runImport($profile_id, $offset = 0, $limit = 0) {
        $profile = $this->getProfile($profile_id);

        if (!$profile) {
            throw new Exception('Profile not found');
        }

        if (!(int)$profile['status']) {
            throw new Exception('Profile is disabled');
        }

        $offset = max(0, (int)$offset);
        $limit = max(0, (int)$limit);
        $is_chunked = $limit > 0;

        $summary = $this->initializeImportSummary($profile, $offset);
        $summary['chunk_size'] = $limit;

        if ($offset === 0) {
            $this->writeProgress($profile_id, $summary, false);
        }

        $content = $this->fetchFeedContent((string)$profile['feed_url']);
        $xml = @simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$xml) {
            throw new Exception('Invalid YML XML');
        }

        $offers = null;
        if (isset($xml->shop->offers->offer)) {
            $offers = $xml->shop->offers->offer;
        } elseif (isset($xml->offers->offer)) {
            $offers = $xml->offers->offer;
        }

        if ($offers === null) {
            throw new Exception('No offers found in feed');
        }

        $offers_array = array();
        foreach ($offers as $offer_item) {
            $offers_array[] = $offer_item;
        }

        $summary['stage'] = 'processing_offers';

        $summary['total_offers'] = (int)count($offers_array);
        if ($summary['total_offers'] < 0) {
            $summary['total_offers'] = 0;
        }

        if ($summary['processed'] > $summary['total_offers']) {
            $summary['processed'] = $summary['total_offers'];
        }

        $this->writeProgress($profile_id, $summary, false);

        if ($offset === 0 && (string)$profile['import_mode'] === 'replace') {
            $this->deleteAllCatalogData();
        }

        $category_payload = $this->buildFeedCategoryMap($profile, $xml, $offset);
        $feed_category_map = $category_payload['map'];

        if ($offset === 0 && isset($category_payload['stats'])) {
            $summary['categories_in_feed'] = (int)$category_payload['stats']['total'];
            $summary['categories_created'] = (int)$category_payload['stats']['created'];
            $summary['categories_mapped'] = (int)$category_payload['stats']['mapped'];
            $summary['categories_skipped'] = (int)$category_payload['stats']['skipped'];
        }

        $chunk_start = $is_chunked ? $offset : 0;
        $chunk_end = $is_chunked ? min($summary['total_offers'], $offset + $limit) : $summary['total_offers'];

        for ($offer_index = $chunk_start; $offer_index < $chunk_end; $offer_index++) {
            $offer = $offers_array[$offer_index];
            $summary['processed']++;
            if ($summary['total_offers'] > 0) {
                $summary['progress_percent'] = (int)floor(($summary['processed'] * 100) / $summary['total_offers']);
            } else {
                $summary['progress_percent'] = 0;
            }

            if ($summary['progress_percent'] > 100) {
                $summary['progress_percent'] = 100;
            }

            try {
                $offer_id = $this->extractOfferId($offer);
                if ($offer_id === '') {
                    $summary['skipped']++;
                    continue;
                }

                $name = $this->xmlText($offer, 'name');
                if ($name === '') {
                    $summary['skipped']++;
                    continue;
                }

                $price = (float)$this->xmlText($offer, 'price');
                if ($price < 0) {
                    $price = 0;
                }

                $allow_zero_price = !empty($profile['allow_zero_price']);
                if ($price == 0 && !$allow_zero_price) {
                    $summary['skipped']++;
                    continue;
                }

                $vendor = $this->xmlText($offer, 'vendor');
                $vendor_code = $this->xmlText($offer, 'vendorCode');
                $description = $this->prepareDescription($this->xmlText($offer, 'description'));

                $quantity = $this->extractQuantity($offer);
                $category_id = $this->resolveCategoryId($offer, $profile, $feed_category_map);
                $manufacturer_name = $this->extractManufacturerName($offer, $vendor);
                $manufacturer_id = $this->resolveManufacturerId($manufacturer_name, (int)$profile['store_id']);

                // Extract and download product images (if enabled in profile)
                $download_images = !isset($profile['download_images']) || (int)$profile['download_images'] === 1;

                $image_urls = $download_images ? $this->extractImageUrls($offer) : array();
                $main_image = '';
                $additional_images = array();

                if (!empty($image_urls)) {
                    try {
                        $downloaded = $this->downloadProductImages($image_urls, $offer_id, (int)$profile_id);
                        $main_image = $downloaded['main'];
                        $additional_images = $downloaded['additional'];
                    } catch (Exception $e) {
                        error_log("DockerCart Import YML: Failed to download images for offer {$offer_id}: " . $e->getMessage());
                    }
                }

                $product_data = array(
                    'model' => $vendor_code !== '' ? $vendor_code : ('YML-' . $offer_id),
                    'sku' => $vendor_code !== '' ? $vendor_code : $offer_id,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'quantity' => $quantity,
                    'manufacturer_id' => $manufacturer_id,
                    'store_id' => (int)$profile['store_id'],
                    'language_id' => (int)$profile['language_id'],
                    'category_id' => (int)$category_id,
                    'image' => $main_image,
                    'additional_images' => $additional_images
                );

                $existing_product_id = $this->findProductByOffer($profile_id, $offer_id);
                if (!$existing_product_id) {
                    $existing_product_id = $this->findProductBySkuOrModel($product_data['sku'], $product_data['model']);
                }

                $mode = (string)$profile['import_mode'];
                if ($mode === 'add' && $existing_product_id) {
                    $summary['skipped']++;
                    $this->upsertOfferMap($profile_id, $offer_id, $existing_product_id);
                    continue;
                }

                if ($mode === 'update_only' && !$existing_product_id) {
                    $summary['skipped']++;
                    continue;
                }

                if ($mode === 'update_price_qty_only' && !$existing_product_id) {
                    $summary['skipped']++;
                    continue;
                }

                if ($existing_product_id) {
                    if ($mode === 'update_price_qty_only') {
                        $this->updateProductPriceQuantity($existing_product_id, $product_data);
                    } else {
                        $this->updateProduct($existing_product_id, $product_data);
                    }
                    $summary['updated']++;
                    $this->upsertOfferMap($profile_id, $offer_id, $existing_product_id);
                } else {
                    $product_id = $this->addProduct($product_data);
                    $summary['added']++;
                    $this->upsertOfferMap($profile_id, $offer_id, $product_id);
                }
            } catch (Exception $e) {
                $summary['errors']++;
            }

            if ($summary['processed'] % 10 === 0 || $summary['processed'] === $chunk_end || $summary['processed'] === $summary['total_offers']) {
                $this->writeProgress($profile_id, $summary, false);
            }
        }

        if ($is_chunked && $chunk_end < $summary['total_offers']) {
            $summary['in_progress'] = true;
            $summary['stage'] = 'processing_offers';
            $summary['next_offset'] = (int)$chunk_end;
            $this->writeProgress($profile_id, $summary, false);
            return $summary;
        }

        $summary['in_progress'] = false;
        $summary['progress_percent'] = 100;
        $summary['stage'] = 'completed';
        $summary['next_offset'] = null;
        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_import_yml_profile`
            SET `last_run` = NOW(),
                `last_result` = '" . $this->db->escape(json_encode($summary, JSON_UNESCAPED_UNICODE)) . "',
                `date_modified` = NOW()
            WHERE `profile_id` = '" . (int)$profile_id . "'");

        return $summary;
    }

    private function initializeImportSummary($profile, $offset = 0) {
        $profile_id = (int)$profile['profile_id'];

        if ($offset > 0 && !empty($profile['last_result']) && is_array($profile['last_result'])) {
            $prev = $profile['last_result'];
            if (!empty($prev['in_progress']) || (int)($prev['processed'] ?? 0) > 0) {
                return array(
                    'profile_id' => $profile_id,
                    'mode' => (string)$profile['import_mode'],
                    'added' => (int)($prev['added'] ?? 0),
                    'updated' => (int)($prev['updated'] ?? 0),
                    'skipped' => (int)($prev['skipped'] ?? 0),
                    'errors' => (int)($prev['errors'] ?? 0),
                    'processed' => (int)($prev['processed'] ?? 0),
                    'total_offers' => (int)($prev['total_offers'] ?? 0),
                    'categories_in_feed' => (int)($prev['categories_in_feed'] ?? 0),
                    'categories_created' => (int)($prev['categories_created'] ?? 0),
                    'categories_mapped' => (int)($prev['categories_mapped'] ?? 0),
                    'categories_skipped' => (int)($prev['categories_skipped'] ?? 0),
                    'progress_percent' => (int)($prev['progress_percent'] ?? 0),
                    'stage' => 'processing_offers',
                    'in_progress' => true,
                    'next_offset' => isset($prev['next_offset']) ? (int)$prev['next_offset'] : null
                );
            }
        }

        return array(
            'profile_id' => $profile_id,
            'mode' => (string)$profile['import_mode'],
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed' => 0,
            'total_offers' => 0,
            'categories_in_feed' => 0,
            'categories_created' => 0,
            'categories_mapped' => 0,
            'categories_skipped' => 0,
            'progress_percent' => 0,
            'stage' => 'preparing_feed',
            'in_progress' => true,
            'next_offset' => null
        );
    }

    private function buildFeedCategoryMap($profile, $xml, $offset = 0) {
        if ($offset === 0) {
            $feed_categories = $this->buildFeedCategories($xml);
            $load_categories = !isset($profile['load_categories']) || (int)$profile['load_categories'] === 1;
            $default_category_id = (int)$profile['default_category_id'];

            if ($load_categories) {
                $root_parent_id = $default_category_id > 0 ? $default_category_id : 0;
                $category_import = $this->importFeedCategories($profile, $feed_categories, $root_parent_id);
                return array('map' => $category_import['map'], 'stats' => $category_import);
            }

            return array(
                'map' => array(),
                'stats' => array(
                    'total' => count($feed_categories),
                    'created' => 0,
                    'mapped' => 0,
                    'skipped' => count($feed_categories)
                )
            );
        }

        // Rebuild map for subsequent chunks without changing counters
        $feed_categories = $this->buildFeedCategories($xml);
        $load_categories = !isset($profile['load_categories']) || (int)$profile['load_categories'] === 1;
        $default_category_id = (int)$profile['default_category_id'];

        if (!$load_categories) {
            return array('map' => array());
        }

        $root_parent_id = $default_category_id > 0 ? $default_category_id : 0;
        $category_import = $this->importFeedCategories($profile, $feed_categories, $root_parent_id);

        return array('map' => $category_import['map']);
    }

    /**
     * Ensure category has correct 'top' value based on parent_id
     * Root categories (parent_id = 0) should always have top = 1
     */
    private function ensureCategoryTop($category_id, $parent_id) {
        $parent_id = (int)$parent_id;
        $top_value = ($parent_id === 0) ? 1 : 0;
        
        $query = $this->db->query("SELECT `top` FROM `" . DB_PREFIX . "category` WHERE category_id = '" . (int)$category_id . "'");
        
        if ($query->num_rows && (int)$query->row['top'] !== $top_value) {
            error_log("DockerCart Import YML: Updating category {$category_id} top value from {$query->row['top']} to {$top_value}");
            $this->db->query("UPDATE `" . DB_PREFIX . "category` SET `top` = '" . $top_value . "' WHERE category_id = '" . (int)$category_id . "'");
        }
    }

    private function prepareDescription($raw_description) {
        $description = html_entity_decode((string)$raw_description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = str_replace(array("\r\n", "\r"), "\n", $description);

        $allowed_tags = '<p><br><b><strong><i><em><u><ul><ol><li>';
        $description = strip_tags($description, $allowed_tags);

        // Strip all attributes from allowed tags for safety/consistency
        $description = preg_replace('/<(\/?)(p|br|b|strong|i|em|u|ul|ol|li)\b[^>]*>/iu', '<$1$2>', $description);

        // If there are no explicit paragraphs/lists, build paragraphs from text blocks
        if (!preg_match('/<p\b|<ul\b|<ol\b/iu', $description)) {
            $parts = preg_split('/\n\s*\n/u', $description);
            $formatted = array();

            foreach ($parts as $part) {
                $part = trim((string)$part);
                if ($part === '') {
                    continue;
                }

                $part = preg_replace('/\n+/u', '<br>', $part);
                $formatted[] = '<p>' . $part . '</p>';
            }

            $description = implode("\n", $formatted);
        }

        return trim((string)$description);
    }

    private function fetchFeedContent($url) {
        $url = trim($url);
        if ($url === '') {
            throw new Exception('Feed URL is empty');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DockerCart-ImportYML/1.1');
        $content = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new Exception('cURL error: ' . $error);
        }
        if ($code >= 400 || $content === false || $content === '') {
            throw new Exception('Failed to fetch feed. HTTP code: ' . $code);
        }

        return $content;
    }

    private function xmlText($node, $name) {
        return isset($node->{$name}) ? trim((string)$node->{$name}) : '';
    }

    private function extractOfferId($offer) {
        $attrs = $offer->attributes();
        $offer_id = isset($attrs['id']) ? trim((string)$attrs['id']) : '';

        if ($offer_id === '') {
            $offer_id = $this->xmlText($offer, 'vendorCode');
        }
        if ($offer_id === '') {
            $offer_id = md5($this->xmlText($offer, 'name') . '|' . $this->xmlText($offer, 'url'));
        }

        return $offer_id;
    }

    private function extractQuantity($offer) {
        if (isset($offer->stock_quantity) && (int)$offer->stock_quantity >= 0) {
            return (int)$offer->stock_quantity;
        }

        if (isset($offer->count) && (int)$offer->count >= 0) {
            return (int)$offer->count;
        }

        $attrs = $offer->attributes();
        if (isset($attrs['available'])) {
            return ((string)$attrs['available'] === 'true') ? 100 : 0;
        }

        return 0;
    }

    private function buildFeedCategories($xml) {
        $categories_data = array();

        $categories = null;
        if (isset($xml->shop->categories->category)) {
            $categories = $xml->shop->categories->category;
        } elseif (isset($xml->categories->category)) {
            $categories = $xml->categories->category;
        }

        if ($categories === null) {
            return $categories_data;
        }

        foreach ($categories as $category) {
            $attrs = $category->attributes();
            $id = isset($attrs['id']) ? trim((string)$attrs['id']) : '';
            $parent_id = isset($attrs['parentId']) ? trim((string)$attrs['parentId']) : '';
            $name = trim((string)$category);

            if ($id !== '' && $name !== '') {
                $categories_data[$id] = array(
                    'id' => $id,
                    'parent_id' => $parent_id,
                    'name' => $name
                );
            }
        }

        return $categories_data;
    }

    private function importFeedCategories($profile, $feed_categories, $root_parent_id = 0) {
        $result = array(
            'total' => count($feed_categories),
            'created' => 0,
            'mapped' => 0,
            'skipped' => 0,
            'map' => array()
        );

        if (!$feed_categories) {
            return $result;
        }

        $language_id = (int)$profile['language_id'];
        $store_id = (int)$profile['store_id'];
        $profile_id = isset($profile['profile_id']) ? (int)$profile['profile_id'] : 0;

        $this->ensureCategoryMapTable();

        $remaining = $feed_categories;
        $guard = 0;
        $max_guard = count($remaining) + 5;

        while (!empty($remaining) && $guard < $max_guard) {
            $guard++;
            $progress = false;

            foreach ($remaining as $feed_id => $item) {
                $parent_feed_id = (string)$item['parent_id'];
                $parent_local_id = (int)$root_parent_id;

                if ($parent_feed_id !== '') {
                    if (!isset($result['map'][$parent_feed_id])) {
                        continue;
                    }
                    $parent_local_id = (int)$result['map'][$parent_feed_id];
                }

                $local_id = $this->findCategoryByNameAndParent($item['name'], $language_id, $parent_local_id);
                $was_created = 0;

                if (!$local_id) {
                    $local_id = $this->createCategory($item['name'], $language_id, $store_id, $parent_local_id);
                    $result['created']++;
                    $was_created = 1;
                } else {
                    // Category already exists - update its 'top' field if it's a root category
                    $this->ensureCategoryTop($local_id, $parent_local_id);
                }

                $result['map'][$feed_id] = (int)$local_id;
                if ($profile_id > 0) {
                    $this->upsertCategoryMap($profile_id, (string)$feed_id, (int)$local_id, $was_created);
                }
                $result['mapped']++;
                unset($remaining[$feed_id]);
                $progress = true;
            }

            if (!$progress) {
                foreach ($remaining as $feed_id => $item) {
                    $fallback_parent_id = (int)$root_parent_id;
                    if (!empty($item['parent_id']) && isset($result['map'][$item['parent_id']])) {
                        $fallback_parent_id = (int)$result['map'][$item['parent_id']];
                    }

                    $local_id = $this->findCategoryByNameAndParent($item['name'], $language_id, $fallback_parent_id);
                    $was_created = 0;
                    if (!$local_id) {
                        $local_id = $this->createCategory($item['name'], $language_id, $store_id, $fallback_parent_id);
                        $result['created']++;
                        $was_created = 1;
                    } else {
                        // Category already exists - update its 'top' field if it's a root category
                        $this->ensureCategoryTop($local_id, $fallback_parent_id);
                    }

                    $result['map'][$feed_id] = (int)$local_id;
                    if ($profile_id > 0) {
                        $this->upsertCategoryMap($profile_id, (string)$feed_id, (int)$local_id, $was_created);
                    }
                    $result['mapped']++;
                }

                $remaining = array();
                break;
            }
        }

        $result['skipped'] = max(0, $result['total'] - $result['mapped']);

        return $result;
    }

    private function resolveCategoryId($offer, $profile, $feed_category_map) {
        $profile_category = (int)$profile['default_category_id'];
        $feed_category_id = $this->xmlText($offer, 'categoryId');

        if ($feed_category_id !== '' && isset($feed_category_map[$feed_category_id])) {
            return (int)$feed_category_map[$feed_category_id];
        }

        return $profile_category;
    }

    private function extractManufacturerName($offer, $vendor_name = '') {
        $vendor_name = trim((string)$vendor_name);
        if ($vendor_name !== '') {
            return $vendor_name;
        }

        $brand_param_names = array(
            'бренд',
            'brand',
            'manufacturer',
            'производитель',
            'виробник',
            'торгова марка',
            'торговая марка'
        );

        foreach ($offer->param as $param) {
            $attributes = $param->attributes();
            if (!isset($attributes['name'])) {
                continue;
            }

            $param_name = $this->normalizeParamName((string)$attributes['name']);
            if ($param_name === '' || !in_array($param_name, $brand_param_names, true)) {
                continue;
            }

            $param_value = trim((string)$param);
            if ($param_value !== '') {
                return $param_value;
            }
        }

        return '';
    }

    private function normalizeParamName($name) {
        $name = html_entity_decode(trim((string)$name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($name === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtolower($name);
        }

        $name = preg_replace('/\s+/u', ' ', $name);

        return trim((string)$name);
    }

    private function findCategoryByNameAndParent($name, $language_id, $parent_id) {
        $query = $this->db->query("SELECT c.category_id
            FROM `" . DB_PREFIX . "category` c
            INNER JOIN `" . DB_PREFIX . "category_description` cd ON (cd.category_id = c.category_id)
            WHERE cd.language_id = '" . (int)$language_id . "'
              AND cd.name = '" . $this->db->escape($name) . "'
              AND c.parent_id = '" . (int)$parent_id . "'
            LIMIT 1");

        return $query->num_rows ? (int)$query->row['category_id'] : 0;
    }

    private function createCategory($name, $language_id, $store_id, $parent_id, $is_top = false) {
        // Root categories (parent_id = 0 or NULL) must have top = 1
        $parent_id = (int)$parent_id;
        $top_value = ($parent_id === 0) ? 1 : ((int)$is_top);
        
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category`
            SET image = '',
                parent_id = '" . $parent_id . "',
                `top` = '" . $top_value . "',
                `column` = '1',
                sort_order = '0',
                status = '1',
                date_added = NOW(),
                date_modified = NOW()");

        $category_id = (int)$this->db->getLastId();

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_description`
            SET category_id = '" . $category_id . "',
                language_id = '" . (int)$language_id . "',
                name = '" . $this->db->escape($name) . "',
                description = '',
                meta_title = '" . $this->db->escape($name) . "',
                meta_description = '',
                meta_keyword = ''");

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store`
            SET category_id = '" . $category_id . "',
                store_id = '" . (int)$store_id . "'");

        if ((int)$store_id !== 0) {
            $exists_default_store = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category_to_store` WHERE category_id = '" . $category_id . "' AND store_id = '0' LIMIT 1");
            if (!$exists_default_store->num_rows) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET category_id = '" . $category_id . "', store_id = '0'");
            }
        }

        $this->rebuildCategoryPath($category_id, (int)$parent_id);

        return $category_id;
    }

    private function rebuildCategoryPath($category_id, $parent_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$category_id . "'");

        $level = 0;
        if ((int)$parent_id > 0) {
            $parent_paths = $this->db->query("SELECT path_id, level FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . (int)$parent_id . "' ORDER BY level ASC");
            foreach ($parent_paths->rows as $parent_path) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path`
                    SET category_id = '" . (int)$category_id . "',
                        path_id = '" . (int)$parent_path['path_id'] . "',
                        level = '" . (int)$level . "'");
                $level++;
            }
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path`
            SET category_id = '" . (int)$category_id . "',
                path_id = '" . (int)$category_id . "',
                level = '" . (int)$level . "'");
    }

    private function ensureCategoryMapTable() {
        if ($this->category_map_schema_checked) {
            return;
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_import_yml_category_map` (
                `map_id` int(11) NOT NULL AUTO_INCREMENT,
                `profile_id` int(11) NOT NULL,
                `feed_category_id` varchar(255) NOT NULL,
                `category_id` int(11) NOT NULL,
                `was_created` tinyint(1) NOT NULL DEFAULT '0',
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`map_id`),
                UNIQUE KEY `profile_feed_category` (`profile_id`,`feed_category_id`),
                KEY `profile_created` (`profile_id`,`was_created`),
                KEY `category_id` (`category_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->category_map_schema_checked = true;
    }

    private function upsertCategoryMap($profile_id, $feed_category_id, $category_id, $was_created = 0) {
        $profile_id = (int)$profile_id;
        $category_id = (int)$category_id;
        $feed_category_id = trim((string)$feed_category_id);
        $was_created = $was_created ? 1 : 0;

        if ($profile_id <= 0 || $category_id <= 0 || $feed_category_id === '') {
            return;
        }

        $this->ensureCategoryMapTable();

        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_import_yml_category_map`
            SET profile_id = '" . $profile_id . "',
                feed_category_id = '" . $this->db->escape($feed_category_id) . "',
                category_id = '" . $category_id . "',
                was_created = '" . $was_created . "',
                date_modified = NOW()
            ON DUPLICATE KEY UPDATE
                category_id = VALUES(category_id),
                was_created = VALUES(was_created),
                date_modified = NOW()");
    }

    private function clearCategoryMap($profile_id) {
        $profile_id = (int)$profile_id;
        if ($profile_id <= 0) {
            return;
        }

        $this->ensureCategoryMapTable();
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_import_yml_category_map` WHERE profile_id = '" . $profile_id . "'");
    }

    private function deleteImportedCategoriesByProfile($profile_id, $fallback_category_ids = array(), $default_category_id = 0) {
        $profile_id = (int)$profile_id;
        $default_category_id = (int)$default_category_id;

        $fallback_category_ids = array_values(array_unique(array_map('intval', (array)$fallback_category_ids)));

        $this->ensureCategoryMapTable();

        $category_ids_to_delete = array();

        if ($profile_id > 0) {
            $query = $this->db->query("SELECT DISTINCT category_id
                FROM `" . DB_PREFIX . "dockercart_import_yml_category_map`
                WHERE profile_id = '" . $profile_id . "'
                  AND was_created = '1'");

            foreach ($query->rows as $row) {
                $category_ids_to_delete[] = (int)$row['category_id'];
            }

            if ($fallback_category_ids) {
                $category_ids_to_delete = array_merge($category_ids_to_delete, $fallback_category_ids);
            }
        } else {
            $category_ids_to_delete = $fallback_category_ids;
        }

        $category_ids_to_delete = array_values(array_unique(array_filter(array_map('intval', $category_ids_to_delete))));
        if (!$category_ids_to_delete) {
            return;
        }

        $category_ids_to_delete = $this->expandCategoryIdsWithAncestors($category_ids_to_delete);

        $ordered_category_ids = $this->sortCategoryIdsByDepthDesc($category_ids_to_delete);

        foreach ($ordered_category_ids as $category_id) {
            if ($category_id <= 0 || ($default_category_id > 0 && $category_id === $default_category_id)) {
                continue;
            }

            if (!$this->canDeleteCategory($category_id)) {
                continue;
            }

            $this->deleteCategoryById($category_id);
        }
    }

    private function getStoreProductCategoryIds($store_id) {
        $store_id = (int)$store_id;

        $query = $this->db->query("SELECT DISTINCT ptc.category_id
            FROM `" . DB_PREFIX . "product_to_store` pts
            INNER JOIN `" . DB_PREFIX . "product_to_category` ptc ON (ptc.product_id = pts.product_id)
            WHERE pts.store_id = '" . $store_id . "'");

        $result = array();
        foreach ($query->rows as $row) {
            $result[] = (int)$row['category_id'];
        }

        return array_values(array_unique(array_filter($result)));
    }

    private function sortCategoryIdsByDepthDesc($category_ids) {
        $category_ids = array_values(array_unique(array_filter(array_map('intval', (array)$category_ids))));
        if (!$category_ids) {
            return array();
        }

        $in = implode(',', $category_ids);
        $query = $this->db->query("SELECT cp.category_id, MAX(cp.level) AS depth
            FROM `" . DB_PREFIX . "category_path` cp
            WHERE cp.category_id IN (" . $in . ")
            GROUP BY cp.category_id
            ORDER BY depth DESC, cp.category_id DESC");

        $ordered = array();
        foreach ($query->rows as $row) {
            $ordered[] = (int)$row['category_id'];
        }

        if (count($ordered) < count($category_ids)) {
            foreach ($category_ids as $category_id) {
                if (!in_array($category_id, $ordered, true)) {
                    $ordered[] = $category_id;
                }
            }
        }

        return $ordered;
    }

    private function expandCategoryIdsWithAncestors($category_ids) {
        $category_ids = array_values(array_unique(array_filter(array_map('intval', (array)$category_ids))));
        if (!$category_ids) {
            return array();
        }

        $in = implode(',', $category_ids);
        $query = $this->db->query("SELECT DISTINCT cp.path_id AS category_id
            FROM `" . DB_PREFIX . "category_path` cp
            WHERE cp.category_id IN (" . $in . ")");

        $expanded = $category_ids;
        foreach ($query->rows as $row) {
            $expanded[] = (int)$row['category_id'];
        }

        return array_values(array_unique(array_filter($expanded)));
    }

    private function canDeleteCategory($category_id) {
        $category_id = (int)$category_id;
        if ($category_id <= 0) {
            return false;
        }

        $category = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category` WHERE category_id = '" . $category_id . "' LIMIT 1");
        if (!$category->num_rows) {
            return false;
        }

        $has_children = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category` WHERE parent_id = '" . $category_id . "' LIMIT 1");
        if ($has_children->num_rows) {
            return false;
        }

        $has_products = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_to_category` WHERE category_id = '" . $category_id . "' LIMIT 1");
        if ($has_products->num_rows) {
            return false;
        }

        return true;
    }

    private function deleteCategoryById($category_id) {
        $category_id = (int)$category_id;
        if ($category_id <= 0) {
            return;
        }

        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id = '" . $category_id . "' OR path_id = '" . $category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_description` WHERE category_id = '" . $category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_to_store` WHERE category_id = '" . $category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_to_layout` WHERE category_id = '" . $category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_filter` WHERE category_id = '" . $category_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category` WHERE category_id = '" . $category_id . "'");
    }

    private function resolveManufacturerId($vendor_name, $store_id = 0) {
        $vendor_name = trim($vendor_name);
        if ($vendor_name === '') {
            return 0;
        }

        $store_id = (int)$store_id;

        $query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE name = '" . $this->db->escape($vendor_name) . "' LIMIT 1");
        if ($query->num_rows) {
            $manufacturer_id = (int)$query->row['manufacturer_id'];
            $this->ensureManufacturerStore($manufacturer_id, 0);
            if ($store_id > 0) {
                $this->ensureManufacturerStore($manufacturer_id, $store_id);
            }

            return $manufacturer_id;
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET name = '" . $this->db->escape($vendor_name) . "', sort_order = 0");
        $manufacturer_id = (int)$this->db->getLastId();
        $this->ensureManufacturerStore($manufacturer_id, 0);
        if ($store_id > 0) {
            $this->ensureManufacturerStore($manufacturer_id, $store_id);
        }

        return $manufacturer_id;
    }

    private function ensureManufacturerStore($manufacturer_id, $store_id) {
        $manufacturer_id = (int)$manufacturer_id;
        $store_id = (int)$store_id;

        if ($manufacturer_id <= 0 || $store_id < 0) {
            return;
        }

        $exists = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer_to_store`
            WHERE manufacturer_id = '" . $manufacturer_id . "' AND store_id = '" . $store_id . "' LIMIT 1");

        if (!$exists->num_rows) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer_to_store`
                SET manufacturer_id = '" . $manufacturer_id . "', store_id = '" . $store_id . "'");
        }
    }

    private function findProductByOffer($profile_id, $offer_id) {
        $query = $this->db->query("SELECT product_id
            FROM `" . DB_PREFIX . "dockercart_import_yml_offer_map`
            WHERE profile_id = '" . (int)$profile_id . "'
              AND offer_id = '" . $this->db->escape($offer_id) . "'
            LIMIT 1");

        return $query->num_rows ? (int)$query->row['product_id'] : 0;
    }

    private function findProductBySkuOrModel($sku, $model) {
        $where = array();
        if ($sku !== '') {
            $where[] = "sku = '" . $this->db->escape($sku) . "'";
        }
        if ($model !== '') {
            $where[] = "model = '" . $this->db->escape($model) . "'";
        }

        if (!$where) {
            return 0;
        }

        $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE " . implode(' OR ', $where) . " LIMIT 1");
        return $query->num_rows ? (int)$query->row['product_id'] : 0;
    }

    private function upsertOfferMap($profile_id, $offer_id, $product_id) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_import_yml_offer_map`
            SET profile_id = '" . (int)$profile_id . "',
                offer_id = '" . $this->db->escape($offer_id) . "',
                product_id = '" . (int)$product_id . "',
                date_modified = NOW()
            ON DUPLICATE KEY UPDATE
                product_id = VALUES(product_id),
                date_modified = NOW()");
    }

    private function addProduct($data) {
        $stock_status_id = (int)$this->config->get('config_stock_status_id');
        if ($stock_status_id <= 0) {
            $stock_status_id = 5;
        }

        $image = isset($data['image']) ? $this->db->escape($data['image']) : '';

        $this->db->query("INSERT INTO `" . DB_PREFIX . "product`
            SET model = '" . $this->db->escape($data['model']) . "',
                sku = '" . $this->db->escape($data['sku']) . "',
                upc = '',
                ean = '',
                jan = '',
                isbn = '',
                mpn = '',
                location = '',
                quantity = '" . (int)$data['quantity'] . "',
                stock_status_id = '" . $stock_status_id . "',
                image = '" . $image . "',
                manufacturer_id = '" . (int)$data['manufacturer_id'] . "',
                shipping = '1',
                price = '" . (float)$data['price'] . "',
                points = '0',
                tax_class_id = '0',
                date_available = NOW(),
                weight = '0',
                weight_class_id = '1',
                length = '0',
                width = '0',
                height = '0',
                length_class_id = '1',
                subtract = '1',
                minimum = '1',
                sort_order = '0',
                status = '1',
                viewed = '0',
                date_added = NOW(),
                date_modified = NOW()");

        $product_id = (int)$this->db->getLastId();

        $this->upsertProductDescription($product_id, $data['language_id'], $data['name'], $data['description']);
        $this->upsertProductStore($product_id, $data['store_id']);
        $this->upsertProductCategory($product_id, $data['category_id']);

        // Add additional images if provided
        if (!empty($data['additional_images'])) {
            $this->addProductImages($product_id, $data['additional_images']);
        }

        return $product_id;
    }

    private function updateProduct($product_id, $data) {
        $image = isset($data['image']) ? $this->db->escape($data['image']) : '';

        $this->db->query("UPDATE `" . DB_PREFIX . "product`
            SET model = '" . $this->db->escape($data['model']) . "',
                sku = '" . $this->db->escape($data['sku']) . "',
                quantity = '" . (int)$data['quantity'] . "',
                manufacturer_id = '" . (int)$data['manufacturer_id'] . "',
                price = '" . (float)$data['price'] . "',
                status = '1',
                date_modified = NOW()" . ($image !== '' ? ", image = '" . $image . "'" : "") . "
            WHERE product_id = '" . (int)$product_id . "'");

        $this->upsertProductDescription($product_id, $data['language_id'], $data['name'], $data['description']);
        $this->upsertProductStore($product_id, $data['store_id']);
        $this->upsertProductCategory($product_id, $data['category_id']);

        // Update additional images if provided
        if (!empty($data['additional_images'])) {
            $this->updateProductImages($product_id, $data['additional_images']);
        }
    }

    private function updateProductPriceQuantity($product_id, $data) {
        $this->db->query("UPDATE `" . DB_PREFIX . "product`
            SET quantity = '" . (int)$data['quantity'] . "',
                price = '" . (float)$data['price'] . "',
                date_modified = NOW()
            WHERE product_id = '" . (int)$product_id . "'");
    }

    private function upsertProductDescription($product_id, $language_id, $name, $description) {
        $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_description` WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");

        if ($query->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product_description`
                SET name = '" . $this->db->escape($name) . "',
                    description = '" . $this->db->escape($description) . "',
                    meta_title = '" . $this->db->escape($name) . "'
                WHERE product_id = '" . (int)$product_id . "'
                  AND language_id = '" . (int)$language_id . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_description`
                SET product_id = '" . (int)$product_id . "',
                    language_id = '" . (int)$language_id . "',
                    name = '" . $this->db->escape($name) . "',
                    description = '" . $this->db->escape($description) . "',
                    tag = '',
                    meta_title = '" . $this->db->escape($name) . "',
                    meta_description = '',
                    meta_keyword = ''");
        }
    }

    private function upsertProductStore($product_id, $store_id) {
        $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = '" . (int)$product_id . "' AND store_id = '" . (int)$store_id . "' LIMIT 1");
        if (!$query->num_rows) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
        }
    }

    private function upsertProductCategory($product_id, $category_id) {
        if ((int)$category_id <= 0) {
            return;
        }

        $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$product_id . "' AND category_id = '" . (int)$category_id . "' LIMIT 1");
        if (!$query->num_rows) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
        }
    }

    private function deleteAllStoreProducts($store_id) {
        $store_id = (int)$store_id;
        $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_to_store` WHERE store_id = '" . $store_id . "'");
        if (!$query->num_rows) {
            return;
        }

        $ids = array();
        foreach ($query->rows as $row) {
            $ids[] = (int)$row['product_id'];
        }

        $this->deleteByProductIds(DB_PREFIX . 'product_attribute', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_description', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_discount', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_filter', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_image', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_option', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_option_value', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_related', $ids, 'product_id');
        $this->deleteByProductIds(DB_PREFIX . 'product_related', $ids, 'related_id');
        $this->deleteByProductIds(DB_PREFIX . 'product_reward', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_special', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_to_category', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_to_download', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_to_layout', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product_to_store', $ids);
        $this->deleteByProductIds(DB_PREFIX . 'product', $ids);
    }

    /**
     * Full wipe for replace mode:
     * products + categories + manufacturers + importer maps.
     */
    private function deleteAllCatalogData() {
        $tables = array(
            // Product relations and entities
            'product_related',
            'product_option_value',
            'product_option',
            'product_attribute',
            'product_discount',
            'product_filter',
            'product_image',
            'product_reward',
            'product_special',
            'product_recurring',
            'product_to_download',
            'product_to_layout',
            'product_to_store',
            'product_to_category',
            'product_description',
            'product',

            // Category entities
            'category_filter',
            'category_path',
            'category_to_layout',
            'category_to_store',
            'category_description',
            'category',

            // Manufacturer entities
            'manufacturer_to_store',
            'manufacturer',

            // Importer maps
            'dockercart_import_yml_offer_map',
            'dockercart_import_yml_category_map'
        );

        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . $table . "`");
            }
        }
    }

    private function tableExists($table) {
        $table = trim((string)$table);
        if ($table === '') {
            return false;
        }

        $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
        return (bool)$query->num_rows;
    }

    private function deleteByProductIds($table, $ids, $column = 'product_id') {
        if (!$ids) {
            return;
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            $chunk = array_map('intval', $chunk);
            $this->db->query("DELETE FROM `" . $table . "` WHERE `" . $column . "` IN (" . implode(',', $chunk) . ")");
        }
    }

    private function writeProgress($profile_id, $summary, $touchLastRun = false) {
        $sql = "UPDATE `" . DB_PREFIX . "dockercart_import_yml_profile`
            SET `last_result` = '" . $this->db->escape(json_encode($summary, JSON_UNESCAPED_UNICODE)) . "',
                `date_modified` = NOW()";

        if ($touchLastRun) {
            $sql .= ", `last_run` = NOW()";
        }

        $sql .= " WHERE `profile_id` = '" . (int)$profile_id . "'";

        $this->db->query($sql);
    }

    /**
     * Extract image URLs from YML offer <picture> tags.
     * Uses foreach which correctly iterates all same-name SimpleXML siblings.
     */
    private function extractImageUrls($offer) {
        $urls = array();

        foreach ($offer->picture as $picture) {
            $url = $this->normalizeImageUrl((string)$picture);
            if ($url !== '' && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Normalize image URL to avoid cURL issues with spaces/Cyrillic and HTML entities.
     */
    private function normalizeImageUrl($url) {
        $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return '';
        }

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        $parts = @parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        if (!empty($parts['path'])) {
            $parts['path'] = $this->encodeUrlPath($parts['path']);
        }

        return $this->buildUrlFromParts($parts);
    }

    private function encodeUrlPath($path) {
        $segments = explode('/', (string)$path);

        foreach ($segments as &$segment) {
            if ($segment === '') {
                continue;
            }

            $segment = rawurlencode(rawurldecode($segment));
        }
        unset($segment);

        return implode('/', $segments);
    }

    private function buildUrlFromParts($parts) {
        $url = '';

        if (!empty($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }

        if (!empty($parts['user'])) {
            $url .= $parts['user'];
            if (!empty($parts['pass'])) {
                $url .= ':' . $parts['pass'];
            }
            $url .= '@';
        }

        $url .= $parts['host'];

        if (!empty($parts['port'])) {
            $url .= ':' . (int)$parts['port'];
        }

        $url .= isset($parts['path']) ? $parts['path'] : '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            $url .= '?' . $parts['query'];
        }

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $url .= '#' . $parts['fragment'];
        }

        return $url;
    }

    /**
     * Download product images and organize them in subfolders.
     * Returns array with 'main' and 'additional' image paths.
     */
    private function downloadProductImages($urls, $offer_id, $profile_id = 0) {
        $result = array(
            'main' => '',
            'additional' => array()
        );

        if (empty($urls)) {
            return $result;
        }

        $base_dir = rtrim(DIR_IMAGE, '/\\') . '/catalog';

        if (!$this->ensureDirectory($base_dir)) {
            throw new Exception('Failed to create or access base image directory: ' . $base_dir);
        }

        foreach ($urls as $url) {
            try {
                $downloaded_path = $this->downloadImage($url, $base_dir, (int)$profile_id);

                if ($downloaded_path !== '') {
                    $relative_path = $this->toRelativeImagePath($downloaded_path);

                    if ($result['main'] === '') {
                        $result['main'] = $relative_path;
                    } elseif (!in_array($relative_path, $result['additional'], true)) {
                        $result['additional'][] = $relative_path;
                    }
                }
            } catch (Exception $e) {
                error_log("DockerCart Import YML: Image download failed ({$url}): " . $e->getMessage());
                continue;
            }
        }

        return $result;
    }

    /**
     * Generate subfolder name based on offer_id
     * Uses first 2 characters to distribute files
     */
    private function generateImageSubfolder($offer_id) {
        $hash = md5($offer_id);
        // Create subfolder using first 2 chars: a/b/
        return substr($hash, 0, 1) . '/' . substr($hash, 1, 1);
    }

    /**
     * Convert absolute image path to DB relative path (catalog/...)
     */
    private function toRelativeImagePath($absolute_path) {
        $absolute_path = str_replace('\\\\', '/', (string)$absolute_path);
        $dir_image = str_replace('\\\\', '/', rtrim((string)DIR_IMAGE, '/'));

        if (strpos($absolute_path, $dir_image . '/') === 0) {
            return ltrim(substr($absolute_path, strlen($dir_image)), '/');
        }

        return ltrim($absolute_path, '/');
    }

    /**
     * Ensure directory exists and is writable, without emitting PHP warnings
     */
    private function ensureDirectory($dir) {
        if (is_dir($dir)) {
            return is_writable($dir);
        }

        $old_error = error_reporting();
        error_reporting($old_error & ~E_WARNING);
        $created = @mkdir($dir, 0755, true);
        error_reporting($old_error);

        if (!$created && !is_dir($dir)) {
            return false;
        }

        return is_writable($dir);
    }

    /**
     * Download single image from URL
     */
    private function downloadImage($url, $base_dir, $profile_id = 0) {
        $url = $this->normalizeImageUrl($url);

        if ($url === '') {
            throw new Exception('Empty URL');
        }

        // Generate unique filename based on URL
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if ($extension === '') {
            $extension = 'jpg';
        }

        // Sanitize extension
        $extension = strtolower($extension);
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        if (!in_array($extension, $allowed_extensions)) {
            $extension = 'jpg';
        }

        // Use URL hash as global key to avoid duplicate downloads across offers
        $hash = md5($url);
        $profile_segment = 'profile_' . max(0, (int)$profile_id);
        $subfolder = 'dockercart_import_yml/' . $profile_segment . '/' . substr($hash, 0, 1) . '/' . substr($hash, 1, 1);
        $preferred_dir = rtrim($base_dir, '/\\') . '/' . $subfolder;
        $fallback_dir = rtrim($base_dir, '/\\');

        $target_dir = $preferred_dir;
        if (!$this->ensureDirectory($preferred_dir)) {
            if (!$this->ensureDirectory($fallback_dir)) {
                throw new Exception('Failed to create or access image directory: ' . $fallback_dir);
            }
            $target_dir = $fallback_dir;
        }

        $filename = $hash . '.' . $extension;
        $target_path = $target_dir . '/' . $filename;

        // Skip if file already exists
        if (file_exists($target_path)) {
            return $target_path;
        }

        // Download image
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DockerCart-ImportYML/1.1');
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($errno || $code >= 400 || $content === false || $content === '') {
            throw new Exception("Failed to download ({$url}): HTTP {$code}" . ($errno ? ", cURL: {$error}" : ""));
        }

        if ($content_type !== ''
            && stripos($content_type, 'image/') !== 0
            && stripos($content_type, 'application/octet-stream') !== 0) {
            throw new Exception('Downloaded content is not an image. Content-Type: ' . $content_type);
        }

        if (strlen($content) < 100) {
            throw new Exception('Downloaded file is too small');
        }

        // Save image
        if (file_put_contents($target_path, $content, LOCK_EX) === false) {
            throw new Exception('Failed to save image to: ' . $target_path);
        }

        return $target_path;
    }

    /**
     * Add additional images to product
     */
    private function addProductImages($product_id, $images) {
        if (empty($images)) {
            return;
        }

        $images = array_values(array_unique(array_filter(array_map('strval', (array)$images))));

        foreach ($images as $image) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_image`
                SET product_id = '" . (int)$product_id . "',
                    image = '" . $this->db->escape($image) . "',
                    sort_order = '0'");
        }
    }

    /**
     * Update product images (replace existing ones)
     */
    private function updateProductImages($product_id, $images) {
        if (empty($images)) {
            return;
        }

        $images = array_values(array_unique(array_filter(array_map('strval', (array)$images))));

        // Get existing images
        $existing = $this->db->query("SELECT image FROM `" . DB_PREFIX . "product_image`
            WHERE product_id = '" . (int)$product_id . "'");

        $existing_images = array();
        foreach ($existing->rows as $row) {
            $existing_images[] = $row['image'];
        }

        // Add new images that don't exist
        foreach ($images as $image) {
            if (!in_array($image, $existing_images)) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_image`
                    SET product_id = '" . (int)$product_id . "',
                        image = '" . $this->db->escape($image) . "',
                        sort_order = '0'");
            }
        }
    }
}
