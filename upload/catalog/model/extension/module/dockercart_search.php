<?php
/**
 * DockerCart Search Module - Catalog Model
 * 
 * Handles search queries on frontend using Manticore
 * 
 * @package    DockerCart
 * @subpackage Module
 * @author     DockerCart Team
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.3
 */

require_once DIR_SYSTEM . 'library/dockercart/manticore.php';

use Dockercart\ManticoreClient;

class ModelExtensionModuleDockercartSearch extends Model {
    private $manticore;
    private $query_mappings = null;
    
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
     * Search products using Manticore.
     * Uses wildcard (query | query*) so results are identical to autocomplete.
     */
    public function search($query_text, $options = []) {
        $query_text = $this->normalizeSearchQuery($query_text);

        if ($query_text === '') {
            return ['products' => [], 'total' => 0];
        }

        $manticore = $this->getManticore();
        
        if (!$manticore->connect()) {
            return ['products' => [], 'total' => 0];
        }
        
        // Prepare filters
        $filters = [
            'store_id'    => (int)$this->config->get('config_store_id'),
            'language_id' => (int)$this->config->get('config_language_id'),
            'status'      => 1
        ];
        
        // Add category filter
        if (!empty($options['category_id'])) {
            $filters['category_id'] = (int)$options['category_id'];
            
            // Handle sub-categories
            if (!empty($options['sub_category'])) {
                $this->load->model('catalog/category');
                $categories = $this->model_catalog_category->getCategories($options['category_id']);
                
                $category_ids = [(int)$options['category_id']];
                foreach ($categories as $category) {
                    $category_ids[] = (int)$category['category_id'];
                }
                
                $filters['category_id'] = $category_ids;
            }
        }
        
        // Build search options.
        // wildcard=true makes the engine use (query | query*) so results are 100% consistent
        // between the autocomplete dropdown and the search results page.
        $search_options = [
            'filters'  => $filters,
            'limit'    => $options['limit']  ?? 20,
            'offset'   => $options['offset'] ?? 0,
            'ranker'   => 'proximity_bm25',
            'wildcard' => true,
        ];
        
        // Add sorting
        if (!empty($options['sort'])) {
            switch ($options['sort']) {
                case 'price_asc':
                    $search_options['sort']  = 'price';
                    $search_options['order'] = 'ASC';
                    break;
                case 'price_desc':
                    $search_options['sort']  = 'price';
                    $search_options['order'] = 'DESC';
                    break;
                case 'name_asc':
                    $search_options['sort']  = 'title';
                    $search_options['order'] = 'ASC';
                    break;
                case 'name_desc':
                    $search_options['sort']  = 'title';
                    $search_options['order'] = 'DESC';
                    break;
                case 'date_desc':
                    $search_options['sort']  = 'date_added';
                    $search_options['order'] = 'DESC';
                    break;
                default:
                    // Relevance (default — no explicit sort)
                    break;
            }
        }
        
        // Perform search and get real total_found for pagination
        $result_data = $manticore->searchWithMeta('products', $query_text, $search_options);
        $raw_results = $result_data['results'];
        $total       = $result_data['total'];
        
        // Extract product IDs (composite ID = product_id * 100 + language_id)
        $product_ids = [];
        foreach ($raw_results as $result) {
            $product_id = (int)floor($result['id'] / 100);
            if ($product_id > 0) {
                $product_ids[] = $product_id;
            }
        }
        
        // Get full product data from DockerCart
        $products = [];
        if (!empty($product_ids)) {
            $this->load->model('catalog/product');
            
            foreach ($product_ids as $product_id) {
                $product = $this->model_catalog_product->getProduct($product_id);
                if ($product) {
                    $products[] = $product;
                }
            }
        }
        
        return [
            'products' => $products,
            'total'    => $total,
        ];
    }
    
