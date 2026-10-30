<?php
/**
 * DockerCart customer (storefront) REST API — products.
 *
 *   GET /store-api/v1/products                    list (filters + pagination)
 *   GET /store-api/v1/products/{id}               full product card
 *   GET /store-api/v1/products/{id}/variants      configurable variant matrix
 *
 * Read-only and storefront-scoped: only products that are enabled, available
 * (date_available) and assigned to the request store are visible, and every
 * amount is priced for the authenticated buyer's customer group, normalised to
 * the store base currency and taxed like the storefront.
 *
 * All work is delegated to Dockercart\Api\StoreCatalog so the visibility and
 * pricing rules live in one place.
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

use Dockercart\Api\StoreCatalog;

class ControllerStoreApiProducts extends \Dockercart\Api\Controller {
	public function index() {
		$catalog = new StoreCatalog($this->registry);

		$pagination = $this->pagination();

		$filters = [
			'search'          => $this->queryString('search'),
			'name'            => $this->queryString('name'),
			'category_id'     => $this->queryInt('category_id'),
			'sub_category'    => $this->queryString('sub_category') !== '0',
			'manufacturer_id' => $this->queryInt('manufacturer_id'),
			'in_stock'        => $this->queryString('in_stock') === '1',
			'special'         => $this->queryString('special') === '1',
			'product_ids'     => $this->productIds(),
		];

		$sorting = $this->sorting(
			['name', 'price', 'date_added', 'sort_order', 'rating'],
			'sort_order',
			'ASC'
		);

		$rows = $catalog->listProducts($filters, $pagination['start'], $pagination['limit'], $sorting['sort'], $sorting['order']);
		$total = $catalog->countProducts($filters);

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $total));
	}

	public function get() {
		$catalog = new StoreCatalog($this->registry);

		$this->respondResource($catalog->product($this->param('product_id')));
	}

	public function variants() {
		$catalog = new StoreCatalog($this->registry);

		$this->respondResource($catalog->variants($this->param('product_id')));
	}

	/**
	 * ?product_ids=1,2,3 -> [1, 2, 3]
	 *
	 * @return array<int>
	 */
	private function productIds(): array {
		$value = $this->queryString('product_ids');

		if ($value === '') {
			return [];
		}

		$ids = [];

		foreach (explode(',', $value) as $id) {
			$id = (int)trim($id);

			if ($id > 0) {
				$ids[] = $id;
			}
		}

		return $ids;
	}
}
