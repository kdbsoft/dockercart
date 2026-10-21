<?php
/**
 * DockerCart admin REST API — blog post payload mapper.
 *
 * ModelExtensionModuleDockercartBlogPost contract (addPost / editPost):
 *   { "author_id": 1, "image": "...", "status": 1, "featured": 0,
 *     "allow_comments": 1, "sort_order": 0, "date_published": "...",
 *     "descriptions": { "en-gb": { "title": "...", "excerpt": "...", "content": "...",
 *                                  "meta_title": "...", "meta_description": "...",
 *                                  "meta_keyword": "...", "tags": "a, b" } },
 *     "categories": [1], "stores": [0], "products": [12],
 *     "product_categories": [3], "manufacturers": [4],
 *     "seo_urls": { "0": { "en-gb": "keyword" } } }
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api\Payload;

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\LanguageMap;
use Dockercart\Api\Serializer;
use Dockercart\Api\Validator;

final class BlogPost {
	private \Registry $registry;
	private LanguageMap $languages;

	public function __construct(\Registry $registry) {
		$this->registry = $registry;
		$this->languages = new LanguageMap($registry);
	}

	public function fromRequest(array $body): array {
		$validator = new Validator();

		$data = $validator->validate($body, $this->rules(true));

		return $this->expand($this->applyDefaults($data));
	}

	public function fromPatch(array $body): array {
		$validator = new Validator();

		$data = $validator->validate($body, $this->rules(false));

		return $this->expand($data);
	}

	public function fromExisting(int $post_id): array {
		$model = $this->model('extension/module/dockercart_blog_post');

		$post = $model->getPost($post_id);

		if (!$post) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return [
			'author_id'          => (int)$post['author_id'],
			'image'              => (string)($post['image'] ?? ''),
			'background_image'   => (string)($post['background_image'] ?? ''),
			'status'             => (int)$post['status'],
			'featured'           => (int)($post['featured'] ?? 0),
			'allow_comments'     => (int)($post['allow_comments'] ?? 0),
			'sort_order'         => (int)($post['sort_order'] ?? 0),
			'date_published'     => (string)($post['date_published'] ?? ''),
			'post_description'   => $model->getPostDescriptions($post_id),
			'post_category'      => $model->getPostCategories($post_id),
			'post_store'         => $model->getPostStores($post_id),
			'post_product'       => $model->getPostProducts($post_id),
			'post_product_category' => $model->getPostProductCategories($post_id),
			'post_manufacturer'  => $model->getPostManufacturers($post_id),
			'post_seo_url'       => $model->getPostSeoUrls($post_id),
		];
	}

	public function toResponse(array $post, array $extra = []): array {
		$response = [
			'post_id'          => (int)$post['post_id'],
			'author_id'        => (int)($post['author_id'] ?? 0),
			'title'            => (string)($post['name'] ?? $post['title'] ?? ''),
			'image'            => (string)($post['image'] ?? ''),
			'background_image' => (string)($post['background_image'] ?? ''),
			'status'           => (int)($post['status'] ?? 0),
			'featured'         => (int)($post['featured'] ?? 0),
			'allow_comments'   => (int)($post['allow_comments'] ?? 0),
			'sort_order'       => (int)($post['sort_order'] ?? 0),
			'date_published'   => Serializer::datetime($post['date_published'] ?? ''),
			'date_added'       => Serializer::datetime($post['date_added'] ?? ''),
			'date_modified'    => Serializer::datetime($post['date_modified'] ?? ''),
		];

		if (isset($post['author_name'])) {
			$response['author_name'] = (string)$post['author_name'];
		}

		if (isset($post['comment_count'])) {
			$response['comment_count'] = (int)$post['comment_count'];
		}

		return array_merge($response, $extra);
	}

	public function descriptionsToResponse(array $descriptions): array {
		$output = [];

		foreach ($descriptions as $language_id => $description) {
			foreach (['title', 'excerpt', 'content', 'meta_title', 'meta_description', 'meta_keyword'] as $field) {
				if (isset($description[$field])) {
					$description[$field] = Serializer::decodeText($description[$field]);
				}
			}

			$output[$this->languages->code((int)$language_id)] = $description;
		}

		return $output;
	}

	public function seoUrlsToResponse(array $seo_urls): array {
		$output = [];

		foreach ($seo_urls as $store_id => $languages) {
			foreach ($languages as $language_id => $keyword) {
				$output[(int)$store_id][$this->languages->code((int)$language_id)] = $keyword;
			}
		}

		return $output;
	}

	private function rules(bool $require_core): array {
		$rules = [
			'author_id'          => ['int', 'min:0'],
			'image'              => ['string', 'max:255'],
			'background_image'   => ['string', 'max:255'],
			'status'             => ['int', 'in:0,1'],
			'featured'           => ['int', 'in:0,1'],
			'allow_comments'     => ['int', 'in:0,1'],
			'sort_order'         => ['int'],
			'date_published'     => ['datetime'],
			'descriptions'       => ['array'],
			'categories'         => ['array'],
			'stores'             => ['array'],
			'products'           => ['array'],
			'product_categories' => ['array'],
			'manufacturers'      => ['array'],
			'seo_urls'           => ['array'],
		];

		if ($require_core) {
			$rules['descriptions'] = ['required', 'array'];
			$rules['author_id'] = ['required', 'int', 'min:0'];
			$rules['status'] = ['required', 'int', 'in:0,1'];
		}

		return $rules;
	}

	private function applyDefaults(array $data): array {
		$defaults = [
			'image'            => '',
			'background_image' => '',
			'featured'         => 0,
			'allow_comments'   => 1,
			'sort_order'       => 0,
			'date_published'   => date('Y-m-d H:i:s'),
			'stores'           => [0],
		];

		foreach ($defaults as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		return $data;
	}

	private function expand(array $data): array {
		$payload = [];

		foreach (['author_id', 'status', 'featured', 'allow_comments', 'sort_order'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (int)$data[$key];
			}
		}

		foreach (['image', 'background_image', 'date_published'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (string)$data[$key];
			}
		}

		if (array_key_exists('descriptions', $data)) {
			$payload['post_description'] = [];

			foreach ((array)$data['descriptions'] as $code => $fields) {
				if (!is_array($fields)) {
					throw new HttpException(Error::VALIDATION_FAILED, [
						'descriptions.' . $code => ['must be an object'],
					]);
				}

				$title = trim((string)($fields['title'] ?? ''));

				if ($title === '') {
					throw new HttpException(Error::VALIDATION_FAILED, [
						'descriptions.' . $code . '.title' => ['required'],
					]);
				}

				$payload['post_description'][$this->languages->requireId((string)$code)] = [
					'title'            => Serializer::encodeText($title),
					'excerpt'          => Serializer::encodeText((string)($fields['excerpt'] ?? '')),
					'content'          => Serializer::encodeText((string)($fields['content'] ?? '')),
					'meta_title'       => Serializer::encodeText((string)($fields['meta_title'] ?? $title)),
					'meta_description' => Serializer::encodeText((string)($fields['meta_description'] ?? '')),
					'meta_keyword'     => Serializer::encodeText((string)($fields['meta_keyword'] ?? '')),
					'tags'             => Serializer::encodeText((string)($fields['tags'] ?? '')),
				];
			}
		}

		$relations = [
			'categories'         => 'post_category',
			'stores'             => 'post_store',
			'products'           => 'post_product',
			'product_categories' => 'post_product_category',
			'manufacturers'      => 'post_manufacturer',
		];

		foreach ($relations as $key => $column) {
			if (array_key_exists($key, $data)) {
				$payload[$column] = $this->ids($data[$key]);

				if ($column === 'post_store' && !$payload[$column]) {
					$payload[$column] = [0];
				}
			}
		}

		if (array_key_exists('seo_urls', $data)) {
			$payload['post_seo_url'] = [];

			foreach ((array)$data['seo_urls'] as $store_id => $languages) {
				foreach ((array)$languages as $code => $keyword) {
					$keyword = trim((string)$keyword);

					if ($keyword === '') {
						continue;
					}

					$payload['post_seo_url'][(int)$store_id][$this->languages->requireId((string)$code)] = $keyword;
				}
			}
		}

		return $payload;
	}

	private function ids($values): array {
		$ids = [];

		foreach ((array)$values as $value) {
			$id = (int)$value;

			if ($id > 0) {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}

	private function model(string $route) {
		$load = $this->registry->get('load');
		$load->model($route);

		return $this->registry->get('model_' . str_replace('/', '_', $route));
	}
}
