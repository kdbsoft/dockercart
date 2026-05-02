<?php
/**
 * DockerCart Multicurrency Module
 * Admin Controller
 * 
 * @package    DockerCart
 * @subpackage Multicurrency
 * @author     DockerCart
 * @version    1.0.0
 */

class ControllerExtensionModuleDockercartMulticurrency extends Controller {
    private $error = array();
    
    /**
     * Main module settings page
     */
    public function index() {
        $this->load->language('extension/module/dockercart_multicurrency');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Проверка лицензии
        $this->validateLicense();
        
        $this->load->model('setting/setting');
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_dockercart_multicurrency', $this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_success');
            
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }
        
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        
        $data['breadcrumbs'] = array();
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dockercart_multicurrency', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['action'] = $this->url->link('extension/module/dockercart_multicurrency', 'user_token=' . $this->session->data['user_token'], true);
        
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        
        // Get module status
        if (isset($this->request->post['module_dockercart_multicurrency_status'])) {
            $data['module_dockercart_multicurrency_status'] = $this->request->post['module_dockercart_multicurrency_status'];
        } else {
            $data['module_dockercart_multicurrency_status'] = $this->config->get('module_dockercart_multicurrency_status');
        }
        
        // License fields
        if (isset($this->request->post['module_dockercart_multicurrency_license_key'])) {
            $data['module_dockercart_multicurrency_license_key'] = $this->request->post['module_dockercart_multicurrency_license_key'];
        } else {
            $data['module_dockercart_multicurrency_license_key'] = $this->config->get('module_dockercart_multicurrency_license_key');
        }
        
        if (isset($this->request->post['module_dockercart_multicurrency_public_key'])) {
            $data['module_dockercart_multicurrency_public_key'] = $this->request->post['module_dockercart_multicurrency_public_key'];
        } else {
            $data['module_dockercart_multicurrency_public_key'] = $this->config->get('module_dockercart_multicurrency_public_key');
        }
        
        // User token for AJAX
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('extension/module/dockercart_multicurrency', $data));
    }
    
    /**
     * Install module - add currency_id column to product table and register events
     */
    public function install() {
        $this->load->model('setting/event');
        
        // Add currency_id column to product table if not exists
        $query = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product` LIKE 'currency_id'");
        
        if (!$query->num_rows) {
            $this->db->query("ALTER TABLE `" . DB_PREFIX . "product` ADD `currency_id` INT(11) DEFAULT NULL AFTER `price`");
        }
        
        // Install OCMOD for cart modifications
        $this->installOCMOD();
        
        // Register events
        $events = [
            // Admin: Add currency data to product form
            [
                'code'    => 'dockercart_multicurrency_product_form_before',
                'trigger' => 'admin/view/catalog/product_form/before',
                'action'  => 'extension/module/dockercart_multicurrency/eventProductFormBefore'
            ],
            // Admin: Modify product form template to add currency field
            [
                'code'    => 'dockercart_multicurrency_product_form_after',
                'trigger' => 'admin/view/catalog/product_form/after',
                'action'  => 'extension/module/dockercart_multicurrency/eventProductFormAfter'
            ],
            // Admin: Save currency when product is added (after to get product_id)
            [
                'code'    => 'dockercart_multicurrency_product_add',
                'trigger' => 'admin/model/catalog/product/addProduct/after',
                'action'  => 'extension/module/dockercart_multicurrency/eventProductAddAfter'
            ],
            // Admin: Save currency when product is edited
            [
                'code'    => 'dockercart_multicurrency_product_edit',
                'trigger' => 'admin/model/catalog/product/editProduct/before',
                'action'  => 'extension/module/dockercart_multicurrency/eventProductEditBefore'
            ],
            // Admin: Enhance product list with currency info
            [
                'code'    => 'dockercart_multicurrency_product_list',
                'trigger' => 'admin/view/catalog/product_list/before',
                'action'  => 'extension/module/dockercart_multicurrency/eventProductListBefore'
            ],
            // Catalog: Override getProduct in model BEFORE to prevent OpenCart conversion
            [
                'code'    => 'dockercart_multicurrency_model_product_before',
                'trigger' => 'catalog/model/catalog/product/getProduct/before',
                'action'  => 'extension/module/dockercart_multicurrency/eventModelProductBefore',
                'sort_order' => 0
            ],
            // Catalog: Fix prices after getProduct (high priority to run last)
            [
                'code'    => 'dockercart_multicurrency_model_product_after',
                'trigger' => 'catalog/model/catalog/product/getProduct/after',
                'action'  => 'extension/module/dockercart_multicurrency/eventModelProductAfter',
                'sort_order' => 999
            ],
            // Catalog: Fix prices after getProducts (high priority to run last)
            [
                'code'    => 'dockercart_multicurrency_model_products_after',
                'trigger' => 'catalog/model/catalog/product/getProducts/after',
                'action'  => 'extension/module/dockercart_multicurrency/eventModelProductsAfter',
                'sort_order' => 999
            ]
        ];
        
        foreach ($events as $event) {
            // Delete if exists (for clean reinstall)
            $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` = '" . $this->db->escape($event['code']) . "'");
            
            // Add event
            $this->model_setting_event->addEvent(
                $event['code'],
                $event['trigger'],
                $event['action']
            );
        }
    }
    
    /**
     * Uninstall module - remove events (keep currency_id column for data safety)
     */
    public function uninstall() {
        $this->load->model('setting/event');
        
        // Remove all module events
        $this->db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE `code` LIKE 'dockercart_multicurrency_%'");
        
        // Uninstall OCMOD
        $this->uninstallOCMOD();
        
        // Note: We intentionally do NOT drop the currency_id column to preserve data
        // If you want to completely remove it, run manually:
        // ALTER TABLE `oc_product` DROP COLUMN `currency_id`;
    }
    
    /**
     * Install OCMOD modification for cart
     */
    private function installOCMOD() {
        $this->load->model('setting/modification');
        
        // Check if modification already exists
        $modification_query = $this->db->query("SELECT modification_id FROM " . DB_PREFIX . "modification WHERE code = 'dockercart_multicurrency_cart'");
        
        if ($modification_query->num_rows) {
            // Remove old version
            $this->model_setting_modification->deleteModification($modification_query->row['modification_id']);
        }
        
        // Read OCMOD XML file
        $xml_file = DIR_SYSTEM . 'dockercart_multicurrency.ocmod.xml';
        
        if (file_exists($xml_file)) {
            $xml = file_get_contents($xml_file);
            
            // Parse XML to get modification details
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->loadXml($xml);
            
            $name = $dom->getElementsByTagName('name')->item(0)->nodeValue;
            $code = $dom->getElementsByTagName('code')->item(0)->nodeValue;
            $version = $dom->getElementsByTagName('version')->item(0)->nodeValue;
            $author = $dom->getElementsByTagName('author')->item(0)->nodeValue;
            $link = $dom->getElementsByTagName('link')->item(0)->nodeValue;
            
            // Add modification to database
            $this->db->query("INSERT INTO " . DB_PREFIX . "modification SET 
                name = '" . $this->db->escape($name) . "', 
                code = '" . $this->db->escape($code) . "',
                author = '" . $this->db->escape($author) . "',
                version = '" . $this->db->escape($version) . "',
                link = '" . $this->db->escape($link) . "',
                xml = '" . $this->db->escape($xml) . "',
                status = '1',
                date_added = NOW()
            ");
            
            // Refresh modifications
            $this->load->controller('marketplace/modification/refresh');
        }
    }
    
    /**
     * Uninstall OCMOD modification
     */
    private function uninstallOCMOD() {
        $this->load->model('setting/modification');
        
        // Find and remove modification
        $modification_query = $this->db->query("SELECT modification_id FROM " . DB_PREFIX . "modification WHERE code = 'dockercart_multicurrency_cart'");
        
        if ($modification_query->num_rows) {
            $this->model_setting_modification->deleteModification($modification_query->row['modification_id']);
            
            // Refresh modifications
            $this->load->controller('marketplace/modification/refresh');
        }
    }
    
    /**
     * Add currency select field to product form
     */
    public function eventProductFormBefore(&$route, &$data, &$template) {
        // Check if module is enabled
        if (!$this->config->get('module_dockercart_multicurrency_status')) {
            return;
        }
        
        // Load currencies
        $this->load->model('localisation/currency');
        $currencies = $this->model_localisation_currency->getCurrencies();
        
        // Get current product currency
        $product_currency_id = 0;
        if (isset($this->request->get['product_id'])) {
            $query = $this->db->query("SELECT currency_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$this->request->get['product_id'] . "'");
            if ($query->num_rows && isset($query->row['currency_id'])) {
                $product_currency_id = (int)$query->row['currency_id'];
            }
        }
        
        // Add currencies to data for template
        $data['currencies'] = array();
        foreach ($currencies as $currency) {
            $data['currencies'][] = array(
                'currency_id' => $currency['currency_id'],
                'title'       => $currency['title'],
                'code'        => $currency['code']
            );
        }
        
        $data['product_currency_id'] = $product_currency_id;
    }
    
    /**
     * Modify product form template to add currency field
     */
    public function eventProductFormAfter(&$route, &$data, &$output) {
        // Check if we have currencies data
        if (!isset($data['currencies']) || empty($data['currencies'])) {
            return;
        }
        
        // Build currency select HTML - match OpenCart 3.x template style exactly
        $currency_html = '          <div class="form-group">' . "\n";
        $currency_html .= '            <label class="col-sm-2 control-label" for="input-currency">';
        $currency_html .= '<span data-toggle="tooltip" title="Select the currency for this product. Prices will be displayed in this currency on the frontend." data-original-title="Product Currency">Product Currency</span>';
        $currency_html .= '</label>' . "\n";
        $currency_html .= '            <div class="col-sm-10">' . "\n";
        $currency_html .= '              <select name="currency_id" id="input-currency" class="form-control">' . "\n";
        $currency_html .= '                <option value="0"';
        if (empty($data['product_currency_id'])) {
            $currency_html .= ' selected="selected"';
        }
        $currency_html .= '>-- Use Default Currency --</option>' . "\n";
        
        foreach ($data['currencies'] as $currency) {
            $currency_html .= '                <option value="' . (int)$currency['currency_id'] . '"';
            if (isset($data['product_currency_id']) && $currency['currency_id'] == $data['product_currency_id']) {
                $currency_html .= ' selected="selected"';
            }
            $currency_html .= '>' . htmlspecialchars($currency['title'], ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($currency['code'], ENT_QUOTES, 'UTF-8') . ')</option>' . "\n";
        }
        
        $currency_html .= '              </select>' . "\n";
        $currency_html .= '            </div>' . "\n";
        $currency_html .= '          </div>';
        
        // Strategy 1: Look for the price input by name="price"
        $pos = strpos($output, 'name="price"');
        
        if ($pos !== false) {
            // Go backwards to find the start of this form-group
            $form_group_start = $pos;
            for ($i = $pos; $i >= 0; $i--) {
                if (substr($output, $i, 23) === '<div class="form-group"') {
                    $form_group_start = $i;
                    break;
                }
                if (substr($output, $i, 33) === '<div class="form-group required') {
                    $form_group_start = $i;
                    break;
                }
            }
            
            // Go forward to find the closing </div> of this form-group
            $level = 0;
            $in_div = false;
            for ($i = $form_group_start; $i < strlen($output); $i++) {
                $char = $output[$i];
                
                // Detect <div
                if ($char === '<' && substr($output, $i, 5) === '<div ') {
                    $level++;
                    $in_div = true;
                }
                // Detect </div>
                elseif ($char === '<' && substr($output, $i, 6) === '</div>') {
                    if ($in_div) {
                        $level--;
                        if ($level === 0) {
                            // Found the closing div, insert after it
                            $insert_pos = $i + 6;
                            $output = substr($output, 0, $insert_pos) . "\n" . $currency_html . "\n" . substr($output, $insert_pos);
                            return;
                        }
                    }
                }
            }
        }
        
        // Strategy 2: If strategy 1 failed, look for id="input-price"
        $pos = strpos($output, 'id="input-price"');
        if ($pos !== false) {
            // Find the next </div></div> sequence (end of form-group)
            $search_from = $pos;
            $pattern = '</div>' . "\n" . '          </div>';
            $pos2 = strpos($output, $pattern, $search_from);
            if ($pos2 !== false) {
                $insert_pos = $pos2 + strlen($pattern);
                $output = substr($output, 0, $insert_pos) . "\n" . $currency_html . "\n" . substr($output, $insert_pos);
                return;
            }
        }
        
        // Strategy 3: Fallback - insert right before the "Minimum Quantity" field if it exists
        $pos = strpos($output, 'name="minimum"');
        if ($pos !== false) {
            // Go backwards to find the start of the minimum form-group
            for ($i = $pos; $i >= 0; $i--) {
                if (substr($output, $i, 23) === '<div class="form-group"') {
                    $output = substr($output, 0, $i) . $currency_html . "\n          " . substr($output, $i);
                    return;
                }
            }
        }
    }
    
    /**
     * Save currency_id when adding product
     */
    public function eventProductAddBefore(&$route, &$args) {
        // Add currency_id to the SQL query
        if (isset($this->request->post['currency_id']) && (int)$this->request->post['currency_id'] > 0) {
            // We need to modify the INSERT query by updating product after insert
            // This will be done in the after event
        }
    }
    
    /**
     * Save currency_id after adding product
     */
    public function eventProductAddAfter(&$route, &$args, &$output) {
        if (isset($this->request->post['currency_id']) && (int)$this->request->post['currency_id'] > 0) {
            $product_id = $output; // The return value is the new product_id
            if ($product_id) {
                $this->db->query("UPDATE " . DB_PREFIX . "product SET currency_id = '" . (int)$this->request->post['currency_id'] . "' WHERE product_id = '" . (int)$product_id . "'");
            }
        }
    }
    
    /**
     * Save currency_id when editing product
     */
    public function eventProductEditBefore(&$route, &$args) {
        // Add currency_id to update query
        if (isset($this->request->post['currency_id'])) {
            $product_id = $args[0];
            $this->db->query("UPDATE " . DB_PREFIX . "product SET currency_id = '" . (int)$this->request->post['currency_id'] . "' WHERE product_id = '" . (int)$product_id . "'");
        }
    }
    
    /**
     * Modify product prices in list to show currency
     */
    public function eventProductListBefore(&$route, &$data, &$template) {
        // Enhance product list with currency information
        if (isset($data['products'])) {
            $this->load->model('localisation/currency');
            
            // Get default currency
            $default_currency = $this->config->get('config_currency');
            
            foreach ($data['products'] as &$product) {
                $query = $this->db->query("SELECT currency_id FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product['product_id'] . "'");
                
                if ($query->num_rows && $query->row['currency_id']) {
                    $currency = $this->model_localisation_currency->getCurrency($query->row['currency_id']);
                    
                    if ($currency && $currency['code'] != $default_currency) {
                        // Add currency code to price display if different from default
                        $product['price'] = $product['price'] . ' <span class="label label-info">' . $currency['code'] . '</span>';
                    }
                } else {
                    // No specific currency set, uses default
                }
            }
        }
    }
    
    /**
     * Проверка лицензии (для UI warnings, не блокирует работу админки)
     */
    private function validateLicense() {
        $license_key = $this->config->get('module_dockercart_multicurrency_license_key');

        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false) {
            return true;
        }

        if (empty($license_key)) {
            return true;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            return true;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            return true;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $result = $license->verify($license_key, 'dockercart_multicurrency');

            if (!$result['valid']) {
                $error_msg = $this->language->get('error_license_invalid');
                if (isset($result['error'])) {
                    $error_msg .= ': ' . $result['error'];
                }
            }
        } catch (Exception $e) {
            // Silent fail in admin
        }

        return true;
    }

    /**
     * Проверка лицензии для работы модуля (блокирует работу если нет лицензии)
     */
    private function checkLicenseForOperation() {
        $license_key = $this->config->get('module_dockercart_multicurrency_license_key');

        // Allow localhost/dev environments
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false || strpos($domain, '.docker.localhost') !== false) {
            return true;
        }

        if (empty($license_key)) {
            return false;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            return false;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            return false;
        }

        try {
            $license = new DockercartLicense($this->registry);
            $result = $license->verify($license_key, 'dockercart_multicurrency');

            return $result['valid'];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * AJAX проверка лицензии
     */
    public function verifyLicenseAjax() {
        $json = array();

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $license_key = isset($data['license_key']) ? $data['license_key'] : '';
        $public_key = isset($data['public_key']) ? $data['public_key'] : '';

        if (empty($license_key)) {
            $json['valid'] = false;
            $json['error'] = 'License key is empty';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        if (!file_exists(DIR_SYSTEM . 'library/dockercart_license.php')) {
            $json['valid'] = false;
            $json['error'] = 'License library not found';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        require_once(DIR_SYSTEM . 'library/dockercart_license.php');

        if (!class_exists('DockercartLicense')) {
            $json['valid'] = false;
            $json['error'] = 'DockercartLicense class not found';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $license = new DockercartLicense($this->registry);

            if (!empty($public_key)) {
                $result = $license->verifyWithPublicKey($license_key, $public_key, 'dockercart_multicurrency', true);
            } else {
                $result = $license->verify($license_key, 'dockercart_multicurrency', true);
            }

            $json = $result;
        } catch (Exception $e) {
            $json['valid'] = false;
            $json['error'] = 'Error: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Validate form
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_multicurrency')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        
        return !$this->error;
    }
}
