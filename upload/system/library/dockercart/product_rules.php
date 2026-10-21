<?php
/**
 * DockerCart product form rules.
 *
 * The validation rules that used to live only inside
 * ControllerCatalogProduct::validateForm() are extracted here so the panel form
 * and the REST API (PATCH/POST /v1/products) validate exactly the same payload:
 *
 *   - product name / meta title length per language,
 *   - model length,
 *   - quantity_step / minimum are positive decimals and minimum is a multiple of
 *     the step,
 *   - SEO keywords are unique per store + language.
 *
 * validate() returns the same nested structure the panel stores in
 * Controller::$error (field => message, or field => [language_id => message]),
 * so the controller can merge it straight into its error bag and the API can
 * translate it into a 422 "fields" map.
 *
 * The decimal helpers are shared too: the controller's private helpers delegate
 * here, so there is a single implementation of the rounding/comparison rules.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

class DockercartProductRules {
	private const NAME_MAX = 255;
	private const META_TITLE_MAX = 255;
	private const MODEL_MAX = 64;

	private $registry;
	private $db;
	private $language;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->db = $registry->get('db');
		$this->language = $registry->get('language');
	}

	/**
	 * @param array $post       model shaped payload (product_description, model, quantity_step,
	 *                          minimum, product_seo_url as built by the form/API mapper)
	 * @param int   $product_id 0 on create (SEO keywords are then always unique-checked)
	 * @return array nested error bag; empty when the payload is valid
	 */
	public function validate(array $post, int $product_id = 0): array {
		$errors = array();

		foreach ((array)($post['product_description'] ?? array()) as $language_id => $description) {
			if (!is_array($description)) {
				continue;
			}

			$name = (string)($description['name'] ?? '');

			if (utf8_strlen($name) < 1 || utf8_strlen($name) > self::NAME_MAX) {
				$errors['name'][(int)$language_id] = $this->text('error_name', 'Product Name must be between 1 and 255 characters!');
			}

			$meta_title = (string)($description['meta_title'] ?? '');

			if (utf8_strlen($meta_title) < 1 || utf8_strlen($meta_title) > self::META_TITLE_MAX) {
				$errors['meta_title'][(int)$language_id] = $this->text('error_meta_title', 'Meta Title must be between 1 and 255 characters!');
			}
		}

		$model = (string)($post['model'] ?? '');

		if (utf8_strlen($model) < 1 || utf8_strlen($model) > self::MODEL_MAX) {
			$errors['model'] = $this->text('error_model', 'Model must be between 1 and 64 characters!');
		}

		$quantity_step_raw = isset($post['quantity_step']) ? $post['quantity_step'] : '1';
		$minimum_raw = isset($post['minimum']) ? $post['minimum'] : '1';

		$quantity_step = self::normalizeDecimal($quantity_step_raw, 1.0);
		$minimum = self::normalizeDecimal($minimum_raw, 1.0);

		if (!self::isValidDecimal($quantity_step_raw) || $quantity_step <= 0) {
			$errors['quantity_step'] = $this->text('error_quantity_step', 'Quantity Step must be a positive number with up to 2 decimals!');
		}

		if (!self::isValidDecimal($minimum_raw) || $minimum <= 0) {
			$errors['minimum'] = $this->text('error_minimum_value', 'Minimum Quantity must be a positive number with up to 2 decimals!');
		}

		if (!isset($errors['quantity_step']) && !isset($errors['minimum']) && !self::isQuantityMultiple($minimum, $quantity_step)) {
			$errors['minimum_step'] = $this->text('error_minimum_step', 'Minimum Quantity must be a multiple of Quantity Step!');
		}

		$errors = array_merge($errors, $this->validateSeoUrls((array)($post['product_seo_url'] ?? array()), $product_id));

		return $errors;
	}

	/**
	 * SEO keyword uniqueness across stores/languages.
	 *
	 * The admin form passes product_seo_url[store_id][language_id] = keyword; a
	 * keyword may only be reused by the product being edited.
	 */
	public function validateSeoUrls(array $seo_urls, int $product_id = 0): array {
		if (!$seo_urls) {
			return array();
		}

		$errors = array();

		foreach ($seo_urls as $store_id => $languages) {
			foreach ((array)$languages as $language_id => $keyword) {
				$keyword = trim((string)$keyword);

				if ($keyword === '') {
					continue;
				}

				$query = $this->db->query("SELECT `store_id`, `query` FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($keyword) . "' AND `language_id` = '" . (int)$language_id . "'");

				foreach ($query->rows as $row) {
					if ((int)$row['store_id'] !== (int)$store_id) {
						continue;
					}

					if ($product_id && $row['query'] === 'product_id=' . $product_id) {
						continue;
					}

					$errors['keyword'][(int)$store_id][(int)$language_id] = $this->text('error_keyword', 'SEO Keyword is already in use!');

					break;
				}
			}
		}

		return $errors;
	}

	/**
	 * Decimal string with at most 2 decimals, comma or dot.
	 */
	public static function isValidDecimal($value): bool {
		$normalized = str_replace(',', '.', trim((string)$value));

		return preg_match('/^\d+(\.\d{1,2})?$/', $normalized) === 1;
	}

	/**
	 * Decimal string => float, rounded to 2 decimals.
	 */
	public static function normalizeDecimal($value, $default = 0.0): float {
		$normalized = str_replace(',', '.', trim((string)$value));

		if (!is_numeric($normalized)) {
			return (float)$default;
		}

		return round((float)$normalized, 2);
	}

	/**
	 * Whether $quantity is a whole multiple of $step (cent precision).
	 */
	public static function isQuantityMultiple($quantity, $step): bool {
		$quantity_cents = (int)round((float)$quantity * 100);
		$step_cents = (int)round((float)$step * 100);

		if ($step_cents <= 0) {
			return false;
		}

		return ($quantity_cents % $step_cents) === 0;
	}

	/**
	 * Language string with an English fallback for callers that did not load
	 * admin/language/<locale>/catalog/product.php (the REST API).
	 */
	private function text(string $key, string $fallback): string {
		$value = $this->language ? $this->language->get($key) : $key;

		return $value === $key ? $fallback : $value;
	}
}
