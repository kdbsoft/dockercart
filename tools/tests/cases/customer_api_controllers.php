<?php
/**
 * Customer (storefront) API — controller/library regression anchors.
 *
 * These are static checks on code paths that need a live stack to execute (the
 * HTTP surface is covered end-to-end by tools/tests/live/customer_api_e2e.py).
 * Each anchor pins a bug that was found by that live suite and is easy to
 * reintroduce by accident:
 *
 *   1. POST /v1/cart/items must let the server default the quantity to the
 *      product minimum (the endpoint must not force 0.0 when it is omitted);
 *   2. a rejected ?language=... must actually reach the JSON error controller
 *      instead of being overwritten by the normal route assignment;
 *   3. the buyer's customer group must be applied *after* the store settings
 *      reload, otherwise every price falls back to the default group;
 *   4. Cart\Cart::remove() must drop the per-request product cache, otherwise
 *      the "cart state" answer of a DELETE/PATCH 0 still contains the line;
 *   5. the spendable reward points of a line must come from the customer-group
 *      reward map (they were read from a non-existent product column);
 *   6. catalogue pagination needs a total order (product_id tiebreak) or tied
 *      rows move between pages.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

/**
 * Source of a method body, or '' when the method is missing.
 */
function customer_api_method_source(string $file, string $method): string {
	$source = repo_file($file);

	$position = strpos($source, 'function ' . $method . '(');

	if ($position === false) {
		return '';
	}

	$start = strpos($source, '{', $position);

	if ($start === false) {
		return '';
	}

	$depth = 0;
	$length = strlen($source);

	for ($i = $start; $i < $length; $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $start, $i - $start + 1);
			}
		}
	}

	return '';
}

return [
	'customer api: an omitted cart quantity is defaulted by the server' => static function (): void {
		$body = customer_api_method_source('upload/admin/controller/store_api/cart.php', 'addItem');

		Assert::true($body !== '', 'addItem() not found');
		Assert::contains("\$input['quantity'] = (float)\$data['quantity'];", $body);
		Assert::notContains('0.0', $body, 'the controller must not send a fake 0.0 quantity');
	},

	'customer api: a rejected ?language= is routed to the error controller' => static function (): void {
		$startup = repo_file('upload/admin/controller/startup/store_api.php');

		Assert::contains('if (!$this->applyLanguageOverride($context))', $startup);
		Assert::contains('private function applyLanguageOverride(\Dockercart\Api\Context $context): bool', $startup);
		Assert::contains('return false;', customer_api_method_source('upload/admin/controller/startup/store_api.php', 'applyLanguageOverride'));
	},

	'customer api: the buyer customer group survives the store settings reload' => static function (): void {
		$body = customer_api_method_source('upload/admin/controller/startup/store_api.php', 'applyPricingContext');

		Assert::true($body !== '', 'applyPricingContext() not found');

		$reload = strpos($body, 'foreach ($query->rows as $setting)');
		$group = strpos($body, "config->set('config_customer_group_id', \$principal->getCustomerGroupId())");

		Assert::true($reload !== false && $group !== false, 'both the settings reload and the group assignment must exist');
		Assert::true($group > $reload, 'config_customer_group_id must be set after the oc_setting reload');
	},

	'customer api: removing a cart line invalidates the products cache' => static function (): void {
		$body = customer_api_method_source('upload/system/library/cart/cart.php', 'remove');

		Assert::true($body !== '', 'Cart::remove() not found');
		Assert::contains('$this->products_cache = null;', $body);
	},

	'customer api: the spendable reward points come from the customer-group map' => static function (): void {
		$source = repo_file('upload/system/library/cart/cart.php');

		Assert::notContains('$product_query["row"]["points"]', $source, 'oc_product has no points column');
		Assert::contains('"points" => $reward', $source);
	},

	'customer api: catalogue pagination is a total order' => static function (): void {
		$body = customer_api_method_source('upload/system/library/dockercart/api/store_catalog.php', 'orderBy');

		Assert::true($body !== '', 'StoreCatalog::orderBy() not found');
		Assert::contains("', p.product_id ASC'", $body);
		Assert::same(7, substr_count($body, '$tiebreak'), 'every ORDER BY branch must append the tiebreak');
	},
];
