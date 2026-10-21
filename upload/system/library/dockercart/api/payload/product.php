<?php
/**
 * DockerCart admin REST API — product payload mapper.
 *
 * Translates between the REST representation and the array the admin models
 * expect (ModelCatalogProduct::addProduct() / editProduct()).
 *
 * Why a mapper instead of calling the panel controller: the admin product form
 * payload is HTML-form shaped (product_description[language_id][field],
 * product_seo_url[store_id][language_id], product_image[]...). The API exposes
 * the same model contract but with language *codes* and plain arrays, so the
 * translation is explicit and testable in one place.
 *
 * Two entry points:
 *   fromRequest($body)   — validated + normalized payload for POST / PUT
 *   fromExisting($id)    — current state in the same shape (PATCH base)
 *
 * fromExisting() is essential for PATCH: editProduct() is a full replacement
 * (it deletes and re-inserts descriptions, categories, options, images, …), so
 * a partial update must start from the complete current document or it would
 * silently wipe the relations the client did not mention.
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

final class Product {
	private \Registry $registry;
	private LanguageMap $languages;

	public function __construct(\Registry $registry) {
		$this->registry = $registry;
		$this->languages = new LanguageMap($registry);
	}

	/**
	 * Validates and expands a create/replace payload into the model payload.
	 *
	 * @param array $body       request body
	 * @param int   $product_id 0 on create
	 */
	public function fromRequest(array $body, int $product_id = 0): array {
		$validator = new Validator();

		$data = $validator->validate($body, $this->rules($product_id, true));

		$payload = $this->expand($this->applyDefaults($data));

		$this->assertRules($payload, $product_id);

		return $payload;
	}

	/**
	 * Validates and expands a partial payload (PATCH). Only the keys the client
	 * sent are present, so merging it into fromExisting() never resets values.
	 */
	public function fromPatch(array $body, int $product_id = 0): array {
		$validator = new Validator();

		$data = $validator->validate($body, $this->rules($product_id, false));

		return $this->expand($data);
	}

	/**
	 * Shared rules with the panel form (name/model/decimal/SEO keyword).
	 */
	public function assertRules(array $payload, int $product_id = 0): void {
		$rules = new \DockercartProductRules($this->registry);

		$errors = $rules->validate($payload, $product_id);

		if ($errors) {
			throw new HttpException(Error::VALIDATION_FAILED, $this->mapRuleErrors($errors));
		}
	}

	/**
	 * Defaults for optional create fields (mirrors the panel form defaults).
	 */
	private function applyDefaults(array $data): array {
		$defaults = [
			'sku'              => '',
			'upc'              => '',
			'ean'              => '',
			'jan'              => '',
			'isbn'             => '',
			'mpn'              => '',
			'image'            => '',
			'quantity'         => 0,
			'minimum'          => 1,
			'quantity_step'    => 1,
			'subtract'         => 1,
			'preorder'         => 0,
			'discontinued'     => 0,
			'call_for_price'   => 0,
			'date_available'   => date('Y-m-d'),
			'manufacturer_id'  => 0,
			'main_category_id' => 0,
			'shipping'         => 1,
			'price'            => 0,
			'points'           => 0,
			'currency_id'      => 0,
			'weight'           => 0,
			'weight_class_id'  => 0,
			'length'           => 0,
			'length_class_id'  => 0,
			'width'            => 0,
			'height'           => 0,
			'tax_class_id'     => 0,
			'sort_order'       => 0,
			'status'           => 1,
			'stores'           => [0],
		];

		foreach ($defaults as $key => $value) {
			if (!array_key_exists($key, $data)) {
				$data[$key] = $value;
			}
		}

		return $data;
	}

	/**
	 * Maps the API payload keys onto the model payload keys (only present keys).
	 */
	private function expand(array $data): array {
		$payload = [];

		foreach (['model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'image', 'date_available'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (string)$data[$key];
			}
		}

		foreach (['quantity', 'minimum', 'quantity_step', 'price', 'weight', 'length', 'width', 'height'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (float)$data[$key];
			}
		}

		foreach (['subtract', 'preorder', 'discontinued', 'call_for_price', 'manufacturer_id', 'main_category_id', 'shipping', 'points', 'currency_id', 'weight_class_id', 'length_class_id', 'tax_class_id', 'sort_order', 'status'] as $key) {
			if (array_key_exists($key, $data)) {
				$payload[$key] = (int)$data[$key];
			}
		}

		if (array_key_exists('descriptions', $data)) {
			$payload['product_description'] = $this->descriptions((array)$data['descriptions'], (string)($data['model'] ?? ''));
		}

		if (array_key_exists('categories', $data)) {
			$payload['product_category'] = $this->ids($data['categories']);
		}

		if (array_key_exists('stores', $data)) {
			$payload['product_store'] = $this->ids($data['stores']) ?: [0];
		}

		if (array_key_exists('images', $data)) {
			$payload['product_image'] = $this->images($data['images']);
		}

		if (array_key_exists('videos', $data)) {
			$payload['product_video'] = $this->videos($data['videos']);
		}

		if (array_key_exists('seo_urls', $data)) {
			$payload['product_seo_url'] = $this->seoUrls((array)$data['seo_urls']);
		}

		if (array_key_exists('layouts', $data)) {
			$payload['product_layout'] = [];

			foreach ((array)$data['layouts'] as $store_id => $layout_id) {
				$payload['product_layout'][(int)$store_id] = (int)$layout_id;
			}
		}

		foreach (['related', 'upsell', 'accessory', 'fbt', 'similar', 'downloads', 'related_categories'] as $key) {
			if (array_key_exists($key, $data)) {
				$column = $key === 'downloads' ? 'product_download' : 'product_' . $key;

				$payload[$column] = $this->ids($data[$key]);
			}
		}

		if (array_key_exists('discounts', $data)) {
			$payload['product_discount'] = $this->promotions($data['discounts'], ['customer_group_id', 'quantity', 'priority', 'price', 'date_start', 'date_end', 'auto_renew']);
		}

		if (array_key_exists('specials', $data)) {
			$payload['product_special'] = $this->promotions($data['specials'], ['customer_group_id', 'priority', 'price', 'date_start', 'date_end', 'auto_renew']);
		}

		if (array_key_exists('rewards', $data)) {
			$payload['product_reward'] = $this->rewards($data['rewards']);
		}

		if (array_key_exists('customer_group_prices', $data)) {
			$payload['product_customer_group_price'] = $this->customerGroupPrices($data['customer_group_prices']);
		}

		if (array_key_exists('attributes', $data)) {
			$payload['product_attribute'] = $this->attributes($data['attributes']);
		}

		if (array_key_exists('options', $data)) {
			$payload['product_option'] = $this->options($data['options']);
		}

		if (array_key_exists('gifts', $data)) {
			$payload['product_gift'] = $this->promotions($data['gifts'], ['gift_product_id', 'minimum_quantity', 'date_start', 'date_end', 'auto_renew']);
		}

		if (array_key_exists('bxgy', $data)) {
			$payload['product_bxgy'] = $this->promotions($data['bxgy'], ['reward_product_id', 'trigger_quantity', 'discount_value', 'date_start', 'date_end', 'auto_renew']);
		}

		return $payload;
	}

	/**
	 * Current state of a product in the model payload shape.
	 */
	public function fromExisting(int $product_id): array {
		$model = $this->model('catalog/product');

		$product = $model->getProduct($product_id);

		if (!$product) {
			throw new HttpException(Error::NOT_FOUND);
		}

		$payload = [];

		foreach ($product as $key => $value) {
			if (in_array($key, ['name', 'description', 'meta_title', 'meta_description', 'meta_keyword', 'tag'], true)) {
				continue;
			}

			$payload[$key] = $value;
		}

		$payload['product_description'] = $model->getProductDescriptions($product_id);
		$payload['product_category'] = $model->getProductCategories($product_id);
		$payload['product_store'] = $model->getProductStores($product_id);
		$payload['product_image'] = $model->getProductImages($product_id);
		$payload['product_video'] = $model->getProductVideos($product_id);
		$payload['product_seo_url'] = $model->getProductSeoUrls($product_id);
		$payload['product_layout'] = $model->getProductLayouts($product_id);
		$payload['product_related'] = $model->getProductRelated($product_id);
		$payload['product_upsell'] = $model->getProductUpsell($product_id);
		$payload['product_accessory'] = $model->getProductAccessory($product_id);
		$payload['product_fbt'] = $model->getProductFbt($product_id);
		$payload['product_similar'] = $model->getProductSimilar($product_id);
		$payload['product_related_category'] = $model->getProductRelatedCategories($product_id);
		$payload['product_download'] = $model->getProductDownloads($product_id);
		$payload['product_discount'] = $model->getProductDiscounts($product_id);
		$payload['product_special'] = $model->getProductSpecials($product_id);
		$payload['product_reward'] = $model->getProductRewards($product_id);
		$payload['product_customer_group_price'] = $model->getProductCustomerGroupPrices($product_id);
		$payload['product_attribute'] = $model->getProductAttributes($product_id);
		$payload['product_gift'] = $model->getProductGifts($product_id);
		$payload['product_bxgy'] = $model->getProductBxgy($product_id);

		// Options come back with their identity columns; editProduct() deletes
		// and re-inserts them, so the ids must not be reused.
		$options = [];

		foreach ($model->getProductOptions($product_id) as $option) {
			$values = [];

			foreach ((array)($option['product_option_value'] ?? []) as $value) {
				unset($value['product_option_value_id']);

				$values[] = $value;
			}

			unset($option['product_option_id'], $option['name']);

			$option['product_option_value'] = $values;

			$options[] = $option;
		}

		$payload['product_option'] = $options;

		unset($payload['quantity']);

		// editProduct() routes quantity through the warehouse layer; keep the
		// current stock so a PATCH never reports a phantom delta.
		$payload['quantity'] = (float)$product['quantity'];

		return $payload;
	}

	/**
	 * PATCH merge: scalars overwrite, relation lists replace wholesale when the
	 * client sends them and stay untouched otherwise.
	 */
	public function merge(array $existing, array $patch): array {
		foreach ($patch as $key => $value) {
			$existing[$key] = $value;
		}

		return $existing;
	}

	/**
	 * API representation of a product (single resource).
	 */
	public function toResponse(array $product, array $extra = []): array {
		$response = [
			'product_id'       => (int)$product['product_id'],
			'model'            => (string)$product['model'],
			'sku'              => (string)($product['sku'] ?? ''),
			'upc'              => (string)($product['upc'] ?? ''),
			'ean'              => (string)($product['ean'] ?? ''),
			'jan'              => (string)($product['jan'] ?? ''),
			'isbn'             => (string)($product['isbn'] ?? ''),
			'mpn'              => (string)($product['mpn'] ?? ''),
			'quantity'         => (float)($product['quantity'] ?? 0),
			'minimum'          => (float)($product['minimum'] ?? 0),
			'quantity_step'    => (float)($product['quantity_step'] ?? 0),
			'subtract'         => (int)($product['subtract'] ?? 0),
			'price'            => \Dockercart\Api\Serializer::money($product['price'] ?? 0),
			'points'           => (int)($product['points'] ?? 0),
			'status'           => (int)($product['status'] ?? 0),
			'image'            => (string)($product['image'] ?? ''),
			'manufacturer_id'  => (int)($product['manufacturer_id'] ?? 0),
			'main_category_id' => (int)($product['main_category_id'] ?? 0),
			'tax_class_id'     => (int)($product['tax_class_id'] ?? 0),
			'sort_order'       => (int)($product['sort_order'] ?? 0),
			'date_available'   => \Dockercart\Api\Serializer::date($product['date_available'] ?? ''),
			'date_added'       => \Dockercart\Api\Serializer::datetime($product['date_added'] ?? ''),
			'date_modified'    => \Dockercart\Api\Serializer::datetime($product['date_modified'] ?? ''),
		];

		if (!empty($product['name'])) {
			$response['name'] = (string)$product['name'];
		}

		return array_merge($response, $extra);
	}

	/**
	 * Descriptions keyed by language code, with the panel's HTML encoding
	 * removed (see Serializer::decodeText()).
	 */
	public function descriptionsToResponse(array $descriptions): array {
		$output = [];

		foreach ($descriptions as $language_id => $description) {
			foreach (['name', 'description', 'meta_title', 'meta_description', 'meta_keyword', 'tag'] as $field) {
				if (isset($description[$field])) {
					$description[$field] = \Dockercart\Api\Serializer::decodeText($description[$field]);
				}
			}

			$output[$this->languages->code((int)$language_id)] = $description;
		}

		return $output;
	}

	/**
	 * Converts validation errors from DockercartProductRules (language ids) into
	 * the API field map (language codes).
	 */
	public function mapRuleErrors(array $errors): array {
		$fields = [];

		foreach ($errors as $field => $value) {
			if ($field === 'keyword') {
				foreach ((array)$value as $store_id => $languages) {
					foreach ((array)$languages as $language_id => $message) {
						$fields['seo_urls.' . $store_id . '.' . $this->languages->code((int)$language_id)] = [$message];
					}
				}

				continue;
			}

			if (is_array($value)) {
				foreach ($value as $language_id => $message) {
					$fields['descriptions.' . $this->languages->code((int)$language_id) . '.' . $field] = [$message];
				}

				continue;
			}

			$fields[$field] = [(string)$value];
		}

		return $fields;
	}

	private function rules(int $product_id, bool $require_core = true): array {
		$rules = [
			'model'            => ['required', 'string', 'max:64', 'no_html'],
			'sku'              => ['string', 'max:64', 'no_html'],
			'upc'              => ['string', 'max:12', 'no_html'],
			'ean'              => ['string', 'max:14', 'no_html'],
			'jan'              => ['string', 'max:13', 'no_html'],
			'isbn'             => ['string', 'max:17', 'no_html'],
			'mpn'              => ['string', 'max:64', 'no_html'],
			'image'            => ['string', 'max:255'],
			'quantity'         => ['float', 'min:0'],
			'minimum'          => ['float', 'min:0'],
			'quantity_step'    => ['float', 'min:0'],
			'subtract'         => ['int', 'in:0,1'],
			'preorder'         => ['int', 'in:0,1'],
			'discontinued'     => ['int', 'in:0,1'],
			'call_for_price'   => ['int', 'in:0,1'],
			'date_available'   => ['date'],
			'manufacturer_id'  => ['int', 'min:0'],
			'main_category_id' => ['int', 'min:0'],
			'shipping'         => ['int', 'in:0,1'],
			'price'            => ['float', 'min:0'],
			'points'           => ['int', 'min:0'],
			'currency_id'      => ['int', 'min:0'],
			'weight'           => ['float', 'min:0'],
			'weight_class_id'  => ['int', 'min:0'],
			'length'           => ['float', 'min:0'],
			'length_class_id'  => ['int', 'min:0'],
			'width'            => ['float', 'min:0'],
			'height'           => ['float', 'min:0'],
			'tax_class_id'     => ['int', 'min:0'],
			'sort_order'       => ['int'],
			'status'           => ['int', 'in:0,1'],
			'descriptions'     => ['required', 'array'],
			'categories'       => ['array'],
			'stores'           => ['array'],
			'images'           => ['array'],
			'videos'           => ['array'],
			'seo_urls'         => ['array'],
			'layouts'          => ['array'],
			'related'          => ['array'],
			'upsell'           => ['array'],
			'accessory'        => ['array'],
			'fbt'              => ['array'],
			'similar'          => ['array'],
			'related_categories' => ['array'],
			'downloads'        => ['array'],
			'discounts'        => ['array'],
			'specials'         => ['array'],
			'rewards'          => ['array'],
			'customer_group_prices' => ['array'],
			'attributes'       => ['array'],
			'options'          => ['array'],
			'gifts'            => ['array'],
			'bxgy'             => ['array'],
		];

		if (!$require_core) {
			$rules['model'] = ['string', 'max:64', 'no_html'];
			$rules['descriptions'] = ['array'];
		}

		return $rules;
	}

	/**
	 * @param array $descriptions language code => field values
	 */
	private function descriptions(array $descriptions, string $fallback_name): array {
		if (!$descriptions) {
			return [];
		}

		$output = [];

		foreach ($descriptions as $code => $fields) {
			if (!is_array($fields)) {
				throw new HttpException(Error::VALIDATION_FAILED, [
					'descriptions.' . $code => ['must be an object'],
				]);
			}

			$language_id = $this->languages->requireId((string)$code);

			$name = trim((string)($fields['name'] ?? ''));

			if ($name === '') {
				throw new HttpException(Error::VALIDATION_FAILED, [
					'descriptions.' . $code . '.name' => ['required'],
				]);
			}

			$description = (string)($fields['description'] ?? '');

			$output[$language_id] = [
				'name'             => \Dockercart\Api\Serializer::encodeText($name),
				'description'      => \Dockercart\Api\Serializer::encodeText($description),
				'meta_title'       => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_title'] ?? $name)),
				'meta_description' => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_description'] ?? '')),
				'meta_keyword'     => \Dockercart\Api\Serializer::encodeText((string)($fields['meta_keyword'] ?? '')),
				'tag'              => \Dockercart\Api\Serializer::encodeText((string)($fields['tag'] ?? '')),
			];
		}

		return $output;
	}

	private function images(array $images): array {
		$output = [];

		foreach ($images as $image) {
			if (is_string($image)) {
				$image = ['image' => $image];
			}

			$path = trim((string)($image['image'] ?? ''));

			if ($path === '') {
				continue;
			}

			$output[] = [
				'image'       => $path,
				'sort_order'  => (int)($image['sort_order'] ?? 0),
				'language_id' => (int)($image['language_id'] ?? 0),
			];
		}

		return $output;
	}

	private function videos(array $videos): array {
		$output = [];

		foreach ($videos as $video) {
			$type = (string)($video['video_type'] ?? '');
			$url = trim((string)($video['video'] ?? ''));

			if ($url === '' || !in_array($type, ['youtube', 'mp4'], true)) {
				continue;
			}

			$output[] = [
				'video_type'  => $type,
				'video'       => $url,
				'language_id' => (int)($video['language_id'] ?? 0),
				'sort_order'  => (int)($video['sort_order'] ?? 0),
			];
		}

		return $output;
	}

	/**
	 * {store_id: {language code: keyword}} => {store_id: {language_id: keyword}}
	 */
	private function seoUrls(array $seo_urls): array {
		$output = [];

		foreach ($seo_urls as $store_id => $languages) {
			foreach ((array)$languages as $code => $keyword) {
				$keyword = trim((string)$keyword);

				if ($keyword === '') {
					continue;
				}

				$output[(int)$store_id][$this->languages->requireId((string)$code)] = $keyword;
			}
		}

		return $output;
	}

	private function promotions(array $promotions, array $fields): array {
		$output = [];

		foreach ($promotions as $promotion) {
			if (!is_array($promotion)) {
				continue;
			}

			$row = [];

			foreach ($fields as $field) {
				switch ($field) {
					case 'price':
					case 'discount_value':
					case 'trigger_quantity':
					case 'minimum_quantity':
						$row[$field] = (float)($promotion[$field] ?? 0);
						break;
					case 'date_start':
					case 'date_end':
						$row[$field] = (string)($promotion[$field] ?? '0000-00-00');
						break;
					case 'auto_renew':
						$row[$field] = (int)(!empty($promotion[$field]));
						break;
					default:
						$row[$field] = (int)($promotion[$field] ?? 0);
				}
			}

			$output[] = $row;
		}

		return $output;
	}

	/**
	 * {customer_group_id: points} or [{customer_group_id, points}]
	 */
	private function rewards(array $rewards): array {
		$output = [];

		foreach ($rewards as $key => $value) {
			if (is_array($value)) {
				$output[(int)($value['customer_group_id'] ?? $key)] = ['points' => (int)($value['points'] ?? 0)];

				continue;
			}

			$output[(int)$key] = ['points' => (int)$value];
		}

		return $output;
	}

	private function customerGroupPrices(array $prices): array {
		$output = [];

		foreach ($prices as $price) {
			if (!is_array($price)) {
				continue;
			}

			$output[] = [
				'customer_group_id' => (int)($price['customer_group_id'] ?? 0),
				'price'             => (float)($price['price'] ?? 0),
			];
		}

		return $output;
	}

	/**
	 * [{attribute_id, text: {language code: text}}]
	 */
	private function attributes(array $attributes): array {
		$output = [];

		foreach ($attributes as $attribute) {
			if (!is_array($attribute) || empty($attribute['attribute_id'])) {
				continue;
			}

			$descriptions = [];

			foreach ((array)($attribute['text'] ?? []) as $code => $text) {
				$descriptions[$this->languages->requireId((string)$code)] = ['text' => (string)$text];
			}

			$output[] = [
				'attribute_id'                 => (int)$attribute['attribute_id'],
				'product_attribute_description' => $descriptions,
			];
		}

		return $output;
	}

	/**
	 * Option rows as accepted by ModelCatalogProduct (type is resolved from
	 * oc_option, since the model dispatches on it).
	 */
	private function options(array $options): array {
		$db = $this->registry->get('db');
		$output = [];

		foreach ($options as $option) {
			if (!is_array($option) || empty($option['option_id'])) {
				continue;
			}

			$option_id = (int)$option['option_id'];

			$type_query = $db->query("SELECT `type` FROM `" . DB_PREFIX . "option` WHERE `option_id` = '" . $option_id . "'");

			$type = $type_query->num_rows ? (string)$type_query->row['type'] : (string)($option['type'] ?? '');

			$values = [];

			foreach ((array)($option['values'] ?? $option['product_option_value'] ?? []) as $value) {
				if (!is_array($value)) {
					continue;
				}

				$values[] = [
					'option_value_id'  => (int)($value['option_value_id'] ?? 0),
					'price'            => (float)($value['price'] ?? 0),
					'price_prefix'     => (string)($value['price_prefix'] ?? '+'),
					'points'           => (int)($value['points'] ?? 0),
					'points_prefix'    => (string)($value['points_prefix'] ?? '+'),
					'weight'           => (float)($value['weight'] ?? 0),
					'weight_prefix'    => (string)($value['weight_prefix'] ?? '+'),
					'is_hit'           => (int)($value['is_hit'] ?? 0),
					'sort_order'       => (int)($value['sort_order'] ?? 0),
				];
			}

			$output[] = [
				'option_id'            => $option_id,
				'type'                 => $type,
				'required'             => (int)($option['required'] ?? 0),
				'product_option_value' => $values,
			];
		}

		return $output;
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
