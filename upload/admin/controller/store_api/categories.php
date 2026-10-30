<?php
/**
 * DockerCart customer (storefront) REST API — categories.
 *
 *   GET /store-api/v1/categories              flat list (parent_id + pagination)
 *   GET /store-api/v1/categories/tree         nested tree from parent_id
 *   GET /store-api/v1/categories/{id}         single category + breadcrumb path
 *
 * Read-only and storefront-scoped: only enabled categories assigned to the
 * request store are visible. Data access goes through
 * Dockercart\Api\StoreCatalog.
 *
 * @package    DockerCart
 * @subpackage StoreApi
 */

declare(strict_types=1);

use Dockercart\Api\StoreCatalog;

class ControllerStoreApiCategories extends \Dockercart\Api\Controller {
	public function index() {
		$catalog = new StoreCatalog($this->registry);

		$pagination = $this->pagination();

		$parent_id = $this->queryInt('parent_id', 0);

		$rows = $catalog->listCategories($parent_id, $pagination['start'], $pagination['limit']);
		$total = $catalog->countCategories($parent_id);

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $total));
	}

	public function tree() {
		$catalog = new StoreCatalog($this->registry);

		$this->respond($catalog->categoryTree($this->queryInt('parent_id', 0)));
	}

	public function get() {
		$catalog = new StoreCatalog($this->registry);

		$this->respondResource($catalog->category($this->param('category_id')));
	}
}
