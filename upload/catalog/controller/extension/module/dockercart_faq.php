<?php
class ControllerExtensionModuleDockercartFaq extends Controller {
    public function index($setting = array()) {
        if (!(int)$this->config->get('module_dockercart_faq_status')) {
            return '';
        }

        $this->load->language('extension/module/dockercart_faq');
        $this->load->model('extension/module/dockercart_faq');

        $store_id = (int)$this->config->get('config_store_id');
        $language_id = (int)$this->config->get('config_language_id');

        $context = $this->getCurrentContext();
        $placeholder_map = $this->buildPlaceholderMap($context);

        $code = isset($setting['faq_code']) ? trim((string)$setting['faq_code']) : '';
        $faqs = array();

        if ($code !== '') {
            $single = $this->model_extension_module_dockercart_faq->getFaqByCode($code, $store_id, $language_id);
            if ($single) {
                $faqs[] = $single;
            }
        } else {
            $faqs = $this->model_extension_module_dockercart_faq->getFaqsByContext(
                $context['type'],
                $context['value'],
                $store_id,
                $language_id
            );
        }

        return $this->renderFaqHtml($faqs, $placeholder_map, isset($setting['title']) ? (string)$setting['title'] : '');
    }

    public function eventReplacePlaceholders(&$route, &$args, &$output) {
        if (!(int)$this->config->get('module_dockercart_faq_status')) {
            return;
        }

        if (!is_string($output) || $output === '' || strpos($output, '{dc_faq:') === false) {
            return;
        }

        $this->load->language('extension/module/dockercart_faq');
        $this->load->model('extension/module/dockercart_faq');

        $store_id = (int)$this->config->get('config_store_id');
        $language_id = (int)$this->config->get('config_language_id');
        $context = $this->getCurrentContext();
        $placeholder_map = $this->buildPlaceholderMap($context);

        if (preg_match_all('/\{dc_faq:([a-z0-9_\-\.]+)\}/i', $output, $matches)) {
            $codes = array_unique($matches[1]);

            foreach ($codes as $code) {
                $faq = $this->model_extension_module_dockercart_faq->getFaqByCode($code, $store_id, $language_id);
                $html = '';

                if ($faq) {
                    $html = $this->renderFaqHtml(array($faq), $placeholder_map, '');
                }

                $output = str_replace('{dc_faq:' . $code . '}', $html, $output);
                $output = str_replace('{dc_faq:' . strtoupper($code) . '}', $html, $output);
            }
        }

        if (strpos($output, '{dc_faq:auto}') !== false) {
            $faqs = $this->model_extension_module_dockercart_faq->getFaqsByContext(
                $context['type'],
                $context['value'],
                $store_id,
                $language_id
            );

            $html = $this->renderFaqHtml($faqs, $placeholder_map, '');
            $output = str_replace('{dc_faq:auto}', $html, $output);
            $output = str_replace('{dc_faq:AUTO}', $html, $output);
        }
    }

    private function renderFaqHtml($faqs, $placeholder_map, $title = '') {
        if (!is_array($faqs) || !$faqs) {
            return '';
        }

        $items = array();
        $json_items = array();
        $include_json_ld = false;

        foreach ($faqs as $faq) {
            $question = isset($faq['question']) ? $this->applyPlaceholders((string)$faq['question'], $placeholder_map) : '';
            $answer = isset($faq['answer']) ? $this->applyPlaceholders((string)$faq['answer'], $placeholder_map) : '';

            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = array(
                'question' => $question,
                'answer' => html_entity_decode($answer, ENT_QUOTES, 'UTF-8')
            );

            if ((int)$faq['show_json_ld']) {
                $include_json_ld = true;
                $json_items[] = array(
                    '@type' => 'Question',
                    'name' => strip_tags($question),
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => strip_tags(html_entity_decode($answer, ENT_QUOTES, 'UTF-8'))
                    )
                );
            }
        }

        if (!$items) {
            return '';
        }

