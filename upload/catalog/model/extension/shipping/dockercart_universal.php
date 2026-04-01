<?php
/**
 * DockerCart Universal Shipping Model (Catalog)
 * Calculates shipping quotes based on configured methods.
 *
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2024-2026 DockerCart
 * @license    MIT
 */
class ModelExtensionShippingDockercartUniversal extends Model {
    
    /**
     * Get shipping quote(s) for the current cart
     *
     * @param array $address Customer shipping address
     * @return array Shipping method data or empty array
     */
    public function getQuote(array $address): array {
        $this->load->language('extension/shipping/dockercart_universal');
        
        // Get all active shipping methods
        $methods = $this->getActiveMethods();
        
        if (!$methods) {
            return [];
        }
        
        $quote_data = [];
        
        // Get cart totals for conditions
        $cart_total = $this->cart->getSubTotal();
        $cart_weight = $this->cart->getWeight();
        
        foreach ($methods as $method) {
            // Check geo zone restriction
            if (!$this->checkGeoZone($method['geo_zone_id'], $address)) {
                continue;
            }
            
            // Check order total conditions
            if (!$this->checkOrderTotal($method, $cart_total)) {
                continue;
            }
            
            // Check weight conditions
            if (!$this->checkWeight($method, $cart_weight)) {
                continue;
            }
            
            // Calculate cost
            $cost = $this->calculateCost($method, $cart_total, $cart_weight);
            
            // Get tax class (use method-specific or global fallback)
            $tax_class_id = $method['tax_class_id'] ?: $this->config->get('shipping_dockercart_universal_tax_class_id');
            
            // Build title with optional delivery time
            $title = $method['name'];
            if (!empty($method['delivery_time'])) {
                $title .= ' (' . $method['delivery_time'] . ')';
            }
            
            // Format the display text
            if ($cost === null) {
                $text = '';
            } elseif ($cost > 0) {
                $text = $this->currency->format(
                    $this->tax->calculate($cost, $tax_class_id, $this->config->get('config_tax')),
                    $this->session->data['currency']
                );
            } else {
                $text = $this->language->get('text_free');
            }
            
            $quote_data['dockercart_universal_' . $method['method_id']] = [
                'code'         => 'dockercart_universal.dockercart_universal_' . $method['method_id'],
                'title'        => $title,
                'cost'         => $cost,
                'tax_class_id' => $tax_class_id,
                'text'         => $text,
                'description'  => $method['description'] ?? ''
            ];
        }
        
        if (!$quote_data) {
            return [];
        }
        
        return [
            'code'       => 'dockercart_universal',
            'title'      => $this->language->get('text_title'),
            'quote'      => $quote_data,
            'sort_order' => $this->config->get('shipping_dockercart_universal_sort_order'),
            'error'      => false
        ];
    }
    
    /**
     * Get all active shipping methods with descriptions
     */
    protected function getActiveMethods(): array {
        $language_id = $this->config->get('config_language_id');
        
        $query = $this->db->query("
            SELECT m.*, md.name, md.description, md.delivery_time
            FROM `" . DB_PREFIX . "dockercart_universal_shipping` m
            LEFT JOIN `" . DB_PREFIX . "dockercart_universal_shipping_description` md
                ON (m.method_id = md.method_id AND md.language_id = '" . (int)$language_id . "')
            WHERE m.status = '1'
            ORDER BY m.sort_order ASC, m.method_id ASC
        ");
        
        return $query->rows;
    }
    
    /**
     * Check if the address matches the geo zone
     */
    protected function checkGeoZone(int $geo_zone_id, array $address): bool {
        // Geo zone 0 = all zones
        if ($geo_zone_id == 0) {
            return true;
        }
        
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone`
            WHERE geo_zone_id = '" . (int)$geo_zone_id . "'
            AND country_id = '" . (int)$address['country_id'] . "'
            AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')
        ");
        
        return $query->num_rows > 0;
    }
    
    /**
     * Check order total conditions
     */
    protected function checkOrderTotal(array $method, float $cart_total): bool {
        // Check minimum total
        if ($method['min_total'] !== null && $cart_total < (float)$method['min_total']) {
            return false;
        }
        
        // Check maximum total
        if ($method['max_total'] !== null && $cart_total > (float)$method['max_total']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check weight conditions
     */
    protected function checkWeight(array $method, float $cart_weight): bool {
        // Check minimum weight
        if ($method['min_weight'] !== null && $cart_weight < (float)$method['min_weight']) {
            return false;
        }
        
        // Check maximum weight
        if ($method['max_weight'] !== null && $cart_weight > (float)$method['max_weight']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Calculate shipping cost based on method configuration
     */
    protected function calculateCost(array $method, float $cart_total, float $cart_weight): ?float {
        // Check free shipping threshold
        if ($method['free_shipping_threshold'] !== null && $cart_total >= (float)$method['free_shipping_threshold']) {
            return 0.00;
        }
        
        // Calculate based on cost type
        if ($method['cost_type'] === 'weight' && !empty($method['weight_rates'])) {
            return $this->calculateWeightCost($method['weight_rates'], $cart_weight);
        }
        
        // Fixed cost — null means no price displayed
        if ($method['cost'] === null || $method['cost'] === '') {
            return null;
        }
        
        return (float)$method['cost'];
    }
    
    /**
     * Calculate weight-based shipping cost
     * Format: weight:cost,weight:cost (e.g., "1:5.00,5:10.00,10:15.00")
     */
    protected function calculateWeightCost(string $weight_rates, float $cart_weight): float {
        $rates = explode(',', $weight_rates);
        $cost = 0.00;
        
        foreach ($rates as $rate) {
            $data = explode(':', trim($rate));
            
            if (count($data) === 2) {
                $weight_threshold = (float)$data[0];
                $rate_cost = (float)$data[1];
                
                if ($cart_weight <= $weight_threshold) {
                    return $rate_cost;
                }
                
                // Store the last rate as fallback for weights exceeding all thresholds
                $cost = $rate_cost;
            }
        }
        
        return $cost;
    }
}
