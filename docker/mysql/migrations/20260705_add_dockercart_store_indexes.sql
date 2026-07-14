-- Add missing indexes for Extension Store queries
ALTER TABLE `oc_dockercart_extension_meta`
  ADD INDEX `idx_sku` (`sku`),
  ADD INDEX `idx_extension_install_id` (`extension_install_id`);

ALTER TABLE `oc_dockercart_license`
  ADD INDEX `idx_sku` (`sku`);
