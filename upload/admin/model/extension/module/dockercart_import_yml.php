<?php
class ModelExtensionModuleDockercartImportYml extends Model {

    private $schema_checked = false;

    private function ensureSchema() {
        if ($this->schema_checked) {
            return;
        }

        $table_exists = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "dockercart_import_yml_profile") . "'");

        if ($table_exists->num_rows) {
            $column_load_categories = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "dockercart_import_yml_profile` LIKE 'load_categories'");
            if (!$column_load_categories->num_rows) {
                $this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_import_yml_profile` ADD `load_categories` tinyint(1) NOT NULL DEFAULT '1' AFTER `default_category_id`");
            }

            $column_import_mode = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "dockercart_import_yml_profile` LIKE 'import_mode'");
            if ($column_import_mode->num_rows && strpos((string)$column_import_mode->row['Type'], 'update_price_qty_only') === false) {
                $this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_import_yml_profile` MODIFY `import_mode` enum('add','update','update_only','update_price_qty_only','replace') NOT NULL DEFAULT 'update'");
            }

            $column_download_images = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "dockercart_import_yml_profile` LIKE 'download_images'");
            if (!$column_download_images->num_rows) {
                $this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_import_yml_profile` ADD `download_images` tinyint(1) NOT NULL DEFAULT '1' AFTER `load_categories`");
            }

            $column_allow_zero_price = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "dockercart_import_yml_profile` LIKE 'allow_zero_price'");
            if (!$column_allow_zero_price->num_rows) {
                $this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_import_yml_profile` ADD `allow_zero_price` tinyint(1) NOT NULL DEFAULT '0' AFTER `download_images`");
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
        }

        $this->schema_checked = true;
    }

    public function install() {
        $this->schema_checked = false;
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_import_yml_profile` (
                `profile_id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `feed_url` text NOT NULL,
                `store_id` int(11) NOT NULL DEFAULT '0',
                `language_id` int(11) NOT NULL DEFAULT '1',
                `currency_code` varchar(3) NOT NULL DEFAULT 'RUB',
                `default_category_id` int(11) NOT NULL DEFAULT '0',
            `load_categories` tinyint(1) NOT NULL DEFAULT '1',
            `download_images` tinyint(1) NOT NULL DEFAULT '1',
                `import_mode` enum('add','update','update_only','update_price_qty_only','replace') NOT NULL DEFAULT 'update',
                `status` tinyint(1) NOT NULL DEFAULT '1',
                `cron_key` varchar(64) NOT NULL,
                `last_run` datetime DEFAULT NULL,
                `last_result` text,
                `date_added` datetime NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`profile_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Migration for existing installs without new import modes
        $column_query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "dockercart_import_yml_profile` LIKE 'import_mode'");
        if ($column_query->num_rows && strpos((string)$column_query->row['Type'], 'update_price_qty_only') === false) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_import_yml_profile` MODIFY `import_mode` enum('add','update','update_only','update_price_qty_only','replace') NOT NULL DEFAULT 'update'");
        }

        $column_load_categories = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "dockercart_import_yml_profile` LIKE 'load_categories'");
        if (!$column_load_categories->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_import_yml_profile` ADD `load_categories` tinyint(1) NOT NULL DEFAULT '1' AFTER `default_category_id`");
        }

        $column_download_images = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "dockercart_import_yml_profile` LIKE 'download_images'");
        if (!$column_download_images->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_import_yml_profile` ADD `download_images` tinyint(1) NOT NULL DEFAULT '1' AFTER `load_categories`");
        }

        $column_allow_zero_price = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "dockercart_import_yml_profile` LIKE 'allow_zero_price'");
        if (!$column_allow_zero_price->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "dockercart_import_yml_profile` ADD `allow_zero_price` tinyint(1) NOT NULL DEFAULT '0' AFTER `download_images`");
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_import_yml_offer_map` (
                `map_id` int(11) NOT NULL AUTO_INCREMENT,
                `profile_id` int(11) NOT NULL,
                `offer_id` varchar(255) NOT NULL,
                `product_id` int(11) NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`map_id`),
                UNIQUE KEY `profile_offer` (`profile_id`,`offer_id`),
                KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

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

        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "dockercart_import_yml_profile`");
        if ((int)$query->row['total'] === 0) {
            $this->addProfile(array(
                'name' => 'Default Import Profile',
                'feed_url' => '',
                'store_id' => 0,
                'language_id' => 1,
                'currency_code' => 'RUB',
                'default_category_id' => 0,
                'load_categories' => 1,
                'download_images' => 1,
                'import_mode' => 'update',
                'status' => 0
            ));
        }
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_import_yml_category_map`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_import_yml_offer_map`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_import_yml_profile`");
        $this->schema_checked = false;
    }

    public function getProfiles() {
        $this->ensureSchema();
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_import_yml_profile` ORDER BY `name` ASC");

        $rows = $query->rows;
        foreach ($rows as &$row) {
            if (empty($row['cron_key'])) {
                $row['cron_key'] = $this->generateCronKey();
            }

            $last_result = json_decode((string)$row['last_result'], true);
            $row['last_result'] = is_array($last_result) ? $last_result : array();
        }

        return $rows;
    }

    public function getProfile($profile_id) {
        $this->ensureSchema();
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_import_yml_profile` WHERE `profile_id` = '" . (int)$profile_id . "'");

        if (!$query->num_rows) {
            return null;
        }

        $row = $query->row;
        $last_result = json_decode((string)$row['last_result'], true);
        $row['last_result'] = is_array($last_result) ? $last_result : array();

        return $row;
    }

    public function addProfile($data) {
        $this->ensureSchema();
        $cron_key = $this->generateCronKey();

        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_import_yml_profile`
            SET
                `name` = '" . $this->db->escape($data['name']) . "',
                `feed_url` = '" . $this->db->escape(trim((string)$data['feed_url'])) . "',
                `store_id` = '" . (int)(isset($data['store_id']) ? $data['store_id'] : 0) . "',
                `language_id` = '" . (int)(isset($data['language_id']) ? $data['language_id'] : 1) . "',
                `currency_code` = '" . $this->db->escape(isset($data['currency_code']) ? (string)$data['currency_code'] : 'RUB') . "',
                `default_category_id` = '" . (int)(isset($data['default_category_id']) ? $data['default_category_id'] : 0) . "',
                `load_categories` = '" . (!empty($data['load_categories']) ? 1 : 0) . "',
                `download_images` = '" . (!empty($data['download_images']) ? 1 : 0) . "',
                `allow_zero_price` = '" . (!empty($data['allow_zero_price']) ? 1 : 0) . "',
                `import_mode` = '" . $this->db->escape($this->normalizeImportMode(isset($data['import_mode']) ? $data['import_mode'] : 'update')) . "',
                `status` = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "',
                `cron_key` = '" . $this->db->escape($cron_key) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()");

        return (int)$this->db->getLastId();
    }

    public function updateProfile($profile_id, $data) {
        $this->ensureSchema();
        $profile = $this->getProfile($profile_id);
        if (!$profile) {
            return;
        }

        $cron_key = !empty($profile['cron_key']) ? $profile['cron_key'] : $this->generateCronKey();
        if (!empty($data['regenerate_cron_key'])) {
            $cron_key = $this->generateCronKey();
        }

        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_import_yml_profile`
            SET
                `name` = '" . $this->db->escape($data['name']) . "',
                `feed_url` = '" . $this->db->escape(trim((string)$data['feed_url'])) . "',
                `store_id` = '" . (int)(isset($data['store_id']) ? $data['store_id'] : 0) . "',
                `language_id` = '" . (int)(isset($data['language_id']) ? $data['language_id'] : 1) . "',
                `currency_code` = '" . $this->db->escape(isset($data['currency_code']) ? (string)$data['currency_code'] : 'RUB') . "',
                `default_category_id` = '" . (int)(isset($data['default_category_id']) ? $data['default_category_id'] : 0) . "',
                `load_categories` = '" . (!empty($data['load_categories']) ? 1 : 0) . "',
                `download_images` = '" . (!empty($data['download_images']) ? 1 : 0) . "',
                `allow_zero_price` = '" . (!empty($data['allow_zero_price']) ? 1 : 0) . "',
                `import_mode` = '" . $this->db->escape($this->normalizeImportMode(isset($data['import_mode']) ? $data['import_mode'] : 'update')) . "',
                `status` = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "',
                `cron_key` = '" . $this->db->escape($cron_key) . "',
                `date_modified` = NOW()
            WHERE `profile_id` = '" . (int)$profile_id . "'");
    }

    public function deleteProfile($profile_id) {
        $this->ensureSchema();
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_import_yml_category_map` WHERE `profile_id` = '" . (int)$profile_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_import_yml_offer_map` WHERE `profile_id` = '" . (int)$profile_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_import_yml_profile` WHERE `profile_id` = '" . (int)$profile_id . "'");
    }

    private function normalizeImportMode($mode) {
        $mode = (string)$mode;
        if (!in_array($mode, array('add', 'update', 'update_only', 'update_price_qty_only', 'replace'))) {
            $mode = 'update';
        }

        return $mode;
    }

    private function generateCronKey() {
        try {
            return bin2hex(random_bytes(20));
        } catch (Exception $e) {
            return sha1(uniqid('dockercart_import_yml_', true));
        }
    }
}
