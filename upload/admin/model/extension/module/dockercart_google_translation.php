<?php

class ModelExtensionModuleDockercartGoogleTranslation extends Model {
    private $logger;

    public function __construct($registry) {
        parent::__construct($registry);

        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        require_once DIR_SYSTEM . 'library/dockercart/google_cloud_translation.php';

        $this->logger = new DockercartLogger($this->registry, 'google_translation');
    }

    public function install() {
        $this->load->model('setting/setting');

        $defaults = array(
            'module_dockercart_google_translation_status' => 0,
            'module_dockercart_google_translation_api_key' => getenv('GOOGLE_TRANSLATE_API_KEY') ?: '',
            'module_dockercart_google_translation_match_threshold' => 90,
            'module_dockercart_google_translation_force_overwrite' => 0,
            'module_dockercart_google_translation_price_per_million' => 20,
            'module_dockercart_google_translation_license_key' => '',
            'module_dockercart_google_translation_public_key' => ''
        );

        $this->model_setting_setting->editSetting('module_dockercart_google_translation', $defaults);
    }

    public function getDefaultLanguageId() {
        $default_code = (string)$this->config->get('config_language');

        $query = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code = '" . $this->db->escape($default_code) . "' LIMIT 1");

        if ($query->num_rows) {
            return (int)$query->row['language_id'];
        }

        return (int)$this->config->get('config_language_id');
    }

    public function buildScanReport($source_language_id, $target_language_id, $match_threshold, $include_db = true, $include_files = true) {
        $report = array(
            'summary' => array(
                'items' => 0,
                'characters' => 0,
                'estimated_cost' => 0.0,
                'currency' => 'USD'
            ),
            'db' => array(),
            'files' => array(
                'total_files' => 0,
                'untranslated_entries' => 0,
                'characters' => 0,
                'preview' => array()
            )
        );

        if ($include_db) {
            // Scan should always reflect untranslated/near-source content by threshold,
            // independent from force-overwrite mode used for translation execution.
            $db_report = $this->scanDbEntities($source_language_id, $target_language_id, $match_threshold, false);
            $report['db'] = $db_report;

            foreach ($db_report as $entity_code => $row) {
                $report['summary']['items'] += (int)$row['items'];
                $report['summary']['characters'] += (int)$row['characters'];
            }
        }

        if ($include_files) {
            $source_code = $this->getLanguageCodeById($source_language_id);
            $target_code = $this->getLanguageCodeById($target_language_id);

            $files_report = $this->scanLanguageFiles($source_code, $target_code, $match_threshold, false);
            $report['files'] = $files_report;

            $report['summary']['items'] += (int)$files_report['untranslated_entries'];
            $report['summary']['characters'] += (int)$files_report['characters'];
        }

        $price_per_million = (float)$this->config->get('module_dockercart_google_translation_price_per_million');
        if ($price_per_million <= 0) {
            $price_per_million = 20.0;
        }

        $report['summary']['estimated_cost'] = round(((float)$report['summary']['characters'] / 1000000) * $price_per_million, 4);

        return $report;
    }

