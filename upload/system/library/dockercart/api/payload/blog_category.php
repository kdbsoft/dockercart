<?php
/**
 * DockerCart admin REST API — blog category payload mapper.
 *
 * ModelExtensionModuleDockercartBlogCategory contract:
 *   { "parent_id": 0, "image": "...", "status": 1, "sort_order": 0,
 *     "descriptions": { "en-gb": { "name": "...", ... } },
 *     "stores": [0], "seo_urls": { "0": { "en-gb": "keyword" } } }
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

final class BlogCategory {
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

	public function fromExisting(int $category_id): array {
		$model = $this->model('extension/module/dockercart_blog_category');

		$category = $model->getCategory($category_id);

		if (!$category) {
			throw new HttpException(Error::NOT_FOUND);
		}

		return [
			'parent_id'            => (int)$category['parent_id'],
			'image'                => (string)($category['image'] ?? ''),
			'status'               => (int)$category['status'],
			'sort_order'           => (int)($category['sort_order'] ?? 0),
			'category_description' => $model->getCategoryDescriptions($category_id),
			'category_store'       => $model->getCategoryStores($category_id),
			'category_seo_url'     => $model->getCategorySeoUrls($category_id),
		];
	}

	public function toResponse(array $category, array $extra = []): array {
		return array_merge([
			'category_id' => (int)$category['category_id'],
			'parent_id'   => (int)($category['parent_id'] ?? 0),
			'name'        => Serializer::decodeText($category['name'] ?? ''),
			'image'       => (string)($category['image'] ?? ''),
			'status'      => (int)($category['status'] ?? 0),
			'sort_order'  => (int)($category['sort_order'] ?? 0),
		], $extra);
	}

	public function descriptionsToResponse(array $descriptions): array {
		$output = [];

		foreach ($descriptions as $language_id => $description) {
			foreach (['name', 'description', 'meta_title', 'meta_description', 'meta_keyword'] as $field) {
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
			'parent_id'    => ['int', 'min:0'],
			'image'        => ['string', 'max:255'],
			'status'       => ['int', 'in:0,1'],
			'sort_order'   => ['int'],
			'descriptions' => ['array'],
			'stores'       => ['array'],
			'seo_urls'     => ['array'],
		];

		if ($require_core) {
			$rules['descriptions'] = ['required', 'array'];
			$rules['status'] = ['required', 'int', 'in:0,1'];
		}

		return $rules;
	}

	private function applyDefaults(array $data): array
	{
		foreach (['parent_id' => 0, 'image' => '', 'sort_order' => 0, 'stores' => [0]] as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		return $data;
	}

	private function expand(array $data): array
	{
		$payload = [];

		foreach (['parent_id', 'status', 'sort_order'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (int)$data[$key];
			}
		}

		if (array_key_exists('image', $data)) {
			$payload['image'] = (string)$data['image'];
		}

		if (array_key_exists('descriptions', $data)) {
			$payload['category_description'] = [];

			foreach ((array)$data['descriptions'] as $code => $fields) {
				if (!is_array($fields)) {
					throw new HttpException(Error::VALIDATION_FAILED, [
						'descriptions.' . $code => ['must be an object'],
					]);
				}

				$name = trim((string)($fields['name'] ?? ''));

				if ($name === '') {
					throw new HttpException(Error::VALIDATION_FAILED, [
						'descriptions.' . $code . '.name' => ['required'],
					]);
				}

				$payload['category_description'][$this->languages->requireId((string)$code)] = [
					'name'             => Serializer::encodeText($name),
					'description'      => Serializer::encodeText((string)($fields['description'] ?? '')),
					'meta_title'       => Serializer::encodeText((string)($fields['meta_title'] ?? $name)),
					'meta_description' => Serializer::encodeText((string)($fields['meta_description'] ?? '')),
					'meta_keyword'     => Serializer::encodeText((string)($fields['meta_keyword'] ?? '')),
				];
			}
		}

		if (array_key_exists('stores', $data)) {
			$payload['category_store'] = [];

			foreach ((array)$data['stores'] as $store_id) {
				$payload['category_store'][] = (int)$store_id;
			}

			if (!$payload['category_store']) {
				$payload['category_store'] = [0];
			}
		}

		if (array_key_exists('seo_urls', $data)) {
			$payload['category_seo_url'] = [];

			foreach ((array)$data['seo_urls'] as $store_id => $languages) {
				foreach ((array)$languages as $code => $keyword) {
					$keyword = trim((string)$keyword);

					if ($keyword === '') {
						continue;
					}

					$payload['category_seo_url'][(int)$store_id][$this->languages->requireId((string)$code)] = $keyword;
				}
			}
		}

		return $payload;
	}

	private function model(string $route) {
		$load = $this->registry->get('load');
		$load->model($route);

		return $this->registry->get('model_' . str_replace('/', '_', $route));
	}
}
