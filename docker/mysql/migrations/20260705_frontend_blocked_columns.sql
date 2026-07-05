ALTER TABLE `oc_dockercart_license`
	ADD COLUMN IF NOT EXISTS `frontend_blocked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `consecutive_failures`,
	ADD COLUMN IF NOT EXISTS `original_status_value` VARCHAR(16) DEFAULT NULL AFTER `frontend_blocked`;
