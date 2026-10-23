<?php
/**
 * Product file upload validation: the hard allowlist, active-content detection
 * and the script/polyglot guards.
 *
 * The rules live in ControllerCatalogProduct::validateProductFileUpload() and
 * are exercised through reflection so a regression in the security gate fails
 * the build.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

if (!class_exists('Controller', false)) {
	require_once DIR_SYSTEM . 'engine/controller.php';
}

if (!class_exists('ControllerCatalogProduct', false)) {
	require_once DIR_APPLICATION . 'controller/catalog/product.php';
}

/**
 * Runs the private validator against a throwaway file.
 */
function product_file_upload_validate(string $filename, ?string $content): ?string {
	$path = sys_get_temp_dir() . '/dc-upload-' . bin2hex(random_bytes(6)) . '-' . $filename;

	if ($content !== null) {
		file_put_contents($path, $content);
	}

	try {
		$registry = new \Registry();
		$registry->set('config', new \Config());

		$controller = new ControllerCatalogProduct($registry);

		$method = new \ReflectionMethod($controller, 'validateProductFileUpload');
		$method->setAccessible(true);

		return $method->invoke($controller, ['tmp_name' => $path, 'type' => 'application/octet-stream'], $filename);
	} finally {
		@unlink($path);
	}
}

const PRODUCT_FILE_TEST_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

return [
	'a valid PDF passes' => static function (): void {
		Assert::same(null, product_file_upload_validate('manual.pdf', PRODUCT_FILE_TEST_PDF));
	},

	'a valid raster image passes' => static function (): void {
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

		Assert::same(null, product_file_upload_validate('photo.png', $png));
	},

	'a plain text file is accepted under a text extension' => static function (): void {
		Assert::same(null, product_file_upload_validate('notes.txt', "Installation notes\n1. Unpack\n"));
	},

	'a PHP script extension is refused' => static function (): void {
		Assert::same('error_filetype', product_file_upload_validate('shell.php', '<?php echo 1;'));
	},

	'an SVG is refused even though it is an image' => static function (): void {
		Assert::same('error_filetype', product_file_upload_validate('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'));
	},

	'an HTML file renamed to .pdf is refused' => static function (): void {
		Assert::same('error_filetype', product_file_upload_validate('invoice.pdf', '<html><body>hi</body></html>'));
	},

	'a PHP payload hidden in a PDF is refused' => static function (): void {
		Assert::same('error_filetype', product_file_upload_validate('manual.pdf', "%PDF-1.4\n<?php echo 1; ?>\n%%EOF\n"));
	},

	'a PHP tag in a text file is refused' => static function (): void {
		Assert::same('error_filetype', product_file_upload_validate('notes.txt', "hello\n<?php echo 1;\n"));
	},

	'a shebang payload is refused' => static function (): void {
		Assert::same('error_filetype', product_file_upload_validate('notes.txt', "#!/bin/sh\nrm -rf /\n"));
	},

	'plain text under a binary extension is refused' => static function (): void {
		Assert::same('error_filetype', product_file_upload_validate('manual.pdf', "just some text\n"));
	},

	'a file without an extension is refused' => static function (): void {
		Assert::same('error_filetype', product_file_upload_validate('README', "text\n"));
	},

	'a missing temporary file is reported as an upload error' => static function (): void {
		Assert::same('error_upload', product_file_upload_validate('manual.pdf', null));
	},
];
