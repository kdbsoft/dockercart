<?php
/**
 * DockerCart Search Module - Catalog Controller
 * 
 * Handles search requests and autocomplete on frontend
 * 
 * @package    DockerCart
 * @subpackage Module
 * @author     DockerCart Team
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.3
 */

class ControllerExtensionModuleDockercartSearch extends Controller {
    
    /**
     * Autocomplete suggestions (AJAX endpoint)
     * Returns products, categories and manufacturers grouped by type.
     */
    public function suggest() {
        $json = [];

        if (!$this->config->get('module_dockercart_search_status')) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!$this->config->get('module_dockercart_search_autocomplete')) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $query     = isset($this->request->get['query'])       ? trim($this->request->get['query'])         : '';
        $cat_filter = isset($this->request->get['category_id']) ? (int)$this->request->get['category_id']  : 0;
        $min_chars = $this->config->get('module_dockercart_search_min_chars') ?: 3;

        if (mb_strlen($query) < $min_chars) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $this->load->model('extension/module/dockercart_search');
        $this->load->model('tool/image');

        $normalized_query = $this->model_extension_module_dockercart_search->normalizeSearchQuery($query);

        if ($normalized_query === '') {
            $normalized_query = $query;
        }

        $limit = $this->config->get('module_dockercart_search_autocomplete_limit') ?: 10;

        // ── 1. Products ──────────────────────────────────────────────────────
        $product_opts = ['limit' => $limit];
        if ($cat_filter) {
            $product_opts['category_id'] = $cat_filter;
            $product_opts['sub_category'] = true;
        }

        $results = $this->model_extension_module_dockercart_search->suggest($query, $product_opts);

        foreach ($results as $result) {
            $image = $result['image']
                ? $this->model_tool_image->resize($result['image'], 50, 50)
                : $this->model_tool_image->resize('placeholder.png', 50, 50);

            $price   = '';
            $special = '';

            if (isset($result['price'])) {
                $price = $this->currency->format(
                    $this->tax->calculate($result['price'], $result['tax_class_id'] ?? 0, $this->config->get('config_tax')),
                    $this->session->data['currency']
                );
            }

            if (isset($result['special']) && $result['special'] > 0) {
                $special = $this->currency->format(
                    $this->tax->calculate($result['special'], $result['tax_class_id'] ?? 0, $this->config->get('config_tax')),
                    $this->session->data['currency']
                );
            }

            $json[] = [
                'type'       => 'product',
                'product_id' => $result['product_id'],
                'name'       => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
                'model'      => $result['model'] ?? '',
                'image'      => $image,
                'price'      => $price,
                'special'    => $special,
                'href'       => $this->url->link('product/product', 'product_id=' . $result['product_id'])
            ];
        }

