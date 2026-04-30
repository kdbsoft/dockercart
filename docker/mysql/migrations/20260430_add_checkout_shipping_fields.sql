-- Add default country/zone configuration for DockerCart Checkout
-- These settings pre-fill country/zone on checkout page so shipping methods show immediately

INSERT IGNORE INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES
(0, 'module_dockercart_checkout', 'module_dockercart_checkout_default_country_id', '', 0),
(0, 'module_dockercart_checkout', 'module_dockercart_checkout_default_zone_id', '', 0);
