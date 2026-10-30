<?php
/**
 * DockerCart admin REST API — blog authors.
 *
 *   GET    /v1/blog/authors        list
 *   POST   /v1/blog/authors        create
 *   GET    /v1/blog/authors/{id}   single author
 *   PUT    /v1/blog/authors/{id}   full replace
 *   PATCH  /v1/blog/authors/{id}   partial update
 *   DELETE /v1/blog/authors/{id}   delete
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\Serializer;

class ControllerApiBlogAuthors extends \Dockercart\Api\Controller {
	public function index() {
		$this->load->model('extension/module/dockercart_blog_author');

		$pagination = $this->pagination();

		$rows = [];

		foreach ($this->model_extension_module_dockercart_blog_author->getAuthors([
			'start' => $pagination['start'],
			'limit' => $pagination['limit'],
		]) as $author) {
			$rows[] = $this->authorResponse($author);
		}

		$this->respondCollection($rows, $this->meta($pagination['page'], $pagination['limit'], (int)$this->model_extension_module_dockercart_blog_author->getTotalAuthors()));
	}

	public function get() {
		$this->respondResource($this->authorResponse($this->requireAuthor($this->param('author_id'))));
	}

	public function add() {
		$this->load->model('extension/module/dockercart_blog_author');

		$data = $this->validateAuthor($this->body(), true);

		$author_id = (int)$this->model_extension_module_dockercart_blog_author->addAuthor($data);

		$this->context()->etag = $this->etagFor($this->authorResponse($this->requireAuthor($author_id)));

		$this->respondCreated(['author_id' => $author_id], '/api/v1/blog/authors/' . $author_id);
	}

	public function edit() {
		$author_id = $this->param('author_id');

		$this->assertPreconditions($this->authorResponse($this->requireAuthor($author_id)));

		$this->load->model('extension/module/dockercart_blog_author');

		$this->model_extension_module_dockercart_blog_author->editAuthor($author_id, $this->validateAuthor($this->body(), true));

		$this->respondResource($this->authorResponse($this->requireAuthor($author_id)));
	}

	public function patch() {
		$author_id = $this->param('author_id');

		$author = $this->requireAuthor($author_id);

		$this->assertPreconditions($this->authorResponse($author));

		$patch = $this->validateAuthor($this->body(), false);

		if (!$patch) {
			throw new HttpException(Error::VALIDATION_FAILED, ['body' => ['no updatable fields supplied']]);
		}

		$this->load->model('extension/module/dockercart_blog_author');

		$this->model_extension_module_dockercart_blog_author->editAuthor($author_id, array_merge([
			'name'       => (string)$author['name'],
			'email'      => (string)$author['email'],
			'image'      => (string)$author['image'],
			'bio'        => (string)$author['bio'],
			'status'     => (int)$author['status'],
			'sort_order' => (int)$author['sort_order'],
		], $patch));

		$this->respondResource($this->authorResponse($this->requireAuthor($author_id)));
	}

	public function delete() {
		$author_id = $this->param('author_id');

		$this->assertPreconditions($this->authorResponse($this->requireAuthor($author_id)));

		$this->load->model('extension/module/dockercart_blog_author');

		$this->model_extension_module_dockercart_blog_author->deleteAuthor($author_id);

		$this->respondNoContent();
	}

	private function validateAuthor(array $body, bool $require_core): array {
		$rules = [
			'name'       => ['string', 'max:255', 'no_html'],
			'email'      => ['email', 'max:96'],
			'image'      => ['string', 'max:255'],
			'bio'        => ['string', 'max:65535'],
			'status'     => ['int', 'in:0,1'],
			'sort_order' => ['int'],
		];

		if ($require_core) {
			$rules['name'] = ['required', 'string', 'max:255', 'no_html'];
			$rules['status'] = ['required', 'int', 'in:0,1'];
		}

		$data = $this->validateRequest($body, $rules);

		// The author name and bio are rendered by the storefront blog with
		// autoescape off, so they are escaped on write (the panel form is escaped
		// by Request::clean()) and decoded again by authorResponse().
		if (array_key_exists('name', $data)) {
			$data['name'] = Serializer::encodeTextWithin('name', $data['name'], 255);
		}

		if (array_key_exists('bio', $data)) {
			$data['bio'] = Serializer::encodeTextWithinBytes('bio', $data['bio'], 65535);
		}

		$defaults = [
			'email'      => '',
			'image'      => '',
			'bio'        => '',
			'sort_order' => 0,
		];

		foreach ($defaults as $key => $value) {
			if ($require_core && !array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		return $data;
	}

	private function requireAuthor(int $author_id): array {
		$this->load->model('extension/module/dockercart_blog_author');

		$author = $this->model_extension_module_dockercart_blog_author->getAuthor($author_id);

		if (!$author) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return $author;
	}

	private function authorResponse(array $author): array {
		return [
			'author_id'    => (int)$author['author_id'],
			'name'         => Serializer::decodeText($author['name'] ?? ''),
			'email'        => (string)$author['email'],
			'image'        => (string)$author['image'],
			'bio'          => Serializer::decodeText($author['bio'] ?? ''),
			'status'       => (int)$author['status'],
			'sort_order'   => (int)$author['sort_order'],
			'date_added'   => Serializer::datetime($author['date_added'] ?? ''),
			'date_modified' => Serializer::datetime($author['date_modified'] ?? ''),
		];
	}
}
