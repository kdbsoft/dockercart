<?php
class ModelExtensionModuleDockercartImportExportExcel extends Model {
    private $option_type_cache = array();

    public function getProfile($profile_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_import_export_excel_profile` WHERE `profile_id` = '" . (int)$profile_id . "'");

        if (!$query->num_rows) {
            return null;
        }

        $row = $query->row;
        $row['field_map'] = $this->decodeJson((string)$row['field_map_json']);
        $row['extra_settings'] = $this->decodeJson((string)$row['extra_settings_json']);

        return $row;
    }

    public function runImport($profile_id) {
        $profile = $this->getProfile($profile_id);

        if (!$profile) {
            throw new Exception('Profile not found');
        }

        if (!(int)$profile['status']) {
            throw new Exception('Profile is disabled');
        }

        $rows = $this->loadSourceRows($profile);
        if (!$rows) {
            throw new Exception('No rows found in source file');
        }

        $field_map = is_array($profile['field_map']) ? $profile['field_map'] : array();
        $category_map = $this->buildCategoryMap($profile);
        $attribute_rules = $this->buildAttributeRules($profile);
        $option_rules = $this->buildOptionRules($profile);
        $mode = (string)$profile['import_mode'];

        $summary = array(
            'profile_id' => (int)$profile_id,
            'mode' => $mode,
            'total_rows' => count($rows),
            'processed' => 0,
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        );

        if ($mode === 'replace') {
            $this->deleteAllStoreProducts((int)$profile['store_id']);
            $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_import_export_excel_row_map` WHERE `profile_id` = '" . (int)$profile_id . "'");
        }

        foreach ($rows as $row) {
            $summary['processed']++;

            try {
                $data = $this->extractProductDataFromRow($row, $field_map, $profile, $category_map, $attribute_rules, $option_rules);

                if ($data['name'] === '' || ($data['sku'] === '' && $data['model'] === '' && $data['external_id'] === '')) {
                    $summary['skipped']++;
                    continue;
                }

                $existing_product_id = 0;
                if ($data['external_id'] !== '') {
                    $existing_product_id = $this->findProductByExternalId($profile_id, $data['external_id']);
                }

                if (!$existing_product_id) {
                    $existing_product_id = $this->findProductBySkuOrModel($data['sku'], $data['model']);
                }

                if ($mode === 'add' && $existing_product_id) {
                    $summary['skipped']++;
                    if ($data['external_id'] !== '') {
                        $this->upsertExternalMap($profile_id, $data['external_id'], $existing_product_id);
                    }
                    continue;
                }

                if (($mode === 'update_only' || $mode === 'update_price_qty_only') && !$existing_product_id) {
                    $summary['skipped']++;
                    continue;
                }

                if ($existing_product_id) {
                    if ($mode === 'update_price_qty_only') {
                        $this->updateProductPriceQuantity($existing_product_id, $data);
                    } else {
                        $this->updateProduct($existing_product_id, $data);
                    }

                    $summary['updated']++;

                    if ($data['external_id'] !== '') {
                        $this->upsertExternalMap($profile_id, $data['external_id'], $existing_product_id);
                    }
                } else {
                    $product_id = $this->addProduct($data);
                    $summary['added']++;

                    if ($data['external_id'] !== '') {
                        $this->upsertExternalMap($profile_id, $data['external_id'], $product_id);
                    }
                }
            } catch (Exception $e) {
                $summary['errors']++;
            }
        }

        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_import_export_excel_profile`
            SET `last_run` = NOW(),
                `last_result` = '" . $this->db->escape(json_encode($summary, JSON_UNESCAPED_UNICODE)) . "',
                `date_modified` = NOW()
            WHERE `profile_id` = '" . (int)$profile_id . "'");

        return $summary;
    }

    public function runExport($profile_id, $file_format = 'xlsx') {
        $profile = $this->getProfile($profile_id);

        if (!$profile) {
            throw new Exception('Profile not found');
        }

        if (!(int)$profile['status']) {
            throw new Exception('Profile is disabled');
        }

        $file_format = in_array($file_format, array('xlsx', 'csv')) ? $file_format : 'xlsx';

        $rows = $this->getRowsForExport($profile);
        $dir = rtrim(DIR_STORAGE, '/\\') . '/dockercart_import_export_excel/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'dockercart_export_profile_' . (int)$profile_id . '_' . date('Ymd_His') . '.' . $file_format;
        $filepath = $dir . '/' . $filename;

        if ($file_format === 'csv') {
            $this->writeCsvFile($filepath, $rows);
        } else {
            $this->writeXlsxFile($filepath, $rows);
        }

        $summary = array(
            'profile_id' => (int)$profile_id,
            'file' => $filepath,
            'filename' => $filename,
            'format' => $file_format,
            'rows' => max(0, count($rows) - 1)
        );

        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_import_export_excel_profile`
            SET `last_run` = NOW(),
                `last_result` = '" . $this->db->escape(json_encode($summary, JSON_UNESCAPED_UNICODE)) . "',
                `date_modified` = NOW()
            WHERE `profile_id` = '" . (int)$profile_id . "'");

