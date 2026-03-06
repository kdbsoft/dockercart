<?php
class ModelExtensionModuleDockercartImportYml extends Model {

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

        if ($offset === 0) {
            $feed_categories = $this->buildFeedCategories($xml);
            $load_categories = !isset($profile['load_categories']) || (int)$profile['load_categories'] === 1;
            $default_category_id = (int)$profile['default_category_id'];

            if ($load_categories) {
                $root_parent_id = $default_category_id > 0 ? $default_category_id : 0;
                $category_import = $this->importFeedCategories($profile, $feed_categories, $root_parent_id);
                $category_map = $category_import['map'];
            } else {
                $category_import = array(
                    'total' => count($feed_categories),
                    'created' => 0,
                    'mapped' => 0,
                    'skipped' => count($feed_categories),
                    'map' => array()
                );
                $category_map = array();
            }

            $summary['categories_in_feed'] = (int)$category_import['total'];
            $summary['categories_created'] = (int)$category_import['created'];
            $summary['categories_mapped'] = (int)$category_import['mapped'];
            $summary['categories_skipped'] = (int)$category_import['skipped'];
        }

        $category_payload = $this->buildFeedCategoryMap($profile, $xml, $offset);
        $feed_category_map = $category_payload['map'];

        if ($offset === 0 && isset($category_payload['stats'])) {
            $summary['categories_in_feed'] = (int)$category_payload['stats']['total'];
            $summary['categories_created'] = (int)$category_payload['stats']['created'];
            $summary['categories_mapped'] = (int)$category_payload['stats']['mapped'];
            $summary['categories_skipped'] = (int)$category_payload['stats']['skipped'];
        }

