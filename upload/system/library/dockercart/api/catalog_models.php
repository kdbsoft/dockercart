<?php
/**
 * DockerCart customer (storefront) REST API — catalog model loader.
 *
 * The store API reuses the admin MVC tree (DIR_APPLICATION = upload/admin/), so
 * $this->load->model() only finds admin models. The checkout pipeline, on the
 * other hand, lives in the catalog tree (extension/total/*, extension/shipping/*,
 * extension/payment/*, checkout/order) — these models are shared business logic
 * with the storefront and must not be duplicated.
 *
 * This loader requires the catalog file and registers the model under the same
 * registry key the admin loader uses ("model_<route>"), so $this->load->model()
 * and $this->model_<route> keep working for both the controllers and for the
 * catalog models that load further models while they run.
 *
 * Loader::model() always prefers an existing registry key, which is also how a
 * nested $this->load->model('setting/extension') inside a catalog model keeps
 * resolving to the admin model — deliberate, and the reason the admin model
 * carries getExtensions() as well.
 *
 * Only routes whose class name is unique in the catalog tree may be loaded this
 * way: upload/catalog/model/catalog/product.php (ModelCatalogProduct) and the
 * localisation models also exist in the admin tree with the same class name, so
 * they are read through the admin models or through raw SQL instead.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class CatalogModels {
	/**
	 * Routes that are safe to load from the catalog tree (no admin class with
	 * the same name). Kept explicit so an accidental collision fails loudly.
	 */
	private const ALLOWED = [
		'checkout/order',
		'account/customer',
		'extension/total/voucher',
	];

	private const ALLOWED_PREFIX = [
		'extension/total/',
		'extension/shipping/',
		'extension/payment/',
	];

	/**
	 * Loads a catalog model and registers it under the admin loader's key.
	 *
	 * @return mixed the model instance (also registered as model_<route>)
	 */
	public static function load(\Registry $registry, string $route) {
		$route = (string)preg_replace('/[^a-zA-Z0-9_\/]/', '', $route);

		$key = 'model_' . str_replace('/', '_', $route);

		if ($registry->has($key)) {
			return $registry->get($key);
		}

		if (!self::allowed($route)) {
			throw new \RuntimeException('Catalog model ' . $route . ' may not be loaded in the admin MVC tree');
		}

		$file = DIR_CATALOG . 'model/' . $route . '.php';

		if (!is_file($file)) {
			throw new \RuntimeException('Catalog model ' . $route . ' is missing');
		}

		include_once(modification($file));

		$class = 'Model' . preg_replace('/[^a-zA-Z0-9]/', '', $route);

		if (!class_exists($class)) {
			throw new \RuntimeException('Catalog model ' . $route . ' declares no ' . $class);
		}

		$model = new $class($registry);

		$registry->set($key, $model);

		return $model;
	}

	/**
	 * Every total extension code registered in the store, enabled or not.
	 *
	 * The totals pipeline only instantiates the enabled ones, but the order
	 * writer loads "extension/total/voucher" unconditionally, and a total that
	 * is disabled today can still be the one that produced an existing order
	 * total. Loading them all keeps the order write path free of surprises.
	 *
	 * @return string[]
	 */
	public static function totalCodes(\Registry $registry): array {
		$db = $registry->get('db');

		$query = $db->query("SELECT `code` FROM `" . DB_PREFIX . "extension` WHERE `type` = 'total' GROUP BY `code` ORDER BY `code`");

		$codes = [];

		foreach ($query->rows as $row) {
			$codes[] = (string)$row['code'];
		}

		return $codes;
	}

	/**
	 * Pre-loads every catalog model the order pipeline can reach, so a nested
	 * $this->load->model() inside ModelCheckoutOrder::addOrder() (voucher) or
	 * addOrderHistory() (customer) finds the registry key instead of looking for
	 * a missing admin model.
	 */
	public static function preloadForOrder(\Registry $registry): void {
		foreach (self::totalCodes($registry) as $code) {
			self::load($registry, 'extension/total/' . $code);
		}

		self::load($registry, 'extension/total/voucher');
		self::load($registry, 'account/customer');
	}

	private static function allowed(string $route): bool {
		if (in_array($route, self::ALLOWED, true)) {
			return true;
		}

		foreach (self::ALLOWED_PREFIX as $prefix) {
			if (strpos($route, $prefix) === 0 && strpos($route, '..') === false) {
				return strlen($route) > strlen($prefix);
			}
		}

		return false;
	}
}
