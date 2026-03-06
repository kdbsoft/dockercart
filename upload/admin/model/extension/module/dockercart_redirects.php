<?php
/**
 * DockerCart Redirects - Admin Model (renamed)
 */

class ModelExtensionModuleDockercartRedirects extends Model {
    public function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "redirect_manager` (
            `redirect_id` INT(11) NOT NULL AUTO_INCREMENT,
            `old_url` VARCHAR(512) NOT NULL,
            `new_url` VARCHAR(512) NOT NULL,
            `code` SMALLINT(3) UNSIGNED NOT NULL DEFAULT 301,
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `is_regex` TINYINT(1) NOT NULL DEFAULT 0,
            `preserve_query` TINYINT(1) NOT NULL DEFAULT 1,
            `hits` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            `last_hit` DATETIME NULL DEFAULT NULL,
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`redirect_id`),
            INDEX `idx_old_url` (`old_url`(255)),
            INDEX `idx_status` (`status`),
            INDEX `idx_is_regex` (`is_regex`),
            INDEX `idx_status_regex` (`status`, `is_regex`),
            INDEX `idx_hits` (`hits`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->db->query($sql);
    }

    public function dropTable() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "redirect_manager`");
    }

    public function addRedirect($data) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "redirect_manager` SET 
            `old_url` = '" . $this->db->escape($data['old_url']) . "',
            `new_url` = '" . $this->db->escape($data['new_url']) . "',
            `code` = '" . (int)$data['code'] . "',
            `status` = '" . (isset($data['status']) ? (int)$data['status'] : 1) . "',
            `is_regex` = '" . (isset($data['is_regex']) ? (int)$data['is_regex'] : 0) . "',
            `preserve_query` = '" . (isset($data['preserve_query']) ? (int)$data['preserve_query'] : 1) . "',
            `date_added` = NOW(),
            `date_modified` = NOW()");

        return $this->db->getLastId();
    }

    public function editRedirect($redirect_id, $data) {
        $this->db->query("UPDATE `" . DB_PREFIX . "redirect_manager` SET 
            `old_url` = '" . $this->db->escape($data['old_url']) . "',
            `new_url` = '" . $this->db->escape($data['new_url']) . "',
            `code` = '" . (int)$data['code'] . "',
            `status` = '" . (isset($data['status']) ? (int)$data['status'] : 1) . "',
            `is_regex` = '" . (isset($data['is_regex']) ? (int)$data['is_regex'] : 0) . "',
            `preserve_query` = '" . (isset($data['preserve_query']) ? (int)$data['preserve_query'] : 1) . "',
            `date_modified` = NOW()
            WHERE `redirect_id` = '" . (int)$redirect_id . "'");
    }

    public function deleteRedirect($redirect_id) {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "redirect_manager` WHERE `redirect_id` = '" . (int)$redirect_id . "'");
    }

    public function getRedirect($redirect_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "redirect_manager` WHERE `redirect_id` = '" . (int)$redirect_id . "'");
        return $query->row;
    }

    public function getRedirects($data = array()) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "redirect_manager` WHERE 1=1";

        if (!empty($data['filter_old_url'])) {
            $sql .= " AND `old_url` LIKE '%" . $this->db->escape($data['filter_old_url']) . "%'";
        }

        if (!empty($data['filter_new_url'])) {
            $sql .= " AND `new_url` LIKE '%" . $this->db->escape($data['filter_new_url']) . "%'";
        }

        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $sql .= " AND `status` = '" . (int)$data['filter_status'] . "'";
        }

        if (isset($data['filter_is_regex']) && $data['filter_is_regex'] !== '') {
            $sql .= " AND `is_regex` = '" . (int)$data['filter_is_regex'] . "'";
        }

        $sql .= " ORDER BY `redirect_id` DESC";

        if (isset($data['start']) || isset($data['limit'])) {
            $start = isset($data['start']) ? (int)$data['start'] : 0;
            $limit = isset($data['limit']) ? (int)$data['limit'] : 50;

            if ($start < 0) {
                $start = 0;
            }

            if ($limit < 1) {
                $limit = 50;
            }

            $sql .= " LIMIT " . $start . "," . $limit;
        }

        $query = $this->db->query($sql);
        return $query->rows;
    }

    public function getTotalRedirects($data = array()) {
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "redirect_manager` WHERE 1=1";

        if (!empty($data['filter_old_url'])) {
            $sql .= " AND `old_url` LIKE '%" . $this->db->escape($data['filter_old_url']) . "%'";
        }

        if (!empty($data['filter_new_url'])) {
            $sql .= " AND `new_url` LIKE '%" . $this->db->escape($data['filter_new_url']) . "%'";
        }

        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $sql .= " AND `status` = '" . (int)$data['filter_status'] . "'";
        }

        if (isset($data['filter_is_regex']) && $data['filter_is_regex'] !== '') {
            $sql .= " AND `is_regex` = '" . (int)$data['filter_is_regex'] . "'";
        }

        $query = $this->db->query($sql);
        return $query->row['total'];
    }

    public function getStatistics() {
        $stats = array(
            'total' => 0,
            'active' => 0,
            'regex' => 0,
            'total_hits' => 0
        );

        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "redirect_manager`");
        $stats['total'] = $query->row['total'];

        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "redirect_manager` WHERE `status` = 1");
        $stats['active'] = $query->row['total'];

        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "redirect_manager` WHERE `is_regex` = 1");
        $stats['regex'] = $query->row['total'];

        $query = $this->db->query("SELECT SUM(`hits`) AS total FROM `" . DB_PREFIX . "redirect_manager`");
        $stats['total_hits'] = $query->row['total'] ? $query->row['total'] : 0;

        return $stats;
    }

    public function updateStatus($redirect_id, $status) {
        $this->db->query("UPDATE `" . DB_PREFIX . "redirect_manager` SET 
            `status` = '" . (int)$status . "',
            `date_modified` = NOW()
            WHERE `redirect_id` = '" . (int)$redirect_id . "'");
    }


    public function clearStatistics() {
        $this->db->query("UPDATE `" . DB_PREFIX . "redirect_manager` SET `hits` = 0, `last_hit` = NULL");
    }

    public function findDuplicates() {
        $query = $this->db->query("SELECT `old_url`, COUNT(*) AS c FROM `" . DB_PREFIX . "redirect_manager` GROUP BY `old_url` HAVING c > 1");
        return $query->rows;
    }

    /**
     * Get current SEO URL from oc_seo_url table
     */
    public function getCurrentSeoUrl($entity_type, $entity_id, $language_id) {
        $query_field = $entity_type . '_id';
        $query_value = $query_field . '=' . (int)$entity_id;

        $query = $this->db->query(
            "SELECT keyword FROM `" . DB_PREFIX . "seo_url`
            WHERE query = '" . $this->db->escape($query_value) . "'
            AND language_id = '" . (int)$language_id . "'
            LIMIT 1"
        );

        return $query->num_rows ? $query->row['keyword'] : '';
    }
}

