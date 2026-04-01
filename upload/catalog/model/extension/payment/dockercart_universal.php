<?php
/**
 * DockerCart Universal Payment Model (Catalog)
 * Returns one suitable payment method based on configured records.
 *
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2024-2026 DockerCart
 * @license    MIT
 */
class ModelExtensionPaymentDockercartUniversal extends Model {
    /**
     * Get payment method for checkout
     */
    public function getMethod($address, $total) {
        $this->load->language('extension/payment/dockercart_universal');

        if (!$this->config->get('payment_dockercart_universal_status')) {
            return [];
        }

        $methods = $this->getActiveMethods();
        $base_sort_order = (int)$this->config->get('payment_dockercart_universal_sort_order');

        if (!$methods) {
            return [];
        }

        $quote_data = [];

        foreach ($methods as $method) {
            if (!$this->checkGeoZone((int)$method['geo_zone_id'], $address)) {
                continue;
            }

            if (!$this->checkOrderTotal($method, (float)$total)) {
                continue;
            }

            $title = !empty($method['name']) ? $method['name'] : $this->language->get('text_title');
            $description = $method['description'] ?? '';
            $quote_key = 'dockercart_universal_' . (int)$method['method_id'];
            $quote_code = 'dockercart_universal.' . $quote_key;

            $quote_data[$quote_key] = [
                'code'                           => $quote_code,
                'title'                          => $title,
                'terms'                          => $description,
                'sort_order'                     => $this->buildMethodSortOrder($method, $base_sort_order),
                'dockercart_universal_method_id' => (int)$method['method_id'],
                'dockercart_universal_description' => $description,
                'dockercart_universal_parent_code' => 'dockercart_universal'
            ];
        }

        if (!$quote_data) {
            return [];
        }

        return [
            'code'       => 'dockercart_universal',
            'title'      => $this->language->get('text_title'),
            'quote'      => $quote_data,
            'sort_order' => $base_sort_order,
            'terms'      => ''
        ];
    }

    /**
     * Get active payment methods with current language descriptions
     */
    protected function getActiveMethods(): array {
        $language_id = (int)$this->config->get('config_language_id');

        $query = $this->db->query("
            SELECT m.*, md.name, md.description
            FROM `" . DB_PREFIX . "dockercart_universal_payment` m
            LEFT JOIN `" . DB_PREFIX . "dockercart_universal_payment_description` md
                ON (m.method_id = md.method_id AND md.language_id = '" . $language_id . "')
            WHERE m.status = '1'
            ORDER BY m.sort_order ASC, m.method_id ASC
        ");

        return $query->rows;
    }

    /**
     * Check if customer address matches geo zone restriction
     */
    protected function checkGeoZone(int $geo_zone_id, array $address): bool {
        if ($geo_zone_id === 0) {
            return true;
        }

        $country_id = (int)($address['country_id'] ?? 0);
        $zone_id = (int)($address['zone_id'] ?? 0);

        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone`
            WHERE `geo_zone_id` = '" . (int)$geo_zone_id . "'
              AND `country_id` = '" . $country_id . "'
              AND (`zone_id` = '" . $zone_id . "' OR `zone_id` = '0')
        ");

        return (bool)$query->num_rows;
    }

    /**
     * Check order total conditions
     */
    protected function checkOrderTotal(array $method, float $total): bool {
        if ($method['min_total'] !== null && $total < (float)$method['min_total']) {
            return false;
        }

        if ($method['max_total'] !== null && $total > (float)$method['max_total']) {
            return false;
        }

        return true;
    }

    /**
     * Build deterministic sort order for a method inside Universal Payment.
     */
    protected function buildMethodSortOrder(array $method, int $base_sort_order): int {
        $method_sort = isset($method['sort_order']) ? (int)$method['sort_order'] : 0;

        return ($base_sort_order * 1000) + $method_sort;
    }
}
