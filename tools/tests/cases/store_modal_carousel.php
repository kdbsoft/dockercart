<?php
/**
 * Add-ons Store modal gallery — arrows must always work.
 *
 * The gallery items are injected when the modal opens, but Bootstrap's
 * `data-ride="carousel"` init runs once on `window.load` — while the carousel is
 * still empty. Its 5 s autoplay then calls `next()` with no `.item.active`:
 * the transition-end handler is attached to an empty set and `sliding` latches
 * `true` forever, so every later prev/next click returns early. The arrows only
 * worked when the modal was opened in the first seconds after a page load.
 *
 * The fix is structural and easy to regress, so it is pinned here:
 *   - no `data-ride` init on the empty carousel;
 *   - the items are rendered first, then the instance is recreated with
 *     `interval: false` (no autoplay, no pause()/cycle() start-up path);
 *   - a single picture hides the arrows and the dots.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

const STORE_CAROUSEL_TEMPLATE = 'upload/admin/view/template/extension/store.twig';

const STORE_CAROUSEL_CSS = 'upload/admin/view/stylesheet/dockercart_store.css';

return [
	'init: the empty carousel is not autoplayed on page load' => static function (): void {
		$template = repo_file(STORE_CAROUSEL_TEMPLATE);

		Assert::notContains('data-ride="carousel"', $template, 'data-ride inits on window.load, before any item exists');
	},

	'init: the gallery instance is recreated for every opening' => static function (): void {
		$template = repo_file(STORE_CAROUSEL_TEMPLATE);

		Assert::contains('function renderCarousel(pictures)', $template);
		Assert::contains('renderCarousel(data.pictures);', $template, 'showModal must delegate to the renderer');
		Assert::contains("removeData('bs.carousel')", $template, 'a stale instance keeps its latched sliding flag');
		Assert::contains('carousel({ interval: false, pause: false, wrap: pictures.length > 1 });', $template);
	},

	'init: items are built before the plugin instance is created' => static function (): void {
		$template = repo_file(STORE_CAROUSEL_TEMPLATE);

		$render = strpos($template, 'function renderCarousel(pictures)');
		$append = strpos($template, '$modalCarouselInner.append(', $render);
		$create = strpos($template, 'removeData(\'bs.carousel\')', $render);

		Assert::true($render !== false && $append !== false && $create !== false);
		Assert::true($append < $create, 'the carousel must be created only after its .item children exist');
	},

	'controls: a single picture hides arrows and dots' => static function (): void {
		$template = repo_file(STORE_CAROUSEL_TEMPLATE);
		$css = repo_file(STORE_CAROUSEL_CSS);

		Assert::contains("toggleClass('store-modal-carousel--single', pictures.length < 2)", $template);
		Assert::contains('.store-modal-carousel--single .carousel-control', $css);
		Assert::contains('.store-modal-carousel--single .carousel-indicators', $css);
		Assert::contains("display: none;", $css);
	},
];
