<?php
/**
 * DockerCart customer (storefront) REST API — read-only catalogue.
 *
 * The storefront-facing counterpart of Dockercart\Api\Payload\Product: it serves
 * products and categories through the customer API, applying the same rules the
 * storefront applies and reusing the same shared libraries, so prices and
 * visibility can never drift from the shop:
 *
 *   - visibility: status = 1, date_available <= NOW(), assigned to the current
 *     store (there is no product <-> customer-group access table in this fork);
 *   - pricing: ProductPricingCalculator (special, quantity discount, per-group
 *     price, group % discount/markup), converted to the store base currency
 *     (Cart\Currency::convertProductPrice) and taxed like the storefront
 *     (Cart\Tax::calculate + config_tax);
 *   - configurable products: ProductConfigurable / Dockercart\Api\Variants.
 *
 * The catalog model is deliberately not loaded: upload/catalog and upload/admin
 * both declare ModelCatalogProduct / ModelCatalogCategory, so only one can exist
 * in a request (the store API reuses the admin MVC tree). This library owns the
 * storefront queries and uses the admin models only for relation data.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class StoreCatalog {
	private \Registry $registry;
	private LanguageMap $languages;
	private int $customer_group_id;
	private int $store_id;
	private int $language_id;

	public function __construct(\Registry $registry) {
		$this->registry = $registry;

		$config = $registry->get('config');

		$this->customer_group_id = (int)$config->get('config_customer_group_id');
		$this->store_id = (int)$config->get('config_store_id');
		$this->language_id = (int)$config->get('config_language_id');
		$this->languages = new LanguageMap($registry);
	}

	/* ─────────────────────────────── products ─────────────────────────────── */

	/**
	 * Visible products matching the filters, priced for the request customer.
	 *
	 * @return array<int, array> ProductSummary rows
	 */
	public function listProducts(array $filters, int $start, int $limit, string $sort, string $order): array {
		$ids = $this->productIds($filters, $start, $limit, $sort, $order);

		return $this->summaries($ids);
	}

	public function countProducts(array $filters): int {
		$query = $this->productQuery($filters);

		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total FROM " . DB_PREFIX . "product p" . $query['join'] . " WHERE " . implode(' AND ', $query['where']);

		return (int)$this->db()->query($sql)->row['total'];
	}

	/**
	 * Full product card (product page representation).
	 */
	public function product(int $product_id): array {
		$rows = $this->productRows([$product_id]);

		if (!isset($rows[$product_id])) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$row = $rows[$product_id];

		$pc = new \ProductConfigurable($this->registry);

		$pricing = $this->bulkPricing([$product_id]);
		$reserved = $this->reservedMap([$product_id]);

		$summary = $this->summary($row, $pricing[$product_id . ':0'] ?? null, $pc, $reserved);

		$model = $this->model('catalog/product');

		$currency_id = (int)($row['currency_id'] ?? 0);
		$tax_class_id = (int)($row['tax_class_id'] ?? 0);

		$summary['descriptions'] = $this->descriptions($model->getProductDescriptions($product_id));
		$summary['images'] = $this->images($model->getProductImages($product_id));
		$summary['videos'] = $this->videos($model->getProductVideos($product_id));
		$summary['attributes'] = $this->attributes($model->getProductAttributes($product_id));
		$summary['options'] = $this->options($model->getProductOptions($product_id), $this->optionAxisIds($product_id), $currency_id, $tax_class_id);
		$summary['categories'] = $this->productCategories($product_id);
		$summary['reward'] = ['points' => $this->rewardPoints($product_id)];
		$summary['weight'] = $this->measurement('weight', (float)($row['weight'] ?? 0), (int)($row['weight_class_id'] ?? 0));
		$summary['dimensions'] = $this->dimensions($row);
		$summary['discounts'] = $this->discounts($product_id, $currency_id, $tax_class_id);
		$summary['variants'] = $this->variants($product_id);
		$summary['related'] = $this->summaries(array_slice(array_map('intval', $model->getProductRelated($product_id)), 0, 12));
		$summary['upsell'] = $this->summaries(array_slice(array_map('intval', $model->getProductUpsell($product_id)), 0, 12));
		$summary['accessory'] = $this->summaries(array_slice(array_map('intval', $model->getProductAccessory($product_id)), 0, 12));

		return $summary;
	}

	/**
	 * Configurable variant matrix, priced for the request customer.
	 */
	public function variants(int $product_id): array {
		$rows = $this->productRows([$product_id]);

		if (!isset($rows[$product_id])) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$variants = new Variants($this->registry);

		return $variants->forProduct($product_id, [
			'customer_group_id' => $this->customer_group_id,
			'normalize'         => true,
			'tax'               => true,
			'tax_class_id'      => (int)($rows[$product_id]['tax_class_id'] ?? 0),
		]);
	}

	/**
	 * Summaries for a list of ids (visible ones only), preserving order.
	 *
	 * @param array<int> $product_ids
	 * @return array<int, array>
	 */
	public function summaries(array $product_ids): array {
		$ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

		if (!$ids) {
			return [];
		}

		$rows = $this->productRows($ids);

		if (!$rows) {
			return [];
		}

		$ordered = [];

		foreach ($ids as $id) {
			if (isset($rows[$id])) {
				$ordered[$id] = $rows[$id];
			}
		}

		$pricing = $this->bulkPricing(array_keys($ordered));

		$pc = new \ProductConfigurable($this->registry);
		$reserved = $this->reservedMap(array_keys($ordered));

		$out = [];

		foreach ($ordered as $id => $row) {
			$out[] = $this->summary($row, $pricing[$id . ':0'] ?? null, $pc, $reserved);
		}

		return $out;
	}

	/* ────────────────────────────── categories ────────────────────────────── */

	/**
	 * @return array<int, array>
	 */
	public function listCategories(int $parent_id, int $start, int $limit): array {
		$out = [];

		foreach ($this->categoryRows($parent_id, $start, $limit) as $row) {
			$out[] = $this->categorySummary($row);
		}

		return $out;
	}

	public function countCategories(int $parent_id): int {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND c.status = '1' AND c2s.store_id = '" . $this->store_id . "'";

		return (int)$this->db()->query($sql)->row['total'];
	}

	/**
	 * Nested category tree from $parent_id (one query, built in PHP).
	 *
	 * @return array<int, array>
	 */
	public function categoryTree(int $parent_id): array {
		$rows = $this->db()->query("SELECT c.category_id, c.parent_id, c.image, c.sort_order, cd.name, cd.description FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id AND cd.language_id = '" . $this->language_id . "') LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.status = '1' AND c2s.store_id = '" . $this->store_id . "' ORDER BY c.sort_order ASC, cd.name ASC")->rows;

		$by_parent = [];

		foreach ($rows as $row) {
			$by_parent[(int)$row['parent_id']][] = $row;
		}

		return $this->buildTree($by_parent, $parent_id);
	}

	/**
	 * Single category with its breadcrumb path.
	 */
	public function category(int $category_id): array {
		$query = $this->db()->query("SELECT c.category_id, c.parent_id, c.image, c.sort_order, cd.name, cd.description FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id AND cd.language_id = '" . $this->language_id . "') LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.category_id = '" . (int)$category_id . "' AND c.status = '1' AND c2s.store_id = '" . $this->store_id . "'");

		if (!$query->num_rows) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$summary = $this->categorySummary($query->row);
		$summary['path'] = $this->categoryPath($category_id);

		return $summary;
	}

	/* ────────────────────────────── internals ─────────────────────────────── */

	/**
	 * @return array{join: string, where: array<int, string>}
	 */
	private function productQuery(array $filters): array {
		$join = " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $this->language_id . "')";

		$where = [
			"p.status = '1'",
			"p.date_available <= NOW()",
			"EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_to_store p2s WHERE p2s.product_id = p.product_id AND p2s.store_id = '" . $this->store_id . "')",
		];

		if (!empty($filters['search'])) {
			$like = "%" . $this->db()->escape((string)$filters['search']) . "%";

			$join .= " LEFT JOIN " . DB_PREFIX . "product_variant pvs ON (pvs.product_id = p.product_id)";

			$where[] = "(pd.name LIKE '" . $like . "' OR p.model LIKE '" . $like . "' OR p.sku LIKE '" . $like . "' OR p.upc LIKE '" . $like . "' OR p.ean LIKE '" . $like . "' OR p.jan LIKE '" . $like . "' OR p.isbn LIKE '" . $like . "' OR p.mpn LIKE '" . $like . "' OR pvs.sku LIKE '" . $like . "' OR pvs.model LIKE '" . $like . "')";
		}

		if (!empty($filters['name'])) {
			$where[] = "pd.name LIKE '%" . $this->db()->escape((string)$filters['name']) . "%'";
		}

		if (!empty($filters['category_id'])) {
			$category_id = (int)$filters['category_id'];

			if (!empty($filters['sub_category'])) {
				$join .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id) LEFT JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id)";
				$where[] = "cp.path_id = '" . $category_id . "'";
			} else {
				$join .= " LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (p.product_id = p2c.product_id)";
				$where[] = "p2c.category_id = '" . $category_id . "'";
			}
		}

		if (!empty($filters['manufacturer_id'])) {
			$where[] = "p.manufacturer_id = '" . (int)$filters['manufacturer_id'] . "'";
		}

		if (!empty($filters['in_stock'])) {
			$where[] = "(p.quantity > 0 OR p.preorder = '1' OR EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_variant pv WHERE pv.product_id = p.product_id AND pv.status = '1' AND pv.quantity > 0))";
		}

		if (!empty($filters['special'])) {
			$where[] = "EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . $this->customer_group_id . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())))";
		}

		if (!empty($filters['product_ids'])) {
			$ids = array_filter(array_map('intval', (array)$filters['product_ids']));

			$where[] = $ids ? "p.product_id IN (" . implode(',', array_unique($ids)) . ")" : "1 = 0";
		}

		return ['join' => $join, 'where' => $where];
	}

	/**
	 * @return array<int, int>
	 */
	private function productIds(array $filters, int $start, int $limit, string $sort, string $order): array {
		$query = $this->productQuery($filters);

		$sql = "SELECT p.product_id FROM " . DB_PREFIX . "product p" . $query['join'] . " WHERE " . implode(' AND ', $query['where']) . " GROUP BY p.product_id ORDER BY " . $this->orderBy($sort, $order) . " LIMIT " . (int)$start . "," . (int)$limit;

		$ids = [];

		foreach ($this->db()->query($sql)->rows as $row) {
			$ids[] = (int)$row['product_id'];
		}

		return $ids;
	}

	private function orderBy(string $sort, string $order): string {
		$direction = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

		// product_id is always appended so the order is total: without it ties
		// (equal sort_order/price/rating) could move between pages and a client
		// would see the same row twice and miss another one.
		$tiebreak = ', p.product_id ASC';

		switch ($sort) {
			case 'name':
				return 'pd.name ' . $direction . $tiebreak;
			case 'price':
				return 'p.price ' . $direction . $tiebreak;
			case 'date_added':
				return 'p.date_added ' . $direction . $tiebreak;
			case 'rating':
				return '(SELECT AVG(r.rating) FROM ' . DB_PREFIX . 'review r WHERE r.product_id = p.product_id AND r.status = \'1\') ' . $direction . $tiebreak;
			case 'sort_order':
				return 'p.sort_order ' . $direction . $tiebreak;
		}

		return 'p.sort_order ASC' . $tiebreak;
	}

	/**
	 * Visible product rows keyed by product_id.
	 *
	 * @param array<int> $product_ids
	 * @return array<int, array>
	 */
	private function productRows(array $product_ids): array {
		$ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));

		if (!$ids) {
			return [];
		}

		$sql = "SELECT p.*, pd.name, pd.description, pd.meta_title, pd.meta_description, pd.meta_keyword, pd.tag, m.name AS manufacturer, (SELECT AVG(r.rating) FROM " . DB_PREFIX . "review r WHERE r.product_id = p.product_id AND r.status = '1') AS rating, (SELECT COUNT(*) FROM " . DB_PREFIX . "review r2 WHERE r2.product_id = p.product_id AND r2.status = '1') AS reviews FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $this->language_id . "') LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id) WHERE p.product_id IN (" . implode(',', $ids) . ") AND p.status = '1' AND p.date_available <= NOW() AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_to_store p2s WHERE p2s.product_id = p.product_id AND p2s.store_id = '" . $this->store_id . "')";

		$rows = [];

		foreach ($this->db()->query($sql)->rows as $row) {
			$rows[(int)$row['product_id']] = $row;
		}

		return $rows;
	}

	/**
	 * Bulk effective pricing keyed "product_id:0" (one calculator call).
	 *
	 * @param array<int> $product_ids
	 * @return array<string, array>
	 */
	private function bulkPricing(array $product_ids): array {
		$lines = [];

		foreach ($product_ids as $product_id) {
			$lines[] = ['product_id' => (int)$product_id, 'variant_id' => 0, 'quantity' => 1];
		}

		if (!$lines) {
			return [];
		}

		return $this->calculator()->calculateForLines($lines)['pricing'];
	}

	private function summary(array $row, ?array $pricing, \ProductConfigurable $pc, array $reserved): array {
		$product_id = (int)$row['product_id'];
		$currency_id = (int)($row['currency_id'] ?? 0);
		$tax_class_id = (int)($row['tax_class_id'] ?? 0);

		$is_configurable = $pc->isConfigurable($product_id);

		if ($is_configurable) {
			$range = $pc->getAggregatedPriceRange($product_id, $this->customer_group_id);

			$price_min = $this->money($range['min'] ?? 0, $currency_id, $tax_class_id);
			$price_max = $this->money($range['max'] ?? 0, $currency_id, $tax_class_id);
			$price = $price_min;
			$special = null;
			$base_price = null;

			$stock = $pc->getAggregatedStock($product_id);
			$quantity = (float)($stock['total_stock'] ?? 0);
			$variants_in_stock = (int)($stock['variants_in_stock'] ?? 0);
		} else {
			$price = $this->money($pricing['price'] ?? ($row['price'] ?? 0), $currency_id, $tax_class_id);
			$special = isset($pricing['special']) && $pricing['special'] !== null ? $this->money($pricing['special'], $currency_id, $tax_class_id) : null;
			$base_price = isset($pricing['base_price']) && $pricing['base_price'] !== null ? $this->money($pricing['base_price'], $currency_id, $tax_class_id) : null;

			$price_min = $price;
			$price_max = $price;
			$variants_in_stock = 0;
			$quantity = max(0.0, (float)($row['quantity'] ?? 0) - (float)($reserved[$product_id . ':0'] ?? 0));
		}

		$preorder = (int)($row['preorder'] ?? 0) === 1;
		$discontinued = (int)($row['discontinued'] ?? 0) === 1 && $quantity <= 0 && $variants_in_stock === 0;
		$in_stock = $quantity > 0 || $preorder || $variants_in_stock > 0;

		$image = (string)($row['image'] ?? '');

		return [
			'product_id'       => $product_id,
			'name'             => Serializer::decodeText($row['name'] ?? ''),
			'model'            => (string)($row['model'] ?? ''),
			'sku'              => (string)($row['sku'] ?? ''),
			'image'            => $image,
			'image_url'        => $this->imageUrl($image),
			'thumb_url'        => $this->thumbUrl($image),
			'price'            => $price,
			'special'          => $special,
			'base_price'       => $base_price,
			'currency_code'    => $this->baseCurrencyCode(),
			'is_configurable'  => $is_configurable,
			'price_min'        => $price_min,
			'price_max'        => $price_max,
			'quantity'         => Serializer::quantity($quantity),
			'in_stock'         => $in_stock,
			'preorder'         => $preorder,
			'discontinued'     => $discontinued,
			'rating'           => isset($row['rating']) ? round((float)$row['rating'], 2) : 0.0,
			'reviews'          => (int)($row['reviews'] ?? 0),
			'manufacturer'     => [
				'manufacturer_id' => (int)($row['manufacturer_id'] ?? 0),
				'name'            => (string)($row['manufacturer'] ?? ''),
			],
			'main_category_id' => (int)($row['main_category_id'] ?? 0),
			'date_added'       => Serializer::datetime($row['date_added'] ?? ''),
		];
	}

	/**
	 * @return array<string, array>
	 */
	private function descriptions(array $descriptions): array {
		$out = [];

		foreach ($descriptions as $language_id => $fields) {
			$code = $this->languages->code((int)$language_id);

			if ($code === '') {
				continue;
			}

			$out[$code] = [
				'name'             => Serializer::decodeText((string)($fields['name'] ?? '')),
				'description'      => Serializer::decodeText((string)($fields['description'] ?? '')),
				'meta_title'       => Serializer::decodeText((string)($fields['meta_title'] ?? '')),
				'meta_description' => Serializer::decodeText((string)($fields['meta_description'] ?? '')),
				'meta_keyword'     => Serializer::decodeText((string)($fields['meta_keyword'] ?? '')),
				'tag'              => Serializer::decodeText((string)($fields['tag'] ?? '')),
			];
		}

		return $out;
	}

	private function images(array $images): array {
		$out = [];

		foreach ($images as $image) {
			$path = (string)($image['image'] ?? '');

			$out[] = [
				'image'      => $path,
				'image_url'  => $this->imageUrl($path),
				'sort_order' => (int)($image['sort_order'] ?? 0),
			];
		}

		return $out;
	}

	private function videos(array $videos): array {
		$out = [];

		foreach ($videos as $video) {
			$out[] = [
				'video_type' => (string)($video['video_type'] ?? ''),
				'video'      => (string)($video['video'] ?? ''),
				'sort_order' => (int)($video['sort_order'] ?? 0),
			];
		}

		return $out;
	}

	private function attributes(array $attributes): array {
		$ids = [];

		foreach ($attributes as $attribute) {
			$ids[] = (int)$attribute['attribute_id'];
		}

		if (!$ids) {
			return [];
		}

		$names = [];

		$query = $this->db()->query("SELECT attribute_id, name FROM " . DB_PREFIX . "attribute_description WHERE attribute_id IN (" . implode(',', array_unique($ids)) . ") AND language_id = '" . $this->language_id . "'");

		foreach ($query->rows as $row) {
			$names[(int)$row['attribute_id']] = (string)$row['name'];
		}

		$out = [];

		foreach ($attributes as $attribute) {
			$attribute_id = (int)$attribute['attribute_id'];
			$description = $attribute['product_attribute_description'][$this->language_id] ?? null;

			if (!$description) {
				continue;
			}

			$out[] = [
				'attribute_id' => $attribute_id,
				'name'         => $names[$attribute_id] ?? '',
				'text'         => (string)($description['text'] ?? ''),
			];
		}

		return $out;
	}

	/**
	 * @return array<int, bool> option_id => true for configurable axes
	 */
	private function optionAxisIds(int $product_id): array {
		$ids = [];

		$query = $this->db()->query("SELECT option_id FROM " . DB_PREFIX . "product_configurable_option WHERE product_id = '" . (int)$product_id . "'");

		foreach ($query->rows as $row) {
			$ids[(int)$row['option_id']] = true;
		}

		return $ids;
	}

	private function options(array $options, array $axis_ids, int $currency_id, int $tax_class_id): array {
		$out = [];

		foreach ($options as $option) {
			$values = [];

			foreach ((array)($option['product_option_value'] ?? []) as $value) {
				$price = (float)($value['price'] ?? 0);
				$prefix = (string)($value['price_prefix'] ?? '+');

				foreach ((array)($value['customer_group_prices'] ?? []) as $cg_price) {
					if ((int)($cg_price['customer_group_id'] ?? 0) === $this->customer_group_id) {
						$price = (float)($cg_price['price'] ?? $price);
						$prefix = (string)($cg_price['price_prefix'] ?? $prefix);

						break;
					}
				}

				$values[] = [
					'product_option_value_id' => (int)($value['product_option_value_id'] ?? 0),
					'option_value_id'         => (int)($value['option_value_id'] ?? 0),
					'name'                    => (string)($value['name'] ?? ''),
					'price'                   => $this->money($price, $currency_id, $tax_class_id, 'P'),
					'price_prefix'            => $prefix,
					'is_hit'                  => (bool)($value['is_hit'] ?? false),
				];
			}

			$out[] = [
				'product_option_id'    => (int)($option['product_option_id'] ?? 0),
				'option_id'            => (int)($option['option_id'] ?? 0),
				'name'                 => (string)($option['name'] ?? ''),
				'type'                 => (string)($option['type'] ?? ''),
				'required'             => (bool)($option['required'] ?? false),
				'is_configurable_axis' => isset($axis_ids[(int)($option['option_id'] ?? 0)]),
				'values'               => $values,
			];
		}

		return $out;
	}

	/**
	 * @return array<int, array>
	 */
	private function productCategories(int $product_id): array {
		$ids = array_map('intval', $this->model('catalog/product')->getProductCategories($product_id));

		$out = [];

		foreach ($ids as $category_id) {
			$query = $this->db()->query("SELECT cd.name FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id AND cd.language_id = '" . $this->language_id . "') LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.category_id = '" . $category_id . "' AND c.status = '1' AND c2s.store_id = '" . $this->store_id . "'");

			if (!$query->num_rows) {
				continue;
			}

			$out[] = [
				'category_id' => $category_id,
				'name'        => (string)$query->row['name'],
				'path'        => $this->categoryPath($category_id),
			];
		}

		return $out;
	}

	private function rewardPoints(int $product_id): int {
		$query = $this->db()->query("SELECT points FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . $this->customer_group_id . "'");

		return $query->num_rows ? (int)$query->row['points'] : 0;
	}

	/**
	 * Active quantity discounts for the request customer.
	 *
	 * @return array<int, array>
	 */
	private function discounts(int $product_id, int $currency_id, int $tax_class_id): array {
		$query = $this->db()->query("SELECT quantity, price FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "' AND customer_group_id = '" . $this->customer_group_id . "' AND quantity > 1 AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY quantity ASC, priority ASC, price ASC");

		$out = [];

		foreach ($query->rows as $row) {
			$out[] = [
				'quantity' => Serializer::quantity($row['quantity']),
				'price'    => $this->money($row['price'], $currency_id, $tax_class_id),
			];
		}

		return $out;
	}

	/**
	 * @return array{value: float, unit: string}
	 */
	private function measurement(string $type, float $value, int $class_id): array {
		$unit = '';

		if ($class_id > 0) {
			$table = $type === 'weight' ? 'weight_class_description' : 'length_class_description';
			$column = $type === 'weight' ? 'weight_class_id' : 'length_class_id';

			$query = $this->db()->query("SELECT unit FROM " . DB_PREFIX . $table . " WHERE " . $column . " = '" . (int)$class_id . "' AND language_id = '" . $this->language_id . "'");

			$unit = $query->num_rows ? (string)$query->row['unit'] : '';
		}

		return ['value' => Serializer::quantity($value), 'unit' => $unit];
	}

	/**
	 * @return array{length: float, width: float, height: float, unit: string}
	 */
	private function dimensions(array $row): array {
		return [
			'length' => Serializer::quantity($row['length'] ?? 0),
			'width'  => Serializer::quantity($row['width'] ?? 0),
			'height' => Serializer::quantity($row['height'] ?? 0),
			'unit'   => $this->measurement('length', 0, (int)($row['length_class_id'] ?? 0))['unit'],
		];
	}

	/**
	 * Active checkout holds, keyed "product_id:variant_id". Empty when the
	 * feature is off or the (stateless) API has no session.
	 *
	 * @param array<int> $product_ids
	 * @return array<string, float>
	 */
	private function reservedMap(array $product_ids): array {
		if (!$product_ids) {
			return [];
		}

		try {
			$reservation = new \DockercartStockReservation($this->registry);

			if (!$reservation->isEnabled() || !$this->registry->has('session') || !$this->registry->get('session')) {
				return [];
			}

			return $reservation->getReservedByProductIds($product_ids);
		} catch (\Throwable $exception) {
			return [];
		}
	}

	/**
	 * @return array<int, array>
	 */
	private function categoryRows(int $parent_id, int $start, int $limit): array {
		$sql = "SELECT c.category_id, c.parent_id, c.image, c.sort_order, cd.name, cd.description FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id AND cd.language_id = '" . $this->language_id . "') LEFT JOIN " . DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '" . (int)$parent_id . "' AND c.status = '1' AND c2s.store_id = '" . $this->store_id . "' ORDER BY c.sort_order ASC, cd.name ASC LIMIT " . (int)$start . "," . (int)$limit;

		return $this->db()->query($sql)->rows;
	}

	private function categorySummary(array $row): array {
		$category_id = (int)$row['category_id'];
		$image = (string)($row['image'] ?? '');

		return [
			'category_id'   => $category_id,
			'parent_id'     => (int)($row['parent_id'] ?? 0),
			'name'          => (string)($row['name'] ?? ''),
			'description'   => Serializer::decodeText((string)($row['description'] ?? '')),
			'image'         => $image,
			'image_url'     => $this->imageUrl($image),
			'sort_order'    => (int)($row['sort_order'] ?? 0),
			'product_count' => $this->categoryProductCount($category_id),
		];
	}

	private function categoryProductCount(int $category_id): int {
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total FROM " . DB_PREFIX . "product_to_category p2c INNER JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id) WHERE p2c.category_id = '" . (int)$category_id . "' AND p.status = '1' AND p.date_available <= NOW() AND EXISTS (SELECT 1 FROM " . DB_PREFIX . "product_to_store p2s WHERE p2s.product_id = p.product_id AND p2s.store_id = '" . $this->store_id . "')";

		return (int)$this->db()->query($sql)->row['total'];
	}

	/**
	 * @return array<int, array{category_id: int, name: string}>
	 */
	private function categoryPath(int $category_id): array {
		$query = $this->db()->query("SELECT cp.path_id, cd.name FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category_description cd ON (cp.path_id = cd.category_id AND cd.language_id = '" . $this->language_id . "') WHERE cp.category_id = '" . (int)$category_id . "' ORDER BY cp.level ASC");

		$path = [];

		foreach ($query->rows as $row) {
			$path[] = [
				'category_id' => (int)$row['path_id'],
				'name'        => (string)$row['name'],
			];
		}

		return $path;
	}

	/**
	 * @return array<int, array>
	 */
	private function buildTree(array $by_parent, int $parent_id): array {
		$out = [];

		foreach ($by_parent[$parent_id] ?? [] as $row) {
			$node = $this->categorySummary($row);
			$node['children'] = $this->buildTree($by_parent, (int)$row['category_id']);

			$out[] = $node;
		}

		return $out;
	}

	/**
	 * Stored amount (product currency) -> base currency -> tax -> 4-decimal string.
	 *
	 * @param mixed $tax_mode true (all rates), 'P' (percentage only) or false
	 */
	private function money($amount, int $product_currency_id, int $tax_class_id, $tax_mode = true): string {
		$value = (float)$this->currency()->convertProductPrice($amount, $product_currency_id);

		if ($this->config()->get('config_tax')) {
			$value = (float)$this->tax()->calculate($value, $tax_class_id, $tax_mode);
		}

		return Serializer::money($value);
	}

	private function imageUrl(string $image): string {
		if ($image === '') {
			return '';
		}

		return $this->imageBase() . $image;
	}

	private function imageBase(): string {
		$request = $this->registry->get('request');
		$https = $request && !empty($request->server['HTTPS']);

		$base = $https ? (string)$this->config()->get('config_ssl') : (string)$this->config()->get('config_url');

		if ($base === '') {
			$base = (string)$this->config()->get('config_url');
		}

		if ($base === '' && defined('HTTP_CATALOG')) {
			$base = (string)HTTP_CATALOG;
		}

		return rtrim($base, '/') . '/image/';
	}

	private function thumbUrl(string $image): string {
		if ($image === '') {
			return '';
		}

		try {
			$theme = (string)$this->config()->get('config_theme');

			$width = (int)$this->config()->get('theme_' . $theme . '_image_thumb_width');
			$height = (int)$this->config()->get('theme_' . $theme . '_image_thumb_height');

			if ($width <= 0) {
				$width = 200;
			}

			if ($height <= 0) {
				$height = 200;
			}

			$resized = (string)$this->model('tool/image')->resize($image, $width, $height);

			return $resized !== '' ? $resized : $this->imageUrl($image);
		} catch (\Throwable $exception) {
			return $this->imageUrl($image);
		}
	}

	private function baseCurrencyCode(): string {
		return (string)$this->config()->get('config_currency');
	}

	private function calculator(): \ProductPricingCalculator {
		return new \ProductPricingCalculator($this->registry, ['customer_group_id' => $this->customer_group_id]);
	}

	/**
	 * Loads an admin model (the store API reuses the admin MVC tree) and returns
	 * its registry proxy, exactly like Dockercart\Api\Payload\Product does.
	 *
	 * @return mixed
	 */
	private function model(string $route) {
		$load = $this->registry->get('load');

		$load->model($route);

		return $this->registry->get('model_' . str_replace('/', '_', $route));
	}

	private function db() {
		return $this->registry->get('db');
	}

	private function config() {
		return $this->registry->get('config');
	}

	private function currency() {
		return $this->registry->get('currency');
	}

	private function tax() {
		return $this->registry->get('tax');
	}
}
