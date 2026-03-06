<?php
/**
 * DockerCart Logger
 * 
 * Централизованное логирование для всех модулей DockerCart
 * 
 * @package    DockerCart
 * @subpackage Logger
 * @version    1.0.0
 * @author     DockerCart Team
 * @license    Commercial
 */

class DockercartLogger {
    /**
     * @var object OpenCart registry instance
     */
    private $registry;
    
    /**
     * @var string Module name (e.g., 'checkout', 'redirects', 'filter')
     */
    private $module_name;
    
    /**
     * @var string Log file name
     */
    private $log_file;
    
    /**
     * @var string Config key for debug setting
     */
    private $debug_config_key;
    
    /**
     * @var string Prefix for log messages
     */
    private $log_prefix;
    
    /**
     * @var bool Cache for debug setting to avoid repeated checks
     */
    private $debug_enabled = null;

    /**
     * Constructor
     * 
     * @param object $registry OpenCart registry instance
     * @param string $module_name Module name (e.g., 'checkout', 'redirects', 'filter')
     * @param string $log_file Optional custom log file name (default: dockercart_{module}.log)
     */
    public function __construct($registry, $module_name, $log_file = null) {
        $this->registry = $registry;
        $this->module_name = $module_name;
        $this->log_file = $log_file ? $log_file : 'dockercart_' . $module_name . '.log';       
        $this->debug_config_key = 'module_dockercart_' . $module_name . '_debug';
         if ($module_name === 'license') {
            $this->debug_config_key = 'config_error_log';
        }
        $this->log_prefix = '[DockerCart ' . ucfirst($module_name) . ']';
    }

    /**
     * Write a log message
     * 
     * @param string $message Log message
     * @param string $level Log level (INFO, WARNING, ERROR, DEBUG)
     * @return void
     */
    public function write($message, $level = 'INFO') {
        if ($this->isDebugEnabled()) {
            $log = new Log($this->log_file);
            $formatted_message = $this->log_prefix . ' [' . $level . '] ' . $message;
            $log->write($formatted_message);
        }
    }

    /**
     * Write INFO level log
     * 
     * @param string $message Log message
     * @return void
     */
    public function info($message) {
        $this->write($message, 'INFO');
    }

    /**
     * Write WARNING level log
     * 
     * @param string $message Log message
     * @return void
     */
    public function warning($message) {
        $this->write($message, 'WARNING');
    }

    /**
     * Write ERROR level log
     * 
     * @param string $message Log message
     * @return void
     */
    public function error($message) {
        $this->write($message, 'ERROR');
    }

    /**
     * Write DEBUG level log
     * 
     * @param string $message Log message
     * @return void
     */
    public function debug($message) {
        $this->write($message, 'DEBUG');
    }

    /**
     * Write log with exception details
     * 
     * @param Exception $e Exception object
     * @param string $context Additional context message
     * @return void
     */
    public function exception($e, $context = '') {
        $message = ($context ? $context . ': ' : '') . $e->getMessage();
        $message .= ' in ' . $e->getFile() . ':' . $e->getLine();
        
        $this->error($message);
        
        // Log stack trace in debug mode
        if ($this->isDebugEnabled()) {
            $this->debug('Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Check if debug logging is enabled
     * Uses cache to avoid repeated config/DB checks
     * 
     * @return bool
     */
    private function isDebugEnabled() {
        // Return cached value if already checked
        if ($this->debug_enabled !== null) {
            return $this->debug_enabled;
        }

        $debug = false;
        
        // Try to get from config first
        if ($this->registry->has('config') && method_exists($this->registry->get('config'), 'get')) {
            $config = $this->registry->get('config');
            $debug = (bool)$config->get($this->debug_config_key);
        }

        // Fallback to database if config not found
        if (!$debug && $this->registry->has('db')) {
            $db = $this->registry->get('db');
            $query = $db->query(
                "SELECT `value` FROM `" . DB_PREFIX . "setting` " .
                "WHERE `key` = '" . $db->escape($this->debug_config_key) . "' " .
                "AND `store_id` = '0' LIMIT 1"
            );
            
            if ($query->num_rows) {
                $val = $query->row['value'];
                $debug = ((string)$val === '1' || (string)$val === 'true' || $val === true);
            }
        }

        // Cache the result
        $this->debug_enabled = $debug;
        
        return $debug;
    }

    /**
     * Force refresh debug setting cache (useful after settings update)
     * 
     * @return void
     */
    public function refreshDebugSetting() {
        $this->debug_enabled = null;
    }

    /**
     * Get current log file name
     * 
     * @return string
     */
    public function getLogFile() {
        return $this->log_file;
    }

    /**
     * Get module name
     * 
     * @return string
     */
    public function getModuleName() {
        return $this->module_name;
    }

    /**
     * Set custom log prefix
     * 
     * @param string $prefix Custom prefix
     * @return void
     */
    public function setLogPrefix($prefix) {
        $this->log_prefix = $prefix;
    }

    /**
     * Get current log prefix
     * 
     * @return string
     */
    public function getLogPrefix() {
        return $this->log_prefix;
    }
}
