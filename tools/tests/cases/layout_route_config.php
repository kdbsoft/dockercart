<?php
/**
 * Layout Builder — route to zone map.
 *
 * The map used to live inside the admin controller. It is now a library so the
 * builder canvas and any future caller (tests, migrations, CLI tools) resolve
 * the same positions and responsive flags without booting the admin app.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Layout\RouteConfig;

return [
	'map: modern grid routes keep all four zones with lg-only columns' => static function (): void {
		foreach (['product/category', 'product/special', 'product/search', 'blog/category'] as $route) {
			$config = RouteConfig::forRoute($route);

			Assert::same(['content_top', 'column_left', 'column_right', 'content_bottom'], $config['positions'], $route);
			Assert::same(['column_left' => 'lg_only', 'column_right' => 'lg_only'], $config['responsive'], $route);
		}
	},

	'map: legacy routes keep columns visible at every breakpoint' => static function (): void {
		foreach (['common/home', 'checkout/checkout', 'affiliate/register'] as $route) {
			$config = RouteConfig::forRoute($route);

			Assert::same(['column_left' => 'always', 'column_right' => 'always'], $config['responsive'], $route);
		}
	},

	'map: narrow templates expose only the zones they render' => static function (): void {
		Assert::same(['content_bottom'], RouteConfig::forRoute('product/compare')['positions']);
		Assert::same(['content_bottom'], RouteConfig::forRoute('blog/post')['positions']);
		Assert::same(['content_top', 'content_bottom'], RouteConfig::forRoute('product/product')['positions']);
		Assert::same([], RouteConfig::forRoute('product/product')['responsive']);
	},

	'map: exact routes win over the account/affiliate prefix rules' => static function (): void {
		Assert::same(['column_left' => 'always', 'column_right' => 'always'], RouteConfig::forRoute('affiliate/login')['responsive']);
		Assert::same(['content_top', 'content_bottom'], RouteConfig::forRoute('account/orders')['positions']);
		Assert::same(['content_top', 'content_bottom'], RouteConfig::forRoute('affiliate/custom')['positions']);
	},

	'map: unknown routes fall back to the full grid' => static function (): void {
		$config = RouteConfig::forRoute('extension/my_page');

		Assert::same(['content_top', 'column_left', 'column_right', 'content_bottom'], $config['positions']);
		Assert::same(['column_left' => 'lg_only', 'column_right' => 'lg_only'], $config['responsive']);
	},
];
