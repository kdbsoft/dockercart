<?php
/**
 * DockerCart admin REST API — category payload mapper.
 *
 * Mirrors ModelCatalogCategory's contract (addCategory / editCategory) while
 * exposing language codes instead of language ids:
 *
 *   { "parent_id": 0, "status": 1, "top": 0, "column": 1, "sort_order": 0,
 *     "image": "...", "descriptions": { "en-gb": { "name": "...", ... } },
 *     "stores": [0], "seo_urls": { "0": { "en-gb": "keyword" } }, ... }
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api\Payload;

use Dockercart\Api\Error;
use Dockercart\Api\HttpException;
use Dockercart\Api\LanguageMap;
use Dockercart\Api\Validator;

final class Category {
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
		$model = $this->model('catalog/category');

		$category = $model->getCategory($category_id);

		if (!$category) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$payload = [
			'parent_id'                => (int)$category['parent_id'],
			'top'                      => (int)$category['top'],
			'column'                   => (int)$category['column'],
			'sort_order'               => (int)$category['sort_order'],
			'status'                   => (int)$category['status'],
			'image'                    => (string)($category['image'] ?? ''),
			'icon'                     => (string)($category['icon'] ?? ''),
			'background_image'         => (string)($category['background_image'] ?? ''),
			'banner_id'                => (int)($category['banner_id'] ?? 0),
			'review_criteria_group_id' => (int)($category['review_criteria_group_id'] ?? 0),
			'category_description'     => $model->getCategoryDescriptions($category_id),
			'category_store'           => $model->getCategoryStores($category_id),
			'category_seo_url'         => $model->getCategorySeoUrls($category_id),
			'category_layout'          => $model->getCategoryLayouts($category_id),
			'category_related'         => $model->getCategoryRelated($category_id),
		];

		// A category without stores would lose its storefront visibility on a
		// partial update; the panel always stores at least the default store.
		if (!$payload['category_store']) {
			$payload['category_store'] = [0];
		}

		return $payload;
	}

	public function toResponse(array $category, array $extra = []): array {
		$response = [
			'category_id'              => (int)$category['category_id'],
			'parent_id'                => (int)($category['parent_id'] ?? 0),
			'top'                      => (int)($category['top'] ?? 0),
			'column'                   => (int)($category['column'] ?? 0),
			'sort_order'               => (int)($category['sort_order'] ?? 0),
			'status'                   => (int)($category['status'] ?? 0),
			'image'                    => (string)($category['image'] ?? ''),
			'icon'                     => (string)($category['icon'] ?? ''),
			'background_image'         => (string)($category['background_image'] ?? ''),
			'banner_id'                => (int)($category['banner_id'] ?? 0),
			'review_criteria_group_id' => (int)($category['review_criteria_group_id'] ?? 0),
		];

		if (isset($category['name'])) {
			$response['name'] = self::plain($category['name']);
		}

		if (isset($category['path'])) {
			$response['path'] = self::plain((string)$category['path']);
		}

		return array_merge($response, $extra);
	}

	public function descriptionsToResponse(array $descriptions): array {
		$output = [];

		foreach ($descriptions as $language_id => $description) {
			foreach (['name', 'description', 'meta_title', 'meta_description', 'meta_keyword'] as $field) {
				if (isset($description[$field])) {
					$description[$field] = \Dockercart\Api\Serializer::decodeText($description[$field]);
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
			'parent_id'                => ['int', 'min:0'],
			'top'                      => ['int', 'in:0,1'],
			'column'                   => ['int', 'min:0'],
			'sort_order'               => ['int'],
			'status'                   => ['int', 'in:0,1'],
			'image'                    => ['string', 'max:255'],
			'icon'                     => ['string', 'max:255'],
			'background_image'         => ['string', 'max:255'],
			'banner_id'                => ['int', 'min:0'],
			'review_criteria_group_id' => ['int', 'min:0'],
			'descriptions'             => ['array'],
			'stores'                   => ['array'],
			'seo_urls'                 => ['array'],
			'layouts'                  => ['array'],
			'related'                  => ['array'],
		];

		if ($require_core) {
			$rules['descriptions'] = ['required', 'array'];
			$rules['parent_id'] = ['required', 'int', 'min:0'];
			$rules['status'] = ['required', 'int', 'in:0,1'];
			$rules['sort_order'] = ['required', 'int'];
		}

		return $rules;
	}

	private function applyDefaults(array $data): array {
		$defaults = [
			'top'        => 0,
			'column'     => 1,
			'image'      => '',
			'icon'       => '',
			'background_image' => '',
			'banner_id'  => 0,
			'review_criteria_group_id' => 0,
			'stores'     => [0],
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

		foreach (['parent_id', 'top', 'column', 'sort_order', 'status', 'banner_id', 'review_criteria_group_id'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (int)$data[$key];
			}
		}

		foreach (['image', 'icon', 'background_image'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (string)$data[$key];
			}
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
					'name'             => \Dockercart\Api\Serializer::encodeText($name),
					'description'      => \Dockercart\Api\Serializer::encodeText((string)($fields['description'] ?? '')),
					'meta_title'       => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_title'] ?? $name)),
					'meta_description' => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_description'] ?? '')),
					'meta_keyword'     => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_keyword'] ?? '')),
				];
			}
		}

		if (array_key_exists('stores', $data)) {
			$payload['category_store'] = $this->ids($data['stores']) ?: [0];
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

		if (array_key_exists('layouts', $data)) {
			$payload['category_layout'] = [];

			foreach ((array)$data['layouts'] as $store_id => $layout_id) {
				$payload['category_layout'][(int)$store_id] = (int)$layout_id;
			}
		}

		if (array_key_exists('related', $data)) {
			$payload['category_related'] = $this->ids($data['related']);
		}

		return $payload;
	}

	private function ids($values): array {		$ids = [];

		foreach ((array)$values as $value) {
			$id = (int)$value;

			if ($id > 0) {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}

	/**
	 * The panel builds category paths as HTML ("A&nbsp;&nbsp;&gt;&nbsp;&nbsp;B") for
	 * its tree widget; the API returns plain "A > B" text.
	 */
	private static function plain(string $value): string {
		$value = str_replace('&nbsp;&nbsp;&gt;&nbsp;&nbsp;', ' > ', $value);

		return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}

	private function model(string $route) {
		$load = $this->registry->get('load');
		$load->model($route);

		return $this->registry->get('model_' . str_replace('/', '_', $route));
	}
}
