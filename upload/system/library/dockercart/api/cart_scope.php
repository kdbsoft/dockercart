<?php
/**
 * DockerCart customer (storefront) REST API — cart session scope.
 *
 * Cart\Cart keeps its lines in oc_cart keyed by (customer_id, session_id) and
 * its constructor re-points every row of the customer onto the session id of
 * the current request. A store API request has no session cookie, so without a
 * stable id the API cart would be a new cart on every call — and a cart the
 * website never sees.
 *
 * The API therefore starts the session with the customer's own scope id
 * (oc_customer.api_cart_scope). Because it *is* a session id, the same id also
 * keys the oc_session row that holds the applied coupon / voucher / reward
 * points, exactly like the storefront checkout, so the total extensions keep
 * working unchanged.
 *
 * The value is a real session id, so it must not be guessable: it is generated
 * once per customer from random_bytes(), never derived from the customer id.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class CartScope {
	/**
	 * Prefix that marks a session id as belonging to the store API. It also
	 * keeps the value recognisable in oc_session / oc_stock_reservation.
	 */
	private const PREFIX = 'stp';

	/**
	 * Session ids accepted by System\Library\Session::start().
	 */
	private const PATTERN = '/^stp[a-z0-9]{22,49}$/';

	/**
	 * Scope id of a customer, generated on first use.
	 */
	public static function forCustomer(\Registry $registry, int $customer_id): string {
		if ($customer_id <= 0) {
			throw new \RuntimeException('A cart scope requires a customer');
		}

		$scope = self::read($registry, $customer_id);

		if ($scope !== '') {
			return $scope;
		}

		$db = $registry->get('db');
		$generated = self::generate();

		// The conditional UPDATE makes two concurrent first requests safe: only
		// one of them writes, the loser re-reads the winner's value.
		$db->query("UPDATE `" . DB_PREFIX . "customer` SET `api_cart_scope` = '" . $db->escape($generated) . "' WHERE `customer_id` = '" . (int)$customer_id . "' AND (`api_cart_scope` IS NULL OR `api_cart_scope` = '')");

		$scope = self::read($registry, $customer_id);

		if ($scope === '') {
			throw new \RuntimeException('Could not persist the cart scope of customer ' . $customer_id);
		}

		return $scope;
	}

	/**
	 * Whether a stored value is a usable session id (protects against a
	 * hand-edited column reaching Session::start(), which exits on a bad id).
	 */
	public static function isValid(string $scope): bool {
		return (bool)preg_match(self::PATTERN, $scope);
	}

	private static function read(\Registry $registry, int $customer_id): string {
		$db = $registry->get('db');

		$query = $db->query("SELECT `api_cart_scope` FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$customer_id . "'");

		if (!$query->num_rows) {
			return '';
		}

		$scope = trim((string)($query->row['api_cart_scope'] ?? ''));

		return self::isValid($scope) ? $scope : '';
	}

	private static function generate(): string {
		// 3 + 28 = 31 characters, inside Session::start()' [a-zA-Z0-9,-]{22,52}.
		return self::PREFIX . bin2hex(random_bytes(14));
	}
}
