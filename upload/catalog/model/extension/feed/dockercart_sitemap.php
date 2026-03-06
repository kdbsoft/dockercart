<?php
class ModelExtensionFeedDockercartSitemap extends Model {



    public function getProducts($language_id) {
        $query = $this->db->query("
            SELECT
                p.product_id,
                pd.name,
                p.date_modified
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_description pd
                ON (p.product_id = pd.product_id)
            LEFT JOIN " . DB_PREFIX . "product_to_store p2s
                ON (p.product_id = p2s.product_id)
            WHERE p.status = '1'
                AND pd.language_id = '" . (int)$language_id . "'
                AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
            ORDER BY p.product_id ASC
        ");

        return $query->rows;
    }



    public function getCategories($language_id) {
        $query = $this->db->query("
            SELECT
                c.category_id,
                cd.name,
                c.date_modified
            FROM " . DB_PREFIX . "category c
            LEFT JOIN " . DB_PREFIX . "category_description cd
                ON (c.category_id = cd.category_id)
            LEFT JOIN " . DB_PREFIX . "category_to_store c2s
                ON (c.category_id = c2s.category_id)
            WHERE c.status = '1'
                AND cd.language_id = '" . (int)$language_id . "'
                AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
            ORDER BY c.category_id ASC
        ");

        return $query->rows;
    }



    public function getManufacturers() {
        $query = $this->db->query("
            SELECT
                manufacturer_id,
                name
            FROM " . DB_PREFIX . "manufacturer
            ORDER BY manufacturer_id ASC
        ");

        return $query->rows;
    }



    public function getInformation($language_id) {

        $query = $this->db->query("
            SELECT
                i.information_id,
                id.title
            FROM " . DB_PREFIX . "information i
            LEFT JOIN " . DB_PREFIX . "information_description id
                ON (i.information_id = id.information_id)
            LEFT JOIN " . DB_PREFIX . "information_to_store i2s
                ON (i.information_id = i2s.information_id)
            WHERE i.status = '1'
                AND id.language_id = '" . (int)$language_id . "'
                AND i2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
            ORDER BY i.information_id ASC
        ");

        return $query->rows;
    }



    public function getLanguages() {
        $query = $this->db->query("
            SELECT *
            FROM " . DB_PREFIX . "language
            WHERE status = '1'
            ORDER BY sort_order, name
        ");

        return $query->rows;
    }



    public function countUrls($language_id, $settings) {
        $total = 1;

        if (!empty($settings['dockercart_sitemap_products'])) {
            $products = $this->getProducts($language_id);
            $total += count($products);
        }

        if (!empty($settings['dockercart_sitemap_categories'])) {
            $categories = $this->getCategories($language_id);
            $total += count($categories);
        }

        if (!empty($settings['dockercart_sitemap_manufacturers'])) {
            $manufacturers = $this->getManufacturers();
            $total += count($manufacturers);
        }

        if (!empty($settings['dockercart_sitemap_information'])) {
            $information = $this->getInformation($language_id);
            $total += count($information);
        }

        return $total;
    }
}
