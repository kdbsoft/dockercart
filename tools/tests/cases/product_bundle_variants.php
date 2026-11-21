<?php
/**
 * Bundle variants: a configurable product cannot be added without an exact variant.
 *
 * Covers the migration, the model normalisation/validation helpers, the variant-aware
 * writes, the storefront exact-variant matching and the admin localisation.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

function bundle_variant_test_model(?FakeDb $db = null): ModelCatalogProductBundle {
	if (!class_exists('ModelCatalogProductBundle', false)) {
		require_once DIR_APPLICATION . 'model/catalog/product_bundle.php';
	}

	$config = new \Config();

	$registry = new \Registry();
	$registry->set('db', $db ?? new FakeDb());
	$registry->set('config', $config);

	return new ModelCatalogProductBundle($registry);
}

return [
	'migration: adds variant_id idempotently with default-variant backfill' => static function (): void {
		$sql = repo_file('docker/mysql/migrations/20261120_bundle_variants.sql');

		Assert::contains('ADD COLUMN IF NOT EXISTS `variant_id`', $sql);
		Assert::contains('`variant_id` INT(11) NOT NULL DEFAULT 0', $sql);
		Assert::contains('`default_variant_id`', $sql);
		Assert::contains('is_configurable', $sql);
	},

	'model: normalises legacy ints, pid:vid strings and arrays with dedupe' => static function (): void {
		$model = bundle_variant_test_model();

		Assert::same([], $model->normalizeBundleLines('nope'));

		$lines = $model->normalizeBundleLines([5001, '5023:703', ['product_id' => 5006, 'variant_id' => 701], '5023:703', 0, -5]);

		Assert::same([
			['product_id' => 5001, 'variant_id' => 0],
			['product_id' => 5023, 'variant_id' => 703],
			['product_id' => 5006, 'variant_id' => 701],
		], $lines);
	},

	'model: parses and encodes the admin pid[:vid] CSV' => static function (): void {
		$model = bundle_variant_test_model();

		$lines = $model->parseBundleLinesCsv('5001,5023:703,5006:0');

		Assert::same([
			['product_id' => 5001, 'variant_id' => 0],
			['product_id' => 5023, 'variant_id' => 703],
			['product_id' => 5006, 'variant_id' => 0],
		], $lines);

		Assert::same('5001,5023:703,5006', $model->encodeBundleLinesCsv($lines));
		Assert::same([], $model->parseBundleLinesCsv(''));
	},

	'model: detects configurable products added without a variant' => static function (): void {
		$db = new FakeDb();
		$model = bundle_variant_test_model($db);

		// SELECT product_configurable answers: 5023 is configurable, 5001 is not.
		$db->pushResult([['product_id' => 5023]]);

		$missing = $model->findConfigurableWithoutVariant([
			['product_id' => 5001, 'variant_id' => 0],
			['product_id' => 5023, 'variant_id' => 0],
		]);

		Assert::same([5023], $missing);
	},

	'model: configurable products with a pinned variant pass validation' => static function (): void {
		$db = new FakeDb();
		$model = bundle_variant_test_model($db);
		$db->pushResult([['product_id' => 5023]]);

		Assert::same([], $model->findConfigurableWithoutVariant([
			['product_id' => 5001, 'variant_id' => 0],
			['product_id' => 5023, 'variant_id' => 703],
		]));
	},

	'model: addBundle and editBundle persist variant_id' => static function (): void {
		$db = new FakeDb();
		$model = bundle_variant_test_model($db);

		$model->addBundle([
			'name' => '', 'discount_type' => 'percentage', 'discount_value' => 10,
			'date_start' => '0000-00-00', 'date_end' => '0000-00-00',
			'status' => 1, 'sort_order' => 0,
			'bundle_product' => [['product_id' => 5001, 'variant_id' => 0], ['product_id' => 5023, 'variant_id' => 703]],
			'bundle_store' => [0],
		]);

		$model->editBundle(7, [
			'name' => '', 'discount_type' => 'percentage', 'discount_value' => 10,
			'date_start' => '0000-00-00', 'date_end' => '0000-00-00',
			'status' => 1, 'sort_order' => 0,
			'bundle_product' => ['5001', '5023:703'],
			'bundle_store' => [0],
		]);

		$inserts = implode("\n", $db->inserts('product_bundle_product'));

		Assert::contains("product_id = '5001', variant_id = '0'", $inserts);
		Assert::contains("product_id = '5023', variant_id = '703'", $inserts);
	},

	'model: getBundleProducts returns product_id + variant_id lines' => static function (): void {
		$db = new FakeDb();
		$model = bundle_variant_test_model($db);
		$db->pushResult([
			['product_id' => '5001', 'variant_id' => '0'],
			['product_id' => '5023', 'variant_id' => '703'],
		]);

		Assert::same([
			['product_id' => 5001, 'variant_id' => 0],
			['product_id' => 5023, 'variant_id' => 703],
		], $model->getBundleProducts(1));
	},

	'library: bundle lines carry variant prices and validity helpers' => static function (): void {
		$lib = repo_file('upload/system/library/product_bundle.php');

		Assert::contains('variant_id', $lib);
		Assert::contains('variant_price', $lib);
		Assert::contains('findConfigurableWithoutVariant', $lib);
		Assert::contains('isBundleValid', $lib);
		Assert::contains("variant_id = '", $lib);
	},

	'admin product: configurable without variant blocks the save' => static function (): void {
		$controller = repo_file('upload/admin/controller/catalog/product.php');

		Assert::contains('findConfigurableWithoutVariant', $controller);
		Assert::contains('error_bundle_variant_required', $controller);
		Assert::contains('buildBundleLines', $controller);
		Assert::contains('getBundleVariantLabel', $controller);
	},

	'admin bundle: standalone validation also requires variants' => static function (): void {
		$controller = repo_file('upload/admin/controller/catalog/product_bundle.php');

		Assert::contains('findConfigurableWithoutVariant', $controller);
		Assert::contains('error_bundle_variant_required', $controller);
	},

	'admin form: variant picker with pid:vid encoding' => static function (): void {
		$twig = repo_file('upload/admin/view/template/catalog/product_form.twig');

		Assert::contains('dc-bundle-variant', $twig);
		Assert::contains('onBundleVariantChange', $twig);
		Assert::contains('data-variant-id', $twig);
		Assert::contains('getMatrix', $twig);
		Assert::contains('is_configurable', $twig);
	},

	'storefront: product page hides invalid bundles and prices variants' => static function (): void {
		$controller = repo_file('upload/catalog/controller/product/product.php');

		Assert::contains('findConfigurableWithoutVariant', $controller);
		Assert::contains('ProductPricingCalculator', $controller);
		Assert::contains('&variant_id=', $controller);
		Assert::contains('variant_label', $controller);
	},

	'storefront: addBundle pins variant_id and rejects invalid bundles' => static function (): void {
		$controller = repo_file('upload/catalog/controller/checkout/cart.php');

		Assert::contains('isBundleValid', $controller);
		Assert::contains("'variant_id' => ", $controller);
		Assert::contains('error_bundle_invalid', $controller);
	},

	'storefront: bundle total matches exact product:variant lines' => static function (): void {
		$total = repo_file('upload/catalog/model/extension/total/product_bundle.php');

		Assert::contains('$cart_by_key', $total);
		Assert::contains("':'", $total);
		Assert::contains('findConfigurableWithoutVariant', $total);
	},

	'locales: variant-required errors in en-gb, ru-ua and uk-ua' => static function (): void {
		foreach (['en-gb', 'ru-ua', 'uk-ua'] as $locale) {
			$product = read_language_file(REPO_ROOT . '/upload/admin/language/' . $locale . '/catalog/product.php');
			$bundle = read_language_file(REPO_ROOT . '/upload/admin/language/' . $locale . '/catalog/product_bundle.php');

			Assert::true(isset($product['error_bundle_variant_required']) && $product['error_bundle_variant_required'] !== '', $locale . '/product.php needs error_bundle_variant_required');
			Assert::true(isset($bundle['error_bundle_variant_required']) && $bundle['error_bundle_variant_required'] !== '', $locale . '/product_bundle.php needs error_bundle_variant_required');
			Assert::true(isset($product['entry_bundle_variant']), $locale . '/product.php needs entry_bundle_variant');
			Assert::true(isset($product['text_bundle_variant_placeholder']), $locale . '/product.php needs text_bundle_variant_placeholder');
		}
	},
];