        // ── 2. Categories (only when no category filter is active) ────────────
        if (!$cat_filter) {
            $category_results = $this->model_extension_module_dockercart_search->searchCategories($query, ['limit' => 4]);

            foreach ($category_results as $cat) {
                $json[] = [
                    'type' => 'category',
                    'name' => strip_tags(html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8')),
                    'href' => $this->url->link('product/category', 'path=' . (int)$cat['category_id'])
                ];
            }
        }

        // ── 3. Manufacturers (MySQL LIKE — no Manticore index required) ───────
        if (!$cat_filter) {
            $query_safe = $this->db->escape(mb_strtolower(trim($normalized_query)));

            $mfr_result = $this->db->query(
                "SELECT m.manufacturer_id, m.name
                 FROM `" . DB_PREFIX . "manufacturer` m
                 INNER JOIN `" . DB_PREFIX . "manufacturer_to_store` ms
                     ON ms.manufacturer_id = m.manufacturer_id AND ms.store_id = '" . (int)$this->config->get('config_store_id') . "'
                 WHERE LOWER(m.name) LIKE '%" . $query_safe . "%'
                 ORDER BY m.name ASC
                 LIMIT 4"
            );

            foreach ($mfr_result->rows as $mfr) {
                $json[] = [
                    'type' => 'manufacturer',
                    'name' => strip_tags(html_entity_decode($mfr['name'], ENT_QUOTES, 'UTF-8')),
                    'href' => $this->url->link('product/manufacturer/info', 'manufacturer_id=' . (int)$mfr['manufacturer_id'])
                ];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Override getProducts when called from search page
     * This intercepts the model call AFTER standard database search and replaces results
     */
    public function overrideGetProducts(&$route, &$args, &$output) {
        if (!$this->config->get('module_dockercart_search_status')) {
            return; // Let standard search work
        }

        // Tag search must always stay on native MySQL logic (product_description.tag).
        // This prevents mismatches between Manticore index and MySQL tags.
        if (isset($this->request->get['tag']) && trim((string)$this->request->get['tag']) !== '') {
            return;
        }
        
        // Only override when search parameter is present
        if (!isset($this->request->get['search']) || empty($this->request->get['search'])) {
            return; // Not a search request
        }
        
        $search = $this->request->get['search'];
        $min_chars = $this->config->get('module_dockercart_search_min_chars') ?: 3;
        
        if (mb_strlen($search) < $min_chars) {
            return; // Let standard search handle validation
        }
        
        // Get filter data from args (passed to getProducts)
        $filter_data = isset($args[0]) ? $args[0] : [];
        
        // Use Manticore search
        $this->load->model('extension/module/dockercart_search');
        
        $search_options = [
            'limit' => isset($filter_data['limit']) ? (int)$filter_data['limit'] : 20,
            'offset' => isset($filter_data['start']) ? (int)$filter_data['start'] : 0
        ];
        
        // Add category filter if specified
        if (isset($filter_data['filter_category_id']) && $filter_data['filter_category_id']) {
            $search_options['category_id'] = (int)$filter_data['filter_category_id'];
        }
        
        // Add sub-category filter
        if (isset($filter_data['filter_sub_category'])) {
            $search_options['sub_category'] = true;
        }
        
        // Search in description if checkbox is checked
        if (isset($this->request->get['description']) && $this->request->get['description']) {
            $search_options['description'] = true;
        }
        
        // Perform Manticore search
        $search_results = $this->model_extension_module_dockercart_search->search($search, $search_options);

        // Always apply Manticore results so search page and autocomplete remain consistent.
        // If Manticore returns stale IDs that cannot be hydrated to real products,
        // force total to 0 to avoid empty result list with non-zero pagination.
        $products = isset($search_results['products']) && is_array($search_results['products']) ? $search_results['products'] : [];
        $total = isset($search_results['total']) ? (int)$search_results['total'] : 0;

        if (empty($products)) {
            $total = 0;
        }

        // Override output with Manticore results (output is passed by reference)
        $output = $products;

        // Store total for pagination
        $this->registry->set('manticore_search_total', $total);
    }
    
    /**
     * Add autocomplete script to header (via event)
     */
    public function addAutocompleteScript(&$route, &$args, &$output) {
        if (!$this->config->get('module_dockercart_search_status')) {
            return;
        }
        if (!$this->config->get('module_dockercart_search_autocomplete')) {
            return;
        }
        
        // Add autocomplete JavaScript before </head>
        $script = '<script src="catalog/view/javascript/dockercart_search_autocomplete.js"></script>' . "\n";
        $script .= '<script>' . "\n";
        // Load search labels
        $this->load->language('common/search');

        $script .= 'var dockercart_search_config = {' . "\n";
        $script .= '    min_chars: ' . ($this->config->get('module_dockercart_search_min_chars') ?: 3) . ',' . "\n";
        $script .= '    suggest_url: "index.php?route=extension/module/dockercart_search/suggest",' . "\n";
        $script .= '    labels: {' . "\n";
        $script .= '        categories: ' . json_encode($this->language->get('text_suggest_categories') ?: 'Categories') . ',' . "\n";
        $script .= '        manufacturers: ' . json_encode($this->language->get('text_suggest_manufacturers') ?: 'Manufacturers') . ',' . "\n";
        $script .= '        products: ' . json_encode($this->language->get('text_suggest_products') ?: 'Products') . "\n";
        $script .= '    }' . "\n";
        $script .= '};' . "\n";
        $script .= '</script>' . "\n";
        $script .= '</head>';
        
        $output = str_replace('</head>', $script, $output);
    }
}
