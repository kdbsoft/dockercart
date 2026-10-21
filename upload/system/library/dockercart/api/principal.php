<?php
/**
 * DockerCart admin REST API — authenticated principal.
 *
 * Stands in for Cart\User inside the api application: it exposes the same
 * permission API (hasPermission('access'|'modify', $route), isLogged(),
 * getId(), getGroupId(), isSuperuser()), so Controller::userHasAccess() /
 * userCanModify() and every reusable JSON action keep working unchanged.
 *
 * Permissions come from the admin user group the API key is bound to, i.e. from
 * oc_user_group.permission — there is no second permission model to maintain.
 * A key owned by an admin user (oc_api_key.user_id > 0) acts with the *current*
 * group of that user, so moving a user to another role immediately changes what
 * the key may do.
 *
 * A shared key is not a user: getId() returns 0 (audit columns such as
 * oc_order_history.user_id stay 0, the API journal keeps api_key_id instead).
 * An owned key reports its owner, so the panel can show who acted.
 *
 * @package    DockerCart
 * @subpackage Api
 */

declare(strict_types=1);

namespace Dockercart\Api;

final class Principal {
	/**
	 * Administrator group: implicit superuser, same rule as Cart\User.
	 */
	private const ADMINISTRATOR_GROUP_ID = 1;

	private int $api_key_id;
	private int $owner_id;
	private int $user_group_id;
	private string $username;
	private array $permission = [];
	private bool $superuser = false;

	/**
	 * @param array $key           oc_api_key row
	 * @param array $group         oc_user_group row (may be empty)
	 * @param int   $user_group_id effective group of the key — the group of the
	 *                            owner when the key belongs to a user, 0 to use
	 *                            the group stored in the key row
	 */
	public function __construct(array $key, array $group = [], int $user_group_id = 0) {
		$this->api_key_id = (int)($key['api_key_id'] ?? 0);
		$this->owner_id = (int)($key['user_id'] ?? 0);
		$this->user_group_id = $user_group_id > 0 ? $user_group_id : (int)($key['user_group_id'] ?? 0);
		$this->username = 'api:' . (string)($key['prefix'] ?? '');
		$this->superuser = ($this->user_group_id === self::ADMINISTRATOR_GROUP_ID);

		$permission = json_decode((string)($group['permission'] ?? ''), true);

		if (is_array($permission)) {
			$this->permission = $permission;
		}
	}

	/**
	 * Mirrors Cart\User::hasPermission().
	 */
	public function hasPermission($key, $value) {
		if ($this->superuser) {
			return true;
		}

		if (isset($this->permission[$key]) && is_array($this->permission[$key])) {
			return in_array($value, $this->permission[$key], true);
		}

		return false;
	}

	/**
	 * True when at least one of the given routes is granted for the key.
	 */
	public function canAny(string $kind, array $routes): bool {
		foreach ($routes as $route) {
			if ($this->hasPermission($kind, $route)) {
				return true;
			}
		}

		return false;
	}

	public function isSuperuser(): bool {
		return $this->superuser;
	}

	public function isLogged() {
		return true;
	}

	public function getId() {
		return $this->owner_id;
	}

	/**
	 * The admin user the key belongs to (0 for a shared key).
	 */
	public function getOwnerId(): int {
		return $this->owner_id;
	}

	public function getUserName() {
		return $this->username;
	}

	public function getGroupId() {
		return $this->user_group_id;
	}

	public function getApiKeyId(): int {
		return $this->api_key_id;
	}

	public function getPermission(): array {
		return $this->permission;
	}
}