        return $summary;
    }

    public function getExportFileByName($filename) {
        $filename = basename((string)$filename);
        $path = rtrim(DIR_STORAGE, '/\\') . '/dockercart_import_export_excel/exports/' . $filename;

        if (!is_file($path)) {
            return null;
        }

        return $path;
    }

    public function previewSourceRows($profile, $limit = 10) {
        if (!is_array($profile)) {
            throw new Exception('Invalid profile data for preview');
        }

        $rows = $this->loadSourceRows($profile);
        if (!$rows) {
            return array();
        }

        $limit = max(1, (int)$limit);
        return array_slice($rows, 0, $limit);
    }

    private function loadSourceRows($profile) {
        $source_type = (string)$profile['source_type'];
        $source_format = $this->resolveSourceFormat($profile);

        $content = '';
        $filepath = '';

        if ($source_type === 'file') {
            $filepath = (string)$profile['source_file'];
            if ($filepath === '' || !is_file($filepath)) {
                throw new Exception('Source file not found');
            }

            if ($source_format === 'csv') {
                $content = file_get_contents($filepath);
            }
        } else {
            $url = trim((string)$profile['source_url']);
            if ($url === '') {
                throw new Exception('Source URL is empty');
            }

            $content = $this->fetchRemoteContent($url);
            if ($source_format === 'xlsx') {
                $temp_dir = rtrim(DIR_STORAGE, '/\\') . '/dockercart_import_export_excel/tmp';
                if (!is_dir($temp_dir)) {
                    mkdir($temp_dir, 0775, true);
                }

                $filepath = $temp_dir . '/source_' . md5($url . microtime(true)) . '.xlsx';
                file_put_contents($filepath, $content);
            }
        }

        if ($source_format === 'csv') {
            $delimiter = (string)$profile['delimiter'];
            $rows = $this->parseCsvRows($content, $delimiter);
        } elseif ($source_format === 'xlsx') {
            $rows = $this->parseXlsxRows($filepath, (int)$profile['sheet_index']);
        } else {
            throw new Exception('Unsupported source format');
        }

        $rows = $this->normalizeRows($rows, (int)$profile['has_header'] === 1, max(1, (int)$profile['start_row']));

        if (!empty($filepath) && strpos($filepath, '/tmp/source_') !== false && is_file($filepath)) {
            @unlink($filepath);
        }

        return $rows;
    }

    private function normalizeRows($rows, $has_header, $start_row) {
        $result = array();
        if (!$rows) {
            return $result;
        }

        $start_index = max(0, (int)$start_row - 1);
        if ($has_header && $start_index < 1) {
            $start_index = 1;
        }

        for ($i = $start_index; $i < count($rows); $i++) {
            $row = $rows[$i];
            $normalized = array();

            foreach ($row as $idx => $value) {
                $normalized[((int)$idx + 1)] = trim((string)$value);
            }

            $has_value = false;
            foreach ($normalized as $v) {
                if ($v !== '') {
                    $has_value = true;
                    break;
                }
            }

            if ($has_value) {
                $result[] = $normalized;
            }
        }

        return $result;
    }

    private function extractProductDataFromRow($row, $field_map, $profile, $category_map = array(), $attribute_rules = array(), $option_rules = array()) {
        $external_id = $this->mappedValue($row, $field_map, 'external_id');
        $sku = $this->mappedValue($row, $field_map, 'sku');
        $model = $this->mappedValue($row, $field_map, 'model');
        $name = $this->mappedValue($row, $field_map, 'name');
        $description = $this->mappedValue($row, $field_map, 'description');
        $price = (float)str_replace(',', '.', $this->mappedValue($row, $field_map, 'price'));
        $quantity = (int)$this->mappedValue($row, $field_map, 'quantity');
        $manufacturer_name = $this->mappedValue($row, $field_map, 'manufacturer');
        $category_name = $this->mappedValue($row, $field_map, 'category');
        $main_image = $this->mappedValue($row, $field_map, 'image');
        $additional_images_raw = implode(',', $this->mappedValuesByColumns($row, $field_map, 'images'));

        if ($price < 0) {
            $price = 0;
        }
        if ($quantity < 0) {
            $quantity = 0;
        }

        if ($model === '' && $sku !== '') {
            $model = $sku;
        }

        if ($sku === '' && $model !== '') {
            $sku = $model;
        }

        if ($external_id === '') {
            $external_id = $sku !== '' ? $sku : $model;
        }

        $manufacturer_id = $this->resolveManufacturerId($manufacturer_name);
        $category_id = $this->resolveCategoryIdFromName($category_name, (int)$profile['default_category_id'], (int)$profile['language_id'], (int)$profile['store_id'], $category_map);
        $additional_images = $this->parseImageList($additional_images_raw);

        if ($main_image === '' && $additional_images) {
            $main_image = array_shift($additional_images);
        }

        $attributes = $this->mapAttributesFromRow($row, $attribute_rules);
        $options = $this->mapOptionsFromRow($row, $option_rules);

        return array(
            'external_id' => $external_id,
            'sku' => $sku,
            'model' => $model,
            'name' => $name,
            'description' => html_entity_decode(strip_tags((string)$description), ENT_QUOTES, 'UTF-8'),
            'price' => $price,
            'quantity' => $quantity,
            'manufacturer_id' => $manufacturer_id,
            'category_id' => $category_id,
            'image' => $main_image,
            'images' => $additional_images,
            'attributes' => $attributes,
            'options' => $options,
            'store_id' => (int)$profile['store_id'],
            'language_id' => (int)$profile['language_id']
        );
    }

    private function mappedValue($row, $field_map, $field) {
        if (!isset($field_map[$field])) {
            return '';
        }

        $column_index = (int)$field_map[$field];
        if ($column_index <= 0) {
            return '';
        }

        return isset($row[$column_index]) ? trim((string)$row[$column_index]) : '';
    }

    private function mappedValuesByColumns($row, $field_map, $field) {
        if (!isset($field_map[$field])) {
            return array();
        }

        $indexes = $this->parseColumnIndexes($field_map[$field]);
        if (!$indexes) {
            return array();
        }

        $values = array();
        foreach ($indexes as $index) {
            if (isset($row[$index])) {
                $value = trim((string)$row[$index]);
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    private function parseColumnIndexes($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return array();
        }

        $parts = preg_split('/[^0-9]+/', $value);
        $result = array();

        foreach ($parts as $part) {
            $index = (int)$part;
            if ($index > 0) {
                $result[] = $index;
            }
        }

        $result = array_values(array_unique($result));
        sort($result);

        return $result;
    }

    private function mapAttributesFromRow($row, $rules) {
        $result = array();
        if (!$rules) {
            return $result;
        }

        foreach ($rules as $rule) {
            $column = (int)$rule['column'];
            $attribute_id = (int)$rule['attribute_id'];
            $value = isset($row[$column]) ? trim((string)$row[$column]) : '';
            if ($attribute_id > 0 && $value !== '') {
                $result[] = array('attribute_id' => $attribute_id, 'text' => $value);
            }
        }

        return $result;
    }

    private function mapOptionsFromRow($row, $rules) {
        $result = array();
        if (!$rules) {
            return $result;
        }

        foreach ($rules as $rule) {
            $column = (int)$rule['column'];
            $option_id = (int)$rule['option_id'];
            $value = isset($row[$column]) ? trim((string)$row[$column]) : '';
            if ($option_id > 0 && $value !== '') {
                $result[] = array('option_id' => $option_id, 'value' => $value);
            }
        }

        return $result;
    }

    private function parseImageList($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return array();
        }

        $parts = preg_split('/[\|,;\n\r]+/', $raw);
        $result = array();
        foreach ($parts as $part) {
            $path = trim((string)$part);
            if ($path !== '') {
                $result[] = $path;
            }
        }

        return array_values(array_unique($result));
    }

    private function resolveCategoryIdFromName($category_name, $default_category_id, $language_id, $store_id, $category_map = array()) {
        $category_name = trim((string)$category_name);
        if ($category_name === '') {
            return (int)$default_category_id;
        }

        if ($category_map) {
            $key = mb_strtolower($category_name, 'UTF-8');
            if (isset($category_map[$key]) && (int)$category_map[$key] > 0) {
                return (int)$category_map[$key];
            }
        }

        $query = $this->db->query("SELECT c.category_id
            FROM `" . DB_PREFIX . "category` c
            INNER JOIN `" . DB_PREFIX . "category_description` cd ON (cd.category_id = c.category_id)
            WHERE cd.language_id = '" . (int)$language_id . "' AND cd.name = '" . $this->db->escape($category_name) . "'
            LIMIT 1");

        if ($query->num_rows) {
            return (int)$query->row['category_id'];
        }

        return $this->createCategory($category_name, $language_id, $store_id, (int)$default_category_id);
    }

    private function buildCategoryMap($profile) {
        $map = array();

        $extra = isset($profile['extra_settings']) && is_array($profile['extra_settings']) ? $profile['extra_settings'] : array();
        if (!empty($extra['category_rules']) && is_array($extra['category_rules'])) {
            foreach ($extra['category_rules'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $source_name = isset($rule['source']) ? trim((string)$rule['source']) : '';
                $target_id = isset($rule['category_id']) ? (int)$rule['category_id'] : 0;

                if ($source_name === '' || $target_id <= 0) {
                    continue;
                }

                $map[mb_strtolower($source_name, 'UTF-8')] = $target_id;
            }
        }

        // Backward compatibility for legacy text mapping
        $rules = isset($extra['category_map_text']) ? (string)$extra['category_map_text'] : '';
        if ($rules !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $rules);
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }

                $parts = explode('=', $line, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                $source_name = trim($parts[0]);
                $target_id = (int)trim($parts[1]);

                if ($source_name === '' || $target_id <= 0) {
                    continue;
                }

                $key = mb_strtolower($source_name, 'UTF-8');
                if (!isset($map[$key])) {
                    $map[$key] = $target_id;
                }
            }
        }

        return $map;
    }

    private function buildAttributeRules($profile) {
        $result = array();
        $extra = isset($profile['extra_settings']) && is_array($profile['extra_settings']) ? $profile['extra_settings'] : array();
        if (empty($extra['attribute_rules']) || !is_array($extra['attribute_rules'])) {
            return $result;
        }

        foreach ($extra['attribute_rules'] as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $attribute_id = isset($rule['attribute_id']) ? (int)$rule['attribute_id'] : 0;
            $column = isset($rule['column']) ? (int)$rule['column'] : 0;
            if ($attribute_id > 0 && $column > 0) {
                $result[] = array('attribute_id' => $attribute_id, 'column' => $column);
            }
        }

        return $result;
    }

    private function buildOptionRules($profile) {
        $result = array();
        $extra = isset($profile['extra_settings']) && is_array($profile['extra_settings']) ? $profile['extra_settings'] : array();
        if (empty($extra['option_rules']) || !is_array($extra['option_rules'])) {
            return $result;
        }

        foreach ($extra['option_rules'] as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $option_id = isset($rule['option_id']) ? (int)$rule['option_id'] : 0;
            $column = isset($rule['column']) ? (int)$rule['column'] : 0;
            if ($option_id > 0 && $column > 0) {
                $result[] = array('option_id' => $option_id, 'column' => $column);
            }
        }

        return $result;
    }

    private function resolveManufacturerId($name) {
        $name = trim((string)$name);
        if ($name === '') {
            return 0;
        }

        $query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE name = '" . $this->db->escape($name) . "' LIMIT 1");
        if ($query->num_rows) {
            return (int)$query->row['manufacturer_id'];
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET name = '" . $this->db->escape($name) . "', sort_order = 0");
        $manufacturer_id = (int)$this->db->getLastId();
        $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer_to_store` SET manufacturer_id = '" . $manufacturer_id . "', store_id = '0'");

        return $manufacturer_id;
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
                date_modified = NOW()"
        );

        $category_id = (int)$this->db->getLastId();

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_description`
            SET category_id = '" . $category_id . "',
                language_id = '" . (int)$language_id . "',
                name = '" . $this->db->escape($name) . "',
                description = '',
                meta_title = '" . $this->db->escape($name) . "',
                meta_description = '',
                meta_keyword = ''"
        );

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET category_id = '" . $category_id . "', store_id = '" . (int)$store_id . "'");
        if ((int)$store_id !== 0) {
            $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "category_to_store` SET category_id = '" . $category_id . "', store_id = '0'");
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

    private function findProductByExternalId($profile_id, $external_id) {
        $query = $this->db->query("SELECT product_id
            FROM `" . DB_PREFIX . "dockercart_import_export_excel_row_map`
            WHERE profile_id = '" . (int)$profile_id . "'
              AND external_row_id = '" . $this->db->escape($external_id) . "'
            LIMIT 1");

        return $query->num_rows ? (int)$query->row['product_id'] : 0;
    }

    private function upsertExternalMap($profile_id, $external_id, $product_id) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_import_export_excel_row_map`
            SET profile_id = '" . (int)$profile_id . "',
                external_row_id = '" . $this->db->escape($external_id) . "',
                product_id = '" . (int)$product_id . "',
                date_modified = NOW()
            ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), date_modified = NOW()");
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

    private function addProduct($data) {
        $stock_status_id = (int)$this->config->get('config_stock_status_id');
        if ($stock_status_id <= 0) {
            $stock_status_id = 5;
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "product`
            SET model = '" . $this->db->escape($data['model']) . "',
                sku = '" . $this->db->escape($data['sku']) . "',
                upc = '', ean = '', jan = '', isbn = '', mpn = '',
                location = '',
                quantity = '" . (int)$data['quantity'] . "',
                stock_status_id = '" . $stock_status_id . "',
                image = '" . $this->db->escape((string)$data['image']) . "',
                manufacturer_id = '" . (int)$data['manufacturer_id'] . "',
                shipping = '1',
                price = '" . (float)$data['price'] . "',
                points = '0', tax_class_id = '0',
                date_available = NOW(),
                weight = '0', weight_class_id = '1',
                length = '0', width = '0', height = '0', length_class_id = '1',
                subtract = '1', minimum = '1', sort_order = '0', status = '1', viewed = '0',
                date_added = NOW(), date_modified = NOW()"
        );

        $product_id = (int)$this->db->getLastId();

        $this->upsertProductDescription($product_id, (int)$data['language_id'], $data['name'], $data['description']);
        $this->upsertProductStore($product_id, (int)$data['store_id']);
        $this->upsertProductCategory($product_id, (int)$data['category_id']);
        $this->upsertProductImages($product_id, isset($data['images']) ? $data['images'] : array());
        $this->upsertProductAttributes($product_id, (int)$data['language_id'], isset($data['attributes']) ? $data['attributes'] : array());
        $this->upsertProductOptions($product_id, isset($data['options']) ? $data['options'] : array());

        return $product_id;
    }

    private function updateProduct($product_id, $data) {
        $set = array();
        $set[] = "model = '" . $this->db->escape($data['model']) . "'";
        $set[] = "sku = '" . $this->db->escape($data['sku']) . "'";
        $set[] = "quantity = '" . (int)$data['quantity'] . "'";
        $set[] = "manufacturer_id = '" . (int)$data['manufacturer_id'] . "'";
        $set[] = "price = '" . (float)$data['price'] . "'";
        $set[] = "status = '1'";
        if (!empty($data['image'])) {
            $set[] = "image = '" . $this->db->escape((string)$data['image']) . "'";
        }
        $set[] = "date_modified = NOW()";

        $this->db->query("UPDATE `" . DB_PREFIX . "product`
            SET " . implode(', ', $set) . "
            WHERE product_id = '" . (int)$product_id . "'");

        $this->upsertProductDescription($product_id, (int)$data['language_id'], $data['name'], $data['description']);
        $this->upsertProductStore($product_id, (int)$data['store_id']);
        $this->upsertProductCategory($product_id, (int)$data['category_id']);
        $this->upsertProductImages($product_id, isset($data['images']) ? $data['images'] : array());
        $this->upsertProductAttributes($product_id, (int)$data['language_id'], isset($data['attributes']) ? $data['attributes'] : array());
        $this->upsertProductOptions($product_id, isset($data['options']) ? $data['options'] : array());
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
                WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_description`
                SET product_id = '" . (int)$product_id . "',
                    language_id = '" . (int)$language_id . "',
                    name = '" . $this->db->escape($name) . "',
                    description = '" . $this->db->escape($description) . "',
                    tag = '',
                    meta_title = '" . $this->db->escape($name) . "',
                    meta_description = '',
                    meta_keyword = ''"
            );
        }
    }

    private function upsertProductStore($product_id, $store_id) {
        $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = '" . (int)$product_id . "' AND store_id = '" . (int)$store_id . "' LIMIT 1");
        if (!$query->num_rows) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
        }

        if ((int)$store_id !== 0) {
            $query_default = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_to_store` WHERE product_id = '" . (int)$product_id . "' AND store_id = '0' LIMIT 1");
            if (!$query_default->num_rows) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET product_id = '" . (int)$product_id . "', store_id = '0'");
            }
        }
    }

    private function upsertProductCategory($product_id, $category_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE product_id = '" . (int)$product_id . "'");

        if ((int)$category_id <= 0) {
            return;
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
    }

    private function upsertProductImages($product_id, $images) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . (int)$product_id . "'");
        if (!$images || !is_array($images)) {
            return;
        }

        $sort = 0;
        foreach ($images as $image) {
            $image = trim((string)$image);
            if ($image === '') {
                continue;
            }

            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_image`
                SET product_id = '" . (int)$product_id . "',
                    image = '" . $this->db->escape($image) . "',
                    sort_order = '" . (int)$sort . "'");
            $sort++;
        }
    }

    private function upsertProductAttributes($product_id, $language_id, $attributes) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_attribute` WHERE product_id = '" . (int)$product_id . "'");

        if (!$attributes || !is_array($attributes)) {
            return;
        }

        foreach ($attributes as $attribute) {
            $attribute_id = isset($attribute['attribute_id']) ? (int)$attribute['attribute_id'] : 0;
            $text = isset($attribute['text']) ? trim((string)$attribute['text']) : '';
            if ($attribute_id <= 0 || $text === '') {
                continue;
            }

            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_attribute`
                SET product_id = '" . (int)$product_id . "',
                    attribute_id = '" . (int)$attribute_id . "',
                    language_id = '" . (int)$language_id . "',
                    text = '" . $this->db->escape($text) . "'");
        }
    }

    private function upsertProductOptions($product_id, $options) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_option_value` WHERE product_id = '" . (int)$product_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product_option` WHERE product_id = '" . (int)$product_id . "'");

        if (!$options || !is_array($options)) {
            return;
        }

        foreach ($options as $option) {
            $option_id = isset($option['option_id']) ? (int)$option['option_id'] : 0;
            $value = isset($option['value']) ? trim((string)$option['value']) : '';
            if ($option_id <= 0 || $value === '') {
                continue;
            }

            $type = $this->getOptionType($option_id);
            if (!in_array($type, array('text', 'textarea', 'date', 'datetime', 'time'))) {
                continue;
            }

            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_option`
                SET product_id = '" . (int)$product_id . "',
                    option_id = '" . (int)$option_id . "',
                    value = '" . $this->db->escape($value) . "',
                    required = '0'");
        }
    }

    private function getOptionType($option_id) {
        $option_id = (int)$option_id;
        if (isset($this->option_type_cache[$option_id])) {
            return $this->option_type_cache[$option_id];
        }

        $query = $this->db->query("SELECT type FROM `" . DB_PREFIX . "option` WHERE option_id = '" . $option_id . "' LIMIT 1");
        $type = $query->num_rows ? (string)$query->row['type'] : '';
        $this->option_type_cache[$option_id] = $type;

        return $type;
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

    private function getRowsForExport($profile) {
        $language_id = (int)$profile['language_id'];
        $store_id = (int)$profile['store_id'];

        $query = $this->db->query("SELECT DISTINCT
                p.product_id,
                p.model,
                p.sku,
                p.price,
                p.quantity,
                p.image,
                p.status,
                pd.name,
                pd.description,
                m.name AS manufacturer,
                cd.name AS category_name
            FROM `" . DB_PREFIX . "product` p
            LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $language_id . "')
            LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (m.manufacturer_id = p.manufacturer_id)
            LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = p.product_id)
            LEFT JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = p.product_id)
            LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cd.category_id = p2c.category_id AND cd.language_id = '" . $language_id . "')
            WHERE p2s.store_id = '" . $store_id . "'
            ORDER BY p.product_id ASC");

        $rows = array();
        $rows[] = array('product_id', 'sku', 'model', 'name', 'description', 'price', 'quantity', 'manufacturer', 'category', 'image', 'status');

        foreach ($query->rows as $row) {
            $rows[] = array(
                (string)$row['product_id'],
                (string)$row['sku'],
                (string)$row['model'],
                (string)$row['name'],
                strip_tags(html_entity_decode((string)$row['description'], ENT_QUOTES, 'UTF-8')),
                (string)$row['price'],
                (string)$row['quantity'],
                (string)$row['manufacturer'],
                (string)$row['category_name'],
                (string)$row['image'],
                (string)$row['status']
            );
        }

        return $rows;
    }

    private function writeCsvFile($filepath, $rows) {
        $fp = fopen($filepath, 'wb');
        if (!$fp) {
            throw new Exception('Cannot write CSV file');
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row, ';');
        }

        fclose($fp);
    }

    private function writeXlsxFile($filepath, $rows) {
        if (!class_exists('ZipArchive')) {
            $csv_fallback = preg_replace('/\.xlsx$/i', '.csv', $filepath);
            $this->writeCsvFile($csv_fallback, $rows);
            throw new Exception('ZipArchive extension is missing. CSV fallback created: ' . basename($csv_fallback));
        }

        $tmp_dir = sys_get_temp_dir() . '/dockercart_xlsx_' . uniqid();
        mkdir($tmp_dir, 0775, true);
        mkdir($tmp_dir . '/_rels', 0775, true);
        mkdir($tmp_dir . '/xl', 0775, true);
        mkdir($tmp_dir . '/xl/_rels', 0775, true);
        mkdir($tmp_dir . '/xl/worksheets', 0775, true);

        file_put_contents($tmp_dir . '/[Content_Types].xml', $this->xlsxContentTypes());
        file_put_contents($tmp_dir . '/_rels/.rels', $this->xlsxRootRels());
        file_put_contents($tmp_dir . '/xl/workbook.xml', $this->xlsxWorkbook());
        file_put_contents($tmp_dir . '/xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRels());
        file_put_contents($tmp_dir . '/xl/styles.xml', $this->xlsxStyles());
        file_put_contents($tmp_dir . '/xl/worksheets/sheet1.xml', $this->xlsxSheet($rows));

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->deleteDirRecursive($tmp_dir);
            throw new Exception('Cannot create XLSX file');
        }

        $this->zipDir($zip, $tmp_dir, '');
        $zip->close();

        $this->deleteDirRecursive($tmp_dir);
    }

    private function xlsxSheet($rows) {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        $r = 1;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $r . '">';

            for ($c = 0; $c < count($row); $c++) {
                $cell_ref = $this->xlsxColumnName($c + 1) . $r;
                $value = isset($row[$c]) ? (string)$row[$c] : '';

                if (is_numeric($value) && !preg_match('/^0\d+$/', $value)) {
                    $xml .= '<c r="' . $cell_ref . '"><v>' . $this->xmlEscape($value) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $this->xmlEscape($value) . '</t></is></c>';
                }
            }

            $xml .= '</row>';
            $r++;
        }

        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        return $xml;
    }

    private function xlsxColumnName($index) {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = (int)($index / 26);
        }

        return $name;
    }

    private function xlsxContentTypes() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private function xlsxRootRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function xlsxWorkbook() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Export" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }

    private function xlsxWorkbookRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    private function xlsxStyles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf/></cellStyleXfs>
  <cellXfs count="1"><xf/></cellXfs>
</styleSheet>';
    }

    private function zipDir($zip, $dir, $base) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $full = $dir . '/' . $file;
            $local = ltrim($base . '/' . $file, '/');

            if (is_dir($full)) {
                $this->zipDir($zip, $full, $local);
            } else {
                $zip->addFile($full, $local);
            }
        }
    }

    private function parseCsvRows($content, $delimiter = '') {
        $content = (string)$content;
        if ($content === '') {
            return array();
        }

        if ($delimiter === '') {
            $delimiter = $this->detectCsvDelimiter($content);
        }

        $rows = array();
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $content);
        rewind($fp);

        while (($data = fgetcsv($fp, 0, $delimiter)) !== false) {
            $rows[] = $data;
        }

        fclose($fp);

        return $rows;
    }

    private function detectCsvDelimiter($content) {
        $line = strtok($content, "\n");
        $candidates = array(';', ',', "\t", '|');
        $best = ';';
        $best_count = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count((string)$line, $candidate);
            if ($count > $best_count) {
                $best_count = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function parseXlsxRows($filepath, $sheet_index = 0) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive extension is required for XLSX parsing');
        }

        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new Exception('Unable to open XLSX file');
        }

        $sheet_name = 'xl/worksheets/sheet' . ((int)$sheet_index + 1) . '.xml';
        $sheet_xml = $zip->getFromName($sheet_name);
        if ($sheet_xml === false) {
            $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        }

        if ($sheet_xml === false) {
            $zip->close();
            throw new Exception('Worksheet XML not found in XLSX');
        }

        $shared_strings = array();
        $shared_xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared_xml !== false) {
            $sx = simplexml_load_string($shared_xml);
            if ($sx && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $shared_strings[] = (string)$si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string)$r->t;
                        }
                        $shared_strings[] = $text;
                    } else {
                        $shared_strings[] = '';
                    }
                }
            }
        }

        $zip->close();

        $xml = simplexml_load_string($sheet_xml);
        if (!$xml) {
            throw new Exception('Invalid worksheet XML');
        }

        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = array();
        $row_nodes = $xml->xpath('//main:sheetData/main:row');

        if (!is_array($row_nodes)) {
            return $rows;
        }

        foreach ($row_nodes as $row_node) {
            $row_values = array();

            if (is_object($row_node) && method_exists($row_node, 'registerXPathNamespace')) {
                $row_node->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            }

            $cells = $row_node->xpath('main:c');

            if (!is_array($cells)) {
                $rows[] = $row_values;
                continue;
            }

            foreach ($cells as $cell) {
                $type = (string)$cell['t'];
                $v = '';

                if ($type === 's') {
                    $idx = isset($cell->v) ? (int)$cell->v : -1;
                    $v = ($idx >= 0 && isset($shared_strings[$idx])) ? $shared_strings[$idx] : '';
                } elseif ($type === 'inlineStr') {
                    if (is_object($cell) && method_exists($cell, 'registerXPathNamespace')) {
                        $cell->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    }

                    $is_nodes = $cell->xpath('main:is/main:t');
                    if (is_array($is_nodes) && isset($is_nodes[0])) {
                        $v = (string)$is_nodes[0];
                    }
                } else {
                    $v = isset($cell->v) ? (string)$cell->v : '';
                }

                $row_values[] = $v;
            }

            $rows[] = $row_values;
        }

        return $rows;
    }

    private function fetchRemoteContent($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DockerCart-ImportExportExcel/1.0');
        $content = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($code >= 400 || $content === false || $content === '') {
            throw new Exception('Failed to fetch source. HTTP code: ' . $code);
        }

        return $content;
    }

    private function resolveSourceFormat($profile) {
        $format = (string)$profile['source_format'];
        if ($format !== 'auto') {
            return $format;
        }

        $source = (string)$profile['source_url'];
        if ((string)$profile['source_type'] === 'file') {
            $source = (string)$profile['source_file'];
        }

        $source = strtolower($source);
        if (substr($source, -5) === '.xlsx') {
            return 'xlsx';
        }

        return 'csv';
    }

    private function decodeJson($json) {
        if ($json === '') {
            return array();
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function xmlEscape($value) {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function deleteDirRecursive($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirRecursive($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