    /**
     * Get autocomplete suggestions.
     * Uses the same Manticore query as search() (wildcard=true) so the autocomplete
     * dropdown shows exactly the same products that will appear on the search page.
     */
    public function suggest($query_text, $options = []) {
        $query_text = $this->normalizeSearchQuery($query_text);

        if ($query_text === '') {
            return [];
        }

        $manticore = $this->getManticore();
        
        if (!$manticore->connect()) {
            return [];
        }
        
        $filters = [
            'store_id'    => (int)$this->config->get('config_store_id'),
            'language_id' => (int)$this->config->get('config_language_id'),
            'status'      => 1
        ];

        // Apply category filter when searching within a specific category
        if (!empty($options['category_id'])) {
            if (!empty($options['sub_category'])) {
                $this->load->model('catalog/category');
                $sub_cats     = $this->model_catalog_category->getCategories((int)$options['category_id']);
                $category_ids = [(int)$options['category_id']];
                foreach ($sub_cats as $sub) {
                    $category_ids[] = (int)$sub['category_id'];
                }
                $filters['category_id'] = $category_ids;
            } else {
                $filters['category_id'] = (int)$options['category_id'];
            }
        }
        
        // Use search() with wildcard — identical query engine to the search page
        $search_options = [
            'filters'  => $filters,
            'limit'    => $options['limit'] ?? 10,
            'offset'   => 0,
            'wildcard' => true,
        ];
        
        $result_data = $manticore->searchWithMeta('products', $query_text, $search_options);
        $raw_results = $result_data['results'];
        
        // Get full product data
        $products = [];
        
        $this->load->model('catalog/product');
        
        foreach ($raw_results as $result) {
            $product_id = (int)floor($result['id'] / 100);
            if ($product_id <= 0) {
                continue;
            }
            
            $product = $this->model_catalog_product->getProduct($product_id);
            
            if ($product) {
                $products[] = [
                    'product_id'  => $product['product_id'],
                    'name'        => $product['name'],
                    'model'       => $product['model'],
                    'image'       => $product['image'],
                    'price'       => $product['price'],
                    'special'     => $product['special'],
                    'tax_class_id'=> $product['tax_class_id'],
                ];
            }
        }
        
        return $products;
    }
    
    /**
     * Search in categories
     */
    public function searchCategories($query_text, $options = []) {
        $query_text = $this->normalizeSearchQuery($query_text);

        if ($query_text === '') {
            return [];
        }

        $manticore = $this->getManticore();
        
        if (!$manticore->connect()) {
            return [];
        }
        
        $filters = [
            'store_id' => (int)$this->config->get('config_store_id'),
            'language_id' => (int)$this->config->get('config_language_id'),
            'status' => 1
        ];
        
        $search_options = [
            'filters' => $filters,
            'limit' => $options['limit'] ?? 10
        ];
        
        // Use suggest() to get prefix wildcard matching (noteboo* → notebook)
        $results = $manticore->suggest('categories', $query_text, $search_options);
        
        $categories = [];
        foreach ($results as $result) {
            $category_id = floor($result['id'] / 100);
            
            $this->load->model('catalog/category');
            $category = $this->model_catalog_category->getCategory($category_id);
            
            if ($category) {
                $categories[] = $category;
            }
        }
        
        return $categories;
    }

    /**
     * Normalize query with admin-defined mappings.
     *
     * Supports one mapping per line in either format:
     *   source=target
     *   source=>target
     */
    public function normalizeSearchQuery($query_text) {
        $query_text = trim((string)$query_text);

        if ($query_text === '') {
            return '';
        }

        $mappings = $this->getQueryMappings();

        if (empty($mappings)) {
            return $query_text;
        }

        $query_text = preg_replace('/\s+/u', ' ', $query_text);
        $query_lc = mb_strtolower($query_text, 'UTF-8');

        // Exact full-phrase mapping has top priority.
        if (isset($mappings[$query_lc])) {
            return $mappings[$query_lc];
        }

        // Apply boundary-aware replacements, longest source first.
        $sources = array_keys($mappings);
        usort($sources, function($a, $b) {
            return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
        });

        $result = $query_text;

        foreach ($sources as $source) {
            $target = $mappings[$source];
            $pattern = '/(?<![\\p{L}\\p{N}_])' . preg_quote($source, '/') . '(?![\\p{L}\\p{N}_])/ui';
            $result = preg_replace($pattern, $target, $result);
        }

        return trim((string)preg_replace('/\s+/u', ' ', (string)$result));
    }

    /**
     * Parse query mappings from module settings.
     *
     * @return array<string,string> source(lowercase) => target
     */
    private function getQueryMappings() {
        if ($this->query_mappings !== null) {
            return $this->query_mappings;
        }

        $this->query_mappings = [];

        $raw = (string)$this->config->get('module_dockercart_search_query_mappings');
        if (trim($raw) === '') {
            return $this->query_mappings;
        }

        $lines = preg_split('/\R/u', $raw);

        foreach ($lines as $line) {
            $line = trim((string)$line);

            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '//') === 0) {
                continue;
            }

            if (strpos($line, '=>') !== false) {
                $parts = explode('=>', $line, 2);
            } elseif (strpos($line, '=') !== false) {
                $parts = explode('=', $line, 2);
            } else {
                continue;
            }

            $source = trim((string)$parts[0]);
            $target = trim((string)$parts[1]);

            if ($source === '' || $target === '') {
                continue;
            }

            $this->query_mappings[mb_strtolower($source, 'UTF-8')] = $target;
        }

        return $this->query_mappings;
    }
}
