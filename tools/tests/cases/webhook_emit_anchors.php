<?php
/**
 * Emit anchors: the integration points must keep their guarantees.
 *
 * These are static checks on the files that carry the `emit()` calls, because
 * they cannot be executed without a full checkout/order stack. They pin the
 * three properties that are easy to break by accident:
 *
 *   1. the event is written inside the existing transaction, before COMMIT;
 *   2. it is emitted only for a real state change;
 *   3. stock events come from the single warehouse journal writer.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

/**
 * Returns the source of a method body, or '' when the method is missing.
 */
function webhook_method_source(string $file, string $method): string {
	$source = repo_file($file);

	$position = strpos($source, 'function ' . $method . '(');

	if ($position === false) {
		return '';
	}

	// Walk braces from the opening one to find the end of the method.
	$start = strpos($source, '{', $position);

	if ($start === false) {
		return '';
	}

	$depth = 0;
	$length = strlen($source);

	for ($i = $start; $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $start, $i - $start + 1);
			}
		}
	}

	return '';
}

return [
	'emit: order.created is written before the order transaction commits' => static function (): void {
		$body = webhook_method_source('upload/catalog/model/checkout/order.php', 'addOrder');

		Assert::true($body !== '', 'addOrder() not found');
		Assert::contains("emit('order.created'", $body);

		$emit = strpos($body, "emit('order.created'");
		$commit = strrpos($body, '"COMMIT"');

		Assert::true($emit !== false && $commit !== false, 'addOrder() must emit and commit');
		Assert::true($emit < $commit, 'order.created must be enqueued before COMMIT (same transaction)');
	},

	'emit: order.created fires once per order (never from the duplicate-claim path)' => static function (): void {
		$body = webhook_method_source('upload/catalog/model/checkout/order.php', 'addOrder');

		Assert::same(1, substr_count($body, "emit('order.created'"), 'a duplicate checkout must not emit twice');
	},

	'emit: the admin status change emits before COMMIT and only for a real change' => static function (): void {
		$body = webhook_method_source('upload/admin/model/sale/order.php', 'addOrderHistory');

		Assert::contains("emit('order.status_changed'", $body);

		$emit = strpos($body, "emit('order.status_changed'");
		$commit = strrpos($body, '"COMMIT"');

		Assert::true($emit !== false && $commit !== false);
		Assert::true($emit < $commit, 'order.status_changed must be enqueued before COMMIT');

		Assert::contains('$old_status_id !== (int)$order_status_id', $body, 'a no-op status change must not emit');
		Assert::contains("'override'        => (bool)\$override", $body);
		Assert::contains("'source'          => 'admin'", $body);
	},

	'emit: the catalog status change skips void calls' => static function (): void {
		$body = webhook_method_source('upload/catalog/model/checkout/order.php', 'addOrderHistory');

		Assert::contains("emit('order.status_changed'", $body);
		Assert::contains('$order_history_id > 0', $body, 'only recorded history rows may emit');
		Assert::contains('$old_status_id > 0', $body, 'the void call (status 0) must not emit');
		Assert::contains("'source'          => 'catalog'", $body);

		$emit = strpos($body, "emit('order.status_changed'");
		$commit = strrpos($body, '"COMMIT"');

		Assert::true($emit !== false && $commit !== false);
		Assert::true($emit < $commit, 'order.status_changed must be enqueued before COMMIT');
	},

	'emit: the admin status history id is captured right after the insert' => static function (): void {
		$body = webhook_method_source('upload/admin/model/sale/order.php', 'addOrderHistory');

		$insert = strpos($body, 'INSERT INTO " . DB_PREFIX . "order_history');
		$capture = strpos($body, '$order_history_id = (int)$this->db->getLastId()');

		Assert::true($insert !== false && $capture !== false, 'the history insert and its id capture must both exist');
		Assert::true($capture > $insert, 'getLastId() must be read immediately after the history insert');
		Assert::contains("'version'         => \$order_history_id", $body);
	},

	'emit: order events carry the order flow flags' => static function (): void {
		foreach ([
			'upload/admin/model/sale/order.php'          => 'addOrderHistory',
			'upload/catalog/model/checkout/order.php'    => 'addOrderHistory',
		] as $file => $method) {
			$body = webhook_method_source($file, $method);

			foreach (["'is_cancelled'", "'is_final'", "'is_terminal'", "'from_status_id'", "'to_status_id'"] as $key) {
				Assert::contains($key, $body, $file . ' payload is missing ' . $key);
			}
		}
	},

	'emit: stock.changed comes from the single journal writer' => static function (): void {
		$body = webhook_method_source('upload/system/library/dockercart_warehouse.php', 'journalMovement');

		Assert::contains("emit('stock.changed'", $body);
		Assert::contains("'movement_id'", $body);
		Assert::contains("'version'      => \$movement_id", $body);

		// Every mutation goes through applyStockDelta(), which calls journalMovement().
		$delta = webhook_method_source('upload/system/library/dockercart_warehouse.php', 'applyStockDelta');

		Assert::contains('journalMovement(', $delta, 'applyStockDelta must publish the event');
	},

	'emit: no code inserts stock movements directly anymore' => static function (): void {
		$offenders = [];

		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(UPLOAD_DIR . '/admin', \FilesystemIterator::SKIP_DOTS));
		$files = array_merge(iterator_to_array($files), iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(UPLOAD_DIR . '/system/library', \FilesystemIterator::SKIP_DOTS))));

		foreach ($files as $file) {
			if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
				continue;
			}

			$source = (string)file_get_contents($file->getPathname());

			if (preg_match('/INSERT INTO `?"?\s*\.?\s*DB_PREFIX\s*\.?\s*"?warehouse_stock_movement/i', $source)
				|| preg_match('/INSERT INTO `oc_warehouse_stock_movement`/i', $source)) {
				$offenders[] = $file->getPathname();
			}
		}

		Assert::count(1, $offenders, 'only DockercartWarehouse may journal movements: ' . implode(', ', $offenders));
		Assert::contains('dockercart_warehouse.php', $offenders[0]);
	},

	'emit: the dispatcher is instantiated with the registry everywhere it is used' => static function (): void {
		foreach ([
			'upload/catalog/model/checkout/order.php',
			'upload/admin/model/sale/order.php',
			'upload/system/library/dockercart_warehouse.php',
		] as $file) {
			Assert::contains('new \Dockercart\Webhook\Dispatcher($this->registry)', repo_file($file), $file);
		}
	},
];
