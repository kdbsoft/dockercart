<?php
/**
 * DockerCart layout builder — route to zone mapping.
 *
 * Single source of truth for which layout positions a storefront route renders
 * and whether its side columns are visible on small screens. The admin builder
 * uses the first route of a layout to decide which drop zones to draw.
 *
 * Pattern A = modern Tailwind grid (columns hidden below lg), pattern B = legacy
 * Bootstrap columns on all breakpoints, pattern C = no sidebars.
 *
 * @package    DockerCart
 * @subpackage Library
 */

declare(strict_types=1);

namespace Dockercart\Layout;

final class RouteConfig {
	/**
	 * @return array{positions: array<int, string>, responsive: array<string, string>}
	 */
	public static function forRoute(string $route): array {

		$configs = array(
			// Pattern A — Modern Tailwind Grid (columns: hidden lg:block)
			'product/category' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/special' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/search' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/manufacturer/info' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/manufacturer' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'product/new_arrivals' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			'blog/category' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
			),
			// Pattern B — Legacy Bootstrap (columns visible on all breakpoints)
			'common/home' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'always', 'column_right' => 'always')
			),
			'checkout/checkout' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'always', 'column_right' => 'always')
			),
			'affiliate/login' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'always', 'column_right' => 'always')
			),
			'affiliate/register' => array(
				'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
				'responsive' => array('column_left' => 'always', 'column_right' => 'always')
			),
			// Pattern C — No sidebars, various
			'common/success' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'information/information' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'information/contact' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'information/sitemap' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'checkout/success' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'error/not_found' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'product/product' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
			'product/compare' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'checkout/cart' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'blog/post' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'blog/author' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'blog/archive' => array(
				'positions' => array('content_bottom'),
				'responsive' => array()
			),
			'blog/search' => array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			),
		);

		// Exact match
		if (isset($configs[$route])) {
			return $configs[$route];
		}

		// Wildcard match for account/* and affiliate/* (excluding login/register already matched above)
		if (strpos($route, 'account/') === 0 || (strpos($route, 'affiliate/') === 0 && !isset($configs[$route]))) {
			return array(
				'positions' => array('content_top', 'content_bottom'),
				'responsive' => array()
			);
		}

		// Default for unknown routes: all 4 positions, lg_only columns
		return array(
			'positions' => array('content_top', 'column_left', 'column_right', 'content_bottom'),
			'responsive' => array('column_left' => 'lg_only', 'column_right' => 'lg_only')
		);
	}
}
