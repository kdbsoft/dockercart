<?php
/**
 * DockerCart admin REST API — products.
 *
 *   GET    /v1/products                       list (filters + pagination)
 *   POST   /v1/products                       create
 *   GET    /v1/products/{id}                  single resource
 *   PUT    /v1/products/{id}                  full replace
 *   PATCH  /v1/products/{id}                  partial update
 *   DELETE /v1/products/{id}                  delete
 *   GET    /v1/products/{id}/variants         configurable variants
 *   PATCH  /v1/products/{id}/stock            set stock (warehouse layer)
 *   PATCH  /v1/products/{id}/price            set price
 *
 * All work is delegated to ModelCatalogProduct — the same model the panel uses —
 * so pricing, warehouse, SEO and configurable-product rules stay in one place.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Payload\Product as ProductPayload;

class ControllerApiCatalogProducts extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('catalog/product');

		$pagination = $this->pagination();

		$filters = [
			'filter_search'       => $this->queryString('search'),
			'filter_name'         => $this->queryString('name'),
			'filter_model'        => $this->queryString('model'),
			'filter_sku'          => $this->queryString('sku'),
			'filter_manufacturer' => $this->queryString('manufacturer'),
			'filter_category_id'  => $this->queryInt('category_id'),
			'filter_quantity_min' => $this->queryString('quantity_min'),
			'filter_quantity_max' => $this->queryString('quantity_max'),
			'start'               => $pagination['start'],
			'limit'               => $pagination['limit'],
		];

		if ($this->queryString('status') !== '') {
			$filters['filter_status'] = $this->queryInt('status');
		}

		$sorting = $this->sorting(
			[
				'name'         => 'pd.name',
				'model'        => 'p.model',
				'manufacturer' => 'm.name',
				'quantity'     => 'p.quantity',
				'status'       => 'p.status',
				'sort_order'   => 'p.sort_order',
			],
			'name',
			'ASC'
		);

		$filters['sort'] = $sorting['sort'];
		$filters['order'] = $sorting['order'];

		$products = $this->model_catalog_product->getProducts($filters);

		$payload = new ProductPayload($this->registry);

		$rows = [];

		foreach ($products as $product) {
			$rows[] = $payload->toResponse($product);
		}

		$total = (int)$this->model_catalog_product->getTotalProducts($filters);

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $total));
	}

	public function get() {
		$this->respondResource($this->productResponse($this->param('product_id')));
	}

	public function add() {
		$this->load->model('catalog/product');

		$payload = new ProductPayload($this->registry);

		$data = $payload->fromRequest($this->body());

		$product_id = (int)$this->model_catalog_product->addProduct($data);

		$product = $this->productResponse($product_id);

		$this->context()->etag = $this->etagFor($product);

		$this->respondCreated($product, '/api/v1/products/' . $product_id);
	}

	public function edit() {
		$product_id = $this->param('product_id');

		// Optimistic locking: If-Match must describe the copy the client edited.
		$this->assertPreconditions($this->productResponse($product_id));

		$payload = new ProductPayload($this->registry);

		$this->model_catalog_product->editProduct($product_id, $payload->fromRequest($this->body(), $product_id));

		$this->respondResource($this->productResponse($product_id));
	}

	public function patch() {
		$product_id = $this->param('product_id');

		$this->assertPreconditions($this->productResponse($product_id));

		$payload = new ProductPayload($this->registry);

		// editProduct() replaces the whole product document (it deletes and
		// re-inserts descriptions, categories, options, …), so a partial update
		// starts from the current model payload.
		$existing = $payload->fromExisting($product_id);

		$patch = $payload->fromPatch($this->body(), $product_id);

		if (!$patch) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$merged = array_merge($existing, $patch);

		$payload->assertRules($merged, $product_id);

		$this->load->model('catalog/product');

		$this->model_catalog_product->editProduct($product_id, $merged);

		$this->respondResource($this->productResponse($product_id));
	}

	public function delete() {
		$product_id = $this->param('product_id');

		$this->assertPreconditions($this->productResponse($product_id));

		$this->load->model('catalog/product');

		$this->model_catalog_product->deleteProduct($product_id);

		$this->respondNoContent();
	}

	public function variants() {
		$product_id = $this->param('product_id');

		$this->requireProduct($product_id);

		$variants = new \Dockercart\Api\Variants($this->registry);

		$this->respond($variants->forProduct($product_id));
	}

	/**
	 * Absolute stock change, routed through the warehouse layer (the owner of
	 * the quantity column) exactly like the panel's inline edit.
	 */
	public function stock() {
		$product_id = $this->param('product_id');

		$this->assertPreconditions($this->productResponse($product_id));

		$data = $this->validateRequest($this->body(), [
			'quantity'   => ['required', 'float', 'min:0'],
			'variant_id' => ['int', 'min:0'],
		]);

		$warehouse = new DockercartWarehouse($this->registry);

		$variant_id = (int)($data['variant_id'] ?? 0);

		// setTotalQuantity($product_id, $new_total, $variant_id, $context):
		// configurable products keep a per-variant level, simple ones use 0.
		$warehouse->setTotalQuantity($product_id, (float)$data['quantity'], $variant_id, ['reference' => 'api-stock']);

		// Report what the warehouse actually holds now, not what was requested:
		// for a configurable product without variant_id the head level is 0 by
		// design (stock lives on the variants), and a negative delta may saturate.
		$this->respond([
			'product_id'      => $product_id,
			'variant_id'      => $variant_id,
			'quantity'        => $warehouse->getStockSum($product_id, $variant_id),
			'is_configurable' => $warehouse->isConfigurableProduct($product_id),
		]);
	}

	public function price() {
		$product_id = $this->param('product_id');

		$this->assertPreconditions($this->productResponse($product_id));

		$data = $this->validateRequest($this->body(), [
			'price' => ['required', 'float', 'min:0'],
		]);

		$this->load->model('catalog/product');

		$this->model_catalog_product->updateProductField($product_id, ['price' => (float)$data['price']]);

		$this->respond([
			'product_id' => $product_id,
			'price'      => \Dockercart\Api\Serializer::money($data['price']),
		]);
	}

	/**
	 * The single-product representation used by GET, PUT, PATCH and the ETag
	 * validators — one builder, so If-Match always compares like with like.
	 */
	private function productResponse(int $product_id): array {
		$product = $this->requireProduct($product_id);

		$payload = new ProductPayload($this->registry);

		return $payload->toResponse($product, [
			'descriptions'          => $payload->descriptionsToResponse($this->model_catalog_product->getProductDescriptions($product_id)),
			'categories'            => array_map('intval', $this->model_catalog_product->getProductCategories($product_id)),
			'stores'                => array_map('intval', $this->model_catalog_product->getProductStores($product_id)),
			'images'                => array_map(static function ($image) {
				return [
					'image'       => (string)$image['image'],
					'sort_order'  => (int)$image['sort_order'],
					'language_id' => (int)($image['language_id'] ?? 0),
				];
			}, $this->model_catalog_product->getProductImages($product_id)),
			'videos'                => array_map(static function ($video) {
				return [
					'video_type'  => (string)$video['video_type'],
					'video'       => (string)$video['video'],
					'sort_order'  => (int)$video['sort_order'],
					'language_id' => (int)($video['language_id'] ?? 0),
				];
			}, $this->model_catalog_product->getProductVideos($product_id)),
			'seo_urls'              => $this->seoUrlsForResponse($product_id),
			'related'               => array_map('intval', $this->model_catalog_product->getProductRelated($product_id)),
			'upsell'                => array_map('intval', $this->model_catalog_product->getProductUpsell($product_id)),
			'accessory'             => array_map('intval', $this->model_catalog_product->getProductAccessory($product_id)),
			'related_categories'    => array_map('intval', $this->model_catalog_product->getProductRelatedCategories($product_id)),
			'discounts'             => $this->model_catalog_product->getProductDiscounts($product_id),
			'specials'              => $this->model_catalog_product->getProductSpecials($product_id),
			'customer_group_prices' => $this->model_catalog_product->getProductCustomerGroupPrices($product_id),
		]);
	}

	/**
	 * Loads the product or fails with 404.
	 */
	private function requireProduct(int $product_id): array {
		$this->load->model('catalog/product');

		$product = $this->model_catalog_product->getProduct($product_id);

		if (!$product) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $product;
	}

	/**
	 * SEO keywords keyed by language code instead of language_id.
	 */
	private function seoUrlsForResponse(int $product_id): array {
		$output = [];

		foreach ($this->model_catalog_product->getProductSeoUrls($product_id) as $store_id => $languages) {
			foreach ($languages as $language_id => $keyword) {
				$output[(int)$store_id][$this->languageMap()->code((int)$language_id)] = $keyword;
			}
		}

		return $output;
	}
}
