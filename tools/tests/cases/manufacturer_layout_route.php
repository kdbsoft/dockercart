<?php
/**
 * Manufacturer info pages must resolve the same layout as the manufacturer
 * list. The list route was seeded, the info route was not, so brand pages fell
 * back to config_layout_id and lost every module in content_bottom.
 *
 * The fix lives in a migration (per-store, no hardcoded layout id) and relies
 * on the storefront controller/template actually loading and rendering the
 * zone. All three ends are guarded here.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const MANUFACTURER_LAYOUT_MIGRATION = 'docker/mysql/migrations/20261125_manufacturer_info_layout_route.sql';

function manufacturer_layout_migration_sql(): string {
	return repo_file(MANUFACTURER_LAYOUT_MIGRATION);
}

return [
	'migration: brand info route inherits the manufacturer layout route' => static function (): void {
		$sql = manufacturer_layout_migration_sql();

		Assert::contains("src.route = 'product/manufacturer'", $sql);
		Assert::contains("'product/manufacturer/info'", $sql);
		Assert::contains('AND NOT EXISTS (', $sql, 're-runs must not duplicate layout routes');
		Assert::notContains('layout_id = 5', $sql, 'the layout id is store-specific, never hardcoded');
	},

	'migration: an existing wildcard route keeps precedence' => static function (): void {
		$sql = manufacturer_layout_migration_sql();

		Assert::contains("'product/manufacturer/info' LIKE existing.route", $sql, 'a route like product/manufacturer/% already covers the info page');
	},

	'storefront: brand info controller loads content_bottom for list, info and 404' => static function (): void {
		$controller = repo_file('upload/catalog/controller/product/manufacturer.php');

		Assert::same(
			3,
			substr_count($controller, "\$data['content_bottom'] = \$this->load->controller('common/content_bottom');"),
			'index(), info() and the not-found branch'
		);
	},

	'storefront: both brand templates render the content_bottom zone' => static function (): void {
		foreach (['manufacturer_list', 'manufacturer_info'] as $template) {
			Assert::contains('{{ content_bottom }}', repo_file('upload/catalog/view/theme/dockercart/template/product/' . $template . '.twig'), $template);
		}
	},
];
