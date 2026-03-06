<?php
/**
 * DockerCart Redirects - Catalog Model (renamed)
 */

class ModelExtensionModuleDockercartRedirects extends Model {
    public function getActiveRedirectsForUrl($url) {
        // Exact non-regex match first
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "redirect_manager` 
            WHERE `status` = 1 
            AND `is_regex` = 0 
            AND `old_url` = '" . $this->db->escape($url) . "' 
            LIMIT 1"
        );

        if ($query->num_rows) {
            return $query->row;
        }

        // Regex redirects
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "redirect_manager` 
            WHERE `status` = 1 
            AND `is_regex` = 1
            ORDER BY `redirect_id` ASC
            LIMIT 500"
        );

        return $query->rows;
    }

    public function incrementHits($redirect_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "redirect_manager` SET 
            `hits` = `hits` + 1,
            `last_hit` = NOW()
            WHERE `redirect_id` = '" . (int)$redirect_id . "'");
    }
}

