<?php
/**
 * DockerCart Admin Search Model
 *
 * Provides unified search across all entities via Manticore
 *
 * @package    DockerCart
 * @subpackage Model
 * @author     DockerCart Official
 * @copyright  2026 DockerCart
 * @license    MIT
 * @version    1.0.0
 */

require_once DIR_SYSTEM . 'library/dockercart/manticore.php';

use Dockercart\ManticoreClient;

class ModelCommonAdminSearch extends Model {
    private $manticore;

    public $permissions = [
        'product'      => 'catalog/product',
        'category'     => 'catalog/category',
        'manufacturer' => 'catalog/manufacturer',
        'information'  => 'catalog/information',
        'order'        => 'sale/order',
        'customer'     => 'customer/customer',
    ];

    private function getManticore() {
        if (!$this->manticore) {
            $host = $this->config->get('module_dockercart_search_host') ?: 'manticore';
            $port = $this->config->get('module_dockercart_search_port') ?: 9306;

            $this->manticore = new ManticoreClient($host, $port);
        }

        return $this->manticore;
    }

    private function hasAccess($type) {
        if (!isset($this->permissions[$type])) {
            return false;
        }

        return $this->user->hasPermission('access', $this->permissions[$type]);
    }

    private function getIndexName($type) {
        $map = [
            'product'      => 'products',
            'category'     => 'categories',
            'manufacturer' => 'manufacturers',
            'information'  => 'information',
            'order'        => 'orders',
            'customer'     => 'customers',
        ];

        return $map[$type] ?? null;
    }

    private function isMultilingualIndex($type) {
        return in_array($type, ['product', 'category', 'manufacturer', 'information']);
    }

    public function suggest($query) {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $results = [];
        $language_id = (int)$this->config->get('config_language_id');

        $limits = [
            'product'      => 5,
            'category'     => 3,
            'manufacturer' => 3,
            'information'  => 3,
            'order'        => 5,
            'customer'     => 5,
        ];

        foreach ($limits as $type => $limit) {
            if (!$this->hasAccess($type)) {
                continue;
            }

            $search = $this->searchIndex($type, $query, [
                'limit' => $limit,
                'language_id' => $language_id,
            ]);

            foreach ($search['results'] as $row) {
                $results[] = $this->formatResult($type, $row);
            }
        }

        return $results;
    }

    public function search($query, $type, $page = 1, $limit = 20) {
        $query = trim($query);

        if ($query === '') {
            return ['results' => [], 'total' => 0];
        }

        $language_id = (int)$this->config->get('config_language_id');

        if ($type === 'all') {
            return $this->searchAll($query, $page, $limit, $language_id);
        }

        if (!$this->hasAccess($type)) {
            return ['results' => [], 'total' => 0];
        }

        $search = $this->searchIndex($type, $query, [
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
            'language_id' => $language_id,
        ]);

        $results = [];
        foreach ($search['results'] as $row) {
            $results[] = $this->formatResult($type, $row);
        }

        return [
            'results' => $results,
            'total' => $search['total'],
        ];
    }

    private function searchAll($query, $page, $limit, $language_id) {
        $all_results = [];
        $totals = [];

        foreach ($this->permissions as $type => $route) {
            if (!$this->hasAccess($type)) {
                continue;
            }

            $search = $this->searchIndex($type, $query, [
                'limit' => 1,
                'language_id' => $language_id,
            ]);

            $totals[$type] = $search['total'];
        }

        $total = array_sum($totals);
        $offset = ($page - 1) * $limit;
        $remaining = $limit;

        foreach ($this->permissions as $type => $route) {
            if (!$this->hasAccess($type)) {
                continue;
            }

            if ($remaining <= 0) {
                break;
            }

            $type_total = $totals[$type];

            if ($type_total <= 0) {
                continue;
            }

            if ($offset >= $type_total) {
                $offset -= $type_total;
                continue;
            }

            $type_offset = $offset;
            $type_limit = min($remaining, $type_total - $type_offset);

            $search = $this->searchIndex($type, $query, [
                'limit' => $type_limit,
                'offset' => $type_offset,
                'language_id' => $language_id,
            ]);

            foreach ($search['results'] as $row) {
                $all_results[] = $this->formatResult($type, $row);
            }

            $remaining -= count($search['results']);
            $offset = 0;
        }

        return [
            'results' => $all_results,
            'total' => $total,
            'type_totals' => $totals,
        ];
    }

