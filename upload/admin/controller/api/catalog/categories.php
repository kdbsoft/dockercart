<?php
/**
 * DockerCart admin REST API — categories.
 *
 *   GET    /v1/categories            list (filter name, pagination)
 *   GET    /v1/categories/tree       flat tree (id, parent, path, level)
 *   POST   /v1/categories            create
 *   GET    /v1/categories/{id}       single resource
 *   PUT    /v1/categories/{id}       full replace
 *   PATCH  /v1/categories/{id}       partial update
 *   DELETE /v1/categories/{id}       delete
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Payload\Category as CategoryPayload;

class ControllerApiCatalogCategories extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('catalog/category');

		$pagination = $this->pagination();

		$filters = [
			'filter_name' => $this->queryString('name'),
			'sort'        => $this->queryString('sort', 'sort_order'),
			'order'       => strtoupper($this->queryString('order', 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
			'start'       => $pagination['start'],
			'limit'       => $pagination['limit'],
		];

		$payload = new CategoryPayload($this->registry);

		$rows = [];

		foreach ($this->model_catalog_category->getCategories($filters) as $category) {
			$rows[] = $payload->toResponse($category);
		}

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], (int)$this->model_catalog_category->getTotalCategories()));
	}

	public function tree() {
		$this->load->model('catalog/category');

		$rows = [];

		foreach ($this->model_catalog_category->getTreeCategories() as $category) {
			$rows[] = [
				'category_id'  => (int)$category['category_id'],
				'parent_id'    => (int)$category['parent_id'],
				'name'         => (string)$category['name'],
				'path'         => (string)$category['path'],
				'level'        => (int)$category['level'],
				'has_children' => (bool)$category['has_children'],
				'sort_order'   => (int)$category['sort_order'],
			];
		}

		$this->respond($rows);
	}

	public function get() {
		$this->respondResource($this->categoryResponse($this->param('category_id')));
	}

	public function add() {
		$this->load->model('catalog/category');

		$payload = new CategoryPayload($this->registry);

		$category_id = (int)$this->model_catalog_category->addCategory($payload->fromRequest($this->body()));

		$this->context()->etag = $this->etagFor($this->categoryResponse($category_id));

		$this->respondCreated(['category_id' => $category_id], '/api/v1/categories/' . $category_id);
	}

	public function edit() {
		$category_id = $this->param('category_id');

		$this->assertPreconditions($this->categoryResponse($category_id));

		$payload = new CategoryPayload($this->registry);

		$this->model_catalog_category->editCategory($category_id, $payload->fromRequest($this->body()));

		$this->respondResource($this->categoryResponse($category_id));
	}

	public function patch() {
		$category_id = $this->param('category_id');

		$this->assertPreconditions($this->categoryResponse($category_id));

		$this->load->model('catalog/category');

		$payload = new CategoryPayload($this->registry);

		$patch = $payload->fromPatch($this->body());

		if (!$patch) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$merged = array_merge($payload->fromExisting($category_id), $patch);

		$this->model_catalog_category->editCategory($category_id, $merged);

		$this->respondResource($this->categoryResponse($category_id));
	}

	public function delete() {
		$category_id = $this->param('category_id');

		$this->assertPreconditions($this->categoryResponse($category_id));

		$this->load->model('catalog/category');

		$this->model_catalog_category->deleteCategory($category_id);

		$this->respondNoContent();
	}

	private function requireCategory(int $category_id): array {
		$this->load->model('catalog/category');

		$category = $this->model_catalog_category->getCategory($category_id);

		if (!$category) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $category;
	}

	private function categoryResponse(int $category_id): array {
		// requireCategory() also loads the model, so the GET path (which has no
		// earlier ->load->model() call) and the write paths share one builder.
		$category = $this->requireCategory($category_id);

		$payload = new CategoryPayload($this->registry);

		return $payload->toResponse($category, [
			'descriptions' => $payload->descriptionsToResponse($this->model_catalog_category->getCategoryDescriptions($category_id)),
			'stores'       => array_map('intval', $this->model_catalog_category->getCategoryStores($category_id)),
			'seo_urls'     => $payload->seoUrlsToResponse($this->model_catalog_category->getCategorySeoUrls($category_id)),
			'layouts'      => $this->model_catalog_category->getCategoryLayouts($category_id),
			'related'      => array_map('intval', $this->model_catalog_category->getCategoryRelated($category_id)),
		]);
	}
}
