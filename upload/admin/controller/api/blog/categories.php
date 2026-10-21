<?php
/**
 * DockerCart admin REST API — blog categories.
 *
 *   GET    /v1/blog/categories        list
 *   POST   /v1/blog/categories        create
 *   GET    /v1/blog/categories/{id}   single category
 *   PUT    /v1/blog/categories/{id}   full replace
 *   PATCH  /v1/blog/categories/{id}   partial update
 *   DELETE /v1/blog/categories/{id}   delete
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Payload\BlogCategory as BlogCategoryPayload;

class ControllerApiBlogCategories extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('extension/module/dockercart_blog_category');

		$pagination = $this->pagination();

		$payload = new BlogCategoryPayload($this->registry);

		$rows = [];

		foreach ($this->model_extension_module_dockercart_blog_category->getCategories([
			'start' => $pagination['start'],
			'limit' => $pagination['limit'],
		]) as $category) {
			$rows[] = $payload->toResponse($category);
		}

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], (int)$this->model_extension_module_dockercart_blog_category->getTotalCategories()));
	}

	public function get() {
		$this->respondResource($this->categoryResponse($this->param('category_id')));
	}

	public function add() {
		$this->load->model('extension/module/dockercart_blog_category');

		$payload = new BlogCategoryPayload($this->registry);

		$category_id = (int)$this->model_extension_module_dockercart_blog_category->addCategory($payload->fromRequest($this->body()));

		$this->context()->etag = $this->etagFor($this->categoryResponse($category_id));

		$this->respondCreated(['category_id' => $category_id], '/api/v1/blog/categories/' . $category_id);
	}

	public function edit() {
		$category_id = $this->param('category_id');

		$this->assertPreconditions($this->categoryResponse($category_id));

		$payload = new BlogCategoryPayload($this->registry);

		$this->model_extension_module_dockercart_blog_category->editCategory($category_id, $payload->fromRequest($this->body()));

		$this->respondResource($this->categoryResponse($category_id));
	}

	public function patch() {
		$category_id = $this->param('category_id');

		$this->assertPreconditions($this->categoryResponse($category_id));

		$this->load->model('extension/module/dockercart_blog_category');

		$payload = new BlogCategoryPayload($this->registry);

		$patch = $payload->fromPatch($this->body());

		if (!$patch) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$merged = array_merge($payload->fromExisting($category_id), $patch);

		$this->model_extension_module_dockercart_blog_category->editCategory($category_id, $merged);

		$this->respondResource($this->categoryResponse($category_id));
	}

	public function delete() {
		$category_id = $this->param('category_id');

		$this->assertPreconditions($this->categoryResponse($category_id));

		$this->load->model('extension/module/dockercart_blog_category');

		$this->model_extension_module_dockercart_blog_category->deleteCategory($category_id);

		$this->respondNoContent();
	}

	private function requireCategory(int $category_id): array {
		$this->load->model('extension/module/dockercart_blog_category');

		$category = $this->model_extension_module_dockercart_blog_category->getCategory($category_id);

		if (!$category) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $category;
	}

	private function categoryResponse(int $category_id): array {
		$category = $this->requireCategory($category_id);

		$model = $this->model_extension_module_dockercart_blog_category;
		$payload = new BlogCategoryPayload($this->registry);

		$name = '';

		$descriptions = $payload->descriptionsToResponse($model->getCategoryDescriptions($category_id));

		$default_code = $this->languageMap()->defaultCode();

		if (isset($descriptions[$default_code]['name'])) {
			$name = $descriptions[$default_code]['name'];
		}

		return $payload->toResponse($category + ['name' => $name], [
			'descriptions' => $descriptions,
			'stores'       => array_map('intval', $model->getCategoryStores($category_id)),
			'seo_urls'     => $payload->seoUrlsToResponse($model->getCategorySeoUrls($category_id)),
		]);
	}
}
