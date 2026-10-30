<?php
/**
 * Customer API — payment initialisation.
 *
 * The gateway call itself needs a running stack (see
 * tools/tests/live/customer_api_e2e.py); this file pins the pure pieces the
 * endpoint rests on: the answer classification, the stored payload shape, the
 * storefront-controller bridge allowlist and the language bridge.
 *
 * @package    DockerCart
 * @subpackage Tests
 */

declare(strict_types=1);

use Dockercart\Api\CatalogControllers;
use Dockercart\Api\CatalogLanguage;
use Dockercart\Api\CatalogLoader;
use Dockercart\Api\CustomerScopes;
use Dockercart\Api\Error;
use Dockercart\Api\StorePayment;
use Dockercart\Api\StoreRoutes;

/**
 * @return array<int, array{method: string, path: string, route: string, action: string, scope: ?string, public: bool}>
 */
function customer_api_payment_routes(): array {
	$routes = [];

	foreach (StoreRoutes::table() as $entry) {
		$routes[] = $entry;
	}

	return $routes;
}

return [
	'store api payment: the payment endpoint is registered under the checkout scope' => static function (): void {
		$payment = null;

		foreach (customer_api_payment_routes() as $entry) {
			if ($entry['method'] === 'POST' && $entry['path'] === '/v1/checkout/{checkout_id}/payment') {
				$payment = $entry;
			}
		}

		Assert::true($payment !== null, 'POST /v1/checkout/{checkout_id}/payment must be registered');
		Assert::same('store_api/checkout', $payment['route']);
		Assert::same('payment', $payment['action']);
		Assert::same(CustomerScopes::CHECKOUT_WRITE, $payment['scope']);
		Assert::false($payment['public'], 'the payment endpoint must never be public');
	},

	'store api payment: the answer kind separates gateways from store pages and passive methods' => static function (): void {
		$store = 'https://shop.example.com';

		// A gateway on another host: the client opens it in a browser/WebView.
		Assert::same('external', StorePayment::kind('https://secure.liqpay.ua/checkout/xyz', '', $store));
		Assert::same('external', StorePayment::kind('https://www.paypal.com/pay?token=1', '<html>', $store));

		// The store's own page (the built-in method redirects to checkout/success):
		// nothing has to be paid online.
		Assert::same('internal', StorePayment::kind('https://shop.example.com/index.php?route=checkout/success', '', $store));
		Assert::same('internal', StorePayment::kind('http://shop.example.com:8080/checkout/success', '', $store));

		// A relative URL cannot leave the shop.
		Assert::same('internal', StorePayment::kind('/index.php?route=checkout/success', '', $store));

		// Markup without a URL: render it.
		Assert::same('html', StorePayment::kind('', '<form action="…"></form>', $store));

		// Nothing at all: show the method instructions.
		Assert::same('none', StorePayment::kind('', '', $store));
		Assert::same('none', StorePayment::kind('', "  \n\t ", $store));

		// No store URL at all (misconfigured config_url) must not classify a
		// gateway as ours.
		Assert::same('external', StorePayment::kind('https://secure.liqpay.ua/checkout', '', ''));
	},

	'store api payment: the stored answer hides its internal keys' => static function (): void {
		$stored = [
			'order_id'      => 42,
			'payment_code'  => 'stripe.stripe',
			'module'        => 'stripe',
			'kind'          => 'external',
			'redirect_url'  => 'https://checkout.stripe.com/c/pay/abc',
			'html'          => null,
			'instructions'  => 'Card payment',
			'gateway'       => [],
			'created_at'    => '2026-10-30T12:00:00+00:00',
			'refreshed_at'  => null,
			'_status_after' => 1,
		];

		$public = StorePayment::publicPayload($stored);

		Assert::false(array_key_exists('_status_after', $public), 'the internal order-status guard must never be returned');
		Assert::same(42, $public['order_id']);
		Assert::same('external', $public['kind']);
		Assert::same('https://checkout.stripe.com/c/pay/abc', $public['redirect_url']);

		// An empty gateway answer becomes a JSON object, never an array.
		Assert::same('{}', json_encode($public['gateway']));

		$public = StorePayment::publicPayload(['gateway' => ['session' => 'abc'], '_status_after' => 3]);

		Assert::same('{"session":"abc"}', json_encode($public['gateway']));
		Assert::false(array_key_exists('_status_after', $public));
	},

	'store api payment: only extension controllers may be bridged' => static function (): void {
		$registry = new \Registry();

		$refused = [
			// A storefront checkout controller would be a second, unreviewed order path.
			'checkout/order',
			'checkout/checkout',
			'checkout/success',
			// The panel tree itself.
			'sale/order',
			// The allowlist is a prefix list, not a substring test.
			'extension/payment',
			'module/extension/payment/cod',
		];

		foreach ($refused as $route) {
			$failed = false;

			try {
				CatalogControllers::run($registry, $route, 'index');
			} catch (\RuntimeException $exception) {
				$failed = true;
			}

			Assert::true($failed, $route . ' must not be bridged');
		}

		// An allowed prefix with a missing file still fails loudly (no silent no-op).
		$failed = false;

		try {
			CatalogControllers::run($registry, 'extension/payment/does_not_exist', 'confirm');
		} catch (\RuntimeException $exception) {
			$failed = true;
		}

		Assert::true($failed, 'a missing storefront controller must fail');
	},

	'store api payment: the bridge reads the storefront language pack' => static function (): void {
		require_once DIR_SYSTEM . 'library/language.php';

		$registry = new \Registry();

		$config = new \Config();
		$config->set('template_engine', 'twig');
		$config->set('config_theme', 'dockercart');
		$config->set('config_language', 'en-gb');

		$language = new \Language('en-gb');

		$registry->set('config', $config);
		$registry->set('language', $language);

		$loader = new CatalogLoader($registry, 'dockercart');

		$loader->language('extension/payment/dockercart_universal');

		$title = $language->get('text_title');

		Assert::true($title !== '' && $title !== 'text_title', 'the panel Language object must receive the storefront strings');

		// A missing pack is a no-op, never a failure.
		$loader->language('extension/payment/does_not_exist');

		Assert::same($title, $language->get('text_title'));

		// Failing over to en-gb keeps a translated store working.
		$registry->get('config')->set('config_language', 'xx-xx');

		$fallback = CatalogLanguage::all($registry, 'extension/payment/dockercart_universal');

		Assert::true(isset($fallback['text_title']), 'en-gb is the fallback pack');
	},

	'store api payment: the payment error codes map to their status' => static function (): void {
		Assert::same(409, Error::status(Error::CHECKOUT_COMPLETED));
		Assert::same(409, Error::status(Error::CHECKOUT_NOT_CONFIRMED));
		Assert::same(409, Error::status(Error::PAYMENT_UNAVAILABLE));
		Assert::same(409, Error::status(Error::PAYMENT_ALREADY_STARTED));
		Assert::same(502, Error::status(Error::PAYMENT_GATEWAY_ERROR));
	},
];