        if ($offset === 0 && (string)$profile['import_mode'] === 'replace') {
            $this->deleteAllStoreProducts((int)$profile['store_id']);
            $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_import_yml_offer_map` WHERE `profile_id` = '" . (int)$profile_id . "'");
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

                $vendor = $this->xmlText($offer, 'vendor');
                $vendor_code = $this->xmlText($offer, 'vendorCode');
                $description = $this->prepareDescription($this->xmlText($offer, 'description'));

                $quantity = $this->extractQuantity($offer);
                $category_id = $this->resolveCategoryId($offer, $profile, $feed_category_map);
                $manufacturer_id = $this->resolveManufacturerId($vendor);

                // Extract image URLs from offer (if enabled in profile)
                $download_images = !isset($profile['download_images']) || (int)$profile['download_images'] === 1;

                // Debug logging
                error_log("DockerCart Import YML: Offer {$offer_id} - download_images setting: " . ($download_images ? 'enabled' : 'disabled'));
                error_log("DockerCart Import YML: Offer {$offer_id} - profile download_images value: " . (isset($profile['download_images']) ? $profile['download_images'] : 'not set'));

                $image_urls = $download_images ? $this->extractImageUrls($offer) : array();
                $main_image = '';
                $additional_images = array();

                // Debug logging for extracted URLs
                if ($download_images) {
                    error_log("DockerCart Import YML: Offer {$offer_id} - extracted " . count($image_urls) . " image URLs: " . implode(', ', $image_urls));
                }

                if (!empty($image_urls)) {
                    try {
                        error_log("DockerCart Import YML: Starting image download for offer {$offer_id}");
                        $downloaded = $this->downloadProductImages($image_urls, $offer_id, (int)$profile_id);
                        $main_image = $downloaded['main'];
                        $additional_images = $downloaded['additional'];

                        // Log successful download
                        if ($main_image !== '') {
                            error_log("DockerCart Import YML: Successfully downloaded " . count($image_urls) . " images for offer {$offer_id}, main: {$main_image}");
                        } else {
                            error_log("DockerCart Import YML: Downloaded images but no main image set for offer {$offer_id}");
                        }
                    } catch (Exception $e) {
                        // Log download error and continue without images
                        error_log("DockerCart Import YML: Failed to download images for offer {$offer_id}: " . $e->getMessage());
                        error_log("DockerCart Import YML: Exception trace: " . $e->getTraceAsString());
                    }
                } else if ($download_images) {
                    // Log when no images found in offer
                    error_log("DockerCart Import YML: No images found in offer {$offer_id}");
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

                if (!$local_id) {
                    $local_id = $this->createCategory($item['name'], $language_id, $store_id, $parent_local_id);
                    $result['created']++;
                }

                $result['map'][$feed_id] = (int)$local_id;
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
                    if (!$local_id) {
                        $local_id = $this->createCategory($item['name'], $language_id, $store_id, $fallback_parent_id);
                        $result['created']++;
                    }

                    $result['map'][$feed_id] = (int)$local_id;
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

    private function createCategory($name, $language_id, $store_id, $parent_id) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category`
            SET image = '',
                parent_id = '" . (int)$parent_id . "',
                `top` = '0',
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

    private function resolveManufacturerId($vendor_name) {
        $vendor_name = trim($vendor_name);
        if ($vendor_name === '') {
            return 0;
        }

        $query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE name = '" . $this->db->escape($vendor_name) . "' LIMIT 1");
        if ($query->num_rows) {
            return (int)$query->row['manufacturer_id'];
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET name = '" . $this->db->escape($vendor_name) . "', sort_order = 0");
        $manufacturer_id = (int)$this->db->getLastId();
        $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer_to_store` SET manufacturer_id = '" . $manufacturer_id . "', store_id = '0'");

        return $manufacturer_id;
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
     * Extract image URLs from YML offer
     * Supports <picture> tags and multiple images
     */
    private function extractImageUrls($offer) {
        $urls = array();

        // Check if picture exists
        if (!isset($offer->picture)) {
            error_log("DockerCart Import YML: extractImageUrls - No picture tag found in offer");
            return $urls;
        }

        $picture_count = count($offer->picture);
        error_log("DockerCart Import YML: extractImageUrls - Found {$picture_count} picture tag(s)");

        // Handle multiple <picture> tags (SimpleXML returns array-like object)
        if ($picture_count > 1) {
            foreach ($offer->picture as $picture) {
                $url = trim((string)$picture);
                error_log("DockerCart Import YML: extractImageUrls - Processing picture: '{$url}'");
                if ($url !== '' && !in_array($url, $urls)) {
                    $urls[] = $url;
                }
            }
        } else {
            // Single <picture> tag
            $url = trim((string)$offer->picture);
            error_log("DockerCart Import YML: extractImageUrls - Single picture: '{$url}'");
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        error_log("DockerCart Import YML: extractImageUrls - Returning " . count($urls) . " URLs");
        return $urls;
    }

    /**
     * Download product images and organize them in subfolders
     * Returns array with 'main' and 'additional' image paths
     */
    private function downloadProductImages($urls, $offer_id, $profile_id = 0) {
        error_log("DockerCart Import YML: downloadProductImages - Starting for offer {$offer_id} with " . count($urls) . " URLs");

        $result = array(
            'main' => '',
            'additional' => array()
        );

        if (empty($urls)) {
            error_log("DockerCart Import YML: downloadProductImages - No URLs provided");
            return $result;
        }

        // Ensure base directory exists
        $base_dir = rtrim(DIR_IMAGE, '/\\') . '/catalog';
        error_log("DockerCart Import YML: downloadProductImages - Base directory: {$base_dir}");

        if (!$this->ensureDirectory($base_dir)) {
            $error_msg = 'Failed to create or access base image directory: ' . $base_dir;
            error_log("DockerCart Import YML: downloadProductImages - " . $error_msg);
            throw new Exception($error_msg);
        }

        foreach ($urls as $index => $url) {
            try {
                error_log("DockerCart Import YML: downloadProductImages - Processing image {$index}: {$url}");
                $downloaded_path = $this->downloadImage($url, $base_dir, (int)$profile_id);

                if ($downloaded_path !== '') {
                    // Relative path for database (without DIR_IMAGE)
                    $relative_path = $this->toRelativeImagePath($downloaded_path);
                    error_log("DockerCart Import YML: downloadProductImages - Relative path: {$relative_path}");

                    if ($index === 0) {
                        $result['main'] = $relative_path;
                        error_log("DockerCart Import YML: downloadProductImages - Set as main image");
                    } else {
                        $result['additional'][] = $relative_path;
                        error_log("DockerCart Import YML: downloadProductImages - Added to additional images");
                    }
                }
            } catch (Exception $e) {
                // Skip this image on error
                error_log("DockerCart Import YML: downloadProductImages - Error processing image {$index}: " . $e->getMessage());
                continue;
            }
        }

        error_log("DockerCart Import YML: downloadProductImages - Completed. Main: {$result['main']}, Additional: " . count($result['additional']));
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
        $url = trim($url);
        error_log("DockerCart Import YML: downloadImage - Starting download from: {$url}");

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
        // Preferred path: image/catalog/dockercart_import_yml/profile_{id}/{a}/{b}/hash.ext
        // Fallback path (if mkdir permissions are restricted): image/catalog/hash.ext
        $hash = md5($url);
        $profile_segment = 'profile_' . max(0, (int)$profile_id);
        $subfolder = 'dockercart_import_yml/' . $profile_segment . '/' . substr($hash, 0, 1) . '/' . substr($hash, 1, 1);
        $preferred_dir = rtrim($base_dir, '/\\') . '/' . $subfolder;
        $fallback_dir = rtrim($base_dir, '/\\');

        $target_dir = $preferred_dir;
        if (!$this->ensureDirectory($preferred_dir)) {
            error_log("DockerCart Import YML: downloadImage - Cannot create/access preferred dir {$preferred_dir}, fallback to {$fallback_dir}");
            if (!$this->ensureDirectory($fallback_dir)) {
                throw new Exception('Failed to create or access image directory: ' . $fallback_dir);
            }
            $target_dir = $fallback_dir;
        }

        $filename = $hash . '.' . $extension;
        $target_path = $target_dir . '/' . $filename;

        error_log("DockerCart Import YML: downloadImage - Target path: {$target_path}");

        // Skip if file already exists
        if (file_exists($target_path)) {
            error_log("DockerCart Import YML: downloadImage - File already exists, skipping download");
            return $target_path;
        }

        // Download image
        error_log("DockerCart Import YML: downloadImage - Initiating cURL request");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DockerCart-ImportYML/1.1');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $content = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log("DockerCart Import YML: downloadImage - cURL response code: {$code}, errno: {$errno}");

        if ($errno || $code >= 400 || $content === false || $content === '') {
            $error_msg = "Failed to download image from {$url}: HTTP {$code}, cURL error: {$error}";
            error_log("DockerCart Import YML: downloadImage - " . $error_msg);
            throw new Exception($error_msg);
        }

        // Validate that content is an image
        $content_length = strlen($content);
        error_log("DockerCart Import YML: downloadImage - Downloaded {$content_length} bytes");

        if ($content_length < 100) {
            throw new Exception('Downloaded file is too small');
        }

        // Save image
        if (file_put_contents($target_path, $content, LOCK_EX) === false) {
            error_log("DockerCart Import YML: downloadImage - Failed to write file to {$target_path}");
            throw new Exception('Failed to save image');
        }

        error_log("DockerCart Import YML: downloadImage - Successfully saved image to {$target_path}");
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
