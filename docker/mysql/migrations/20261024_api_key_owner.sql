-- API keys owned by an admin user.
--
-- A key may now belong to a specific admin user (oc_api_key.user_id). The
-- permissions of such a key follow the *current* group of its owner, so the
-- panel's permission model (oc_user_group.permission) stays the single source
-- of truth and moving a user to another group immediately changes what the
-- key may do.
--
-- user_id = 0 keeps the previous behaviour: the key is bound to the group
-- stored in user_group_id (a shared key, not tied to a person).
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_api_key`
	ADD COLUMN IF NOT EXISTS `user_id` INT(11) NOT NULL DEFAULT 0 AFTER `name`;

ALTER TABLE `oc_api_key`
	ADD INDEX IF NOT EXISTS `idx_api_key_user` (`user_id`);

-- Keep the stored group in sync with the owner's group for keys that already
-- have an owner (no-op on a fresh install).
UPDATE `oc_api_key` k
	INNER JOIN `oc_user` u ON (u.`user_id` = k.`user_id`)
	SET k.`user_group_id` = u.`user_group_id`
	WHERE k.`user_group_id` <> u.`user_group_id`;
