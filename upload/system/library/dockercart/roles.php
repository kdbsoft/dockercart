<?php
/**
 * DockerCart role templates.
 *
 * Single source of truth for the standard admin roles ("templates") offered by
 * Admin > Users > User Groups. The route strings are the exact values stored in
 * `oc_user_group.permission[access|modify]`, so a template can be applied to a
 * group as-is (no transformation).
 *
 * Operator / controller permission model:
 *   - "access"  — the route may be opened (checked by startup/permission.php);
 *   - "modify"  — write operations of the route are allowed;
 *   - capability — a pseudo route (e.g. "sale/order/status") stored in the very
 *     same "modify" list but checked with an OR-fallback against the parent
 *     route (see Controller::userCanModify()). This keeps the JSON schema
 *     unchanged (three-key documents would be dropped by the JSON repair
 *     migrations) while allowing action-level grants such as "courier may only
 *     change the order status / add a tracking number".
 *
 * Since Administrator (user_group_id = 1) is an implicit superuser, role
 * templates never need to enumerate "everything".
 *
 * When a new admin controller is added, add its route to the templates that
 * should reach it — see AGENTS.md.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

class DockercartRoles {
	/**
	 * Access routes every role needs: own profile, global admin search and the
	 * saved-filter tabs of list pages.
	 */
	private const COMMON_ACCESS = [
		'common/admin_search',
		'common/profile',
		'common/user_filter',
	];

	/**
	 * Sections used to group the permission picker. The key is the first
	 * segment of a route, the value is a language key of admin/language/<locale>/
	 * user/user_group.php. Order defines the order in the form.
	 */
	private const SECTIONS = [
		'sale'         => 'text_section_sale',
		'catalog'      => 'text_section_catalog',
		'customer'     => 'text_section_customer',
		'warehouse'    => 'text_section_warehouse',
		'design'       => 'text_section_design',
		'marketing'    => 'text_section_marketing',
		'report'       => 'text_section_report',
		'extension'    => 'text_section_extension',
		'setting'      => 'text_section_setting',
		'localisation' => 'text_section_localisation',
		'tool'         => 'text_section_tool',
		'user'         => 'text_section_user',
		'marketplace'  => 'text_section_marketplace',
		'common'       => 'text_section_common',
	];

	/**
	 * Route prefixes that are not navigable pages and are therefore hidden from
	 * the permission picker:
	 *   - startup/* — bootstrappers loaded by framework.php, never URL routed;
	 *   - event/*   — event handlers invoked by the event system;
	 *   - mail/*    — sub-controllers invoked by other controllers.
	 * No code checks permissions on them (grep verified), so dropping stored
	 * entries for them is harmless.
	 */
	private const HIDDEN_PREFIXES = [
		'startup/',
		'event/',
		'mail/',
	];

	/**
	 * Capability pseudo routes: code => language key.
	 */
	private const CAPABILITIES = [
		'sale/order/status'   => 'text_capability_order_status',
		'sale/order/shipment' => 'text_capability_order_shipment',
	];

	/**
	 * Standard roles. "name" is a language key, capabilities are merged into
	 * the "modify" list by permission().
	 *
	 * @return array<string, array{name: string, access: string[], modify: string[], capabilities: string[]}>
	 */
	public static function templates(): array {
		$report_routes = [
			'extension/report/customer_activity',
			'extension/report/customer_order',
			'extension/report/customer_reward',
			'extension/report/customer_search',
			'extension/report/customer_transaction',
			'extension/report/dockercart_analytics',
			'extension/report/marketing',
			'extension/report/product_purchased',
			'extension/report/product_viewed',
			'extension/report/sale_coupon',
			'extension/report/sale_order',
			'extension/report/sale_return',
			'extension/report/sale_shipping',
			'extension/report/sale_tax',
			'extension/report/supplier_profit',
		];

		$content_routes = [
			'catalog/attribute',
			'catalog/attribute_group',
			'catalog/attribute_set',
			'catalog/category',
			'catalog/download',
			'catalog/information',
			'catalog/manufacturer',
			'catalog/option',
			'catalog/option_set',
			'catalog/product',
			'catalog/product_bundle',
			'catalog/product_configurable',
			'catalog/review',
			'catalog/review_setting',
			'design/banner',
			'design/layout',
			'design/seo_url',
			'extension/module/banner',
			'extension/module/bestseller',
			'extension/module/carousel',
			'extension/module/category',
			'extension/module/dockercart_blog',
			'extension/module/dockercart_blog_author',
			'extension/module/dockercart_blog_category',
			'extension/module/dockercart_blog_comment',
			'extension/module/dockercart_blog_latest',
			'extension/module/dockercart_blog_post',
			'extension/module/dockercart_brand_carousel',
			'extension/module/dockercart_category_tree',
			'extension/module/dockercart_shop_features',
			'extension/module/dockercart_theme',
			'extension/module/dockercart_viewed',
			'extension/module/featured',
			'extension/module/html',
			'extension/module/latest',
			'extension/module/slideshow',
			'extension/module/special',
		];

		$warehouse_routes = [
			'warehouse/movement',
			'warehouse/stock',
			'warehouse/supplier_orders',
			'warehouse/transfer',
			'warehouse/warehouse',
		];

		$seo_routes = [
			'extension/analytics/clarity',
			'extension/analytics/facebook',
			'extension/analytics/google',
			'extension/analytics/tiktok',
			'extension/feed/dockercart_sitemap',
			'extension/module/dockercart_newsletter',
			'extension/module/dockercart_search',
			'extension/module/dockercart_seo_description',
			'design/seo_url',
			'marketing/abandoned_stats',
			'marketing/contact',
			'marketing/coupon',
			'marketing/marketing',
		];

		$templates = [
			'sales_manager' => [
				'name'         => 'text_role_sales_manager',
				'access'       => array_merge(self::COMMON_ACCESS, [
					'sale/order',
					'sale/order_abandoned',
					'sale/order_detail',
					'sale/return',
					'sale/voucher',
					'customer/customer',
					'customer/customer_approval',
					'customer/customer_group',
					'marketing/coupon',
					'catalog/category',
					'catalog/manufacturer',
					'catalog/product',
					'report/report',
					'extension/report/customer_order',
					'extension/report/product_purchased',
					'extension/report/sale_order',
					'extension/report/sale_return',
					'extension/dashboard/chart',
					'extension/dashboard/customer',
					'extension/dashboard/dockercart_aov',
					'extension/dashboard/dockercart_conversion',
					'extension/dashboard/order',
					'extension/dashboard/recent',
					'extension/dashboard/sale',
					'extension/dashboard/traffic_source',
				]),
				'modify'       => [
					'common/admin_search',
					'common/filemanager',
					'common/profile',
					'common/user_filter',
					'sale/order',
					'sale/return',
					'sale/voucher',
					'customer/customer',
					'customer/customer_approval',
					'marketing/coupon',
				],
				'capabilities' => [],
			],
			'support_operator' => [
				'name'         => 'text_role_support_operator',
				'access'       => array_merge(self::COMMON_ACCESS, [
					'sale/order',
					'sale/order_detail',
					'sale/return',
					'customer/customer',
					'customer/customer_approval',
					'catalog/category',
					'catalog/product',
					'extension/dashboard/customer',
					'extension/dashboard/order',
					'extension/dashboard/recent',
				]),
				'modify'       => [
					'common/profile',
					'common/user_filter',
					'customer/customer',
				],
				'capabilities' => [
					'sale/order/status',
				],
			],
			'content_manager' => [
				'name'         => 'text_role_content_manager',
				'access'       => array_merge(self::COMMON_ACCESS, $content_routes, [
					'common/filemanager',
					'extension/dashboard/recent',
				]),
				'modify'       => array_merge($content_routes, [
					'common/admin_search',
					'common/filemanager',
					'common/profile',
					'common/user_filter',
				]),
				'capabilities' => [],
			],
			'marketer' => [
				'name'         => 'text_role_marketer',
				'access'       => array_merge(self::COMMON_ACCESS, $seo_routes, [
					'catalog/category',
					'catalog/information',
					'catalog/product',
					'report/report',
					'extension/report/customer_activity',
					'extension/report/customer_order',
					'extension/report/customer_reward',
					'extension/report/customer_search',
					'extension/report/customer_transaction',
					'extension/report/marketing',
					'extension/report/product_viewed',
					'extension/report/sale_order',
					'extension/dashboard/dockercart_conversion',
					'extension/dashboard/dockercart_repeat',
					'extension/dashboard/dockercart_top_products',
					'extension/dashboard/order',
					'extension/dashboard/sale',
					'extension/dashboard/traffic_source',
				]),
				'modify'       => array_merge($seo_routes, [
					'common/admin_search',
					'common/profile',
					'common/user_filter',
				]),
				'capabilities' => [],
			],
			'courier' => [
				'name'         => 'text_role_courier',
				'access'       => array_merge(self::COMMON_ACCESS, [
					'sale/order',
					'sale/order_detail',
					'extension/dashboard/recent',
				]),
				'modify'       => [
					'common/profile',
				],
				'capabilities' => [
					'sale/order/shipment',
					'sale/order/status',
				],
			],
			'storekeeper' => [
				'name'         => 'text_role_storekeeper',
				'access'       => array_merge(self::COMMON_ACCESS, $warehouse_routes, [
					'catalog/product',
					'common/filemanager',
					'sale/order',
					'sale/order_detail',
					'extension/dashboard/recent',
				]),
				'modify'       => array_merge($warehouse_routes, [
					'catalog/product',
					'common/admin_search',
					'common/filemanager',
					'common/profile',
					'common/user_filter',
				]),
				'capabilities' => [
					'sale/order/shipment',
				],
			],
			'accountant' => [
				'name'         => 'text_role_accountant',
				'access'       => array_merge(self::COMMON_ACCESS, $warehouse_routes, $report_routes, [
					'customer/customer',
					'report/report',
					'sale/order',
					'sale/order_detail',
					'sale/return',
					'sale/voucher',
					'extension/dashboard/chart',
					'extension/dashboard/dockercart_aov',
					'extension/dashboard/dockercart_category_revenue',
					'extension/dashboard/order',
					'extension/dashboard/sale',
				]),
				'modify'       => [
					'common/profile',
				],
				'capabilities' => [],
			],
		];

		return $templates;
	}

	/**
	 * Resolved group permission document (the value stored in
	 * oc_user_group.permission) for a template: capabilities are folded into the
	 * "modify" list and all lists are deduplicated and sorted.
	 *
	 * @param array{access: string[], modify: string[], capabilities: string[]} $template
	 * @return array{access: string[], modify: string[]}
	 */
	public static function permission(array $template): array {
		$access = self::unique($template['access'] ?? []);
		$modify = self::unique(array_merge($template['modify'] ?? [], $template['capabilities'] ?? []));

		return [
			'access' => $access,
			'modify' => $modify,
		];
	}

	/**
	 * All capability pseudo routes (keys of CAPABILITIES).
	 *
	 * @return string[]
	 */
	public static function capabilityRoutes(): array {
		return array_keys(self::CAPABILITIES);
	}

	/**
	 * Capability labels: code => language key.
	 *
	 * @return array<string, string>
	 */
	public static function capabilities(): array {
		return self::CAPABILITIES;
	}

	/**
	 * Section labels: route prefix => language key, in display order.
	 *
	 * @return array<string, string>
	 */
	public static function sections(): array {
		return self::SECTIONS;
	}

	/**
	 * Whether a route should be hidden from the permission picker.
	 */
	public static function isHidden(string $route): bool {
		foreach (self::HIDDEN_PREFIXES as $prefix) {
			if (str_starts_with($route, $prefix)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Section key of a route (first segment, or "other").
	 */
	public static function sectionOf(string $route): string {
		$part = explode('/', $route)[0];

		return isset(self::SECTIONS[$part]) ? $part : 'other';
	}

	/**
	 * Sorts routes into picker sections preserving the SECTIONS order.
	 *
	 * @param string[] $routes
	 * @return array<string, string[]> section => routes
	 */
	public static function groupBySection(array $routes): array {
		$grouped = [];

		foreach (self::SECTIONS as $section => $language_key) {
			$grouped[$section] = [];
		}

		$grouped['other'] = [];

		foreach (self::unique($routes) as $route) {
			$grouped[self::sectionOf($route)][] = $route;
		}

		return array_filter($grouped, static function (array $section_routes): bool {
			return !empty($section_routes);
		});
	}

	/**
	 * @param string[] $routes
	 * @return string[]
	 */
	private static function unique(array $routes): array {
		$routes = array_values(array_unique(array_filter($routes, static function ($route): bool {
			return is_string($route) && $route !== '';
		})));

		sort($routes);

		return $routes;
	}
}
