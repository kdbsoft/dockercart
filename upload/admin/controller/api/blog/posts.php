<?php
/**
 * DockerCart admin REST API — blog posts.
 *
 *   GET    /v1/blog/posts           list (name/status/author filters, pagination)
 *   POST   /v1/blog/posts           create
 *   GET    /v1/blog/posts/{id}      single post with relations
 *   PUT    /v1/blog/posts/{id}      full replace
 *   PATCH  /v1/blog/posts/{id}      partial update
 *   DELETE /v1/blog/posts/{id}      delete
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Payload\BlogPost as BlogPostPayload;

class ControllerApiBlogPosts extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('extension/module/dockercart_blog_post');

		$pagination = $this->pagination();

		$filters = [
			'filter_name'      => $this->queryString('name'),
			'filter_author_id' => $this->queryInt('author_id'),
			'sort'             => $this->queryString('sort', 'date_published'),
			'order'            => strtoupper($this->queryString('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
			'start'            => $pagination['start'],
			'limit'            => $pagination['limit'],
		];

		if ($this->queryString('status') !== '') {
			$filters['filter_status'] = $this->queryInt('status');
		}

		$payload = new BlogPostPayload($this->registry);

		$rows = [];

		foreach ($this->model_extension_module_dockercart_blog_post->getPosts($filters) as $post) {
			$rows[] = $payload->toResponse($post);
		}

		$total = (int)$this->model_extension_module_dockercart_blog_post->getTotalPosts($filters);

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], $total));
	}

	public function get() {
		$this->respondResource($this->postResponse($this->param('post_id')));
	}

	public function add() {
		$this->load->model('extension/module/dockercart_blog_post');

		$payload = new BlogPostPayload($this->registry);

		$post_id = (int)$this->model_extension_module_dockercart_blog_post->addPost($payload->fromRequest($this->body()));

		$this->context()->etag = $this->etagFor($this->postResponse($post_id));

		$this->respondCreated(['post_id' => $post_id], '/api/v1/blog/posts/' . $post_id);
	}

	public function edit() {
		$post_id = $this->param('post_id');

		$this->assertPreconditions($this->postResponse($post_id));

		$payload = new BlogPostPayload($this->registry);

		$this->model_extension_module_dockercart_blog_post->editPost($post_id, $payload->fromRequest($this->body()));

		$this->respondResource($this->postResponse($post_id));
	}

	public function patch() {
		$post_id = $this->param('post_id');

		$this->assertPreconditions($this->postResponse($post_id));

		$this->load->model('extension/module/dockercart_blog_post');

		$payload = new BlogPostPayload($this->registry);

		$patch = $payload->fromPatch($this->body());

		if (!$patch) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$merged = array_merge($payload->fromExisting($post_id), $patch);

		$this->model_extension_module_dockercart_blog_post->editPost($post_id, $merged);

		$this->respondResource($this->postResponse($post_id));
	}

	public function delete() {
		$post_id = $this->param('post_id');

		$this->assertPreconditions($this->postResponse($post_id));

		$this->load->model('extension/module/dockercart_blog_post');

		$this->model_extension_module_dockercart_blog_post->deletePost($post_id);

		$this->respondNoContent();
	}

	private function requirePost(int $post_id): array {
		$this->load->model('extension/module/dockercart_blog_post');

		$post = $this->model_extension_module_dockercart_blog_post->getPost($post_id);

		if (!$post) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $post;
	}

	private function postResponse(int $post_id): array {
		$post = $this->requirePost($post_id);

		$model = $this->model_extension_module_dockercart_blog_post;
		$payload = new BlogPostPayload($this->registry);

		$descriptions = $payload->descriptionsToResponse($model->getPostDescriptions($post_id));

		// getPost() only returns the post row (no description join): expose the
		// default-language title on the resource itself for list/detail parity.
		if (!isset($post['name'])) {
			$default_code = $this->languageMap()->defaultCode();

			$post['name'] = $descriptions[$default_code]['title'] ?? (string)reset($descriptions)['title'] ?? '';
		}

		return $payload->toResponse($post, [
			'descriptions'       => $descriptions,
			'categories'         => array_map('intval', $model->getPostCategories($post_id)),
			'stores'             => array_map('intval', $model->getPostStores($post_id)),
			'products'           => array_map('intval', $model->getPostProducts($post_id)),
			'product_categories' => array_map('intval', $model->getPostProductCategories($post_id)),
			'manufacturers'      => array_map('intval', $model->getPostManufacturers($post_id)),
			'seo_urls'           => $payload->seoUrlsToResponse($model->getPostSeoUrls($post_id)),
		]);
	}
}
