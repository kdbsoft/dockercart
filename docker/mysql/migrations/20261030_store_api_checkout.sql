-- Customer (storefront) REST API — checkout flow.
--
-- Adds the persistence pieces of the API checkout:
--
--   * oc_customer.api_cart_scope — the session id the store API uses for the
--     customer's cart. Cart\Cart stores lines in oc_cart keyed by
--     (customer_id, session_id) and re-points every row of the customer onto
--     the *current* session on construction, so a stable per-customer scope id
--     makes the API cart and the browser cart the same cart. The value is a
--     real session id (it also keys the oc_session row that holds the applied
--     coupon / voucher / reward points), so it must be unguessable and is
--     generated once per customer on first use.
--
--   * oc_api_checkout — the checkout session: the addresses, the chosen
--     shipping/payment method, the comment and the terms agreement, mirroring
--     what the DockerCart one-page checkout keeps in the storefront session.
--     Addresses are snapshotted (country/zone names included) so a later
--     address-book edit cannot change a checkout that is already under way.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

-- ---------------------------------------------------------------- oc_customer
ALTER TABLE `oc_customer`
	ADD COLUMN IF NOT EXISTS `api_cart_scope` VARCHAR(32) NULL DEFAULT NULL AFTER `api_scopes`;

ALTER TABLE `oc_customer`
	ADD INDEX IF NOT EXISTS `idx_customer_api_cart_scope` (`api_cart_scope`);

-- ------------------------------------------------------------ oc_api_checkout
CREATE TABLE IF NOT EXISTS `oc_api_checkout` (
	`api_checkout_id` INT(11) NOT NULL AUTO_INCREMENT,
	`customer_id` INT(11) NOT NULL DEFAULT 0,
	`api_key_id` INT(11) NOT NULL DEFAULT 0,
	`store_id` INT(11) NOT NULL DEFAULT 0,
	`language_id` INT(11) NOT NULL DEFAULT 0,
	`currency_code` VARCHAR(3) NOT NULL DEFAULT '',
	`email` VARCHAR(96) NOT NULL DEFAULT '',
	`telephone` VARCHAR(32) NOT NULL DEFAULT '',
	`firstname` VARCHAR(32) NOT NULL DEFAULT '',
	`lastname` VARCHAR(32) NOT NULL DEFAULT '',
	`tax_number` VARCHAR(32) NOT NULL DEFAULT '',
	`payment_address` MEDIUMTEXT NULL DEFAULT NULL,
	`shipping_address` MEDIUMTEXT NULL DEFAULT NULL,
	`shipping_code` VARCHAR(128) NOT NULL DEFAULT '',
	`payment_code` VARCHAR(128) NOT NULL DEFAULT '',
	`comment` TEXT NULL DEFAULT NULL,
	`agree` TINYINT(1) NOT NULL DEFAULT 0,
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`api_checkout_id`),
	KEY `idx_api_checkout_customer` (`customer_id`),
	KEY `idx_api_checkout_key` (`api_key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
