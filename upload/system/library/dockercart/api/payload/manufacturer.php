<?php
/**
 * DockerCart admin REST API — manufacturer payload mapper.
 *
 * ModelCatalogManufacturer contract:
 *   { "image": "...", "sort_order": 0, "status": 1,
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
use Dockercart\Api\Validator;

final class Manufacturer {
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

	public function fromExisting(int $manufacturer_id): array {
		$model = $this->model('catalog/manufacturer');

		$manufacturer = $model->getManufacturer($manufacturer_id);

		if (!$manufacturer) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$payload = [
			'image'                    => (string)($manufacturer['image'] ?? ''),
			'sort_order'               => (int)$manufacturer['sort_order'],
			'status'                   => (int)$manufacturer['status'],
			'manufacturer_description' => $model->getManufacturerDescriptions($manufacturer_id),
			'manufacturer_store'       => $model->getManufacturerStores($manufacturer_id),
			'manufacturer_seo_url'     => $model->getManufacturerSeoUrls($manufacturer_id),
		];

		if (!$payload['manufacturer_store']) {
			$payload['manufacturer_store'] = [0];
		}

		return $payload;
	}

	public function toResponse(array $manufacturer, array $extra = []): array {
		return array_merge([
			'manufacturer_id' => (int)$manufacturer['manufacturer_id'],
			'name'            => (string)($manufacturer['name'] ?? ''),
			'image'           => (string)($manufacturer['image'] ?? ''),
			'sort_order'      => (int)($manufacturer['sort_order'] ?? 0),
			'status'          => (int)($manufacturer['status'] ?? 0),
		], $extra);
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
			'image'        => ['string', 'max:255'],
			'sort_order'   => ['int'],
			'status'       => ['int', 'in:0,1'],
			'descriptions' => ['array'],
			'stores'       => ['array'],
			'seo_urls'     => ['array'],
		];

		if ($require_core) {
			$rules['descriptions'] = ['required', 'array'];
			$rules['sort_order'] = ['required', 'int'];
			$rules['status'] = ['required', 'int', 'in:0,1'];
		}

		return $rules;
	}

	private function applyDefaults(array $data): array {
		foreach (['image' => '', 'stores' => [0]] as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		return $data;
	}

	private function expand(array $data): array {
		$payload = [];

		foreach (['sort_order', 'status'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (int)$data[$key];
			}
		}

		if (array_key_exists('image', $data)) {
			$payload['image'] = (string)$data['image'];
		}

		if (array_key_exists('descriptions', $data)) {
			$payload['manufacturer_description'] = [];

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

				$payload['manufacturer_description'][$this->languages->requireId((string)$code)] = [
					'name'             => \Dockercart\Api\Serializer::encodeText($name),
					'description'      => \Dockercart\Api\Serializer::encodeText((string)($fields['description'] ?? '')),
					'meta_title'       => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_title'] ?? $name)),
					'meta_description' => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_description'] ?? '')),
					'meta_keyword'     => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_keyword'] ?? '')),
				];
			}
		}

		if (array_key_exists('stores', $data)) {
			$payload['manufacturer_store'] = [];

			foreach ((array)$data['stores'] as $store_id) {
				$payload['manufacturer_store'][] = (int)$store_id;
			}

			if (!$payload['manufacturer_store']) {
				$payload['manufacturer_store'] = [0];
			}
		}

		if (array_key_exists('seo_urls', $data)) {
			$payload['manufacturer_seo_url'] = [];

			foreach ((array)$data['seo_urls'] as $store_id => $languages) {
				foreach ((array)$languages as $code => $keyword) {
					$keyword = trim((string)$keyword);

					if ($keyword === '') {
						continue;
					}

					$payload['manufacturer_seo_url'][(int)$store_id][$this->languages->requireId((string)$code)] = $keyword;
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
