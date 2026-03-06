<?php
/**
 * DockerCart Checkout - Event Handler Controller
 * Handles event system hooks for catalog side
 * 
 * @package    DockerCart Checkout
 * @author     mathflow-bit
 * @license    Commercial License
 */

class ControllerExtensionModuleDockerCartCheckout extends Controller {
    
    /**
     * Event: catalog/controller/checkout/checkout/before
     * Redirects standard checkout to DockerCart Checkout
     * 
     * @param string $route
     * @param array $args
     * @return void
     */
    public function eventRedirectCheckout(&$route, &$args) {
        // Check if module is enabled
        if (!$this->config->get('module_dockercart_checkout_status')) {
            return;
        }
        
        // Verify license
        if (!$this->verifyLicense()) {
            return;
        }
        
        // Check if this is already our checkout
        if (strpos($route, 'dockercart_checkout') !== false) {
            return;
        }
        
        // Redirect to DockerCart Checkout
        $this->response->redirect($this->url->link('checkout/dockercart_checkout', '', true));
    }
    
    /**
     * Event: catalog/controller/checkout/cart/before
     * Redirects cart to DockerCart Checkout if configured
     * 
     * @param string $route
     * @param array $args
     * @return void
     */
    public function eventRedirectCart(&$route, &$args) {
        // Check if module is enabled
        if (!$this->config->get('module_dockercart_checkout_status')) {
            return;
        }
        
        // Check if skip cart is enabled
        if (!$this->config->get('module_dockercart_checkout_skip_cart')) {
            return;
        }
        
        // Verify license
        if (!$this->verifyLicense()) {
            return;
        }
        
        // Only redirect if cart has products
        $this->load->model('checkout/cart');
        
        if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {
            $this->response->redirect($this->url->link('checkout/dockercart_checkout', '', true));
        }
    }
    
    /**
     * Event: catalog/view/common/header/after
     * Adds DockerCart Checkout CSS to header
     * 
     * @param string $route
     * @param array $args
     * @param string $output
     * @return void
     */
    public function eventHeaderAfter(&$route, &$args, &$output) {
        // Check if module is enabled
        if (!$this->config->get('module_dockercart_checkout_status')) {
            return;
        }
        
        // Only add CSS on checkout page
        $currentRoute = isset($this->request->get['route']) ? $this->request->get['route'] : '';
        
        if (strpos($currentRoute, 'checkout/dockercart_checkout') === false) {
            return;
        }
        
        // Get theme
        $theme = $this->config->get('module_dockercart_checkout_theme');
        if (!$theme) {
            $theme = 'light';
        }
        
        // Add theme class
        $themeClass = $theme === 'dark' ? 'dc-theme-dark' : '';
        
        // Custom CSS
        $customCss = $this->config->get('module_dockercart_checkout_custom_css');
        
        // Build CSS to inject
        $css = '';
        
        if ($themeClass) {
            $css .= '<script>document.body.classList.add("' . $themeClass . '");</script>';
        }
        
        if ($customCss) {
            $css .= '<style type="text/css">' . htmlspecialchars_decode($customCss) . '</style>';
        }
        
        // Inject before </head>
        if ($css) {
            $output = str_replace('</head>', $css . '</head>', $output);
        }
    }
    
    /**
     * Event: catalog/controller/api/cart/add/after
     * Updates checkout page after cart add via AJAX
     * 
     * @param string $route
     * @param array $args
     * @param mixed $output
     * @return void
     */
    public function eventCartAddAfter(&$route, &$args, &$output) {
        // Check if module is enabled with auto-open cart
        if (!$this->config->get('module_dockercart_checkout_status')) {
            return;
        }
        
        // Add flag to response if module is active
        if (is_array($output)) {
            $output['dockercart_checkout'] = true;
        }
    }
    
    /**
     * Verify license
     * 
     * @return bool
     */
    private function verifyLicense() {
        $licenseKey = $this->config->get('module_dockercart_checkout_license_key');
        
        if (!$licenseKey) {
            return false;
        }
        
        // Use DockercartLicense library
        if (class_exists('DockercartLicense')) {
            $license = new DockercartLicense($this->registry);
            return $license->verify($licenseKey, 'dockercart_checkout');
        }
        
        // Fallback - simple validation
        return strlen($licenseKey) > 10;
    }
}
