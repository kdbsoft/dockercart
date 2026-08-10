#!/usr/bin/env php
<?php
/**
 * DockerCart Promo Auto-Renew — CLI Worker
 *
 * Renews expired auto-renewable promo entities (product specials, variant
 * specials, quantity discounts, gifts, coupons) once per day via the
 * scheduler daemon — independent of storefront visits.
 *
 * Mirrors the on-demand logic in:
 *   - ModelCatalogProduct::autoRenewProductEntities()
 *   - ModelExtensionTotalCoupon::autoRenewCoupon()
 * The INSERT ... SELECT ... WHERE NOT EXISTS shape is idempotent, so running
 * it twice on the same day is harmless.
 *
 * Usage:
 *   php /var/www/html/bin/dockercart_promo_renew.php
 *
 * Exit codes:
 *   0 — success
 *   1 — failure
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';

$config_path = __DIR__ . '/../config.php';

if (!is_file($config_path)) {
	fwrite(STDERR, "[promo-renew] ERROR: config.php not found at {$config_path}\n");
	exit(1);
}

require_once $config_path;

if (!defined('DIR_APPLICATION')) {
	fwrite(STDERR, "[promo-renew] ERROR: DIR_APPLICATION not defined\n");
	exit(1);
}

require_once DIR_SYSTEM . 'startup.php';

try {
	$config = new Config();
	$config->load('default');
	$config->load('catalog');

	$db = new DB(
		$config->get('db_engine')    ?: 'mysqli',
		$config->get('db_hostname')  ?: 'mariadb',
		$config->get('db_username')  ?: 'dockercart',
		$config->get('db_password')  ?: 'dockercart_password',
		$config->get('db_database')  ?: 'dockercart',
		$config->get('db_port')      ?: '3306'
	);

	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
	foreach ($query->rows as $result) {
		if (!$result['serialized']) {
			$config->set($result['key'], $result['value']);
		} else {
			$config->set($result['key'], json_decode($result['value'], true));
		}
	}

	$renewals = array(
		'product_special' => "
			INSERT INTO " . DB_PREFIX . "product_special (product_id, customer_group_id, priority, price, date_start, date_end, auto_renew)
			SELECT ps.product_id, ps.customer_group_id, ps.priority, ps.price,
				CURDATE(),
				DATE_ADD(CURDATE(), INTERVAL DATEDIFF(ps.date_end, ps.date_start) DAY),
				1
			FROM " . DB_PREFIX . "product_special ps
			WHERE ps.auto_renew = '1'
				AND ps.date_end < CURDATE()
				AND ps.date_end != '0000-00-00'
				AND NOT EXISTS (
					SELECT 1 FROM " . DB_PREFIX . "product_special ps2
					WHERE ps2.product_id = ps.product_id
						AND ps2.customer_group_id = ps.customer_group_id
						AND ps2.priority = ps.priority
						AND ps2.price = ps.price
						AND ps2.date_end > CURDATE()
				)",
		'variant_special' => "
			INSERT INTO " . DB_PREFIX . "dockercart_product_variant_special (variant_id, customer_group_id, priority, price, date_start, date_end, auto_renew)
			SELECT pvs.variant_id, pvs.customer_group_id, pvs.priority, pvs.price,
				CURDATE(),
				DATE_ADD(CURDATE(), INTERVAL DATEDIFF(pvs.date_end, pvs.date_start) DAY),
				1
			FROM " . DB_PREFIX . "dockercart_product_variant_special pvs
			WHERE pvs.auto_renew = '1'
				AND pvs.date_end < CURDATE()
				AND pvs.date_end != '0000-00-00'
				AND NOT EXISTS (
					SELECT 1 FROM " . DB_PREFIX . "dockercart_product_variant_special pvs2
					WHERE pvs2.variant_id = pvs.variant_id
						AND pvs2.customer_group_id = pvs.customer_group_id
						AND pvs2.priority = pvs.priority
						AND pvs2.price = pvs.price
						AND pvs2.date_end > CURDATE()
				)",
		'product_discount' => "
			INSERT INTO " . DB_PREFIX . "product_discount (product_id, customer_group_id, quantity, priority, price, date_start, date_end, auto_renew)
			SELECT pd.product_id, pd.customer_group_id, pd.quantity, pd.priority, pd.price,
				CURDATE(),
				DATE_ADD(CURDATE(), INTERVAL DATEDIFF(pd.date_end, pd.date_start) DAY),
				1
			FROM " . DB_PREFIX . "product_discount pd
			WHERE pd.auto_renew = '1'
				AND pd.date_end < CURDATE()
				AND pd.date_end != '0000-00-00'
				AND NOT EXISTS (
					SELECT 1 FROM " . DB_PREFIX . "product_discount pd2
					WHERE pd2.product_id = pd.product_id
						AND pd2.customer_group_id = pd.customer_group_id
						AND pd2.quantity = pd.quantity
						AND pd2.priority = pd.priority
						AND pd2.price = pd.price
						AND pd2.date_end > CURDATE()
				)",
		'product_gift' => "
			INSERT INTO " . DB_PREFIX . "product_gift (product_id, gift_product_id, minimum_quantity, date_start, date_end, auto_renew)
			SELECT pg.product_id, pg.gift_product_id, pg.minimum_quantity,
				CURDATE(),
				DATE_ADD(CURDATE(), INTERVAL DATEDIFF(pg.date_end, pg.date_start) DAY),
				1
			FROM " . DB_PREFIX . "product_gift pg
			WHERE pg.auto_renew = '1'
				AND pg.date_end < CURDATE()
				AND pg.date_end != '0000-00-00'
				AND NOT EXISTS (
					SELECT 1 FROM " . DB_PREFIX . "product_gift pg2
					WHERE pg2.product_id = pg.product_id
						AND pg2.gift_product_id = pg.gift_product_id
						AND pg2.minimum_quantity = pg.minimum_quantity
						AND pg2.date_end > CURDATE()
				)",
		'coupon' => "
			INSERT INTO `" . DB_PREFIX . "coupon` (name, code, type, discount, logged, shipping, total, date_start, date_end, uses_total, uses_customer, status, auto_renew, date_added)
			SELECT c.name, c.code, c.type, c.discount, c.logged, c.shipping, c.total,
				CURDATE(),
				DATE_ADD(CURDATE(), INTERVAL DATEDIFF(c.date_end, c.date_start) DAY),
				c.uses_total, c.uses_customer, '1', '1', NOW()
			FROM `" . DB_PREFIX . "coupon` c
			WHERE c.auto_renew = '1'
				AND c.date_end < CURDATE()
				AND c.date_end != '0000-00-00'
				AND NOT EXISTS (
					SELECT 1 FROM `" . DB_PREFIX . "coupon` c2
					WHERE c2.code = c.code
						AND c2.date_end > CURDATE()
				)",
	);

	$total = 0;

	foreach ($renewals as $entity => $sql) {
		$db->query($sql);
		$affected = $db->countAffected();
		$total += $affected;

		if ($affected > 0) {
			fwrite(STDOUT, "[promo-renew] {$entity}: renewed {$affected}\n");
		}
	}

	// Copy coupon descriptions for any newly created coupon rows: the new
	// coupon_id inherits the name from the previous (same-code) coupon.
	$db->query("
		INSERT IGNORE INTO `" . DB_PREFIX . "coupon_description` (coupon_id, language_id, name)
		SELECT c2.coupon_id, cd.language_id, cd.name
		FROM `" . DB_PREFIX . "coupon` c2
		INNER JOIN `" . DB_PREFIX . "coupon` c1 ON (c1.code = c2.code AND c1.coupon_id < c2.coupon_id)
		INNER JOIN `" . DB_PREFIX . "coupon_description` cd ON (cd.coupon_id = c1.coupon_id)
		WHERE c2.date_start = CURDATE() AND c2.auto_renew = '1'
		GROUP BY c2.coupon_id, cd.language_id, cd.name
	");

	fwrite(STDOUT, "[promo-renew] Done. Total renewed: " . $total . ".\n");

	exit(0);
} catch (\Throwable $e) {
	fwrite(STDERR, "[promo-renew] FATAL: " . $e->getMessage() . "\n");
	exit(1);
}
