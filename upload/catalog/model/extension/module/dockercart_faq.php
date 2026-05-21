<?php
class ModelExtensionModuleDockercartFaq extends Model {
    public function getFaqByCode($code, $store_id, $language_id) {
        $code = trim((string)$code);
        if ($code === '') {
            return null;
        }

        $cache_key = 'dockercart_faq.code.' . $code . '.' . (int)$language_id . '.' . (int)$store_id;
        $faq_data = $this->cache->get($cache_key);

        if ($faq_data === null || $faq_data === false) {
            $sql = "SELECT f.*, fd.question, fd.answer
                    FROM `" . DB_PREFIX . "dockercart_faq` f
                    INNER JOIN `" . DB_PREFIX . "dockercart_faq_description` fd ON (f.faq_id = fd.faq_id)
                    INNER JOIN `" . DB_PREFIX . "dockercart_faq_to_store` f2s ON (f.faq_id = f2s.faq_id)
                    WHERE f.code = '" . $this->db->escape($code) . "'
                      AND f.status = '1'
                      AND fd.language_id = '" . (int)$language_id . "'
                      AND f2s.store_id = '" . (int)$store_id . "'
                    LIMIT 1";

            $query = $this->db->query($sql);
            $faq_data = $query->num_rows ? $query->row : null;
            $this->cache->set($cache_key, $faq_data, 3600);
        }

        return $faq_data;
    }

    public function getFaqsByContext($context_type, $context_value, $store_id, $language_id) {
        $context_type = strtolower(trim((string)$context_type));
        $context_value = trim((string)$context_value);

        $allowed = array('all', 'home', 'route', 'category', 'product', 'manufacturer', 'information', 'search');
        if (!in_array($context_type, $allowed)) {
            $context_type = 'all';
        }

        $cache_key = 'dockercart_faq.context.' . $context_type . '.' . md5($context_value) . '.' . (int)$language_id . '.' . (int)$store_id;
        $faqs = $this->cache->get($cache_key);

        if ($faqs === null || $faqs === false) {
            $sql = "SELECT f.*, fd.question, fd.answer
                    FROM `" . DB_PREFIX . "dockercart_faq` f
                    INNER JOIN `" . DB_PREFIX . "dockercart_faq_description` fd ON (f.faq_id = fd.faq_id)
                    INNER JOIN `" . DB_PREFIX . "dockercart_faq_to_store` f2s ON (f.faq_id = f2s.faq_id)
                    WHERE f.status = '1'
                      AND f.show_widget = '1'
                      AND fd.language_id = '" . (int)$language_id . "'
                      AND f2s.store_id = '" . (int)$store_id . "'
                      AND (
                          f.context_type = 'all'
                          OR (f.context_type = '" . $this->db->escape($context_type) . "' AND (f.context_value = '' OR f.context_value = '" . $this->db->escape($context_value) . "'))";

            if ($context_type === 'home' || $context_type === 'search') {
                $sql .= " OR (f.context_type = '" . $this->db->escape($context_type) . "' AND f.context_value = '')";
            }

            if ($context_type === 'route') {
                $sql .= " OR (f.context_type = 'route' AND f.context_value = '" . $this->db->escape($context_value) . "')";
            }

            $sql .= ")
                    ORDER BY f.sort_order ASC, f.faq_id ASC";

            $faqs = $this->db->query($sql)->rows;
            $this->cache->set($cache_key, $faqs, 3600);
        }

        return $faqs;
    }
}
