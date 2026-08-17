<?php
/**
 * Mock promo data: customer groups, demo customers, per-group prices,
 * specials, quantity discounts, gifts, BXGY, coupons and reward points
 * for the demo catalog (products 5001-5090).
 *
 * Deterministic (mt_srand) so reruns give the same distribution.
 * Idempotent: deletes only its own rows (groups 2-5, demo emails/codes)
 * before re-seeding. Existing rows for customer group 1 are untouched.
 *
 * Uses PDO: mysqli::bind_param crashes (SIGSEGV) with the bundled mysqlnd
 * 8.5.7 client against this MariaDB, so plain prepared statements are used.
 *
 * Run inside the apache container: php bin/mock_promo_data.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
	fwrite(STDERR, "This script must be run from CLI.\n");
	exit(1);
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

$dbHost = getenv('DB_HOSTNAME') ?: 'mariadb';
$dbUser = getenv('DB_USERNAME') ?: 'dockercart';
$dbPass = getenv('DB_PASSWORD') ?: 'dockercart_password';
$dbName = getenv('DB_DATABASE') ?: 'dockercart';
$prefix = getenv('DB_PREFIX') ?: 'oc_';

try {
	$pdo = new PDO(
		"mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
		$dbUser,
		$dbPass,
		array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
	);
} catch (PDOException $e) {
	fwrite(STDERR, 'DB connect failed: ' . $e->getMessage() . PHP_EOL);
	exit(1);
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
$groupRetail    = 2;
$groupWholesale = 3;
$groupVip       = 4;
$groupGuest     = 5;
$promoGroups    = array($groupRetail, $groupWholesale, $groupVip);

$demoEmails = array(
	'retail@demo.local',
	'wholesale@demo.local',
	'vip@demo.local',
	'guest@demo.local',
);

$demoCouponCodes = array('DEMO10', 'DEMO500', 'DEMO_FREE_SHIP');

$stats = array(
	'customer_groups'        => 0,
	'customers'              => 0,
	'tax_links'              => 0,
	'product_group_prices'   => 0,
	'variant_group_prices'   => 0,
	'specials'               => 0,
	'variant_specials'       => 0,
	'discounts'              => 0,
	'variant_discounts'      => 0,
	'gifts'                  => 0,
	'bxgy'                   => 0,
	'rewards'                => 0,
	'coupons'                => 0,
);

// ---------------------------------------------------------------------------
// Load demo catalog products
// ---------------------------------------------------------------------------
$products = array(); // product_id => row

$stmt = $pdo->query(
	"SELECT product_id, price, quantity
	 FROM {$prefix}product
	 WHERE product_id BETWEEN 5001 AND 5090
	 ORDER BY product_id"
);

foreach ($stmt as $row) {
	$products[(int)$row['product_id']] = array(
		'product_id' => (int)$row['product_id'],
		'price'      => (float)$row['price'],
		'quantity'   => (float)$row['quantity'],
	);
}

$productIds = array_keys($products);

if (!$productIds) {
	fwrite(STDERR, 'No demo products found (5001-5090). Aborting.' . PHP_EOL);
	exit(1);
}

$inAll = implode(',', $productIds);
$inGroups = implode(',', $promoGroups);

// ---------------------------------------------------------------------------
// Cleanup: remove only rows this seeder owns (groups 2-5, demo emails/codes)
// ---------------------------------------------------------------------------
$pdo->exec("DELETE FROM {$prefix}customer_group_description WHERE customer_group_id IN (2,3,4,5)");
$pdo->exec("DELETE FROM {$prefix}customer_group WHERE customer_group_id IN (2,3,4,5)");

$stmt = $pdo->prepare("DELETE FROM {$prefix}customer WHERE email = ?");

foreach ($demoEmails as $email) {
	$stmt->execute(array($email));
}

$pdo->exec("DELETE FROM {$prefix}tax_rate_to_customer_group WHERE customer_group_id IN (2,3,4,5)");

$pdo->exec(
	"DELETE FROM {$prefix}product_special
	 WHERE product_id IN ({$inAll}) AND customer_group_id IN ({$inGroups})"
);
$pdo->exec(
	"DELETE FROM {$prefix}product_discount
	 WHERE product_id IN ({$inAll}) AND customer_group_id IN ({$inGroups})"
);
$pdo->exec(
	"DELETE FROM {$prefix}product_gift WHERE product_id IN ({$inAll}) OR gift_product_id IN ({$inAll})"
);
$pdo->exec(
	"DELETE FROM {$prefix}product_bxgy WHERE product_id IN ({$inAll}) OR reward_product_id IN ({$inAll})"
);
$pdo->exec(
	"DELETE FROM {$prefix}product_reward
	 WHERE product_id IN ({$inAll}) AND customer_group_id IN ({$inGroups})"
);
$pdo->exec(
	"DELETE FROM {$prefix}dockercart_product_customer_group_price
	 WHERE product_id IN ({$inAll}) AND customer_group_id IN ({$inGroups})"
);

// Variant promo tables: delete rows belonging to demo products before re-seeding.
// (Rows for variants that no longer exist are removed at the same time.)
$pdo->exec(
	"DELETE vs FROM {$prefix}dockercart_product_variant_special vs
	 INNER JOIN {$prefix}product_variant v ON (v.variant_id = vs.variant_id)
	 WHERE v.product_id IN ({$inAll}) AND vs.customer_group_id IN ({$inGroups})"
);
$pdo->exec(
	"DELETE vd FROM {$prefix}dockercart_product_variant_discount vd
	 INNER JOIN {$prefix}product_variant v ON (v.variant_id = vd.variant_id)
	 WHERE v.product_id IN ({$inAll}) AND (vd.customer_group_id IN ({$inGroups}) OR vd.customer_group_id = 1)"
);
$pdo->exec(
	"DELETE vcgp FROM {$prefix}dockercart_product_variant_customer_group_price vcgp
	 INNER JOIN {$prefix}product_variant v ON (v.variant_id = vcgp.variant_id)
	 WHERE v.product_id IN ({$inAll}) AND vcgp.customer_group_id IN ({$inGroups})"
);

$couponIds = array();

$stmt = $pdo->query(
	"SELECT coupon_id FROM {$prefix}coupon WHERE code IN ('" . implode("','", $demoCouponCodes) . "')"
);

foreach ($stmt as $row) {
	$couponIds[] = (int)$row['coupon_id'];
}

if ($couponIds) {
	$inCoupons = implode(',', $couponIds);
	$pdo->exec("DELETE FROM {$prefix}coupon_description WHERE coupon_id IN ({$inCoupons})");
	$pdo->exec("DELETE FROM {$prefix}coupon_category WHERE coupon_id IN ({$inCoupons})");
	$pdo->exec("DELETE FROM {$prefix}coupon_product WHERE coupon_id IN ({$inCoupons})");
	$pdo->exec("DELETE FROM {$prefix}coupon WHERE coupon_id IN ({$inCoupons})");
}

// ---------------------------------------------------------------------------
// 1. Customer groups (fixed ids, INSERT IGNORE for idempotency)
// ---------------------------------------------------------------------------
$groupDefinitions = array(
	$groupRetail    => array('approval' => 0, 'discount' => 0.00, 'sort' => 2, 'names' => array(1 => 'Retail', 2 => 'Роздрібні', 3 => 'Розничные')),
	$groupWholesale => array('approval' => 1, 'discount' => 5.00, 'sort' => 3, 'names' => array(1 => 'Wholesale', 2 => 'Оптові', 3 => 'Оптовые')),
	$groupVip       => array('approval' => 0, 'discount' => 10.00, 'sort' => 4, 'names' => array(1 => 'VIP', 2 => 'VIP', 3 => 'VIP')),
	$groupGuest     => array('approval' => 0, 'discount' => 0.00, 'sort' => 5, 'names' => array(1 => 'Guest', 2 => 'Гість', 3 => 'Гость')),
);

$groupStmt = $pdo->prepare(
	"INSERT IGNORE INTO {$prefix}customer_group (customer_group_id, approval, discount_percent, markup_percent, sort_order)
	 VALUES (?, ?, ?, 0.00, ?)"
);
$descStmt = $pdo->prepare(
	"INSERT IGNORE INTO {$prefix}customer_group_description (customer_group_id, language_id, name, description)
	 VALUES (?, ?, ?, '')"
);

foreach ($groupDefinitions as $gid => $def) {
	$groupStmt->execute(array($gid, $def['approval'], $def['discount'], $def['sort']));

	foreach ($def['names'] as $langId => $name) {
		$descStmt->execute(array($gid, $langId, $name));
	}

	$stats['customer_groups']++;
}

// ---------------------------------------------------------------------------
// 2. Tax links: same tax rates as group 1
// ---------------------------------------------------------------------------
$taxRateIds = array();

$stmt = $pdo->query("SELECT tax_rate_id FROM {$prefix}tax_rate_to_customer_group WHERE customer_group_id = 1");

foreach ($stmt as $row) {
	$taxRateIds[] = (int)$row['tax_rate_id'];
}

$taxStmt = $pdo->prepare(
	"INSERT IGNORE INTO {$prefix}tax_rate_to_customer_group (tax_rate_id, customer_group_id) VALUES (?, ?)"
);

foreach ($taxRateIds as $taxRateId) {
	foreach ($promoGroups as $gid) {
		$taxStmt->execute(array($taxRateId, $gid));
		$stats['tax_links']++;
	}
}

// ---------------------------------------------------------------------------
// 3. Demo customers (password: demo123, OpenCart salt+sha1)
// ---------------------------------------------------------------------------
$customerDefinitions = array(
	array('group' => $groupRetail,    'email' => 'retail@demo.local',    'firstname' => 'Demo', 'lastname' => 'Retail'),
	array('group' => $groupWholesale, 'email' => 'wholesale@demo.local', 'firstname' => 'Demo', 'lastname' => 'Wholesale'),
	array('group' => $groupVip,       'email' => 'vip@demo.local',       'firstname' => 'Demo', 'lastname' => 'VIP'),
	array('group' => $groupGuest,     'email' => 'guest@demo.local',     'firstname' => 'Demo', 'lastname' => 'Guest'),
);

$salt = 'demo';
$demoPassword = 'demo123';

$customerStmt = $pdo->prepare(
	"INSERT INTO {$prefix}customer
	 (customer_group_id, store_id, language_id, firstname, lastname, email, telephone,
	  company, tax_number, fax, password, salt, cart, wishlist, newsletter, address_id,
	  custom_field, ip, status, safe, token, code, date_added)
	 VALUES (?, 0, 1, ?, ?, ?, '', '', '', '', ?, ?, NULL, NULL, 0, 0, '', '127.0.0.1', 1, 1, '', ?, NOW())"
);

foreach ($customerDefinitions as $c) {
	$customerStmt->execute(array(
		$c['group'],
		$c['firstname'],
		$c['lastname'],
		$c['email'],
		sha1($salt . sha1($salt . sha1($demoPassword))),
		$salt,
		sha1($c['email']),
	));
	$stats['customers']++;
}

// ---------------------------------------------------------------------------
// Deterministic RNG (same seed as mock_generate_variants.php)
// ---------------------------------------------------------------------------
mt_srand(20260809);

// ---------------------------------------------------------------------------
// 4. Per-group product prices (Retail -3%, Wholesale -10%, VIP -18%)
// ---------------------------------------------------------------------------
$groupPriceFactors = array(
	$groupRetail    => 0.97,
	$groupWholesale => 0.90,
	$groupVip       => 0.82,
);

$groupPriceStmt = $pdo->prepare(
	"INSERT IGNORE INTO {$prefix}dockercart_product_customer_group_price (product_id, customer_group_id, price)
	 VALUES (?, ?, ?)"
);

foreach ($products as $pid => $p) {
	if ($p['price'] <= 0 || mt_rand(1, 100) > 50) {
		continue;
	}

	foreach ($groupPriceFactors as $gid => $factor) {
		$groupPriceStmt->execute(array($pid, $gid, round($p['price'] * $factor, 2)));
		$stats['product_group_prices']++;
	}
}

// ---------------------------------------------------------------------------
// 5. Specials per group
// ---------------------------------------------------------------------------
$specialStmt = $pdo->prepare(
	"INSERT INTO {$prefix}product_special
	 (product_id, customer_group_id, priority, price, date_start, date_end, auto_renew, date_added)
	 VALUES (?, ?, 1, ?, ?, ?, ?, NOW())"
);

foreach ($products as $pid => $p) {
	if ($p['price'] <= 0) {
		continue;
	}

	// Retail: ~30%, Wholesale: ~20%, VIP: ~25%
	$specialRoll = mt_rand(1, 100);

	if ($specialRoll <= 30) {
		insertSpecial($specialStmt, $stats, $pid, $groupRetail, $p['price'] * (float)mt_rand(70, 90) / 100.0);
	}

	if ($specialRoll <= 20) {
		insertSpecial($specialStmt, $stats, $pid, $groupWholesale, $p['price'] * (float)mt_rand(75, 85) / 100.0);
	}

	if ($specialRoll > 30 && $specialRoll <= 55) {
		insertSpecial($specialStmt, $stats, $pid, $groupVip, $p['price'] * (float)mt_rand(65, 80) / 100.0);
	}
}

// ---------------------------------------------------------------------------
// 6. Quantity discounts (tiers 2 / 5 / 10) — plain products only.
// Configurable products price by variant, so their quantity discounts are
// generated per variant in section 11c instead.
// ---------------------------------------------------------------------------
$discountStmt = $pdo->prepare(
	"INSERT INTO {$prefix}product_discount
	 (product_id, customer_group_id, quantity, priority, price, date_start, date_end, auto_renew, date_added)
	 VALUES (?, ?, ?, 1, ?, '0000-00-00', '0000-00-00', 0, NOW())"
);

$configurableProductIds = array();

$stmt = $pdo->query(
	"SELECT DISTINCT product_id
	 FROM {$prefix}product_variant
	 WHERE product_id IN ({$inAll})"
);

foreach ($stmt as $row) {
	$configurableProductIds[(int)$row['product_id']] = true;
}

foreach ($products as $pid => $p) {
	if ($p['price'] <= 0 || isset($configurableProductIds[$pid]) || mt_rand(1, 100) > 25) {
		continue;
	}

	foreach ($promoGroups as $gid) {
		$tierCount = mt_rand(2, 3);

		for ($i = 1; $i <= $tierCount; $i++) {
			$quantity = (int)array(2, 5, 10)[$i - 1];
			$price = $p['price'] * (float)array(0.95, 0.90, 0.85)[$i - 1];

			// Wholesale/VIP get a bit more off
			if ($gid === $groupWholesale) {
				$price *= 0.97;
			}

			if ($gid === $groupVip) {
				$price *= 0.95;
			}

			$discountStmt->execute(array($pid, $gid, $quantity, $price));
			$stats['discounts']++;
		}
	}
}

// ---------------------------------------------------------------------------
// 7. Gifts (trigger product -> cheaper product as gift)
// ---------------------------------------------------------------------------
$inStockIds = array();

foreach ($products as $pid => $p) {
	if ($p['quantity'] > 0) {
		$inStockIds[] = $pid;
	}
}

$giftStmt = $pdo->prepare(
	"INSERT INTO {$prefix}product_gift
	 (product_id, gift_product_id, minimum_quantity, date_start, date_end, auto_renew, date_added)
	 VALUES (?, ?, ?, '0000-00-00', '0000-00-00', 0, NOW())"
);

foreach ($products as $pid => $p) {
	if ($p['quantity'] <= 0 || $p['price'] <= 0 || mt_rand(1, 100) > 15) {
		continue;
	}

	// Pick a cheaper in-stock gift, prefer >= 3x cheaper
	$giftCandidates = array();

	foreach ($inStockIds as $gid) {
		if ($gid !== $pid && $p['price'] / 3 >= $products[$gid]['price']) {
			$giftCandidates[] = $gid;
		}
	}

	if (!$giftCandidates) {
		continue;
	}

	$giftStmt->execute(array(
		$pid,
		$giftCandidates[mt_rand(0, count($giftCandidates) - 1)],
		mt_rand(1, 3),
	));
	$stats['gifts']++;
}

// ---------------------------------------------------------------------------
// 8. Buy X Get Y
// ---------------------------------------------------------------------------
$bxgyStmt = $pdo->prepare(
	"INSERT INTO {$prefix}product_bxgy
	 (product_id, reward_product_id, trigger_quantity, discount_type, discount_value, date_start, date_end, auto_renew, date_added)
	 VALUES (?, ?, ?, ?, ?, '0000-00-00', '0000-00-00', 0, NOW())"
);

foreach ($products as $pid => $p) {
	if ($p['price'] <= 0 || mt_rand(1, 100) > 12) {
		continue;
	}

	$rewardCandidates = array();

	foreach ($productIds as $rid) {
		if ($rid !== $pid) {
			$rewardCandidates[] = $rid;
		}
	}

	if (!$rewardCandidates) {
		continue;
	}

	// 1/3 rules are "free", the rest percentage (50-90%)
	$isFree = (mt_rand(1, 3) === 1);
	$discountType = $isFree ? 'free' : 'percentage';

	$bxgyStmt->execute(array(
		$pid,
		$rewardCandidates[mt_rand(0, count($rewardCandidates) - 1)],
		mt_rand(1, 3),
		$discountType,
		$isFree ? 0.00 : (float)mt_rand(50, 90),
	));
	$stats['bxgy']++;
}

// ---------------------------------------------------------------------------
// 9. Reward points per group (Retail x1, Wholesale x1.5, VIP x2)
// ---------------------------------------------------------------------------
$rewardFactors = array(
	$groupRetail    => 1.0,
	$groupWholesale => 1.5,
	$groupVip       => 2.0,
);

$rewardStmt = $pdo->prepare(
	"INSERT INTO {$prefix}product_reward (product_id, customer_group_id, points) VALUES (?, ?, ?)"
);

foreach ($products as $pid => $p) {
	if ($p['price'] <= 0 || mt_rand(1, 100) > 40) {
		continue;
	}

	foreach ($rewardFactors as $gid => $factor) {
		$points = (int)round($p['price'] / 100 * $factor);

		if ($points <= 0) {
			continue;
		}

		$rewardStmt->execute(array($pid, $gid, $points));
		$stats['rewards']++;
	}
}

// ---------------------------------------------------------------------------
// 10. Coupons
// ---------------------------------------------------------------------------
$couponDefinitions = array(
	array(
		'code'       => 'DEMO10',
		'type'       => 'P',
		'discount'   => 10.00,
		'shipping'   => 0,
		'total'      => 1000.00,
		'uses_total' => 100,
		'names'      => array(1 => '-10% Demo Coupon', 2 => '-10% Демо купон', 3 => '-10% Демо купон'),
	),
	array(
		'code'       => 'DEMO500',
		'type'       => 'F',
		'discount'   => 500.00,
		'shipping'   => 0,
		'total'      => 5000.00,
		'uses_total' => 100,
		'names'      => array(1 => '500 UAH Off Demo Coupon', 2 => '-500 грн Демо купон', 3 => '-500 грн Демо купон'),
	),
	array(
		'code'       => 'DEMO_FREE_SHIP',
		'type'       => 'P',
		'discount'   => 0.00,
		'shipping'   => 1,
		'total'      => 2000.00,
		'uses_total' => 100,
		'names'      => array(1 => 'Free Shipping Demo Coupon', 2 => 'Безкоштовна доставка Демо', 3 => 'Бесплатная доставка Демо'),
	),
);

$couponStmt = $pdo->prepare(
	"INSERT INTO {$prefix}coupon
	 (name, code, type, discount, logged, shipping, total, date_start, date_end,
	  uses_total, uses_customer, status, auto_renew, date_added)
	 VALUES (?, ?, ?, ?, 0, ?, ?, '0000-00-00', DATE_ADD(NOW(), INTERVAL 90 DAY),
	         ?, '5', 1, 0, NOW())"
);
$couponDescStmt = $pdo->prepare(
	"INSERT INTO {$prefix}coupon_description (coupon_id, language_id, name) VALUES (?, ?, ?)"
);

foreach ($couponDefinitions as $c) {
	$couponStmt->execute(array(
		$c['names'][1],
		$c['code'],
		$c['type'],
		$c['discount'],
		$c['shipping'],
		$c['total'],
		$c['uses_total'],
	));

	$couponId = (int)$pdo->lastInsertId();

	foreach ($c['names'] as $langId => $name) {
		$couponDescStmt->execute(array($couponId, $langId, $name));
	}

	$stats['coupons']++;
}

// ---------------------------------------------------------------------------
// 11. Variant per-group prices, specials and discounts
// ---------------------------------------------------------------------------
$variants = array(); // variant_id => row

$stmt = $pdo->query(
	"SELECT v.variant_id, v.product_id, v.price, v.quantity
	 FROM {$prefix}product_variant v
	 INNER JOIN {$prefix}product_configurable pc ON (pc.product_id = v.product_id)
	 WHERE v.product_id IN ({$inAll}) AND v.status = 1
	 ORDER BY v.variant_id"
);

foreach ($stmt as $row) {
	$variants[(int)$row['variant_id']] = array(
		'variant_id' => (int)$row['variant_id'],
		'product_id' => (int)$row['product_id'],
		'price'      => (float)$row['price'],
		'quantity'   => (float)$row['quantity'],
	);
}

if ($variants) {
	$variantGroupPriceStmt = $pdo->prepare(
		"INSERT IGNORE INTO {$prefix}dockercart_product_variant_customer_group_price (variant_id, customer_group_id, price)
		 VALUES (?, ?, ?)"
	);

	// 11a. Per-group variant prices (~40% of variants)
	foreach ($variants as $vid => $v) {
		if ($v['price'] <= 0 || mt_rand(1, 100) > 40) {
			continue;
		}

		$variantGroupPriceStmt->execute(array($vid, $groupWholesale, round($v['price'] * 0.92, 2)));
		$variantGroupPriceStmt->execute(array($vid, $groupVip, round($v['price'] * 0.85, 2)));
		$stats['variant_group_prices'] += 2;
	}

	$variantSpecialStmt = $pdo->prepare(
		"INSERT INTO {$prefix}dockercart_product_variant_special
		 (variant_id, customer_group_id, priority, price, date_start, date_end, auto_renew)
		 VALUES (?, ?, 1, ?, '0000-00-00', '0000-00-00', 0)"
	);

	// 11b. Variant specials (~25% of variants, groups 2-4)
	foreach ($variants as $vid => $v) {
		if ($v['price'] <= 0 || mt_rand(1, 100) > 25) {
			continue;
		}

		$variantSpecialStmt->execute(array(
			$vid,
			$promoGroups[mt_rand(0, 2)],
			round($v['price'] * (float)mt_rand(70, 88) / 100.0, 2),
		));
		$stats['variant_specials']++;
	}

	$variantDiscountStmt = $pdo->prepare(
		"INSERT INTO {$prefix}dockercart_product_variant_discount
		 (variant_id, customer_group_id, quantity, priority, price, date_start, date_end, auto_renew)
		 VALUES (?, ?, ?, 1, ?, '0000-00-00', '0000-00-00', 0)"
	);

	// 11c. Variant quantity discounts (~35% of variants, 1-3 tiers).
	// Group 1 (default/guest) is included so the storefront shows them.
	$variantDiscountGroups = array(1, $groupRetail, $groupWholesale, $groupVip);

	foreach ($variants as $vid => $v) {
		if ($v['price'] <= 0 || mt_rand(1, 100) > 35) {
			continue;
		}

		$variantGid = $variantDiscountGroups[mt_rand(0, count($variantDiscountGroups) - 1)];
		$tierCount = mt_rand(1, 3);

		for ($i = 1; $i <= $tierCount; $i++) {
			$variantDiscountStmt->execute(array(
				$vid,
				$variantGid,
				(int)array(2, 5, 10)[$i - 1],
				round($v['price'] * (float)array(0.93, 0.87, 0.80)[$i - 1], 2),
			));
			$stats['variant_discounts']++;
		}
	}
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
echo 'customer_groups: ' . $stats['customer_groups'] . PHP_EOL;
echo 'customers: ' . $stats['customers'] . PHP_EOL;
echo 'tax_links: ' . $stats['tax_links'] . PHP_EOL;
echo 'product_group_prices: ' . $stats['product_group_prices'] . PHP_EOL;
echo 'variant_group_prices: ' . $stats['variant_group_prices'] . PHP_EOL;
echo 'specials: ' . $stats['specials'] . PHP_EOL;
echo 'variant_specials: ' . $stats['variant_specials'] . PHP_EOL;
echo 'discounts: ' . $stats['discounts'] . PHP_EOL;
echo 'variant_discounts: ' . $stats['variant_discounts'] . PHP_EOL;
echo 'gifts: ' . $stats['gifts'] . PHP_EOL;
echo 'bxgy: ' . $stats['bxgy'] . PHP_EOL;
echo 'rewards: ' . $stats['rewards'] . PHP_EOL;
echo 'coupons: ' . $stats['coupons'] . PHP_EOL;

/**
 * Insert a product special row with a random date window.
 *
 * @param array<string, int> $stats
 */
function insertSpecial(PDOStatement $stmt, array &$stats, int $productId, int $groupId, float $price): void
{
	$dateStart = '0000-00-00';

	if (mt_rand(0, 2) === 0) {
		$dateStart = date('Y-m-d', strtotime('-' . mt_rand(10, 40) . ' days'));
	}

	$dateEnd = '0000-00-00';

	if (mt_rand(0, 1) === 0) {
		$dateEnd = date('Y-m-d', strtotime('+' . mt_rand(30, 90) . ' days'));
	}

	$autoRenew = (mt_rand(0, 3) === 0) ? 1 : 0;

	$stmt->execute(array($productId, $groupId, $price, $dateStart, $dateEnd, $autoRenew));
	$stats['specials']++;
}
