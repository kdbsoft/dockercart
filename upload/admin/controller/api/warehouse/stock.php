<?php
/**
 * DockerCart admin REST API — stock adjustments.
 *
 *   POST /v1/stock/adjustments   signed deltas, applied as one atomic batch
 *
 * Stock is never written directly: every line goes through
 * DockercartWarehouse::adjustStockBatch(), which journals an 'adjustment'
 * movement per line and rewrites the denormalised product/variant quantity
 * caches exactly like the panel does. A delta the warehouse cannot cover rolls
 * the whole batch back (409 insufficient_stock) instead of silently saturating
 * at zero, so an external system can never believe stock moved when it did not.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;

class ControllerApiWarehouseStock extends \Dockercart\Api\Controller {
	/**
	 * Maximum number of lines per request (one transaction per batch).
	 */
	private const MAX_LINES = 100;

	public function adjustments() {
		$data = $this->validateRequest($this->body(), [
			'adjustments' => ['required', 'array'],
		]);

		$requested = array_values((array)$data['adjustments']);

		if (!$requested) {
			throw new HttpException(Error::VALIDATION_FAILED, ['adjustments' => ['at least one adjustment is required']]);
		}

		if (count($requested) > self::MAX_LINES) {
			throw new HttpException(Error::VALIDATION_FAILED, [
				'adjustments' => ['at most ' . self::MAX_LINES . ' adjustments per request'],
			]);
		}

		$lines = $this->adjustmentLines($requested);

		$warehouse = new DockercartWarehouse($this->registry);

		$result = $warehouse->adjustStockBatch($lines, [
			'reference' => 'api',
			'user_id'   => (int)$this->user->getId(),
		]);

		if (!$result['ok']) {
			throw new HttpException(Error::INSUFFICIENT_STOCK, $this->shortfallFields($result['short']));
		}

		$this->respond(['adjustments' => $result['lines']]);
	}

	/**
	 * Validates every line and resolves it into a library stock line. All errors
	 * are collected first, so a client fixes the payload in one round trip.
	 *
	 * @param array $requested raw 'adjustments' list
	 * @return array<int, array<string, mixed>>
	 */
	private function adjustmentLines(array $requested): array {
		$this->load->model('catalog/product');

		$configurable = new ProductConfigurable($this->registry);
		$warehouse = new DockercartWarehouse($this->registry);

		$lines = [];
		$errors = [];

		foreach ($requested as $index => $line) {
			$field = 'adjustments.' . $index;

			if (!is_array($line)) {
				$errors[$field] = ['must be an object'];

				continue;
			}

			try {
				$normalized = $this->validateRequest($line, [
					'warehouse_id' => ['required', 'int', 'min:1'],
					'product_id'   => ['required', 'int', 'min:1'],
					'variant_id'   => ['int', 'min:0'],
					'delta'        => ['required', 'float'],
					'reference'    => ['string', 'max:255'],
					'comment'      => ['string', 'max:255'],
				]);
			} catch (HttpException $exception) {
				foreach ($exception->getFields() as $name => $messages) {
					$errors[$field . '.' . $name] = $messages;
				}

				continue;
			}

			$warehouse_id = (int)$normalized['warehouse_id'];
			$product_id = (int)$normalized['product_id'];
			$variant_id = (int)($normalized['variant_id'] ?? 0);
			$delta = (float)$normalized['delta'];

			if (abs($delta) < 0.0001) {
				$errors[$field . '.delta'] = ['must be a non-zero delta (4 decimals)'];
			}

			if ($warehouse->getWarehouse($warehouse_id) === null) {
				$errors[$field . '.warehouse_id'] = ['unknown warehouse'];
			}

			$product = $this->model_catalog_product->getProduct($product_id);

			if (!$product) {
				$errors[$field . '.product_id'] = ['unknown product'];

				continue;
			}

			if ($variant_id > 0) {
				$variant = $configurable->getVariant($variant_id);

				if (!$variant || (int)$variant['product_id'] !== $product_id) {
					$errors[$field . '.variant_id'] = ['unknown variant of this product'];
				}
			} elseif ($configurable->isConfigurable($product_id)) {
				// Head-level stock of a configurable product is a no-op in the
				// warehouse layer: require the variant instead of reporting success.
				$errors[$field . '.variant_id'] = ['required for a configurable product'];
			}

			$line_data = [
				'warehouse_id' => $warehouse_id,
				'product_id'   => $product_id,
				'variant_id'   => $variant_id,
				'delta'        => $delta,
			];

			// Empty strings must not shadow the batch-level movement context
			// (reference 'api' / the caller's comment).
			if (isset($normalized['reference'])) {
				$line_data['reference'] = (string)$normalized['reference'];
			}

			if (isset($normalized['comment'])) {
				$line_data['comment'] = (string)$normalized['comment'];
			}

			$lines[] = $line_data;
		}

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $errors);
		}

		return $lines;
	}

	/**
	 * Per-line 409 details: the warehouse could not absorb the requested delta.
	 *
	 * @param array $short index => line result (quantity is the clamped result)
	 * @return array<string, array<int, string>>
	 */
	private function shortfallFields(array $short): array {
		$fields = [];

		foreach ($short as $index => $line) {
			$before = (float)$line['quantity'] - (float)$line['applied'];

			$fields['adjustments.' . $index . '.delta'] = [
				'only ' . Serializer::quantity($before) . ' available in warehouse ' . (int)$line['warehouse_id'],
			];
		}

		return $fields;
	}
}