    public function executeTranslation($source_language_id, $target_language_id, $match_threshold, $force_overwrite = false, $translate_db = true, $translate_files = true, array $selected_tables = array()) {
        $api_key = (string)$this->config->get('module_dockercart_google_translation_api_key');

        if (!$api_key) {
            throw new Exception('Google API key is empty.');
        }

        $source_code = $this->getLanguageCodeById($source_language_id);
        $target_code = $this->getLanguageCodeById($target_language_id);

        $source_google = $this->mapLanguageCodeToGoogle($source_code);
        $target_google = $this->mapLanguageCodeToGoogle($target_code);

        if (!$source_google || !$target_google) {
            throw new Exception('Could not map selected language code to Google language code.');
        }

        $translator = new DockercartGoogleCloudTranslation($api_key);

        $result = array(
            'translated_items' => 0,
            'translated_characters' => 0,
            'db' => array(),
            'files' => array(
                'updated_files' => 0,
                'created_files' => 0,
                'translated_entries' => 0,
                'translated_characters' => 0
            )
        );

        if ($translate_db) {
            $db_candidates = $this->collectDbTranslationCandidates($source_language_id, $target_language_id, $match_threshold, $force_overwrite, $selected_tables);

            foreach ($db_candidates as $entity_type => $entity_candidates) {
                $entity_translated = 0;
                $entity_chars = 0;

                foreach ($entity_candidates as $candidate) {
                    $source_text = $candidate['source_text'];

                    if ($source_text === '') {
                        continue;
                    }

                    $translated = $translator->translateBatch(array($source_text), $source_google, $target_google);
                    $translated_value = isset($translated[0]) ? $translated[0] : '';

                    if ($translated_value === '') {
                        continue;
                    }

                    $this->upsertDbField($candidate, $source_language_id, $target_language_id, $translated_value);

                    $entity_translated++;
                    $entity_chars += mb_strlen($source_text);
                }

                $result['db'][$entity_type] = array(
                    'translated_items' => $entity_translated,
                    'translated_characters' => $entity_chars
                );

                $result['translated_items'] += $entity_translated;
                $result['translated_characters'] += $entity_chars;
            }
        }

        if ($translate_files) {
            $files_result = $this->translateLanguageFiles($source_code, $target_code, $match_threshold, $force_overwrite, $translator, $source_google, $target_google);
            $result['files'] = $files_result;
            $result['translated_items'] += (int)$files_result['translated_entries'];
            $result['translated_characters'] += (int)$files_result['translated_characters'];
        }

            if ((int)$result['translated_items'] > 0) {
                try {
                    $this->cache->flush();
                } catch (Throwable $e) {
                    $this->logger->info('Cache flush after translation failed: ' . $e->getMessage());
                }
            }

        return $result;
    }

    public function translateSingleText($text, $source_language_id, $target_language_id) {
        $source_text = trim((string)$text);

        if ($source_text === '') {
            return '';
        }

        // Never translate URL/route/path-like strings, even for manual inline translation.
        if ($this->isLinkLikeText($source_text)) {
            return $source_text;
        }

        $api_key = (string)$this->config->get('module_dockercart_google_translation_api_key');

        if (!$api_key) {
            throw new Exception('Google API key is empty.');
        }

        $source_code = $this->getLanguageCodeById($source_language_id);
        $target_code = $this->getLanguageCodeById($target_language_id);

        $source_google = $this->mapLanguageCodeToGoogle($source_code);
        $target_google = $this->mapLanguageCodeToGoogle($target_code);

        if (!$source_google || !$target_google) {
            throw new Exception('Could not map selected language code to Google language code.');
        }

        $translator = new DockercartGoogleCloudTranslation($api_key);
        $translated = $translator->translateBatch(array($source_text), $source_google, $target_google);

        return isset($translated[0]) ? (string)$translated[0] : '';
    }

    private function scanDbEntities($source_language_id, $target_language_id, $match_threshold, $force_overwrite = false) {
        $scan = array();
        $tables = $this->getLanguageTablesWithTextColumns();

        foreach ($tables as $table_meta) {
            $table = $table_meta['table'];

            $scan[$table] = $this->scanTable(
                $table_meta,
                $source_language_id,
                $target_language_id,
                $match_threshold,
                $force_overwrite
            );
        }

        return $scan;
    }

    private function collectDbTranslationCandidates($source_language_id, $target_language_id, $match_threshold, $force_overwrite = false, array $selected_tables = array()) {
        $candidates = array();
        $tables = $this->getLanguageTablesWithTextColumns();
        $selected_lookup = $this->normalizeSelectedTables($selected_tables);

        foreach ($tables as $table_meta) {
            $table = $table_meta['table'];

            if ($selected_lookup && !isset($selected_lookup[$table])) {
                continue;
            }

            $candidates[$table] = $this->collectTableCandidates(
                $table_meta,
                $source_language_id,
                $target_language_id,
                $match_threshold,
                $force_overwrite
            );
        }

        return $candidates;
    }

