<?php
/**
 * Generate product variants for demo data.
 * Axes = product options with >=2 values, excluding service options
 * (Delivery=12, Accessories=11). For each product: cartesian product of axis
 * values, per-variant price = base + option value surcharges, own quantity,
 * SKU/model, some variants get specials (discounts), some are out of stock.
 *
 * Run inside the apache container: php bin/mock_generate_variants.php
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$dbHost = getenv('DB_HOSTNAME') ?: 'mariadb';
$dbUser = getenv('DB_USERNAME') ?: 'dockercart';
$dbPass = getenv('DB_PASSWORD') ?: 'dockercart_password';
$dbName = getenv('DB_DATABASE') ?: 'dockercart';
$prefix = getenv('DB_PREFIX') ?: 'oc_';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, 3306);

if ($mysqli->connect_errno) {
	fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
	exit(1);
}

$mysqli->set_charset('utf8mb4');

// ---------------------------------------------------------------------------
// 1. Load products (base price, model, quantity)
// ---------------------------------------------------------------------------
$products = array();

$res = $mysqli->query("SELECT product_id, model, price, quantity, weight, weight_class_id FROM {$prefix}product ORDER BY product_id");

while ($row = $res->fetch_assoc()) {
	$products[(int)$row['product_id']] = array(
		'product_id'       => (int)$row['product_id'],
		'model'            => (string)$row['model'],
		'price'            => (float)$row['price'],
		'quantity'         => (float)$row['quantity'],
		'weight'           => (float)$row['weight'],
		'weight_class_id'  => (int)$row['weight_class_id'],
		'axes'             => array(), // option_id => list of value rows
	);
}

// ---------------------------------------------------------------------------
// 2. Load option values per product (from product_option_value), plus the
//    surcharge (price + prefix). Skip service options.
// ---------------------------------------------------------------------------
$serviceOptionIds = array(11, 12); // Accessories, Delivery

$res = $mysqli->query(
	"SELECT pov.product_id, pov.option_id, pov.option_value_id, pov.price, pov.price_prefix, ov.sort_order
	 FROM {$prefix}product_option_value pov
	 LEFT JOIN {$prefix}option_value ov ON (ov.option_value_id = pov.option_value_id)
	 WHERE pov.option_id NOT IN (11, 12)
	 ORDER BY pov.product_id, pov.option_id, ov.sort_order, pov.option_value_id"
);

$valueCountByProductOption = array();

while ($row = $res->fetch_assoc()) {
	$pid = (int)$row['product_id'];
	$oid = (int)$row['option_id'];
	$ovId = (int)$row['option_value_id'];

	if (!isset($products[$pid])) {
		continue;
	}

	$surcharge = (float)$row['price'];
	$prefixSurcharge = (string)$row['price_prefix'] === '-' ? -$surcharge : $surcharge;

	// Aggregate surcharge per (option, value): use max (in case of duplicates)
	$key = $pid . ':' . $oid . ':' . $ovId;

	if (!isset($valueCountByProductOption[$key])) {
		$valueCountByProductOption[$key] = array(
			'product_id'  => $pid,
			'option_id'   => $oid,
			'option_value_id' => $ovId,
			'surcharge'   => $prefixSurcharge,
			'sort_order'  => (int)$row['sort_order'],
		);
	}
}

// Group by product+option, keep only options with >=2 distinct values
foreach ($valueCountByProductOption as $v) {
	$pid = $v['product_id'];
	$oid = $v['option_id'];
	$products[$pid]['axes'][$oid][$v['option_value_id']] = $v;
}

foreach ($products as $pid => &$p) {
	foreach ($p['axes'] as $oid => $values) {
		if (count($values) < 2) {
			unset($p['axes'][$oid]);
		}
	}
}
unset($p);

// ---------------------------------------------------------------------------
// 3. Product name suffixes for model/SKU readability
// ---------------------------------------------------------------------------
$valueSuffix = array(
	1 => array(1 => 'BLK', 2 => 'WHT', 3 => 'SLV', 4 => 'BLU', 5 => 'GRN', 6 => 'RED'),
	2 => array(7 => 'XS', 8 => 'S', 9 => 'M', 10 => 'L', 11 => 'XL', 12 => 'XXL'),
	3 => array(13 => '128', 14 => '256', 15 => '512'),
	4 => array(16 => '8G', 17 => '12G', 18 => '16G'),
	5 => array(19 => 'W12', 20 => 'W24'),
	6 => array(21 => 'NEW', 22 => 'USED'),
	7 => array(23 => 'STD', 24 => 'DLX'),
	8 => array(25 => 'LTH', 26 => 'SWD', 27 => 'TXT'),
	9 => array(28 => '1L', 29 => '2L', 30 => '5L'),
	10 => array(31 => '13', 32 => '14', 33 => '16'),
);

// ---------------------------------------------------------------------------
// 4. Clear old demo variants (keep structure) — only for demo product ids
// ---------------------------------------------------------------------------
$productIds = array_keys($products);

if ($productIds) {
	$in = implode(',', $productIds);
	$mysqli->query("DELETE vv FROM {$prefix}product_variant_value vv INNER JOIN {$prefix}product_variant v ON (v.variant_id = vv.variant_id) WHERE v.product_id IN ({$in})");
	$mysqli->query("DELETE FROM {$prefix}product_variant WHERE product_id IN ({$in})");
	$mysqli->query("DELETE FROM {$prefix}product_configurable_option WHERE product_id IN ({$in})");
	$mysqli->query("DELETE FROM {$prefix}product_configurable WHERE product_id IN ({$in})");
}

// ---------------------------------------------------------------------------
// 5. Generate variants
// ---------------------------------------------------------------------------
$totalVariants = 0;
$totalSpecials = 0;
$totalWithSpecials = 0;
$totalOutOfStock = 0;

$specials = array(); // variant_id => list of special rows (set after insert)

// Deterministic pseudo-randomness so reruns give the same demo data
mt_srand(20260809);

foreach ($products as $pid => $p) {
	if (empty($p['axes'])) {
		continue; // no axes -> not configurable
	}

	// Build cartesian product of axis values
	$axisList = array();

	foreach ($p['axes'] as $oid => $values) {
		usort($values, function ($a, $b) {
			return $a['sort_order'] <=> $b['sort_order'];
		});
		$axisList[] = array(
			'option_id' => $oid,
			'values'    => array_values($values),
		);
	}

	$combinations = array(array());

	foreach ($axisList as $axis) {
		$new = array();

		foreach ($combinations as $comb) {
			foreach ($axis['values'] as $v) {
				$new[] = $comb + array($axis['option_id'] => $v);
			}
		}

		$combinations = $new;
	}

	$basePrice = $p['price'];
	$baseQty = (float)$p['quantity'];

	// Precompute surcharge per (option_id, value_id)
	$surchargeMap = array();

	foreach ($p['axes'] as $oid => $values) {
		foreach ($values as $v) {
			$surchargeMap[$oid . ':' . $v['option_value_id']] = $v['surcharge'];
		}
	}

	// Decide how many variants are on sale (roughly a third, min 1, max n-1)
	$n = count($combinations);
	$saleCount = $n > 1 ? max(1, (int)round($n / 3)) : 0;

	if ($saleCount >= $n) {
		$saleCount = $n - 1;
	}

	$saleIdx = array();

	while (count($saleIdx) < $saleCount) {
		$idx = mt_rand(0, $n - 1);

		if (!isset($saleIdx[$idx])) {
			$saleIdx[$idx] = true;
		}
	}

	$sortOrder = 0;

	foreach ($combinations as $idx => $comb) {
		// Price: base + sum of surcharges
		$variantPrice = $basePrice;

		foreach ($comb as $oid => $v) {
			$variantPrice += isset($surchargeMap[$oid . ':' . $v['option_value_id']])
				? $surchargeMap[$oid . ':' . $v['option_value_id']]
				: 0.0;
		}

		if ($variantPrice < 0) {
			$variantPrice = 0;
		}

		// Quantity: around base, some random variation; keep some at 0
		$variantQty = $baseQty;

		if ($variantQty > 0) {
			$variantQty = max(0, (int)round($baseQty + mt_rand(-5, 8)));
		} else {
			// Products with 0 base qty: make some variants in stock anyway
			$variantQty = (mt_rand(0, 3) === 0) ? 0 : mt_rand(3, 25);
		}

		// Model/SKU: DEMO-<pid>-<suffixes>
		$suffixParts = array();

		foreach ($axisList as $axis) {
			$oid = $axis['option_id'];
			$ovId = $comb[$oid]['option_value_id'];

			if (isset($valueSuffix[$oid][$ovId])) {
				$suffixParts[] = $valueSuffix[$oid][$ovId];
			}
		}

		$suffix = $suffixParts ? '-' . implode('-', $suffixParts) : '';
		$model = $p['model'] . $suffix;
		$sku = $p['model'] . $suffix;

		$status = (mt_rand(0, 9) === 0) ? 0 : 1; // ~10% disabled variants
		$subtract = (int)($variantQty > 0);

		// Insert variant
		$stmt = $mysqli->prepare(
			"INSERT INTO {$prefix}product_variant
			 (product_id, sku, model, upc, ean, jan, isbn, mpn, price, quantity, subtract, weight, weight_class_id, image, variant_hash, sort_order, status)
			 VALUES (?, ?, ?, '', '', '', '', '', ?, ?, ?, ?, ?, '', ?, ?, ?)"
		);

		$hash = buildHash($comb);

		$stmt->bind_param('issddidisii', $pid, $sku, $model, $variantPrice, $variantQty, $subtract, $p['weight'], $p['weight_class_id'], $hash, $sortOrder, $status);
		$stmt->execute();

		if ($stmt->errno) {
			fwrite(STDERR, 'variant insert error (pid ' . $pid . '): ' . $stmt->error . PHP_EOL);
			exit(1);
		}

		$variantId = (int)$mysqli->insert_id;
		$stmt->close();

		// Insert variant values
		$valueStmt = $mysqli->prepare(
			"INSERT INTO {$prefix}product_variant_value (variant_id, product_id, option_id, option_value_id) VALUES (?, ?, ?, ?)"
		);

		foreach ($comb as $oid => $v) {
			$valueStmt->bind_param('iiii', $variantId, $pid, $oid, $v['option_value_id']);
			$valueStmt->execute();

			if ($valueStmt->errno) {
				fwrite(STDERR, 'variant value insert error: ' . $valueStmt->error . PHP_EOL);
				exit(1);
			}
		}

		$valueStmt->close();

		if ($variantQty <= 0) {
			$totalOutOfStock++;
		}

		if (isset($saleIdx[$idx]) && $variantPrice > 0) {
			$discountPct = mt_rand(10, 30);
			$specialPrice = round($variantPrice * (100 - $discountPct) / 100, 2);
			$specialPrice = max(1, $specialPrice);

			// Active window: some end in the future, some are open-ended
			$dateStart = '0000-00-00';

			if (mt_rand(0, 2) === 0) {
				$dateStart = date('Y-m-d', strtotime('-' . mt_rand(10, 40) . ' days'));
			}

			$dateEnd = '0000-00-00';

			if (mt_rand(0, 1) === 0) {
				$dateEnd = date('Y-m-d', strtotime('+' . mt_rand(15, 90) . ' days'));
			}

			$specials[] = array(
				'variant_id'       => $variantId,
				'customer_group_id' => 1,
				'priority'         => 1,
				'price'            => $specialPrice,
				'date_start'       => $dateStart,
				'date_end'         => $dateEnd,
				'auto_renew'       => (mt_rand(0, 3) === 0) ? 1 : 0,
			);

			$totalSpecials++;
		}

		$totalVariants++;
		$sortOrder++;
	}

	// 6. Mark product as configurable + register axes
	if ($n > 0) {
		$mysqli->query(
			"INSERT INTO {$prefix}product_configurable (product_id, is_configurable, default_variant_id)
			 VALUES ({$pid}, 1, 0)"
		);

		$pos = 0;

		foreach ($axisList as $axis) {
			$stmt = $mysqli->prepare(
				"INSERT INTO {$prefix}product_configurable_option (product_id, option_id, position) VALUES (?, ?, ?)"
			);
			$stmt->bind_param('iii', $pid, $axis['option_id'], $pos);
			$stmt->execute();
			$stmt->close();
			$pos++;
		}

		$totalWithSpecials++;
	}
}

// ---------------------------------------------------------------------------
// 7. Insert specials
// ---------------------------------------------------------------------------
foreach ($specials as $sp) {
	$stmt = $mysqli->prepare(
		"INSERT INTO {$prefix}dockercart_product_variant_special
		 (variant_id, customer_group_id, priority, price, date_start, date_end, auto_renew)
		 VALUES (?, ?, ?, ?, ?, ?, ?)"
	);
	$stmt->bind_param('iiidssi', $sp['variant_id'], $sp['customer_group_id'], $sp['priority'], $sp['price'], $sp['date_start'], $sp['date_end'], $sp['auto_renew']);
	$stmt->execute();
	$stmt->close();
}

// ---------------------------------------------------------------------------
// 8. Set default variant = first in-stock variant per product
// ---------------------------------------------------------------------------
// Ensure every configurable product has at least one active variant
$mysqli->query(
	"UPDATE {$prefix}product_variant v
	 JOIN (SELECT product_id FROM {$prefix}product_configurable WHERE is_configurable = 1) pc ON (pc.product_id = v.product_id)
	 LEFT JOIN (SELECT product_id, MIN(variant_id) AS active_id FROM {$prefix}product_variant WHERE status = 1 GROUP BY product_id) a ON (a.product_id = v.product_id)
	 SET v.status = 1
	 WHERE a.active_id IS NULL AND v.variant_id = (SELECT MIN(variant_id) FROM {$prefix}product_variant WHERE product_id = v.product_id)"
);

$res = $mysqli->query("SELECT product_id, MIN(variant_id) AS vid FROM {$prefix}product_variant WHERE status = 1 AND quantity > 0 GROUP BY product_id");

while ($row = $res->fetch_assoc()) {
	$mysqli->query(
		"UPDATE {$prefix}product_configurable SET default_variant_id = " . (int)$row['vid'] . " WHERE product_id = " . (int)$row['product_id'] . " AND is_configurable = 1"
	);
}

// Products where all variants are out of stock: default = first active
$res = $mysqli->query(
	"SELECT pc.product_id FROM {$prefix}product_configurable pc
	 LEFT JOIN (SELECT product_id, COUNT(*) AS c FROM {$prefix}product_variant WHERE quantity > 0 AND status = 1 GROUP BY product_id) v ON (v.product_id = pc.product_id)
	 WHERE pc.is_configurable = 1 AND (v.c IS NULL OR v.c = 0)"
);

while ($row = $res->fetch_assoc()) {
	$sub = $mysqli->query("SELECT MIN(variant_id) AS vid FROM {$prefix}product_variant WHERE product_id = " . (int)$row['product_id'] . " AND status = 1");

	if ($sub && $sub->num_rows) {
		$vidRow = $sub->fetch_assoc();

		if ($vidRow['vid'] !== null) {
			$mysqli->query(
				"UPDATE {$prefix}product_configurable SET default_variant_id = " . (int)$vidRow['vid'] . " WHERE product_id = " . (int)$row['product_id'] . " AND is_configurable = 1"
			);
		}
	}
}

echo 'products_with_axes: ' . count(array_filter($products, function ($p) { return !empty($p['axes']); })) . PHP_EOL;
echo 'total_variants: ' . $totalVariants . PHP_EOL;
echo 'total_specials: ' . $totalSpecials . PHP_EOL;
echo 'out_of_stock_variants: ' . $totalOutOfStock . PHP_EOL;

$mysqli->close();

/**
 * Build variant hash: option_value_ids ordered by option_id, dash separated.
 */
function buildHash(array $comb) {
	ksort($comb);
	$parts = array();

	foreach ($comb as $v) {
		$parts[] = (int)$v['option_value_id'];
	}

	return implode('-', $parts);
}