    private function searchIndex($type, $query, $opts) {
        $manticore = $this->getManticore();
        $index = $this->getIndexName($type);

        if (!$index) {
            return ['results' => [], 'total' => 0];
        }

        $filters = [];

        if ($this->isMultilingualIndex($type)) {
            $filters['language_id'] = $opts['language_id'] ?? (int)$this->config->get('config_language_id');
        }

        $search_options = [
            'filters' => $filters,
            'limit' => $opts['limit'] ?? 20,
            'offset' => $opts['offset'] ?? 0,
            'wildcard' => true,
            'ranker' => 'proximity_bm25',
        ];

        $result = $manticore->searchWithMeta($index, $query, $search_options);

        return [
            'results' => $result['results'] ?? [],
            'total' => $result['total'] ?? 0,
        ];
    }

    private function formatResult($type, $row) {
        $entity_id = $this->isMultilingualIndex($type)
            ? (int)floor($row['id'] / 100)
            : (int)$row['id'];

        return [
            'type' => $type,
            'type_label' => $this->getTypeLabel($type),
            'entity_id' => $entity_id,
            'name' => $this->extractName($type, $row),
            'subtitle' => $this->extractSubtitle($type, $row),
            'image' => $row['image'] ?? '',
            'href' => $this->buildEditLink($type, $entity_id),
        ];
    }

    private function getTypeLabel($type) {
        $labels = [
            'product'      => $this->language->get('text_product'),
            'category'     => $this->language->get('text_category'),
            'manufacturer' => $this->language->get('text_manufacturer'),
            'information'  => $this->language->get('text_information'),
            'order'        => $this->language->get('text_order'),
            'customer'     => $this->language->get('text_customer'),
        ];

        return $labels[$type] ?? $type;
    }

    private function extractName($type, $row) {
        switch ($type) {
            case 'product':
                return $row['title'] ?? '';
            case 'category':
            case 'manufacturer':
                return $row['name'] ?? '';
            case 'information':
                return $row['title'] ?? '';
            case 'order':
                $firstname = $row['firstname'] ?? '';
                $lastname = $row['lastname'] ?? '';
                return sprintf('%s %s', $firstname, $lastname);
            case 'customer':
                $firstname = $row['firstname'] ?? '';
                $lastname = $row['lastname'] ?? '';
                return sprintf('%s %s', $firstname, $lastname);
            default:
                return $row['name'] ?? $row['title'] ?? '';
        }
    }

    private function extractSubtitle($type, $row) {
        switch ($type) {
            case 'product':
                $model = $row['model'] ?? '';
                $price = isset($row['price']) ? number_format((float)$row['price'], 2) : '';
                return $model && $price ? "{$model} | {$price}" : ($model ?: $price);
            case 'category':
                return '';
            case 'manufacturer':
                return '';
            case 'information':
                return '';
            case 'order':
                $order_id = (int)$row['id'];
                $email = $row['email'] ?? '';
                $status = $row['order_status_name'] ?? '';
                $parts = ["#{$order_id}"];
                if ($email) {
                    $parts[] = $email;
                }
                if ($status) {
                    $parts[] = $status;
                }
                return implode(' | ', $parts);
            case 'customer':
                $email = $row['email'] ?? '';
                $telephone = $row['telephone'] ?? '';
                return $email && $telephone ? "{$email} | {$telephone}" : ($email ?: $telephone);
            default:
                return '';
        }
    }

    private function buildEditLink($type, $entity_id) {
        $user_token = $this->session->data['user_token'] ?? '';

        switch ($type) {
            case 'product':
                return $this->url->link('catalog/product/edit', 'user_token=' . $user_token . '&product_id=' . $entity_id, true);
            case 'category':
                return $this->url->link('catalog/category/edit', 'user_token=' . $user_token . '&category_id=' . $entity_id, true);
            case 'manufacturer':
                return $this->url->link('catalog/manufacturer/edit', 'user_token=' . $user_token . '&manufacturer_id=' . $entity_id, true);
            case 'information':
                return $this->url->link('catalog/information/edit', 'user_token=' . $user_token . '&information_id=' . $entity_id, true);
            case 'order':
                return $this->url->link('sale/order/info', 'user_token=' . $user_token . '&order_id=' . $entity_id, true);
            case 'customer':
                return $this->url->link('customer/customer/edit', 'user_token=' . $user_token . '&customer_id=' . $entity_id, true);
            default:
                return '';
        }
    }

    public function getTypeTotals($query) {
        $totals = [];
        $language_id = (int)$this->config->get('config_language_id');

        foreach ($this->permissions as $type => $route) {
            if (!$this->hasAccess($type)) {
                $totals[$type] = 0;
                continue;
            }

            $search = $this->searchIndex($type, $query, [
                'limit' => 1,
                'language_id' => $language_id,
            ]);

            $totals[$type] = $search['total'];
        }

        return $totals;
    }
}
