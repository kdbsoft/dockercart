<?php
/**
 * DockerCart customer (storefront) REST API — scope catalogue.
 *
 * Single source of truth for what a customer API key may reach. Scopes are
 * granted on a customer group, overridden per customer and finally narrowed per
 * key; the effective set is resolved by Dockercart\Api\CustomerAccess.
 *
 * Scope names are "resource:action" strings so they read well in the panel and
 * in the OpenAPI document. Adding a scope means adding it here plus the matching
 * entry in Dockercart\Api\StoreRoutes (and the language labels).
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class CustomerScopes {
	public const PRODUCTS_READ = 'products:read';
	public const ORDERS_READ = 'orders:read';
	public const RETURNS_READ = 'returns:read';
	public const RETURNS_WRITE = 'returns:write';
	public const CHECKOUT_WRITE = 'checkout:write';

	/**
	 * Canonical order and the language key of each scope.
	 */
	private const LABELS = [
		self::PRODUCTS_READ  => 'text_scope_products_read',
		self::ORDERS_READ    => 'text_scope_orders_read',
		self::RETURNS_READ   => 'text_scope_returns_read',
		self::RETURNS_WRITE  => 'text_scope_returns_write',
		self::CHECKOUT_WRITE => 'text_scope_checkout_write',
	];

	/**
	 * Every scope, in canonical order.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array_keys(self::LABELS);
	}

	/**
	 * scope => language key.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return self::LABELS;
	}

	public static function isValid(string $scope): bool {
		return isset(self::LABELS[$scope]);
	}

	/**
	 * Keeps only known scopes, deduplicated and in canonical order.
	 *
	 * @param mixed $scopes
	 * @return string[]
	 */
	public static function sanitize($scopes): array {
		if (!is_array($scopes)) {
			return [];
		}

		$valid = [];

		foreach (self::all() as $scope) {
			if (in_array($scope, $scopes, true)) {
				$valid[] = $scope;
			}
		}

		return $valid;
	}

	/**
	 * Decodes a stored JSON scope list. A null/empty value yields [].
	 *
	 * @return string[]
	 */
	public static function decode($value): array {
		if (!is_string($value) || trim($value) === '') {
			return [];
		}

		$decoded = json_decode($value, true);

		return self::sanitize($decoded);
	}

	/**
	 * Encodes a scope list for storage: [] becomes null (meaning "inherit").
	 */
	public static function encode($scopes): ?string {
		$scopes = self::sanitize($scopes);

		return $scopes ? (string)json_encode(array_values($scopes)) : null;
	}
}
