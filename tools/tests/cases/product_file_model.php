<?php
/**
 * Product file attachments (admin model): storage keys, the save/reconcile
 * contract and the payload lifecycle (delete / copy).
 *
 * The physical payload lives outside the webroot, so the model must never leave
 * an orphan behind when a file row is removed, and a copied product must get its
 * own payload (deleting one product must not break the other's downloads).
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

if (!defined('DIR_STORAGE')) {
	define('DIR_STORAGE', sys_get_temp_dir() . '/dockercart-product-file-tests/');
}

/**
 * Loads the admin product file model with a fake DB and a real Config.
 */
function product_file_test_model(?FakeDb $db = null): ModelCatalogProductFile {
	if (!class_exists('ModelCatalogProductFile', false)) {
		require_once DIR_APPLICATION . 'model/catalog/product_file.php';
	}

	$config = new \Config();
	$config->load('default');

	$registry = new \Registry();
	$registry->set('db', $db ?: new FakeDb());
	$registry->set('config', $config);
	$registry->set('log', new \Log('product-file-tests.log'));

	return new ModelCatalogProductFile($registry);
}

/**
 * @return array<string, mixed>
 */
function product_file_test_row(int $id, string $storage_key): array {
	return [
		'product_file_id' => $id,
		'storage_key'     => $storage_key,
		'original_name'   => $storage_key,
		'file_size'       => 10,
		'mime_type'       => 'application/pdf',
		'sort_order'      => 0,
		'status'          => 1,
	];
}

/**
 * The storage directory is shared between tests (DIR_STORAGE is a constant),
 * so wipe it before a test that counts or deletes payloads.
 */
function product_file_test_clean(ModelCatalogProductFile $model): string {
	$dir = $model->getStorageDirectory();

	foreach (glob($dir . '*') ?: [] as $file) {
		if (is_file($file)) {
			@unlink($file);
		}
	}

	return $dir;
}

return [
	'storage key keeps a sane extension and drops anything else' => static function (): void {
		$model = product_file_test_model();

		Assert::same(true, (bool)preg_match('/^[0-9a-f]{32}\.pdf$/', $model->createStorageKey('Instruction v2.PDF')));
		Assert::same(true, (bool)preg_match('/^[0-9a-f]{32}$/', $model->createStorageKey('weird.<script>')));
		Assert::same(true, (bool)preg_match('/^[0-9a-f]{32}$/', $model->createStorageKey('no-extension')));
	},

	'saveFiles inserts a row and its localized titles for a new file' => static function (): void {
		$db = new FakeDb();
		$model = product_file_test_model($db);

		$db->pushResult([]); // getProductFiles

		$model->saveFiles(10, [[
			'storage_key'   => 'aaa.pdf',
			'original_name' => 'manual.pdf',
			'file_size'     => 123,
			'mime_type'     => 'application/pdf',
			'sort_order'    => 0,
			'status'        => 1,
			'name'          => [1 => 'Manual', 2 => 'Руководство'],
		]]);

		$inserts = $db->inserts();

		Assert::same(3, count($inserts), 'one file row + two descriptions');
		Assert::contains('product_file SET', $inserts[0]);
		Assert::contains('product_file_description', $inserts[1]);
		Assert::contains('product_file_description', $inserts[2]);
	},

	'saveFiles skips a row without a storage key' => static function (): void {
		$db = new FakeDb();
		$model = product_file_test_model($db);

		$db->pushResult([]); // getProductFiles

		$model->saveFiles(10, [['original_name' => 'ghost.pdf']]);

		Assert::same(0, count($db->inserts()));
	},

	'saveFiles updates kept files and removes the ones missing from the form' => static function (): void {
		$db = new FakeDb();
		$model = product_file_test_model($db);

		$dir = product_file_test_clean($model);
		file_put_contents($dir . 'remove-me.pdf', 'x');

		$db->pushResult([
			product_file_test_row(5, 'remove-me.pdf'),
			product_file_test_row(6, 'keep-me.pdf'),
		]); // getProductFiles
		$db->pushResult([product_file_test_row(5, 'remove-me.pdf')]); // deleteFile -> getFile

		$model->saveFiles(10, [[
			'product_file_id' => 6,
			'storage_key'     => 'keep-me.pdf',
			'original_name'   => 'keep-me.pdf',
			'file_size'       => 10,
			'mime_type'       => 'application/pdf',
			'sort_order'      => 0,
			'status'          => 1,
			'name'            => [1 => 'Keep'],
		]]);

		Assert::false(is_file($dir . 'remove-me.pdf'), 'payload of the removed file is deleted');

		$updated = false;

		foreach ($db->queries as $sql) {
			if (stripos($sql, 'UPDATE') === 0 && strpos($sql, 'product_file SET') !== false) {
				$updated = true;
			}
		}

		Assert::true($updated, 'the kept file is updated, not re-inserted');
	},

	'deleteFilesByProduct removes every payload of the product' => static function (): void {
		$db = new FakeDb();
		$model = product_file_test_model($db);

		$dir = product_file_test_clean($model);
		file_put_contents($dir . 'del-a.pdf', 'a');
		file_put_contents($dir . 'del-b.pdf', 'b');

		$db->pushResult([
			['product_file_id' => 7, 'storage_key' => 'del-a.pdf'],
			['product_file_id' => 8, 'storage_key' => 'del-b.pdf'],
		]);

		$model->deleteFilesByProduct(10);

		Assert::false(is_file($dir . 'del-a.pdf'));
		Assert::false(is_file($dir . 'del-b.pdf'));
	},

	'copyFiles gives the copy its own payload and titles' => static function (): void {
		$db = new FakeDb();
		$model = product_file_test_model($db);

		$dir = product_file_test_clean($model);
		file_put_contents($dir . 'orig.pdf', 'payload');

		$db->pushResult([product_file_test_row(9, 'orig.pdf')]); // getProductFiles
		$db->pushResult([['language_id' => 1, 'name' => 'Original']]); // getFileDescriptions

		$model->copyFiles(10, 20);

		Assert::true(is_file($dir . 'orig.pdf'), 'the source payload is left untouched');
		Assert::same(2, count(glob($dir . '*.pdf') ?: []), 'a second, independent payload exists');

		$inserts = $db->inserts();
		Assert::contains('product_file SET', $inserts[0]);
		Assert::contains('product_file_description', $inserts[1]);
	},

	'deleteUploadedFile ignores path traversal in the storage key' => static function (): void {
		$db = new FakeDb();
		$model = product_file_test_model($db);

		$dir = $model->getStorageDirectory();
		$outside = dirname(rtrim($dir, '/')) . '/keep.txt';
		file_put_contents($outside, 'do not delete');

		$model->deleteUploadedFile('../../keep.txt');

		Assert::true(is_file($outside), 'a traversal key must not delete outside the storage directory');

		@unlink($outside);
	},
];
