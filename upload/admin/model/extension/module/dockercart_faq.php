<?php
class ModelExtensionModuleDockercartFaq extends Model {
    private $schema_ensured = false;

    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_faq` (
            `faq_id` int(11) NOT NULL AUTO_INCREMENT,
            `code` varchar(128) NOT NULL,
            `context_type` enum('all','home','route','category','product','manufacturer','information','search') NOT NULL DEFAULT 'all',
            `context_value` varchar(255) NOT NULL DEFAULT '',
            `show_widget` tinyint(1) NOT NULL DEFAULT '1',
            `show_json_ld` tinyint(1) NOT NULL DEFAULT '1',
            `sort_order` int(11) NOT NULL DEFAULT '0',
            `status` tinyint(1) NOT NULL DEFAULT '1',
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`faq_id`),
            UNIQUE KEY `code` (`code`),
            KEY `context_type_context_value` (`context_type`,`context_value`),
            KEY `status_sort_order` (`status`,`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_faq_description` (
            `faq_id` int(11) NOT NULL,
            `language_id` int(11) NOT NULL,
            `question` text NOT NULL,
            `answer` mediumtext NOT NULL,
            PRIMARY KEY (`faq_id`,`language_id`),
            KEY `language_id` (`language_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_faq_to_store` (
            `faq_id` int(11) NOT NULL,
            `store_id` int(11) NOT NULL,
            PRIMARY KEY (`faq_id`,`store_id`),
            KEY `store_id` (`store_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->ensureSchema();
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_faq_to_store`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_faq_description`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "dockercart_faq`");
    }

    public function addFaq($data) {
        $this->ensureSchema();

        $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_faq`
            SET `code` = '" . $this->db->escape($this->normalizeCode($data)) . "',
                `context_type` = '" . $this->db->escape($this->normalizeContextType($data)) . "',
                `context_value` = '" . $this->db->escape($this->normalizeContextValue($data)) . "',
                `show_widget` = '" . (int)$this->normalizeBool($data, 'show_widget', 1) . "',
                `show_json_ld` = '" . (int)$this->normalizeBool($data, 'show_json_ld', 1) . "',
                `sort_order` = '" . (int)$this->getData($data, 'sort_order', 0) . "',
                `status` = '" . (int)$this->normalizeBool($data, 'status', 1) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()");

        $faq_id = (int)$this->db->getLastId();
        $this->saveDescriptions($faq_id, $data);
        $this->saveStores($faq_id, $data);

        return $faq_id;
    }

    public function editFaq($faq_id, $data) {
        $this->ensureSchema();

        $faq_id = (int)$faq_id;

        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_faq`
            SET `code` = '" . $this->db->escape($this->normalizeCode($data)) . "',
                `context_type` = '" . $this->db->escape($this->normalizeContextType($data)) . "',
                `context_value` = '" . $this->db->escape($this->normalizeContextValue($data)) . "',
                `show_widget` = '" . (int)$this->normalizeBool($data, 'show_widget', 1) . "',
                `show_json_ld` = '" . (int)$this->normalizeBool($data, 'show_json_ld', 1) . "',
                `sort_order` = '" . (int)$this->getData($data, 'sort_order', 0) . "',
                `status` = '" . (int)$this->normalizeBool($data, 'status', 1) . "',
                `date_modified` = NOW()
            WHERE `faq_id` = '" . $faq_id . "'");

        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_faq_description` WHERE `faq_id` = '" . $faq_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_faq_to_store` WHERE `faq_id` = '" . $faq_id . "'");

        $this->saveDescriptions($faq_id, $data);
        $this->saveStores($faq_id, $data);
    }

    public function deleteFaq($faq_id) {
        $this->ensureSchema();

        $faq_id = (int)$faq_id;

        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_faq_to_store` WHERE `faq_id` = '" . $faq_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_faq_description` WHERE `faq_id` = '" . $faq_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "dockercart_faq` WHERE `faq_id` = '" . $faq_id . "'");
    }

    public function getFaq($faq_id) {
        $this->ensureSchema();

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_faq` WHERE `faq_id` = '" . (int)$faq_id . "'");
        return $query->num_rows ? $query->row : null;
    }

    public function getFaqs($data = array()) {
        $this->ensureSchema();

        $language_id = (int)$this->config->get('config_language_id');

        $sql = "SELECT f.*, fd.question
                FROM `" . DB_PREFIX . "dockercart_faq` f
                LEFT JOIN `" . DB_PREFIX . "dockercart_faq_description` fd ON (f.faq_id = fd.faq_id AND fd.language_id = '" . $language_id . "')
                WHERE 1=1";

        $sort_data = array('fd.question', 'f.code', 'f.context_type', 'f.sort_order', 'f.status');

        $sort = isset($data['sort']) && in_array($data['sort'], $sort_data) ? $data['sort'] : 'f.sort_order';
        $order = (isset($data['order']) && strtoupper((string)$data['order']) === 'DESC') ? 'DESC' : 'ASC';

        $sql .= " ORDER BY " . $sort . " " . $order;

        if (isset($data['start']) || isset($data['limit'])) {
            $start = isset($data['start']) ? (int)$data['start'] : 0;
            $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
            if ($start < 0) $start = 0;
            if ($limit < 1) $limit = 20;
            $sql .= " LIMIT " . $start . "," . $limit;
        }

        return $this->db->query($sql)->rows;
    }

    public function getFaqDescriptions($faq_id) {
        $this->ensureSchema();

        $result = array();
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "dockercart_faq_description` WHERE `faq_id` = '" . (int)$faq_id . "'");

        foreach ($query->rows as $row) {
            $result[(int)$row['language_id']] = array(
                'question' => $row['question'],
                'answer' => $row['answer']
            );
        }

        return $result;
    }

    public function getFaqStores($faq_id) {
        $this->ensureSchema();

        $stores = array();
        $query = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "dockercart_faq_to_store` WHERE faq_id = '" . (int)$faq_id . "'");

        foreach ($query->rows as $row) {
            $stores[] = (int)$row['store_id'];
        }

        if (!$stores) {
            $stores[] = 0;
        }

        return $stores;
    }

    private function saveDescriptions($faq_id, $data) {
        $faq_descriptions = isset($data['faq_description']) && is_array($data['faq_description'])
            ? $data['faq_description']
            : array();

        foreach ($faq_descriptions as $language_id => $description) {
            $question = isset($description['question']) ? trim((string)$description['question']) : '';
            $answer = isset($description['answer']) ? trim((string)$description['answer']) : '';

            if ($question === '' || $answer === '') {
                continue;
            }

            $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_faq_description`
                SET `faq_id` = '" . (int)$faq_id . "',
                    `language_id` = '" . (int)$language_id . "',
                    `question` = '" . $this->db->escape($question) . "',
                    `answer` = '" . $this->db->escape($answer) . "'");
        }
    }

    private function saveStores($faq_id, $data) {
        $stores = isset($data['faq_store']) && is_array($data['faq_store']) ? $data['faq_store'] : array(0);

        if (!$stores) {
            $stores = array(0);
        }

        foreach ($stores as $store_id) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "dockercart_faq_to_store`
                SET `faq_id` = '" . (int)$faq_id . "',
                    `store_id` = '" . (int)$store_id . "'");
        }
    }

    private function normalizeContextType($data) {
        $context_type = strtolower((string)$this->getData($data, 'context_type', 'all'));
        $allowed = array('all', 'home', 'route', 'category', 'product', 'manufacturer', 'information', 'search');

        return in_array($context_type, $allowed) ? $context_type : 'all';
    }

    private function normalizeContextValue($data) {
        return trim((string)$this->getData($data, 'context_value', ''));
    }

    private function normalizeCode($data) {
        $code = strtolower(trim((string)$this->getData($data, 'code', '')));
        return preg_replace('/[^a-z0-9_\-\.]+/', '-', $code);
    }

    private function normalizeBool($data, $key, $default = 0) {
        return (int)$this->getData($data, $key, $default) ? 1 : 0;
    }

    private function getData($data, $key, $default = null) {
        return isset($data[$key]) ? $data[$key] : $default;
    }

    private function ensureSchema() {
        if ($this->schema_ensured) {
            return;
        }

        $this->schema_ensured = true;

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_faq` (
            `faq_id` int(11) NOT NULL AUTO_INCREMENT,
            `code` varchar(128) NOT NULL,
            `context_type` enum('all','home','route','category','product','manufacturer','information','search') NOT NULL DEFAULT 'all',
            `context_value` varchar(255) NOT NULL DEFAULT '',
            `show_widget` tinyint(1) NOT NULL DEFAULT '1',
            `show_json_ld` tinyint(1) NOT NULL DEFAULT '1',
            `sort_order` int(11) NOT NULL DEFAULT '0',
            `status` tinyint(1) NOT NULL DEFAULT '1',
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`faq_id`),
            KEY `context_type_context_value` (`context_type`,`context_value`),
            KEY `status_sort_order` (`status`,`sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_faq_description` (
            `faq_id` int(11) NOT NULL,
            `language_id` int(11) NOT NULL,
            `question` text NOT NULL,
            `answer` mediumtext NOT NULL,
            PRIMARY KEY (`faq_id`,`language_id`),
            KEY `language_id` (`language_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "dockercart_faq_to_store` (
            `faq_id` int(11) NOT NULL,
            `store_id` int(11) NOT NULL,
            PRIMARY KEY (`faq_id`,`store_id`),
            KEY `store_id` (`store_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'code', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `code` varchar(128) NULL AFTER `faq_id`");
        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'context_type', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `context_type` enum('all','home','route','category','product','manufacturer','information','search') NOT NULL DEFAULT 'all' AFTER `code`");
        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'context_value', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `context_value` varchar(255) NOT NULL DEFAULT '' AFTER `context_type`");
        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'show_widget', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `show_widget` tinyint(1) NOT NULL DEFAULT '1' AFTER `context_value`");
        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'show_json_ld', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `show_json_ld` tinyint(1) NOT NULL DEFAULT '1' AFTER `show_widget`");
        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'sort_order', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `sort_order` int(11) NOT NULL DEFAULT '0' AFTER `show_json_ld`");
        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'status', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `status` tinyint(1) NOT NULL DEFAULT '1' AFTER `sort_order`");
        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'date_added', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `date_added` datetime NOT NULL AFTER `status`");
        $this->addColumnIfMissing(DB_PREFIX . 'dockercart_faq', 'date_modified', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD `date_modified` datetime NOT NULL AFTER `date_added`");

        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_faq` SET `code` = CONCAT('faq-', `faq_id`) WHERE `code` IS NULL OR `code` = ''");
        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_faq` SET `date_added` = NOW() WHERE `date_added` = '0000-00-00 00:00:00' OR `date_added` IS NULL");
        $this->db->query("UPDATE `" . DB_PREFIX . "dockercart_faq` SET `date_modified` = NOW() WHERE `date_modified` = '0000-00-00 00:00:00' OR `date_modified` IS NULL");

        $this->addIndexIfMissing(DB_PREFIX . 'dockercart_faq', 'code', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD INDEX `code` (`code`)");
        $this->addIndexIfMissing(DB_PREFIX . 'dockercart_faq', 'context_type_context_value', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD INDEX `context_type_context_value` (`context_type`,`context_value`)");
        $this->addIndexIfMissing(DB_PREFIX . 'dockercart_faq', 'status_sort_order', "ALTER TABLE `" . DB_PREFIX . "dockercart_faq` ADD INDEX `status_sort_order` (`status`,`sort_order`)");
    }

    private function addColumnIfMissing($table, $column, $alter_sql) {
        $query = $this->db->query("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $this->db->escape($column) . "'");

        if (!$query->num_rows) {
            $this->db->query($alter_sql);
        }
    }

    private function addIndexIfMissing($table, $index_name, $alter_sql) {
        $query = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = '" . $this->db->escape($index_name) . "'");

        if (!$query->num_rows) {
            $this->db->query($alter_sql);
        }
    }
}
