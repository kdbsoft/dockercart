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
            // Out-of-stock products go to the end of search results
            // (uses the out_of_stock attribute on the products index).
            'stock_last' => true,
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
                    // When the query matched a specific variant code, resolve the
                    // variant and expose it so the product card links straight to
                    // that variant (instead of the default one).
                    $variant = $this->resolveVariantByQuery($product_id, $query_text);

                    if ($variant !== null) {
                        $product['matched_variant_id'] = (int)$variant['variant_id'];
                        $product['matched_variant_model'] = !empty($variant['model']) ? $variant['model'] : ($variant['sku'] ?? '');
                    }

                    $products[] = $product;
                }
            }

            // Hydrated via getProduct() (per-product cache), which does not
            // carry the rating distribution used by listing-card popovers.
            $products = $this->attachRatingDistribution($products);
        }

        return [
            'products' => $products,
            'total'    => $total,
        ];
    }

    /**
     * Resolve the matched variant for a list of products in one pass.
     *
     * @param int[]  $product_ids
     * @param string $query
     * @return array<int, array{variant_id: int, model: string}> Keyed by product_id
     */
    public function resolveVariantsForProducts(array $product_ids, $query) {
        $map = array();

        if (empty($product_ids) || trim((string)$query) === '') {
            return $map;
        }

        foreach (array_unique(array_map('intval', $product_ids)) as $product_id) {
            $variant = $this->resolveVariantByQuery($product_id, $query);

            if ($variant !== null) {
                $map[$product_id] = array(
                    'variant_id' => (int)$variant['variant_id'],
                    'model'      => !empty($variant['model']) ? $variant['model'] : ($variant['sku'] ?? ''),
                );
            }
        }

        return $map;
    }

    /**
     * Resolve the variant of a product whose code (model/sku/upc/ean/jan/isbn/mpn)
     * exactly matches the search query.
     *
     * Manticore matches fulltext fields with infix wildcards, so a query like
     * "DEMO-5021-BLK-L" can match the product while the matched term came from a
     * variant code. This method finds the exact variant so the product card can
     * deep-link to it instead of the default one.
     *
     * Matching is case-insensitive; when no exact code matches, the compact form
     * (separators removed, e.g. "DEMO5021BLKL") is compared too, mirroring the
     * "compact variant" search branch in Manticore.
     *
     * @param int    $product_id
     * @param string $query
     * @return array|null Variant row (with `variant_id`, `name`, `sku`, ...) or null
     */
    private function resolveVariantByQuery($product_id, $query) {
        $query = trim((string)$query);

        if ($query === '') {
            return null;
        }

        $this->load->model('catalog/product');

        $pc = new \ProductConfigurable($this->registry);
        $configurable = $pc->getConfigurable($product_id);

        if (empty($configurable['is_configurable'])) {
            return null;
        }

        $variants = $pc->getVariants($product_id);

        if (empty($variants)) {
            return null;
        }

        $needle = mb_strtolower($query, 'UTF-8');
        $needle_compact = preg_replace('/[\s_-]+/u', '', $needle);

        foreach ($variants as $variant) {
            if (empty($variant['status'])) {
                continue;
            }

            $codes = array(
                $variant['model'] ?? '',
                $variant['sku']   ?? '',
                $variant['upc']   ?? '',
                $variant['ean']   ?? '',
                $variant['jan']   ?? '',
                $variant['isbn']  ?? '',
                $variant['mpn']   ?? '',
            );

            foreach ($codes as $code) {
                $code = trim((string)$code);

                if ($code === '') {
                    continue;
                }

                $code_lc = mb_strtolower($code, 'UTF-8');

                if ($code_lc === $needle || preg_replace('/[\s_-]+/u', '', $code_lc) === $needle_compact) {
                    return $variant;
                }
            }
        }

        // No article-code match: try matching the query against the variant option-value
        // names (e.g. "White", "128 GB", size "M"). A token matches either the product title
        // (product-name tokens are ignored) or a variant value name (infix, case-insensitive,
        // compact-form aware: "128GB" matches "128 GB"). A variant is a candidate only when
        // EVERY token matches somewhere AND at least one token matched a value name — a bare
        // product-name query ("Google Pixel 8 Pro") must not deep-link to an arbitrary variant.
        $this->load->model('catalog/product');

        $title = '';

        $title_query = $this->db->query("
            SELECT name FROM " . DB_PREFIX . "product_description
            WHERE product_id = '" . (int)$product_id . "'
            AND language_id = '" . (int)$this->config->get('config_language_id') . "'
            LIMIT 1
        ");

        if ($title_query->num_rows) {
            $title = mb_strtolower($title_query->row['name'], 'UTF-8');
        }

        // Token significance: keep tokens of length >= 2 and pure digits (e.g. "128"),
        // plus single letters that are NOT part of the product title — those are variant
        // values like the size "M" in "White / M". Single letters that occur in the title
        // ("a", "и") are noise and are dropped.
        $value_tokens = array_values(array_filter($this->splitQueryTokens($query), function ($t) use ($title) {
            $len = mb_strlen($t, 'UTF-8');

            if ($len >= 2 || ctype_digit($t)) {
                return true;
            }

            return $len === 1 && ctype_alpha($t) && ($title === '' || mb_strpos($title, mb_strtolower($t, 'UTF-8')) === false);
        }));

        if (count($value_tokens) > 0) {

            $best_variant   = null;
            $best_score     = 0;

            foreach ($variants as $variant) {
                if (empty($variant['status'])) {
                    continue;
                }

                $names = [];

                if (!empty($variant['values']) && is_array($variant['values'])) {
                    foreach ($variant['values'] as $value) {
                        if (!empty($value['name'])) {
                            $names[] = (string)$value['name'];
                        }
                    }
                }

                if (empty($names)) {
                    continue;
                }

                $score = 0;
                $all_match = true;

                foreach ($value_tokens as $token) {
                    $token_lc = mb_strtolower($token, 'UTF-8');
                    $token_compact = $this->compactLabelToken($token_lc);

                    // Product-name token — always satisfied, never scores (a bare
                    // product-name query must not deep-link to an arbitrary variant).
                    if ($title !== '' && mb_strpos($title, $token_lc) !== false) {
                        continue;
                    }

                    $token_matched = false;

                    foreach ($names as $name) {
                        $name_lc = mb_strtolower($name, 'UTF-8');

                        if (mb_strpos($name_lc, $token_lc) !== false || ($token_compact !== '' && mb_strpos($this->compactLabelToken($name_lc), $token_compact) !== false)) {
                            $token_matched = true;
                            break;
                        }
                    }

                    if ($token_matched) {
                        $score++;
                    } else {
                        $all_match = false;
                        break;
                    }
                }

                // Require at least one non-title token to have matched a value name.
                if ($all_match && $score > 0 && $score > $best_score) {
                    $best_score   = $score;
                    $best_variant = $variant;
                }
            }

            if ($best_variant !== null) {
                return $best_variant;
            }
        }

        return null;
    }

    /**
     * Split a query into word tokens, dropping pure-punctuation tokens ("—", "/", "-").
     *
     * @param string $query
     * @return string[]
     */
    private function splitQueryTokens($query) {
        $tokens = preg_split('/\s+/u', trim((string)$query), -1, PREG_SPLIT_NO_EMPTY);

        if (!$tokens) {
            return [];
        }

        return array_values(array_filter($tokens, function ($t) {
            return (bool)preg_match('/[\p{L}\p{N}]/u', (string)$t);
        }));
    }

    /**
     * Compact a label token for separator-insensitive matching ("128 GB" -> "128gb").
     *
     * @param string $value
     * @return string
     */
    private function compactLabelToken($value) {
        return preg_replace('/[\s_\/\x{2014}\x{2013}]+/u', '', (string)$value);
    }

    /**
     * Attach per-star rating distribution from oc_product_rating to a list
     * of hydrated products, mirroring what getProductsByIds() does for
     * native (MySQL) listings so search cards render the same popover.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function attachRatingDistribution(array $products): array {
        $ids = [];

        foreach ($products as $product) {
            if (isset($product['product_id'])) {
                $ids[] = (int)$product['product_id'];
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return $products;
        }

        $query = $this->db->query("SELECT product_id, distribution FROM " . DB_PREFIX . "product_rating WHERE product_id IN (" . implode(',', $ids) . ") AND review_count > 0");

        $distributions = [];

        foreach ($query->rows as $row) {
            $distributions[(int)$row['product_id']] = $row['distribution'];
        }

        foreach ($products as &$product) {
            $pid = isset($product['product_id']) ? (int)$product['product_id'] : 0;

            if ($pid && isset($distributions[$pid])) {
                $decoded = json_decode((string)$distributions[$pid], true);

                if (is_array($decoded)) {
                    $product['rating_distribution'] = $decoded;
                }
            }

            if (!isset($product['rating_distribution'])) {
                $product['rating_distribution'] = [];
            }
        }

        unset($product);

        return $products;
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
                // Resolve the exact variant when the query matches a variant code
                // so the autocomplete dropdown deep-links to that variant, shows
                // its code and prices it correctly.
                $variant = $this->resolveVariantByQuery($product_id, $query_text);

                $price = $product['price'];
                $special = $product['special'];

                if ($variant !== null) {
                    // Price/special of the matched variant via the shared
                    // calculator (same formula as the cart and product page).
                    // The calculator returns prices in the product's own currency,
                    // so normalise them to the store base currency (which is what
                    // product cards/format() expect).
                    $calculator = new \ProductPricingCalculator($this->registry);
                    $pricing = $calculator->calculate((int)$product_id, (int)$variant['variant_id'], 1);
                    $currency_id = isset($product['currency_id']) ? (int)$product['currency_id'] : 0;

                    if (!empty($pricing['price'])) {
                        $price = (float)$this->currency->convertProductPrice($pricing['price'], $currency_id);
                    }
                    $special = $pricing['special'] !== null ? $this->currency->convertProductPrice($pricing['special'], $currency_id) : null;
                }

                $products[] = [
                    'product_id'  => $product['product_id'],
                    'name'        => $product['name'],
                    'model'       => $product['model'],
                    'image'       => $product['image'],
                    'price'       => $price,
                    'special'     => $special,
                    'tax_class_id'=> $product['tax_class_id'],
                    'call_for_price' => !empty($product['call_for_price']),
                    'matched_variant_id' => $variant !== null ? (int)$variant['variant_id'] : 0,
                    'matched_variant_model' => $variant !== null ? ($variant['model'] ?: $variant['sku']) : '',
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
