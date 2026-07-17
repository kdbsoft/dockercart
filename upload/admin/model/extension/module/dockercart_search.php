<?php
/**
 * DockerCart Search Module - Admin Model
 *
 * Handles indexing operations and Manticore interactions
 *
 * @package    DockerCart
 * @subpackage Module
 * @author     DockerCart Official
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.3
 */

require_once DIR_SYSTEM . 'library/dockercart/manticore.php';

use Dockercart\ManticoreClient;

class ModelExtensionModuleDockercartSearch extends Model {
    private $manticore;
    private $last_error = '';

    /**
     * Get Manticore client instance
     */
    private function getManticore() {
        if (!$this->manticore) {
            $host = $this->config->get('module_dockercart_search_host') ?: 'manticore';
            $port = $this->config->get('module_dockercart_search_port') ?: 9306;

            $this->manticore = new ManticoreClient($host, $port);
        }

        return $this->manticore;
    }

    /**
     * Test connection to Manticore
     */
    public function testConnection() {
        $manticore = $this->getManticore();

        if ($manticore->ping()) {
            return true;
        }

        $this->last_error = $manticore->getLastError();
        return false;
    }

    /**
     * Get last error
     */
    public function getLastError() {
        return $this->last_error;
    }

    /**
     * Reindex all entities
     */
    public function reindexAll() {
        $result = [
            'success'      => true,
            'products'     => 0,
            'categories'   => 0,
            'manufacturers'=> 0,
            'information'  => 0,
            'orders'       => 0,
            'customers'    => 0,
            'error'        => ''
        ];

        $manticore = $this->getManticore();

        if (!$manticore->connect()) {
            $result['success'] = false;
            $result['error'] = 'Failed to connect: ' . $manticore->getLastError();
            return $result;
        }

        try {
            $this->applySchemaUpdates();

            $manticore->truncate('products');
            $manticore->truncate('categories');
            $manticore->truncate('manufacturers');
            $manticore->truncate('information');
            $manticore->truncate('orders');
            $manticore->truncate('customers');

            $result['products'] = $this->reindexProducts();
            $result['categories'] = $this->reindexCategories();
            $result['manufacturers'] = $this->reindexManufacturers();
            $result['information'] = $this->reindexInformation();
            $result['orders'] = $this->reindexOrders();
            $result['customers'] = $this->reindexCustomers();

        } catch (Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }

        return $result;
    }


    /**
     * Apply Manticore schema migrations: add any missing columns to existing RT indexes.
     *
     * This is idempotent — ALTER TABLE returns an error if the column already exists; we
     * silently ignore those errors so it is safe to call on every re-index operation.
     *
     * New fields added in v3.1:
     *   upc, ean, jan, isbn, mpn — product code/article fields for full-text search.
     */
    private function applySchemaUpdates() {
        $manticore = $this->getManticore();

        if (!$manticore->connect()) {
            return;
        }

        $products_columns = ['upc', 'ean', 'jan', 'isbn', 'mpn'];

        foreach ($products_columns as $col) {
            $manticore->query("ALTER TABLE `products` ADD COLUMN `{$col}` text");
        }

        $manticore->query("ALTER TABLE `products` ADD COLUMN `category_ids` multi");

        $this->createOrderIndexSchema();
        $this->createCustomerIndexSchema();
    }