    private function scanTable($table_meta, $source_language_id, $target_language_id, $match_threshold, $force_overwrite = false) {
        $table = $table_meta['table'];
        $text_columns = $table_meta['text_columns'];
        $identity_columns = $table_meta['identity_columns'];

        $items = 0;
        $characters = 0;
        $preview = array();

        $sql = "SELECT s.*
            FROM `" . $this->db->escape($table) . "` s
            WHERE s.language_id = '" . (int)$source_language_id . "'";

        $query = $this->db->query($sql);

        foreach ($query->rows as $row) {
            $target_row = $this->getTargetRowByIdentity($table, $identity_columns, $row, $target_language_id);
            $identity_label = $this->buildIdentityLabel($identity_columns, $row);

            foreach ($text_columns as $field) {
                $source_field = isset($row[$field]) ? (string)$row[$field] : '';

                $target_field = '';
                if (isset($target_row[$field])) {
                    $target_field = (string)$target_row[$field];
                }

                if (!$this->shouldTranslateField($source_field, $target_field, $match_threshold, $force_overwrite)) {
                    continue;
                }

                $items++;
                $characters += mb_strlen($source_field);

                if (count($preview) < 15) {
                    $preview[] = array(
                        'row_key' => $identity_label,
                        'field' => $field,
                        'source' => $this->truncate($this->normalizeForDisplay($source_field), 120),
                        'target' => $this->truncate($this->normalizeForDisplay($target_field), 120),
                        'similarity' => $this->calculateSimilarity($source_field, $target_field)
                    );
                }
            }
        }

        return array(
            'table' => $table,
            'items' => $items,
            'characters' => $characters,
            'preview' => $preview
        );
    }

