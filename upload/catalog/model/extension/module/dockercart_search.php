<?php
/**
 * DockerCart Search Module - Catalog Model
 *
 * Handles search queries on frontend using Manticore
 *
 * @package    DockerCart
 * @subpackage Module
 * @author     DockerCart Official
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.3
 */

require_once DIR_SYSTEM . 'library/dockercart/manticore.php';
require_once DIR_SYSTEM . 'library/dockercart/keyboard_layout.php';

use Dockercart\KeyboardLayout;
use Dockercart\ManticoreClient;

class ModelExtensionModuleDockercartSearch extends Model {
    /**
     * Cache key for query mappings (shared with the admin model).
     */
    private const MAPPING_CACHE_KEY = 'dockercart.search.query_mappings';

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
            if (!empty($options['sub_category'])) {
                $filters['category_ids'] = $this->getAllDescendantCategoryIds((int)$options['category_id']);
            } else {
                $filters['category_id'] = (int)$options['category_id'];
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
     * Get all product IDs from Manticore search (without LIMIT).
     * Used for refine search category counts.
     */
    public function getAllProductIds($query_text, $options = []) {
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

        if (!empty($options['category_id'])) {
            if (!empty($options['sub_category'])) {
                $filters['category_ids'] = $this->getAllDescendantCategoryIds((int)$options['category_id']);
            } else {
                $filters['category_id'] = (int)$options['category_id'];
            }
        }

        $search_options = [
            'filters'  => $filters,
            'limit'    => 1000,
            'offset'   => 0,
            'ranker'   => 'proximity_bm25',
            'wildcard' => true,
        ];

        $result_data = $manticore->searchWithMeta('products', $query_text, $search_options);
        $raw_results = $result_data['results'];

        $product_ids = [];
        foreach ($raw_results as $result) {
            $product_id = (int)floor($result['id'] / 100);
            if ($product_id > 0) {
                $product_ids[] = $product_id;
            }
        }

        return $product_ids;
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
                $filters['category_ids'] = $this->getAllDescendantCategoryIds((int)$options['category_id']);
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
     * Get "did you mean" spell-correction suggestion when search yields no results.
     *
     * Uses Manticore CALL QSUGGEST against the products index and verifies that
     * the corrected query actually returns results for the current store/language,
     * so noisy infix fragments or words from other languages are filtered out.
     *
     * @param string $query_text Original search query
     * @param array  $options    Options (category_id, sub_category)
     * @return array|null ['text' => string] or null when no reliable correction
     */
    public function getSpellSuggestion($query_text, $options = []) {
        $query_text = $this->normalizeSearchQuery($query_text);

        if ($query_text === '') {
            return null;
        }

        $manticore = $this->getManticore();

        if (!$manticore->connect()) {
            return null;
        }

        $suggestions = $manticore->suggestCorrected('products', $query_text, [
            'limit'     => 3,
            'max_edits' => 2,
            'reject'    => 1,
        ]);

        if (empty($suggestions)) {
            return null;
        }

        // Prefer the closest correction with the most documents behind it.
        usort($suggestions, function ($a, $b) {
            return ($a['distance'] <=> $b['distance'])
                ?: ($b['docs'] <=> $a['docs']);
        });

        $query_lc = mb_strtolower($query_text, 'UTF-8');

        $search_options = [
            'limit'  => 1,
            'offset' => 0,
        ];

        if (!empty($options['category_id'])) {
            $search_options['category_id'] = (int)$options['category_id'];

            if (!empty($options['sub_category'])) {
                $search_options['sub_category'] = true;
            }
        }

        foreach ($suggestions as $suggestion) {
            $suggest = trim((string)$suggestion['suggest']);

            if ($suggest === '' || (int)$suggestion['distance'] < 1) {
                continue;
            }

            if (mb_strtolower($suggest, 'UTF-8') === $query_lc) {
                continue;
            }

            $result_data = $this->search($suggest, $search_options);

            if (!empty($result_data['total'])) {
                return ['text' => $suggest];
            }
        }

        return null;
    }

    /**
     * Get a keyboard-layout suggestion when search yields no results.
     *
     * Users sometimes type Cyrillic text while the keyboard is in the Latin
     * layout (or vice versa): "ghbdtn" instead of "привет". The query is
     * converted to the opposite layout and the converted query is verified
     * to actually return results for the current store/language, so noisy
     * conversions (e.g. "iphone" -> "ироуту") are filtered out.
     *
     * @param string $query_text Original search query
     * @param array  $options    Options (category_id, sub_category)
     * @return array|null ['text' => string] or null when no reliable conversion
     */
    public function getLayoutSuggestion($query_text, $options = []) {
        $query_text = $this->normalizeSearchQuery($query_text);

        if ($query_text === '') {
            return null;
        }

        $converted = KeyboardLayout::convert($query_text);

        if ($converted === '' || $converted === $query_text) {
            return null;
        }

        $search_options = [
            'limit'  => 1,
            'offset' => 0,
        ];

        if (!empty($options['category_id'])) {
            $search_options['category_id'] = (int)$options['category_id'];

            if (!empty($options['sub_category'])) {
                $search_options['sub_category'] = true;
            }
        }

        $result_data = $this->search($converted, $search_options);

        if (!empty($result_data['total'])) {
            return ['text' => $converted];
        }

        return null;
    }

    /**
     * Recursively collect all descendant category IDs (any depth).
     *
     * @param int $category_id
     * @return int[]
     */
    private function getAllDescendantCategoryIds(int $category_id): array {
        $this->load->model('catalog/category');
        $ids = [$category_id];
        $children = $this->model_catalog_category->getCategories($category_id);
        foreach ($children as $child) {
            $ids = array_merge($ids, $this->getAllDescendantCategoryIds((int)$child['category_id']));
        }
        return $ids;
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
     * Get query mappings (source lowercase => target).
     *
     * Reads from the shared cache (Redis), falls back to the dedicated
     * `search_query_mapping` table and finally to the legacy single-text
     * module setting (pre-migration installs). The admin side invalidates
     * the cache on every change.
     *
     * @return array<string,string>
     */
    private function getQueryMappings() {
        if ($this->query_mappings !== null) {
            return $this->query_mappings;
        }

        $this->query_mappings = [];

        $cached = $this->cache->get(self::MAPPING_CACHE_KEY);

        if (is_array($cached)) {
            $this->query_mappings = $cached;

            return $this->query_mappings;
        }

        $mappings = $this->getQueryMappingsFromDatabase();

        // Table unavailable (module installed before the migration) — fall back
        // to the legacy setting so search keeps working until the admin side
        // performs the one-time migration.
        if ($mappings === null) {
            $mappings = $this->getLegacyQueryMappingsFromConfig();
        }

        // Cache even an empty result to avoid a DB hit on every request
        $this->cache->set(self::MAPPING_CACHE_KEY, $mappings);

        $this->query_mappings = $mappings;

        return $this->query_mappings;
    }

    /**
     * Read query mappings from the dedicated table.
     *
     * @return array<string,string>|null null when the table is unavailable
     */
    private function getQueryMappingsFromDatabase() {
        try {
            $query = $this->db->query("SELECT `source`, `target` FROM `" . DB_PREFIX . "search_query_mapping`");

            $mappings = [];

            foreach ($query->rows as $row) {
                $mappings[mb_strtolower($row['source'], 'UTF-8')] = $row['target'];
            }

            return $mappings;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Parse query mappings from the legacy module setting.
     *
     * @return array<string,string> source(lowercase) => target
     */
    private function getLegacyQueryMappingsFromConfig() {
        $mappings = [];

        $raw = (string)$this->config->get('module_dockercart_search_query_mappings');
        if (trim($raw) === '') {
            return $mappings;
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

            $mappings[mb_strtolower($source, 'UTF-8')] = $target;
        }

        return $mappings;
    }
}