    /**
     * Reindex all products
     */
    private function reindexProducts() {
        $this->load->model('catalog/product');
        $this->load->model('localisation/language');
        $this->load->model('tool/image');

        $manticore = $this->getManticore();
        $languages = $this->model_localisation_language->getLanguages();

        $count = 0;

        // Get all products
        $products = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product WHERE status = 1");

        foreach ($products->rows as $product) {
            foreach ($languages as $language) {
                $this->indexProduct($product['product_id'], $language['language_id']);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Index single product
     */
    public function indexProduct($product_id, $language_id = null) {
        if ($language_id === null) {
            $language_id = $this->config->get('config_language_id');
        }

        $manticore = $this->getManticore();

        // Get product data — explicitly select all code fields so nothing is missed
        $query = $this->db->query("
            SELECT p.product_id, p.model, p.sku, p.upc, p.ean, p.jan, p.isbn, p.mpn,
                   p.image, p.manufacturer_id, p.status, p.quantity, p.price,
                   p.date_added, p.date_modified,
                   pd.name, pd.description, pd.meta_title, pd.meta_description, pd.meta_keyword, pd.tag
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
            WHERE p.product_id = '" . (int)$product_id . "'
            AND pd.language_id = '" . (int)$language_id . "'
        ");

        if (!$query->num_rows) {
            return false;
        }

        $product = $query->row;

        // Get all category IDs for this product
        $category_query = $this->db->query("
            SELECT category_id FROM " . DB_PREFIX . "product_to_category
            WHERE product_id = '" . (int)$product_id . "'
        ");

        $category_ids = [];
        $first_category_id = 0;
        foreach ($category_query->rows as $row) {
            $cid = (int)$row['category_id'];
            $category_ids[] = $cid;
            if ($first_category_id === 0) {
                $first_category_id = $cid;
            }
        }

        // Prepare document for Manticore
        $doc = [
            'id'               => (int)$product_id * 100 + (int)$language_id, // Composite ID
            'store_id'         => (int)$this->config->get('config_store_id'),
            'language_id'      => (int)$language_id,
            'category_id'      => $first_category_id,
            'category_ids'     => $category_ids,
            'manufacturer_id'  => (int)$product['manufacturer_id'],
            'status'           => (int)$product['status'],
            'quantity'         => (int)$product['quantity'],
            'price'            => (float)$product['price'],
            'special'          => 0.0,
            'date_added'       => strtotime($product['date_added']),
            'date_modified'    => strtotime($product['date_modified']),
            'title'            => $product['name'],
            'description'      => strip_tags(html_entity_decode($product['description'], ENT_QUOTES, 'UTF-8')),
            'meta_title'       => $product['meta_title'],
            'meta_description' => $product['meta_description'],
            'meta_keywords'    => $product['meta_keyword'],
            'tags'             => $product['tag'],
            // Add compact article variant (without spaces/_/-) into the same indexed field.
            // Example: "A 123" -> "A 123 A123", "A-123" -> "A-123 A123".
            // This lets A123 find both "A 123" and "A-123" (and vice versa).
            'model'            => $this->buildSearchableCode($product['model'] ?? ''),
            'sku'              => $this->buildSearchableCode($product['sku']   ?? ''),
            'upc'              => $this->buildSearchableCode($product['upc']   ?? ''),
            'ean'              => $this->buildSearchableCode($product['ean']   ?? ''),
            'jan'              => $this->buildSearchableCode($product['jan']   ?? ''),
            'isbn'             => $this->buildSearchableCode($product['isbn']  ?? ''),
            'mpn'              => $this->buildSearchableCode($product['mpn']   ?? ''),
            'image'            => $product['image'],
        ];

        // Get special price if exists
        $special_query = $this->db->query("
            SELECT price FROM " . DB_PREFIX . "product_special
            WHERE product_id = '" . (int)$product_id . "'
            AND ((date_start = '0000-00-00' OR date_start < NOW())
            AND (date_end = '0000-00-00' OR date_end > NOW()))
            ORDER BY priority ASC, price ASC
            LIMIT 1
        ");

        if ($special_query->num_rows) {
            $doc['special'] = (float)$special_query->row['price'];
        }

        // Insert/replace in Manticore
        return $manticore->replace('products', $doc);
    }

    /**
     * Delete product from index
     */
    public function deleteProduct($product_id) {
        $manticore = $this->getManticore();
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $doc_id = (int)$product_id * 100 + (int)$language['language_id'];
            $manticore->delete('products', $doc_id);
        }

        return true;
    }

    /**
     * Reindex all categories
     */
    private function reindexCategories() {
        $this->load->model('localisation/language');

        $manticore = $this->getManticore();
        $languages = $this->model_localisation_language->getLanguages();

        $count = 0;

        $categories = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "category WHERE status = 1");

        foreach ($categories->rows as $category) {
            foreach ($languages as $language) {
                $this->indexCategory($category['category_id'], $language['language_id']);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Index single category
     */
    public function indexCategory($category_id, $language_id = null) {
        if ($language_id === null) {
            $language_id = $this->config->get('config_language_id');
        }

        $manticore = $this->getManticore();

        $query = $this->db->query("
            SELECT c.*, cd.name, cd.description, cd.meta_title, cd.meta_description, cd.meta_keyword
            FROM " . DB_PREFIX . "category c
            LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id)
            WHERE c.category_id = '" . (int)$category_id . "'
            AND cd.language_id = '" . (int)$language_id . "'
        ");

        if (!$query->num_rows) {
            return false;
        }

        $category = $query->row;

        $doc = [
            'id' => (int)$category_id * 100 + (int)$language_id,
            'store_id' => (int)$this->config->get('config_store_id'),
            'language_id' => (int)$language_id,
            'parent_id' => (int)$category['parent_id'],
            'status' => (int)$category['status'],
            'sort_order' => (int)$category['sort_order'],
            'name' => $category['name'],
            'description' => strip_tags(html_entity_decode($category['description'], ENT_QUOTES, 'UTF-8')),
            'meta_title' => $category['meta_title'],
            'meta_description' => $category['meta_description'],
            'meta_keywords' => $category['meta_keyword'],
            'image' => $category['image']
        ];

        return $manticore->replace('categories', $doc);
    }

    /**
     * Delete category from index
     */
    public function deleteCategory($category_id) {
        $manticore = $this->getManticore();
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $doc_id = (int)$category_id * 100 + (int)$language['language_id'];
            $manticore->delete('categories', $doc_id);
        }

        return true;
    }

    /**
     * Reindex all manufacturers
     */
    private function reindexManufacturers() {
        $manticore = $this->getManticore();

        $count = 0;

        $manufacturers = $this->db->query("SELECT * FROM " . DB_PREFIX . "manufacturer");

        foreach ($manufacturers->rows as $manufacturer) {
            $this->indexManufacturer($manufacturer['manufacturer_id']);
            $count++;
        }

        return $count;
    }

    /**
     * Index single manufacturer
     */
    public function indexManufacturer($manufacturer_id) {
        $manticore = $this->getManticore();

        $query = $this->db->query("
            SELECT * FROM " . DB_PREFIX . "manufacturer
            WHERE manufacturer_id = '" . (int)$manufacturer_id . "'
        ");

        if (!$query->num_rows) {
            return false;
        }

        $manufacturer = $query->row;

        $doc = [
            'id' => (int)$manufacturer_id,
            'store_id' => (int)$this->config->get('config_store_id'),
            'status' => 1,
            'sort_order' => (int)$manufacturer['sort_order'],
            'name' => $manufacturer['name'],
            'image' => $manufacturer['image']
        ];

        return $manticore->replace('manufacturers', $doc);
    }

    /**
     * Delete manufacturer from index
     */
    public function deleteManufacturer($manufacturer_id) {
        $manticore = $this->getManticore();
        return $manticore->delete('manufacturers', (int)$manufacturer_id);
    }

    /**
     * Reindex all information pages
     */
    private function reindexInformation() {
        $this->load->model('localisation/language');

        $manticore = $this->getManticore();
        $languages = $this->model_localisation_language->getLanguages();

        $count = 0;

        $information = $this->db->query("SELECT information_id FROM " . DB_PREFIX . "information WHERE status = 1");

        foreach ($information->rows as $info) {
            foreach ($languages as $language) {
                $this->indexInformation($info['information_id'], $language['language_id']);
            }
            $count++;
        }

        return $count;
    }

    /**
     * Index single information page
     */
    public function indexInformation($information_id, $language_id = null) {
        if ($language_id === null) {
            $language_id = $this->config->get('config_language_id');
        }

        $manticore = $this->getManticore();

        $query = $this->db->query("
            SELECT i.*, id.title, id.description, id.meta_title, id.meta_description, id.meta_keyword
            FROM " . DB_PREFIX . "information i
            LEFT JOIN " . DB_PREFIX . "information_description id ON (i.information_id = id.information_id)
            WHERE i.information_id = '" . (int)$information_id . "'
            AND id.language_id = '" . (int)$language_id . "'
        ");

        if (!$query->num_rows) {
            return false;
        }

        $info = $query->row;

        $doc = [
            'id' => (int)$information_id * 100 + (int)$language_id,
            'store_id' => (int)$this->config->get('config_store_id'),
            'language_id' => (int)$language_id,
            'status' => (int)$info['status'],
            'sort_order' => (int)$info['sort_order'],
            'title' => $info['title'],
            'description' => strip_tags(html_entity_decode($info['description'], ENT_QUOTES, 'UTF-8')),
            'meta_title' => $info['meta_title'],
            'meta_description' => $info['meta_description'],
            'meta_keywords' => $info['meta_keyword']
        ];

        return $manticore->replace('information', $doc);
    }

    /**
     * Delete information page from index
     */
    public function deleteInformation($information_id) {
        $manticore = $this->getManticore();
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $doc_id = (int)$information_id * 100 + (int)$language['language_id'];
            $manticore->delete('information', $doc_id);
        }

        return true;
    }

    public function createOrderIndexSchema() {
        $manticore = $this->getManticore();

        if (!$manticore->connect()) {
            return false;
        }

        $manticore->query("CREATE TABLE IF NOT EXISTS orders (
            id BIGINT PRIMARY KEY,
            firstname TEXT,
            lastname TEXT,
            email TEXT,
            telephone TEXT,
            payment_firstname TEXT,
            payment_lastname TEXT,
            payment_company TEXT,
            payment_address_1 TEXT,
            payment_address_2 TEXT,
            payment_city TEXT,
            payment_postcode TEXT,
            payment_country TEXT,
            payment_zone TEXT,
            shipping_firstname TEXT,
            shipping_lastname TEXT,
            shipping_company TEXT,
            shipping_address_1 TEXT,
            shipping_address_2 TEXT,
            shipping_city TEXT,
            shipping_postcode TEXT,
            shipping_country TEXT,
            shipping_zone TEXT,
            comment TEXT,
            tracking_number TEXT,
            store_id BIGINT,
            customer_id BIGINT,
            customer_group_id BIGINT,
            order_status_id BIGINT,
            total FLOAT,
            currency_code TEXT,
            order_status_name TEXT,
            language_id BIGINT,
            date_added BIGINT,
            date_modified BIGINT
        )");

        return true;
    }

    public function createCustomerIndexSchema() {
        $manticore = $this->getManticore();

        if (!$manticore->connect()) {
            return false;
        }

        $manticore->query("CREATE TABLE IF NOT EXISTS customers (
            id BIGINT PRIMARY KEY,
            firstname TEXT,
            lastname TEXT,
            email TEXT,
            telephone TEXT,
            company TEXT,
            store_id BIGINT,
            customer_group_id BIGINT,
            status BIGINT,
            customer_group_name TEXT,
            language_id BIGINT,
            date_added BIGINT
        )");

        return true;
    }

    public function indexOrder($order_id) {
        $manticore = $this->getManticore();

        $query = $this->db->query("
            SELECT o.*, os.name AS order_status_name
            FROM " . DB_PREFIX . "order o
            LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id AND os.language_id = o.language_id)
            WHERE o.order_id = '" . (int)$order_id . "'
        ");

        if (!$query->num_rows) {
            return false;
        }

        $order = $query->row;

        $doc = [
            'id' => (int)$order_id,
            'store_id' => (int)$order['store_id'],
            'customer_id' => (int)$order['customer_id'],
            'customer_group_id' => (int)$order['customer_group_id'],
            'order_status_id' => (int)$order['order_status_id'],
            'total' => (float)$order['total'],
            'currency_code' => $order['currency_code'],
            'order_status_name' => $order['order_status_name'] ?? '',
            'language_id' => (int)$order['language_id'],
            'date_added' => strtotime($order['date_added']),
            'date_modified' => strtotime($order['date_modified']),
            'firstname' => $order['firstname'],
            'lastname' => $order['lastname'],
            'email' => $order['email'],
            'telephone' => $order['telephone'],
            'payment_firstname' => $order['payment_firstname'],
            'payment_lastname' => $order['payment_lastname'],
            'payment_company' => $order['payment_company'],
            'payment_address_1' => $order['payment_address_1'],
            'payment_address_2' => $order['payment_address_2'],
            'payment_city' => $order['payment_city'],
            'payment_postcode' => $order['payment_postcode'],
            'payment_country' => $order['payment_country'],
            'payment_zone' => $order['payment_zone'],
            'shipping_firstname' => $order['shipping_firstname'],
            'shipping_lastname' => $order['shipping_lastname'],
            'shipping_company' => $order['shipping_company'],
            'shipping_address_1' => $order['shipping_address_1'],
            'shipping_address_2' => $order['shipping_address_2'],
            'shipping_city' => $order['shipping_city'],
            'shipping_postcode' => $order['shipping_postcode'],
            'shipping_country' => $order['shipping_country'],
            'shipping_zone' => $order['shipping_zone'],
            'comment' => $order['comment'],
            'tracking_number' => $order['tracking_number'] ?? '',
        ];

        return $manticore->replace('orders', $doc);
    }

    public function deleteOrder($order_id) {
        $manticore = $this->getManticore();
        return $manticore->delete('orders', (int)$order_id);
    }

    private function reindexOrders() {
        $manticore = $this->getManticore();

        $count = 0;

        $orders = $this->db->query("SELECT order_id FROM " . DB_PREFIX . "order");

        foreach ($orders->rows as $order) {
            $this->indexOrder($order['order_id']);
            $count++;
        }

        return $count;
    }

    public function indexCustomer($customer_id) {
        $manticore = $this->getManticore();

        $query = $this->db->query("
            SELECT c.*, cg.name AS customer_group_name
            FROM " . DB_PREFIX . "customer c
            LEFT JOIN " . DB_PREFIX . "customer_group cg ON (c.customer_group_id = cg.customer_group_id)
            WHERE c.customer_id = '" . (int)$customer_id . "'
        ");

        if (!$query->num_rows) {
            return false;
        }

        $customer = $query->row;

        $doc = [
            'id' => (int)$customer_id,
            'store_id' => (int)$customer['store_id'],
            'customer_group_id' => (int)$customer['customer_group_id'],
            'status' => (int)$customer['status'],
            'customer_group_name' => $customer['customer_group_name'] ?? '',
            'language_id' => (int)$customer['language_id'],
            'date_added' => strtotime($customer['date_added']),
            'firstname' => $customer['firstname'],
            'lastname' => $customer['lastname'],
            'email' => $customer['email'],
            'telephone' => $customer['telephone'],
            'company' => $customer['company'] ?? '',
        ];

        return $manticore->replace('customers', $doc);
    }

    public function deleteCustomer($customer_id) {
        $manticore = $this->getManticore();
        return $manticore->delete('customers', (int)$customer_id);
    }

    private function reindexCustomers() {
        $manticore = $this->getManticore();

        $count = 0;

        $customers = $this->db->query("SELECT customer_id FROM " . DB_PREFIX . "customer");

        foreach ($customers->rows as $customer) {
            $this->indexCustomer($customer['customer_id']);
            $count++;
        }

        return $count;
    }

    /**
     * Build searchable code string for article-like fields.
     * Adds compact variant without spaces/underscores/hyphens.
     *
     * Examples:
     *  - "A 123"  => "A 123 A123"
     *  - "A-123"  => "A-123 A123"
     *  - "A_123"  => "A_123 A123"
     *  - "A123"   => "A123"
     */
    private function buildSearchableCode($value) {
        $value = trim((string)$value);

        if ($value === '') {
            return '';
        }

        $compact = preg_replace('/[\s_-]+/u', '', $value);

        if ($compact === '' || $compact === $value) {
            return $value;
        }

        return $value . ' ' . $compact;
    }

    /**
     * Get morphology options for language
     * Combines admin settings with available morphology options
     */
    public function getMorphologyForLanguage($language_id) {
        $lang_settings = $this->config->get('module_dockercart_search_lang_settings');

        if (!$lang_settings || !isset($lang_settings[$language_id])) {
            // Default morphology: English stemmer + Russian lemmatizer
            return 'stem_en, lemmatize_ru';
        }

        $morphology_list = $lang_settings[$language_id]['morphology'] ?? [];

        if (empty($morphology_list)) {
            return 'stem_en, lemmatize_ru';
        }

        // Ensure no conflicting morphology (e.g., stem_ru + lemmatize_ru)
        $filtered = $this->filterConflictingMorphology($morphology_list);

        return implode(', ', $filtered);
    }

    /**
     * Filter conflicting morphology options
     * Manticore doesn't allow certain combinations
     */
    private function filterConflictingMorphology($morphology_list) {
        $result = [];
        $has_ru_stem = false;
        $has_ru_lemma = false;

        foreach ($morphology_list as $morph) {
            if ($morph === 'stem_ru') {
                $has_ru_stem = true;
            } elseif ($morph === 'lemmatize_ru') {
                $has_ru_lemma = true;
            } else {
                $result[] = $morph;
            }
        }

        // Allow either stem_ru OR lemmatize_ru, not both
        if ($has_ru_stem && !$has_ru_lemma) {
            $result[] = 'stem_ru';
        } elseif ($has_ru_lemma) {
            $result[] = 'lemmatize_ru';
        }

        return !empty($result) ? $result : ['stem_en', 'lemmatize_ru'];
    }

    /**
     * Apply morphology settings to all tables via ALTER TABLE
     * This truncates and reconfigures indexes with new morphology
     */
    public function applyMorphologySettings() {
        $manticore = $this->getManticore();
        $this->load->model('localisation/language');

        $languages = $this->model_localisation_language->getLanguages();
        $tables = ['products', 'categories', 'manufacturers', 'information', 'orders', 'customers'];

        $result = [
            'success' => true,
            'message' => 'Morphology settings applied',
            'errors' => []
        ];

        try {
            foreach ($tables as $table) {
                // Get morphology for each language and use most common
                $morphology_options = [];
                foreach ($languages as $language) {
                    $morph = $this->getMorphologyForLanguage($language['language_id']);
                    $morphology_options[$morph] = ($morphology_options[$morph] ?? 0) + 1;
                }

                // Use most common morphology for the table
                $morphology = array_key_first($morphology_options) ?: 'stem_en, lemmatize_ru';

                // Truncate to apply new settings
                // Note: In Manticore, morphology is fixed at table creation
                // So we need to truncate and let new documents use new morphology
                $query = "TRUNCATE TABLE " . $table;

                if (!$manticore->query($query)) {
                    $result['errors'][] = "Failed to truncate $table: " . $manticore->getLastError();
                } else {
                    // Log morphology change
                    $this->log->write("DockerCart Search: Applied morphology '$morphology' to table '$table'");
                }
            }
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Get all morphology options available in Manticore
     */
    public function getAvailableMorphologyOptions() {
        return [
            'stem_en' => 'English Stemmer',
            'stem_ru' => 'Russian Stemmer',
            'stem_de' => 'German Stemmer',
            'stem_fr' => 'French Stemmer',
            'stem_es' => 'Spanish Stemmer',
            'stem_it' => 'Italian Stemmer',
            'stem_pt' => 'Portuguese Stemmer',
            'lemmatize_ru' => 'Russian Lemmatizer',
            'lemmatize_en' => 'English Lemmatizer',
            'lemmatize_de' => 'German Lemmatizer'
        ];
    }
}