        $json_ld = '';
        if ($include_json_ld && $json_items) {
            $json_ld = json_encode(array(
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $json_items
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $data = array(
            'heading_title' => $title !== '' ? $title : $this->language->get('heading_title'),
            'items' => $items,
            'json_ld' => $json_ld,
            'module_id' => 'dc-faq-' . mt_rand(1000, 999999),
            // expose subtitle text from language so template can fallback to it when no module subtitle is set
            'text_heading_subtitle' => $this->language->get('text_heading_subtitle')
        );

        return $this->load->view('extension/module/dockercart_faq', $data);
    }

    private function getCurrentContext() {
        $route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';

        if ($route === 'common/home') {
            return array('route' => $route, 'type' => 'home', 'value' => '');
        }

        if ($route === 'product/category') {
            $category_id = 0;

            if (isset($this->request->get['path']) && $this->request->get['path'] !== '') {
                $parts = explode('_', (string)$this->request->get['path']);
                $category_id = (int)end($parts);
            }

            return array('route' => $route, 'type' => 'category', 'value' => (string)$category_id);
        }

        if ($route === 'product/product') {
            return array('route' => $route, 'type' => 'product', 'value' => (string)(int)$this->getQuery('product_id'));
        }

        if ($route === 'product/manufacturer/info') {
            return array('route' => $route, 'type' => 'manufacturer', 'value' => (string)(int)$this->getQuery('manufacturer_id'));
        }

        if ($route === 'information/information') {
            return array('route' => $route, 'type' => 'information', 'value' => (string)(int)$this->getQuery('information_id'));
        }

        if ($route === 'product/search') {
            return array('route' => $route, 'type' => 'search', 'value' => '');
        }

        return array('route' => $route, 'type' => 'route', 'value' => $route);
    }

    private function getQuery($key, $default = 0) {
        return isset($this->request->get[$key]) ? $this->request->get[$key] : $default;
    }

    private function buildPlaceholderMap($context) {
        $currency_code = isset($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');
        $map = array(
            '{store_name}' => (string)$this->config->get('config_name'),
            '{store_url}' => HTTP_SERVER,
            '{store_phone}' => (string)$this->config->get('config_telephone'),
            '{store_email}' => (string)$this->config->get('config_email'),
            '{route}' => (string)$context['route'],

            '{category_name}' => '',
            '{category_price_min}' => '',
            '{category_price_max}' => '',

            '{manufacturer_name}' => '',

            '{product_name}' => '',
            '{product_model}' => '',
            '{product_price}' => '',
            '{product_special}' => '',
            '{product_currency}' => $currency_code,

            '{information_title}' => ''
        );

        $language_id = (int)$this->config->get('config_language_id');

        if ($context['type'] === 'category' && (int)$context['value'] > 0) {
            $category_id = (int)$context['value'];

            $q = $this->db->query("SELECT name FROM `" . DB_PREFIX . "category_description` WHERE category_id = '" . $category_id . "' AND language_id = '" . $language_id . "' LIMIT 1");
            if ($q->num_rows) {
                $map['{category_name}'] = (string)$q->row['name'];
            }

            $price = $this->db->query("SELECT MIN(p.price) AS min_price, MAX(p.price) AS max_price
                FROM `" . DB_PREFIX . "product` p
                INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)
                WHERE p2c.category_id = '" . $category_id . "'
                  AND p.status = '1'");

            if ($price->num_rows) {
                $map['{category_price_min}'] = $price->row['min_price'] !== null
                    ? $this->currency->format((float)$price->row['min_price'], $currency_code)
                    : '';
                $map['{category_price_max}'] = $price->row['max_price'] !== null
                    ? $this->currency->format((float)$price->row['max_price'], $currency_code)
                    : '';
            }
        }

        if ($context['type'] === 'manufacturer' && (int)$context['value'] > 0) {
            $manufacturer_id = (int)$context['value'];
            $q = $this->db->query("SELECT name FROM `" . DB_PREFIX . "manufacturer` WHERE manufacturer_id = '" . $manufacturer_id . "' LIMIT 1");
            if ($q->num_rows) {
                $map['{manufacturer_name}'] = (string)$q->row['name'];
            }
        }

        if ($context['type'] === 'product' && (int)$context['value'] > 0) {
            $product_id = (int)$context['value'];

            $q = $this->db->query("SELECT p.model, p.price, p.tax_class_id, pd.name
                FROM `" . DB_PREFIX . "product` p
                LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id)
                WHERE p.product_id = '" . $product_id . "'
                  AND pd.language_id = '" . $language_id . "'
                LIMIT 1");

            if ($q->num_rows) {
                $price = (float)$q->row['price'];
                $tax_class_id = (int)$q->row['tax_class_id'];

                $map['{product_name}'] = (string)$q->row['name'];
                $map['{product_model}'] = (string)$q->row['model'];
                $map['{product_price}'] = $this->currency->format($this->tax->calculate($price, $tax_class_id, $this->config->get('config_tax')), $currency_code);

                $special_q = $this->db->query("SELECT price FROM `" . DB_PREFIX . "product_special`
                    WHERE product_id = '" . $product_id . "'
                      AND (date_start = '0000-00-00' OR date_start < NOW())
                      AND (date_end = '0000-00-00' OR date_end > NOW())
                    ORDER BY priority ASC, price ASC
                    LIMIT 1");

                if ($special_q->num_rows) {
                    $special = (float)$special_q->row['price'];
                    $map['{product_special}'] = $this->currency->format($this->tax->calculate($special, $tax_class_id, $this->config->get('config_tax')), $currency_code);
                }
            }
        }

        if ($context['type'] === 'information' && (int)$context['value'] > 0) {
            $information_id = (int)$context['value'];
            $q = $this->db->query("SELECT title FROM `" . DB_PREFIX . "information_description`
                WHERE information_id = '" . $information_id . "'
                  AND language_id = '" . $language_id . "'
                LIMIT 1");

            if ($q->num_rows) {
                $map['{information_title}'] = (string)$q->row['title'];
            }
        }

        return $map;
    }

    private function applyPlaceholders($text, $map) {
        if ($text === '') {
            return '';
        }

        return str_ireplace(array_keys($map), array_values($map), $text);
    }
}
