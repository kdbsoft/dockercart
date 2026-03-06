<?php
class ModelExtensionModuleDockercartImportExportExcel extends Model {

    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_import_export_excel_profile` (
            `profile_id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `supplier_code` varchar(64) NOT NULL DEFAULT '',
            `source_type` enum('url','file') NOT NULL DEFAULT 'url',
            `source_url` text,
            `source_file` text,
            `source_format` enum('auto','csv','xlsx') NOT NULL DEFAULT 'auto',
            `sheet_index` int(11) NOT NULL DEFAULT '0',
            `delimiter` varchar(8) NOT NULL DEFAULT '',
            `has_header` tinyint(1) NOT NULL DEFAULT '1',
            `start_row` int(11) NOT NULL DEFAULT '2',
            `import_mode` enum('add','update','update_only','update_price_qty_only','replace') NOT NULL DEFAULT 'update',
            `store_id` int(11) NOT NULL DEFAULT '0',
            `language_id` int(11) NOT NULL DEFAULT '1',
            `currency_code` varchar(3) NOT NULL DEFAULT 'USD',
            `default_category_id` int(11) NOT NULL DEFAULT '0',
            `field_map_json` longtext,
            `extra_settings_json` longtext,
            `status` tinyint(1) NOT NULL DEFAULT '1',
            `cron_key` varchar(64) NOT NULL,
            `last_run` datetime DEFAULT NULL,
            `last_result` longtext,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`profile_id`),
            KEY `status` (`status`),
            KEY `supplier_code` (`supplier_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_import_export_excel_row_map` (
            `map_id` int(11) NOT NULL AUTO_INCREMENT,
            `profile_id` int(11) NOT NULL,
            `external_row_id` varchar(255) NOT NULL,
            `product_id` int(11) NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`map_id`),
            UNIQUE KEY `profile_row` (`profile_id`,`external_row_id`),
            KEY `product_id` (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_import_export_excel_row_map`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_import_export_excel_profile`");
    }

    public function getProfiles() {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_import_export_excel_profile` ORDER BY `name` ASC");
        $rows = $query->rows;

        foreach ($rows as &$row) {
            $row['field_map'] = $this->decodeJson((string)$row['field_map_json']);
            $row['extra_settings'] = $this->decodeJson((string)$row['extra_settings_json']);
            $row['last_result'] = $this->decodeJson((string)$row['last_result']);
        }

        return $rows;
    }

    public function getProfile($profile_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_import_export_excel_profile` WHERE `profile_id` = '" . (int)$profile_id . "'");

        if (!$query->num_rows) {
            return null;
        }

        $row = $query->row;
        $row['field_map'] = $this->decodeJson((string)$row['field_map_json']);
        $row['extra_settings'] = $this->decodeJson((string)$row['extra_settings_json']);
        $row['last_result'] = $this->decodeJson((string)$row['last_result']);

        return $row;
    }

    public function addProfile($data) {
        $cron_key = $this->generateCronKey();

        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_import_export_excel_profile`
            SET `name` = '" . $this->db->escape((string)$data['name']) . "',
                `supplier_code` = '" . $this->db->escape((string)$this->getData($data, 'supplier_code', '')) . "',
                `source_type` = '" . $this->db->escape($this->normalizeSourceType($this->getData($data, 'source_type', 'url'))) . "',
                `source_url` = '" . $this->db->escape((string)$this->getData($data, 'source_url', '')) . "',
                `source_file` = '" . $this->db->escape((string)$this->getData($data, 'source_file', '')) . "',
                `source_format` = '" . $this->db->escape($this->normalizeSourceFormat($this->getData($data, 'source_format', 'auto'))) . "',
                `sheet_index` = '" . (int)$this->getData($data, 'sheet_index', 0) . "',
                `delimiter` = '" . $this->db->escape((string)$this->getData($data, 'delimiter', '')) . "',
                `has_header` = '" . ((int)$this->getData($data, 'has_header', 1) ? 1 : 0) . "',
                `start_row` = '" . max(1, (int)$this->getData($data, 'start_row', 2)) . "',
                `import_mode` = '" . $this->db->escape($this->normalizeImportMode($this->getData($data, 'import_mode', 'update'))) . "',
                `store_id` = '" . (int)$this->getData($data, 'store_id', 0) . "',
                `language_id` = '" . (int)$this->getData($data, 'language_id', 1) . "',
                `currency_code` = '" . $this->db->escape((string)$this->getData($data, 'currency_code', 'USD')) . "',
                `default_category_id` = '" . (int)$this->getData($data, 'default_category_id', 0) . "',
                `field_map_json` = '" . $this->db->escape(json_encode($this->normalizeFieldMap($this->getData($data, 'field_map', array())), JSON_UNESCAPED_UNICODE)) . "',
                `extra_settings_json` = '" . $this->db->escape(json_encode($this->getData($data, 'extra_settings', array()), JSON_UNESCAPED_UNICODE)) . "',
                `status` = '" . ((int)$this->getData($data, 'status', 1) ? 1 : 0) . "',
                `cron_key` = '" . $this->db->escape($cron_key) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    public function updateProfile($profile_id, $data) {
        $existing = $this->getProfile($profile_id);
        if (!$existing) {
            return;
        }

        $cron_key = !empty($existing['cron_key']) ? $existing['cron_key'] : $this->generateCronKey();
        if (!empty($data['regenerate_cron_key'])) {
            $cron_key = $this->generateCronKey();
        }

        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_import_export_excel_profile`
            SET `name` = '" . $this->db->escape((string)$data['name']) . "',
                `supplier_code` = '" . $this->db->escape((string)$this->getData($data, 'supplier_code', '')) . "',
                `source_type` = '" . $this->db->escape($this->normalizeSourceType($this->getData($data, 'source_type', 'url'))) . "',
                `source_url` = '" . $this->db->escape((string)$this->getData($data, 'source_url', '')) . "',
                `source_file` = '" . $this->db->escape((string)$this->getData($data, 'source_file', '')) . "',
                `source_format` = '" . $this->db->escape($this->normalizeSourceFormat($this->getData($data, 'source_format', 'auto'))) . "',
                `sheet_index` = '" . (int)$this->getData($data, 'sheet_index', 0) . "',
                `delimiter` = '" . $this->db->escape((string)$this->getData($data, 'delimiter', '')) . "',
                `has_header` = '" . ((int)$this->getData($data, 'has_header', 1) ? 1 : 0) . "',
                `start_row` = '" . max(1, (int)$this->getData($data, 'start_row', 2)) . "',
                `import_mode` = '" . $this->db->escape($this->normalizeImportMode($this->getData($data, 'import_mode', 'update'))) . "',
                `store_id` = '" . (int)$this->getData($data, 'store_id', 0) . "',
                `language_id` = '" . (int)$this->getData($data, 'language_id', 1) . "',
                `currency_code` = '" . $this->db->escape((string)$this->getData($data, 'currency_code', 'USD')) . "',
                `default_category_id` = '" . (int)$this->getData($data, 'default_category_id', 0) . "',
                `field_map_json` = '" . $this->db->escape(json_encode($this->normalizeFieldMap($this->getData($data, 'field_map', array())), JSON_UNESCAPED_UNICODE)) . "',
                `extra_settings_json` = '" . $this->db->escape(json_encode($this->getData($data, 'extra_settings', array()), JSON_UNESCAPED_UNICODE)) . "',
                `status` = '" . ((int)$this->getData($data, 'status', 1) ? 1 : 0) . "',
                `cron_key` = '" . $this->db->escape($cron_key) . "',
                `date_modified` = NOW()
            WHERE `profile_id` = '" . (int)$profile_id . "'"
        );
    }

    public function deleteProfile($profile_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_import_export_excel_row_map` WHERE `profile_id` = '" . (int)$profile_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_import_export_excel_profile` WHERE `profile_id` = '" . (int)$profile_id . "'");
    }

    public function previewSourceRows($profile, $limit = 10) {
        if (!is_array($profile)) {
            throw new Exception('Invalid profile data for preview');
        }

        $limit = max(1, (int)$limit);
        return $this->loadSourceRowsForPreview($profile, $limit);
    }

    private function loadSourceRowsForPreview($profile, $limit = 10) {
        $source_type = isset($profile['source_type']) ? (string)$profile['source_type'] : 'url';
        $source_format = $this->resolveSourceFormat($profile);
        $limit = max(1, (int)$limit);

        $has_header = isset($profile['has_header']) && (int)$profile['has_header'] === 1;
        $start_row = max(1, isset($profile['start_row']) ? (int)$profile['start_row'] : 2);
        $raw_limit = max(50, $limit * 5 + $start_row + ($has_header ? 1 : 0));

        $filepath = '';

        if ($source_type === 'file') {
            $filepath = isset($profile['source_file']) ? (string)$profile['source_file'] : '';
            if ($filepath === '' || !is_file($filepath)) {
                throw new Exception('Source file not found');
            }
        } else {
            $url = trim(isset($profile['source_url']) ? (string)$profile['source_url'] : '');
            if ($url === '') {
                throw new Exception('Source URL is empty');
            }

            $temp_dir = rtrim(DIR_STORAGE, '/\\') . '/dockercart_import_export_excel/tmp';
            if (!is_dir($temp_dir)) {
                mkdir($temp_dir, 0775, true);
            }

            $ext = ($source_format === 'xlsx') ? 'xlsx' : 'csv';
            $filepath = $temp_dir . '/source_' . md5($url . microtime(true)) . '.' . $ext;
            $this->fetchRemoteToFile($url, $filepath);
        }

        if ($source_format === 'csv') {
            $delimiter = isset($profile['delimiter']) ? (string)$profile['delimiter'] : '';
            $rows = $this->parseCsvRowsFromFile($filepath, $delimiter, $raw_limit);
        } elseif ($source_format === 'xlsx') {
            $rows = $this->parseXlsxRows($filepath, isset($profile['sheet_index']) ? (int)$profile['sheet_index'] : 0, $raw_limit);
        } else {
            throw new Exception('Unsupported source format');
        }

        $rows = $this->normalizeRows($rows, $has_header, $start_row, $limit);

        if (!empty($filepath) && strpos($filepath, '/tmp/source_') !== false && is_file($filepath)) {
            @unlink($filepath);
        }

        return $rows;
    }

    private function resolveSourceFormat($profile) {
        $format = isset($profile['source_format']) ? (string)$profile['source_format'] : 'auto';
        if ($format !== 'auto') {
            return $format;
        }

        $source = isset($profile['source_url']) ? (string)$profile['source_url'] : '';
        if (isset($profile['source_type']) && (string)$profile['source_type'] === 'file') {
            $source = isset($profile['source_file']) ? (string)$profile['source_file'] : '';
        }

        $source = strtolower($source);
        if (substr($source, -5) === '.xlsx') {
            return 'xlsx';
        }

        return 'csv';
    }

    private function normalizeRows($rows, $has_header, $start_row, $limit = 0) {
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

                if ($limit > 0 && count($result) >= (int)$limit) {
                    break;
                }
            }
        }

        return $result;
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

    private function parseCsvRowsFromFile($filepath, $delimiter = '', $max_rows = 0) {
        if (!is_file($filepath)) {
            throw new Exception('CSV file not found');
        }

        $rows = array();
        $max_rows = max(0, (int)$max_rows);

        $fp = fopen($filepath, 'rb');
        if (!$fp) {
            throw new Exception('Unable to open CSV file');
        }

        if ($delimiter === '') {
            $first_line = fgets($fp);
            $delimiter = $this->detectCsvDelimiter((string)$first_line);
            rewind($fp);
        }

        while (($data = fgetcsv($fp, 0, $delimiter)) !== false) {
            $rows[] = $data;

            if ($max_rows > 0 && count($rows) >= $max_rows) {
                break;
            }
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

    private function parseXlsxRows($filepath, $sheet_index = 0, $max_rows = 0) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive extension is required for XLSX parsing');
        }

        if (!class_exists('XMLReader')) {
            throw new Exception('XMLReader extension is required for XLSX parsing');
        }

        $realpath = realpath($filepath);
        if ($realpath === false) {
            throw new Exception('XLSX file not found');
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

        $zip->close();

        $sheet_uri = 'zip://' . $realpath . '#' . $sheet_name;
        $reader = new XMLReader();
        if (!$reader->open($sheet_uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new Exception('Unable to read worksheet XML');
        }

        $rows = array();
        $shared_indexes = array();
        $max_rows = max(0, (int)$max_rows);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'row') {
                continue;
            }

            $row_values = array();
            $row_depth = $reader->depth;

            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row' && $reader->depth === $row_depth) {
                    break;
                }

                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'c') {
                    continue;
                }

                $cell_type = (string)$reader->getAttribute('t');
                $cell_depth = $reader->depth;
                $cell_value = '';

                while ($reader->read()) {
                    if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'c' && $reader->depth === $cell_depth) {
                        break;
                    }

                    if ($reader->nodeType !== XMLReader::ELEMENT) {
                        continue;
                    }

                    if ($reader->name === 'v') {
                        $cell_value = (string)$reader->readString();
                    } elseif ($reader->name === 't' && $cell_type === 'inlineStr') {
                        $cell_value = (string)$reader->readString();
                    }
                }

                if ($cell_type === 's') {
                    $shared_index = (int)$cell_value;
                    $row_values[] = array('__shared__' => $shared_index);
                    $shared_indexes[$shared_index] = true;
                } else {
                    $row_values[] = $cell_value;
                }
            }

            $rows[] = $row_values;

            if ($max_rows > 0 && count($rows) >= $max_rows) {
                break;
            }
        }

        $reader->close();

        if ($rows && $shared_indexes) {
            $shared_map = $this->readXlsxSharedStringsSubset($realpath, array_keys($shared_indexes));

            foreach ($rows as $r_idx => $row_values) {
                foreach ($row_values as $c_idx => $value) {
                    if (is_array($value) && isset($value['__shared__'])) {
                        $idx = (int)$value['__shared__'];
                        $rows[$r_idx][$c_idx] = isset($shared_map[$idx]) ? $shared_map[$idx] : '';
                    }
                }
            }
        }

        return $rows;
    }

    private function readXlsxSharedStringsSubset($realpath, $indexes) {
        $result = array();
        if (!$indexes) {
            return $result;
        }

        $need = array();
        foreach ($indexes as $index) {
            $index = (int)$index;
            if ($index >= 0) {
                $need[$index] = true;
            }
        }

        if (!$need) {
            return $result;
        }

        $shared_uri = 'zip://' . $realpath . '#xl/sharedStrings.xml';
        $reader = new XMLReader();
        if (!$reader->open($shared_uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            return $result;
        }

        $current_index = -1;
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'si') {
                continue;
            }

            $current_index++;
            if (isset($need[$current_index])) {
                $result[$current_index] = $this->readSharedStringItem($reader);
                unset($need[$current_index]);

                if (!$need) {
                    break;
                }
            }
        }

        $reader->close();

        return $result;
    }

    private function readSharedStringItem($reader) {
        $text = '';
        $depth = $reader->depth;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'si' && $reader->depth === $depth) {
                break;
            }

            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 't') {
                $text .= (string)$reader->readString();
            }
        }

        return $text;
    }

    private function fetchRemoteToFile($url, $filepath) {
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fp = fopen($filepath, 'wb');
        if (!$fp) {
            throw new Exception('Unable to create temporary source file');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DockerCart-ImportExportExcel/1.0');
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($errno) {
            @unlink($filepath);
            throw new Exception('cURL error: ' . $error);
        }

        if ($code >= 400 || !is_file($filepath) || filesize($filepath) === 0) {
            @unlink($filepath);
            throw new Exception('Failed to fetch source. HTTP code: ' . $code);
        }
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

    private function normalizeSourceType($source_type) {
        $source_type = (string)$source_type;
        return in_array($source_type, array('url', 'file')) ? $source_type : 'url';
    }

    private function normalizeSourceFormat($format) {
        $format = (string)$format;
        return in_array($format, array('auto', 'csv', 'xlsx')) ? $format : 'auto';
    }

    private function normalizeImportMode($mode) {
        $mode = (string)$mode;
        if (!in_array($mode, array('add', 'update', 'update_only', 'update_price_qty_only', 'replace'))) {
            $mode = 'update';
        }

        return $mode;
    }

    private function normalizeFieldMap($map) {
        if (!is_array($map)) {
            $map = array();
        }

        $allowed = array('external_id', 'sku', 'model', 'name', 'description', 'price', 'quantity', 'manufacturer', 'category', 'image', 'images');
        $result = array();

        foreach ($allowed as $field) {
            if (isset($map[$field])) {
                if ($field === 'images') {
                    $result[$field] = $this->normalizeColumnList((string)$map[$field]);
                } else {
                    $result[$field] = max(0, (int)$map[$field]);
                }
            }
        }

        return $result;
    }

    private function normalizeColumnList($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $parts = preg_split('/[^0-9]+/', $value);
        $indexes = array();

        foreach ($parts as $part) {
            $index = (int)$part;
            if ($index > 0) {
                $indexes[] = $index;
            }
        }

        $indexes = array_values(array_unique($indexes));
        sort($indexes);

        return $indexes ? implode(',', $indexes) : '';
    }

    public function runFilteredExport($filters, $file_format = 'xlsx') {
        $file_format = in_array((string)$file_format, array('xlsx', 'csv')) ? (string)$file_format : 'xlsx';
        $filters = is_array($filters) ? $filters : array();

        $rows = $this->getRowsForFilteredExport($filters);

        $dir = rtrim(DIR_STORAGE, '/\\') . '/dockercart_import_export_excel/exports';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'dockercart_export_filtered_' . date('Ymd_His') . '.' . $file_format;
        $filepath = $dir . '/' . $filename;

        if ($file_format === 'csv') {
            $this->writeCsvFile($filepath, $rows);
        } else {
            $this->writeXlsxFile($filepath, $rows);
        }

        return array(
            'file' => $filepath,
            'filename' => $filename,
            'format' => $file_format,
            'rows' => max(0, count($rows) - 1)
        );
    }

    public function getExportFileByName($filename) {
        $filename = basename((string)$filename);
        if ($filename === '') {
            return null;
        }

        $base = rtrim(DIR_STORAGE, '/\\') . '/dockercart_import_export_excel/exports';
        $path = $base . '/' . $filename;

        if (!is_file($path)) {
            return null;
        }

        return $path;
    }

    private function getRowsForFilteredExport($filters) {
        $language_id = isset($filters['language_id']) ? (int)$filters['language_id'] : (int)$this->config->get('config_language_id');
        if ($language_id <= 0) {
            $language_id = (int)$this->config->get('config_language_id');
        }

        $store_id = isset($filters['store_id']) ? (int)$filters['store_id'] : 0;
        $status = isset($filters['status']) ? (string)$filters['status'] : '';
        $manufacturer_id = isset($filters['manufacturer_id']) ? (int)$filters['manufacturer_id'] : 0;
        $category_id = isset($filters['category_id']) ? (int)$filters['category_id'] : 0;
        $keyword = isset($filters['keyword']) ? trim((string)$filters['keyword']) : '';

        $quantity_min = isset($filters['quantity_min']) && $filters['quantity_min'] !== '' ? (float)str_replace(',', '.', (string)$filters['quantity_min']) : null;
        $quantity_max = isset($filters['quantity_max']) && $filters['quantity_max'] !== '' ? (float)str_replace(',', '.', (string)$filters['quantity_max']) : null;
        $price_min = isset($filters['price_min']) && $filters['price_min'] !== '' ? (float)str_replace(',', '.', (string)$filters['price_min']) : null;
        $price_max = isset($filters['price_max']) && $filters['price_max'] !== '' ? (float)str_replace(',', '.', (string)$filters['price_max']) : null;

        $sql = "SELECT\n                p.product_id,\n                p.model,\n                p.sku,\n                p.price,\n                p.quantity,\n                p.image,\n                p.status,\n                pd.name,\n                pd.description,\n                m.name AS manufacturer,\n                GROUP_CONCAT(DISTINCT cd.name ORDER BY cd.name SEPARATOR ' | ') AS category_name\n            FROM `" . DB_PREFIX . "product` p\n            LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "')\n            LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (m.manufacturer_id = p.manufacturer_id)\n            LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p2s.product_id = p.product_id)\n            LEFT JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p2c.product_id = p.product_id)\n            LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cd.category_id = p2c.category_id AND cd.language_id = '" . (int)$language_id . "')\n            WHERE p2s.store_id = '" . (int)$store_id . "'";

        if ($status === '0' || $status === '1') {
            $sql .= " AND p.status = '" . (int)$status . "'";
        }

        if ($manufacturer_id > 0) {
            $sql .= " AND p.manufacturer_id = '" . (int)$manufacturer_id . "'";
        }

        if ($category_id > 0) {
            $sql .= " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "product_to_category` p2c2 WHERE p2c2.product_id = p.product_id AND p2c2.category_id = '" . (int)$category_id . "')";
        }

        if ($quantity_min !== null) {
            $sql .= " AND p.quantity >= '" . (float)$quantity_min . "'";
        }

        if ($quantity_max !== null) {
            $sql .= " AND p.quantity <= '" . (float)$quantity_max . "'";
        }

        if ($price_min !== null) {
            $sql .= " AND p.price >= '" . (float)$price_min . "'";
        }

        if ($price_max !== null) {
            $sql .= " AND p.price <= '" . (float)$price_max . "'";
        }

        if ($keyword !== '') {
            $keyword_escaped = $this->db->escape($keyword);
            $sql .= " AND (pd.name LIKE '%" . $keyword_escaped . "%' OR p.model LIKE '%" . $keyword_escaped . "%' OR p.sku LIKE '%" . $keyword_escaped . "%')";
        }

        $sql .= " GROUP BY p.product_id ORDER BY p.product_id ASC";

        $query = $this->db->query($sql);

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
                    $xml .= '<c r="' . $cell_ref . '"><v>' . $this->exportXmlEscape($value) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $this->exportXmlEscape($value) . '</t></is></c>';
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

    private function exportXmlEscape($value) {
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

    private function decodeJson($json) {
        if ($json === '') {
            return array();
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function getData($data, $key, $default = null) {
        return isset($data[$key]) ? $data[$key] : $default;
    }

    private function generateCronKey() {
        try {
            return bin2hex(random_bytes(20));
        } catch (Exception $e) {
            return sha1(uniqid('dockercart_import_export_excel_', true));
        }
    }
}