    private function collectTableCandidates($table_meta, $source_language_id, $target_language_id, $match_threshold, $force_overwrite = false) {
        $table = $table_meta['table'];
        $text_columns = $table_meta['text_columns'];
        $identity_columns = $table_meta['identity_columns'];

        $candidates = array();

        $query = $this->db->query("SELECT s.*
            FROM `" . $this->db->escape($table) . "` s
            WHERE s.language_id = '" . (int)$source_language_id . "'");

        foreach ($query->rows as $row) {
            $target_row = $this->getTargetRowByIdentity($table, $identity_columns, $row, $target_language_id);

            foreach ($text_columns as $field) {
                $source_field = isset($row[$field]) ? (string)$row[$field] : '';
                $target_field = '';

                if (isset($target_row[$field])) {
                    $target_field = (string)$target_row[$field];
                }

                if (!$this->shouldTranslateField($source_field, $target_field, $match_threshold, $force_overwrite)) {
                    continue;
                }

                $candidates[] = array(
                    'table' => $table,
                    'identity_columns' => $identity_columns,
                    'identity_values' => $this->extractIdentityValues($identity_columns, $row),
                    'source_row' => $row,
                    'field' => $field,
                    'source_text' => $source_field
                );
            }
        }

        return $candidates;
    }

    private function upsertDbField($candidate, $source_language_id, $target_language_id, $translated_value) {
        $table = $candidate['table'];
        $identity_columns = isset($candidate['identity_columns']) ? $candidate['identity_columns'] : array();
        $identity_values = isset($candidate['identity_values']) ? $candidate['identity_values'] : array();
        $source_row = isset($candidate['source_row']) ? $candidate['source_row'] : array();
        $field = $candidate['field'];

        $where_parts = array();
        foreach ($identity_columns as $column) {
            if (!array_key_exists($column, $identity_values)) {
                continue;
            }

            $where_parts[] = "`" . $this->db->escape($column) . "` = '" . $this->db->escape((string)$identity_values[$column]) . "'";
        }

        if (!$where_parts) {
            return;
        }

        $where_sql = implode(' AND ', $where_parts);

        $check = $this->db->query("SELECT * FROM `" . $this->db->escape($table) . "`
            WHERE " . $where_sql . "
            AND `language_id` = '" . (int)$target_language_id . "'
            LIMIT 1");

        if (!$check->num_rows) {
            if ($source_row) {
                $clone = $source_row;
                $clone['language_id'] = (int)$target_language_id;
                $clone[$field] = (string)$translated_value;

                $schema = $this->getDbSchemaName();
                $auto_increment_columns = $this->getAutoIncrementColumns($schema, $table);
                $removed_identity_auto_increment = false;

                foreach ($auto_increment_columns as $auto_column) {
                    if (array_key_exists($auto_column, $clone)) {
                        unset($clone[$auto_column]);

                        if (in_array($auto_column, $identity_columns, true)) {
                            $removed_identity_auto_increment = true;
                        }
                    }
                }

                $columns = array();
                foreach ($clone as $column => $value) {
                    $columns[] = "`" . $this->db->escape($column) . "` = '" . $this->db->escape((string)$value) . "'";
                }

                if (!$columns) {
                    return;
                }

                $this->db->query("INSERT INTO `" . $this->db->escape($table) . "` SET " . implode(', ', $columns));

                // If auto-increment identity was removed, translated value is already set in inserted row.
                // Updating by old identity can target no rows, so we can stop here safely.
                if ($removed_identity_auto_increment) {
                    return;
                }
            }
        }

        $this->db->query("UPDATE `" . $this->db->escape($table) . "`
            SET `" . $field . "` = '" . $this->db->escape($translated_value) . "'
            WHERE " . $where_sql . "
            AND `language_id` = '" . (int)$target_language_id . "'");
    }

    private function scanLanguageFiles($source_code, $target_code, $match_threshold, $force_overwrite = false) {
        $result = array(
            'total_files' => 0,
            'untranslated_entries' => 0,
            'characters' => 0,
            'preview' => array()
        );

        $scopes = array(
            'admin' => DIR_APPLICATION . 'language/',
            'catalog' => DIR_APPLICATION . '../catalog/language/'
        );

        foreach ($scopes as $scope => $lang_base) {
            $source_dir = rtrim($lang_base, '/') . '/' . $source_code;
            $target_dir = rtrim($lang_base, '/') . '/' . $target_code;

            if (!is_dir($source_dir)) {
                continue;
            }

            $source_files = $this->collectLanguagePhpFiles($source_dir);

            foreach ($source_files as $source_path) {
                $relative = ltrim(str_replace($source_dir, '', $source_path), '/');
                $target_path = rtrim($target_dir, '/') . '/' . $relative;

                $source_entries = $this->parseLanguageFile($source_path);
                $target_entries = is_file($target_path) ? $this->parseLanguageFile($target_path) : array();

                if (!$source_entries) {
                    continue;
                }

                $result['total_files']++;

                foreach ($source_entries as $key => $source_value) {
                    $target_value = isset($target_entries[$key]) ? $target_entries[$key] : '';

                    if (!$this->shouldTranslateField($source_value, $target_value, $match_threshold, $force_overwrite)) {
                        continue;
                    }

                    $result['untranslated_entries']++;
                    $result['characters'] += mb_strlen($source_value);

                    if (count($result['preview']) < 20) {
                        $result['preview'][] = array(
                            'scope' => $scope,
                            'file' => $relative,
                            'key' => $key,
                            'source' => $this->truncate($this->normalizeForDisplay($source_value), 110),
                            'target' => $this->truncate($this->normalizeForDisplay($target_value), 110),
                            'similarity' => $this->calculateSimilarity($source_value, $target_value)
                        );
                    }
                }
            }
        }

        return $result;
    }

    private function translateLanguageFiles($source_code, $target_code, $match_threshold, $force_overwrite, $translator, $source_google, $target_google) {
        $result = array(
            'updated_files' => 0,
            'created_files' => 0,
            'translated_entries' => 0,
            'translated_characters' => 0
        );

        $scopes = array(
            DIR_APPLICATION . 'language/',
            DIR_APPLICATION . '../catalog/language/'
        );

        foreach ($scopes as $lang_base) {
            $source_dir = rtrim($lang_base, '/') . '/' . $source_code;
            $target_dir = rtrim($lang_base, '/') . '/' . $target_code;

            if (!is_dir($source_dir)) {
                continue;
            }

            $source_files = $this->collectLanguagePhpFiles($source_dir);

            foreach ($source_files as $source_path) {
                $relative = ltrim(str_replace($source_dir, '', $source_path), '/');
                $target_path = rtrim($target_dir, '/') . '/' . $relative;

                $source_entries = $this->parseLanguageFile($source_path);
                if (!$source_entries) {
                    continue;
                }

                $target_exists = is_file($target_path);
                $target_entries = $target_exists ? $this->parseLanguageFile($target_path) : array();
                $changed = false;

                // If the target file does not exist, create a base file first
                // so mode "Language files" always creates missing files.
                // Non-translatable values are preserved from source, while
                // translatable values are translated below.
                if (!$target_exists) {
                    $target_entries = $source_entries;
                    $changed = true;
                    $result['created_files']++;
                }

                foreach ($source_entries as $key => $source_value) {
                    $target_value = isset($target_entries[$key]) ? $target_entries[$key] : '';

                    if (!$this->shouldTranslateField($source_value, $target_value, $match_threshold, $force_overwrite)) {
                        continue;
                    }

                    $translated = $translator->translateBatch(array($source_value), $source_google, $target_google);
                    $translated_value = isset($translated[0]) ? $translated[0] : '';

                    if ($translated_value === '') {
                        continue;
                    }

                    $target_entries[$key] = $translated_value;
                    $changed = true;

                    $result['translated_entries']++;
                    $result['translated_characters'] += mb_strlen($source_value);
                }

                if ($changed) {
                    $this->writeLanguageFile($target_path, $target_entries);
                    $result['updated_files']++;
                }
            }
        }

        return $result;
    }

    private function parseLanguageFile($path) {
        $entries = array();

        if (!is_file($path)) {
            return $entries;
        }

        $_ = array();

        try {
            include $path;
        } catch (Throwable $e) {
            return $entries;
        }

        if (!is_array($_)) {
            return $entries;
        }

        foreach ($_ as $key => $value) {
            if (is_scalar($value)) {
                $entries[(string)$key] = (string)$value;
            }
        }

        return $entries;
    }

    private function writeLanguageFile($path, array $entries) {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        ksort($entries);

        $lines = array("<?php", "");

        foreach ($entries as $key => $value) {
            $escaped_value = str_replace(array('\\', "'"), array('\\\\', "\\'"), $value);
            $lines[] = "\$_['" . $key . "'] = '" . $escaped_value . "';";
        }

        $lines[] = "";

        file_put_contents($path, implode("\n", $lines));
    }

    private function collectLanguagePhpFiles($dir) {
        $files = array();

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function shouldTranslateField($source_value, $target_value, $match_threshold, $force_overwrite = false) {
        $source_clean = trim((string)$source_value);
        $target_clean = trim((string)$target_value);

        if ($source_clean === '') {
            return false;
        }

        if ($this->isNonTranslatableToken($source_clean)) {
            return false;
        }

        // Never translate links/paths/routes-like values.
        if ($this->isLinkLikeText($source_clean) || ($target_clean !== '' && $this->isLinkLikeText($target_clean))) {
            return false;
        }

        if ($force_overwrite) {
            return true;
        }

        if ($target_clean === '') {
            return true;
        }

        $similarity = $this->calculateSimilarity($source_clean, $target_clean);

        // High similarity means target still looks like source and likely not translated
        return $similarity >= (float)$match_threshold;
    }

    private function isNonTranslatableToken($value) {
        $text = trim((string)$value);

        if ($text === '') {
            return false;
        }

        if (preg_match('/\s/u', $text)) {
            return false;
        }

        if (preg_match('/^[0-9]+([\-\/.][0-9]+)*$/u', $text)) {
            return true;
        }

        if (mb_strlen($text, 'UTF-8') <= 8 && preg_match('/^[A-Z0-9][A-Z0-9\-\+\.\/]*$/u', $text)) {
            return true;
        }

        return false;
    }

    private function calculateSimilarity($source, $target) {
        $a = $this->normalizeForCompare($source);
        $b = $this->normalizeForCompare($target);

        if ($a === '' && $b === '') {
            return 100.0;
        }

        if ($a === '' || $b === '') {
            return 0.0;
        }

        $a = mb_substr($a, 0, 1200);
        $b = mb_substr($b, 0, 1200);

        similar_text($a, $b, $percent);

        return (float)$percent;
    }

    private function normalizeForCompare($value) {
        $text = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = mb_strtolower(trim($text), 'UTF-8');
        return $text;
    }

    private function normalizeForDisplay($value) {
        $text = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    private function truncate($value, $length) {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length) . '...';
    }

    private function getLanguageCodeById($language_id) {
        $query = $this->db->query("SELECT code FROM `" . DB_PREFIX . "language` WHERE language_id = '" . (int)$language_id . "' LIMIT 1");

        if ($query->num_rows) {
            return (string)$query->row['code'];
        }

        return '';
    }

    private function mapLanguageCodeToGoogle($opencart_code) {
        $code = strtolower(trim((string)$opencart_code));

        if ($code === '') {
            return '';
        }

        $map = array(
            'en-gb' => 'en',
            'en-us' => 'en',
            'uk-ua' => 'uk',
            'ru-ru' => 'ru',
            'de-de' => 'de',
            'fr-fr' => 'fr',
            'es-es' => 'es',
            'it-it' => 'it',
            'pl-pl' => 'pl',
            'tr-tr' => 'tr',
            'pt-pt' => 'pt',
            'pt-br' => 'pt',
            'zh-cn' => 'zh-CN',
            'zh-tw' => 'zh-TW',
            'ja-jp' => 'ja'
        );

        if (isset($map[$code])) {
            return $map[$code];
        }

        $chunks = explode('-', $code);
        return $chunks[0];
    }

    private function getLanguageTablesWithTextColumns() {
        $schema = $this->getDbSchemaName();

        if ($schema === '') {
            return array();
        }

        $prefix_like = $this->escapeLike(DB_PREFIX) . '%';
        $table_query = $this->db->query("SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '" . $this->db->escape($schema) . "'
                AND COLUMN_NAME = 'language_id'
                AND TABLE_NAME LIKE '" . $this->db->escape($prefix_like) . "' ESCAPE '\\\\'
            GROUP BY TABLE_NAME
            ORDER BY TABLE_NAME ASC");

        $tables = array();

        foreach ($table_query->rows as $row) {
            $table = (string)$row['TABLE_NAME'];

            if ($this->isExcludedLanguageTable($table)) {
                continue;
            }

            $columns_meta = $this->getTableColumnsMeta($schema, $table);
            $text_columns = $this->extractTextColumns($columns_meta);

            if (!$text_columns) {
                continue;
            }

            $identity_columns = $this->resolveIdentityColumns($schema, $table, $columns_meta, $text_columns);

            if (!$identity_columns) {
                continue;
            }

            $tables[] = array(
                'table' => $table,
                'text_columns' => $text_columns,
                'identity_columns' => $identity_columns
            );
        }

        return $tables;
    }

    private function getDbSchemaName() {
        $query = $this->db->query("SELECT DATABASE() AS db_name");

        if ($query->num_rows && !empty($query->row['db_name'])) {
            return (string)$query->row['db_name'];
        }

        return '';
    }

    private function getTableColumnsMeta($schema, $table) {
        $query = $this->db->query("SELECT COLUMN_NAME, DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '" . $this->db->escape($schema) . "'
                AND TABLE_NAME = '" . $this->db->escape($table) . "'
            ORDER BY ORDINAL_POSITION ASC");

        $columns = array();

        foreach ($query->rows as $row) {
            $columns[] = array(
                'name' => (string)$row['COLUMN_NAME'],
                'type' => strtolower((string)$row['DATA_TYPE'])
            );
        }

        return $columns;
    }

    private function extractTextColumns($columns_meta) {
        $text_types = array('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext');
        $columns = array();

        foreach ($columns_meta as $column_meta) {
            $name = $column_meta['name'];
            $type = $column_meta['type'];

            if ($name === 'language_id') {
                continue;
            }

            if (in_array($type, $text_types, true)) {
                if (!$this->isTranslatableTextColumn($name)) {
                    continue;
                }

                $columns[] = $name;
            }
        }

        return $columns;
    }

    private function resolveIdentityColumns($schema, $table, $columns_meta, $text_columns) {
        $auto_increment_columns = $this->getAutoIncrementColumns($schema, $table);
        $auto_lookup = array_fill_keys($auto_increment_columns, true);

        $query = $this->db->query("SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '" . $this->db->escape($schema) . "'
                AND TABLE_NAME = '" . $this->db->escape($table) . "'
                AND CONSTRAINT_NAME = 'PRIMARY'
            ORDER BY ORDINAL_POSITION ASC");

        $identity = array();

        foreach ($query->rows as $row) {
            $column = (string)$row['COLUMN_NAME'];
            if ($column !== 'language_id' && !isset($auto_lookup[$column])) {
                $identity[] = $column;
            }
        }

        if ($identity) {
            return array_values(array_unique($identity));
        }

        $text_lookup = array_fill_keys($text_columns, true);

        foreach ($columns_meta as $column_meta) {
            $name = $column_meta['name'];
            if ($name === 'language_id' || isset($text_lookup[$name]) || isset($auto_lookup[$name])) {
                continue;
            }
            $identity[] = $name;
        }

        if ($identity) {
            return array_values(array_unique($identity));
        }

        foreach ($columns_meta as $column_meta) {
            $name = $column_meta['name'];
            if ($name !== 'language_id' && !isset($auto_lookup[$name]) && preg_match('/_id$/', $name)) {
                return array($name);
            }
        }

        return array();
    }

    private function getTargetRowByIdentity($table, $identity_columns, $source_row, $target_language_id) {
        $where_parts = array();

        foreach ($identity_columns as $column) {
            if (!isset($source_row[$column])) {
                continue;
            }

            $where_parts[] = "`" . $this->db->escape($column) . "` = '" . $this->db->escape((string)$source_row[$column]) . "'";
        }

        if (!$where_parts) {
            return array();
        }

        $query = $this->db->query("SELECT * FROM `" . $this->db->escape($table) . "`
            WHERE " . implode(' AND ', $where_parts) . "
            AND `language_id` = '" . (int)$target_language_id . "'
            LIMIT 1");

        return $query->num_rows ? $query->row : array();
    }

    private function extractIdentityValues($identity_columns, $row) {
        $values = array();

        foreach ($identity_columns as $column) {
            if (isset($row[$column])) {
                $values[$column] = $row[$column];
            }
        }

        return $values;
    }

    private function buildIdentityLabel($identity_columns, $row) {
        $parts = array();

        foreach ($identity_columns as $column) {
            if (isset($row[$column])) {
                $parts[] = $column . '=' . $row[$column];
            }
        }

        return $parts ? implode(', ', $parts) : '';
    }

    private function escapeLike($value) {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string)$value);
    }

    private function normalizeSelectedTables(array $selected_tables) {
        $lookup = array();

        foreach ($selected_tables as $table) {
            $table = trim((string)$table);

            if ($table === '') {
                continue;
            }

            $lookup[$table] = true;

            if (strpos($table, DB_PREFIX) !== 0) {
                $lookup[DB_PREFIX . $table] = true;
            }
        }

        return $lookup;
    }

    private function isLinkLikeText($value) {
        $text = trim((string)$value);

        if ($text === '') {
            return false;
        }

        $decoded = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $decoded = trim($decoded);

        // Links/paths are usually single-token values; don't block regular text with spaces.
        if (preg_match('/\s/u', $decoded)) {
            return false;
        }

        // Absolute URLs / known URL schemes / protocol-relative URLs
        if (preg_match('/^(https?:\/\/|ftp:\/\/|www\.|\/\/|mailto:|tel:|sms:|skype:|javascript:)/iu', $decoded)) {
            return true;
        }

        // Typical OpenCart routes and query strings
        if (preg_match('/(^|\/)index\.php(\?|$)/iu', $decoded) || preg_match('/(^|\?|&)route=/iu', $decoded) || preg_match('/\?.+=/u', $decoded)) {
            return true;
        }

        // Relative/absolute file paths and static assets
        if (preg_match('/^(\.\.\/|\.\/|\/)/u', $decoded)) {
            return true;
        }

        if (preg_match('/\/(?:[^\/]+\/)*[^\/]+\.(?:php|html?|xml|json|css|js|jpg|jpeg|png|gif|webp|svg|ico|pdf|zip)(\?.*)?$/iu', $decoded)) {
            return true;
        }

        // Domain-like value without protocol
        if (preg_match('/^[a-z0-9][a-z0-9\-\.]*\.[a-z]{2,}(?:\/.*)?$/iu', $decoded)) {
            return true;
        }

        return false;
    }

    private function getAutoIncrementColumns($schema, $table) {
        if ($schema === '') {
            return array();
        }

        $query = $this->db->query("SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = '" . $this->db->escape($schema) . "'
                AND TABLE_NAME = '" . $this->db->escape($table) . "'
                AND EXTRA LIKE '%auto_increment%'");

        $columns = array();

        foreach ($query->rows as $row) {
            $columns[] = (string)$row['COLUMN_NAME'];
        }

        return $columns;
    }

    private function isExcludedLanguageTable($table_name) {
        $table_name = strtolower((string)$table_name);

        $without_prefix = $table_name;
        $prefix = strtolower((string)DB_PREFIX);
        if ($prefix !== '' && strpos($without_prefix, $prefix) === 0) {
            $without_prefix = substr($without_prefix, strlen($prefix));
        }

        $excluded_exact = array(
            'seo_url',
            'language',
            'customer',
            'translation',
            'dockercart_import_export_excel_profile',
            'dockercart_import_yml_profile',
            'blog_seo_url',
            'dockercart_seo_log',
            'dockercart_export_yml_profile',
        );

        if (in_array($without_prefix, $excluded_exact, true)) {
            return true;
        }

        return (bool)preg_match('/(_log$|_history$|_audit$|_session$|_cache$|_tmp$|_temp$|_queue$|_report$|_reports$|_stat$|_stats$|_statistics$)/', $table_name);
    }

    private function isTranslatableTextColumn($column_name) {
        $column = strtolower((string)$column_name);

        $excluded_exact = array(
            'image',
            'image_portrait',
            'image_mobile',
            'accent_color',
            'title_color',
            'text_color',
            'button_text_color',
            'button_bg_color',
            'icon',
            'logo',
            'thumb',
            'link',
            'url',
            'path',
            'file',
            'filename',
            'route',
            'code',
            'sku',
            'upc',
            'ean',
            'jan',
            'isbn',
            'mpn',
            'model',
            'location',
            'token',
            'hash',
            'json',
            'xml',
            'yaml',
            'yml',
            'css',
            'js'
        );

        if (in_array($column, $excluded_exact, true)) {
            return false;
        }

        if (preg_match('/(^seo_url$|_url$|_link$|_path$|_color$|^meta_og_image$|^image_|_image$|^old_value$|^new_value$|^request$|^response$)/', $column)) {
            return false;
        }

        return true;
    }
}
