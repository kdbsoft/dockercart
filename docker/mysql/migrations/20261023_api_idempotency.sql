-- Admin REST API v1 — idempotent POST + optimistic concurrency setting.
--
-- 1. oc_api_idempotency stores the response of a POST that carried an
--    `Idempotency-Key` header, so a client retry (network timeout, CI retry)
--    replays the original answer instead of creating a duplicate product/order
--    comment/… Rows are scoped to the API key + method + path and expire after
--    24 hours (purged by the daily `api_log_cleanup` task).
-- 2. config_api_require_if_match turns "If-Match is mandatory for
--    PUT/PATCH/DELETE" into an opt-in strict mode. When 0 (default) a missing
--    If-Match is allowed, but a *provided* one is always validated, so clients
--    that want optimistic locking get 412 on a stale copy.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

CREATE TABLE IF NOT EXISTS `oc_api_idempotency` (
	`api_idempotency_id` INT(11) NOT NULL AUTO_INCREMENT,
	`api_key_id` INT(11) NOT NULL DEFAULT 0,
	`idempotency_key` VARCHAR(128) NOT NULL,
	`method` VARCHAR(10) NOT NULL DEFAULT 'POST',
	`path` VARCHAR(255) NOT NULL DEFAULT '',
	`request_hash` CHAR(40) NOT NULL DEFAULT '',
	`status_code` SMALLINT(5) NOT NULL DEFAULT 0,
	`response_body` MEDIUMTEXT NULL DEFAULT NULL,
	`response_headers` TEXT NULL DEFAULT NULL,
	`locked_until` DATETIME NULL DEFAULT NULL,
	`date_added` DATETIME NOT NULL,
	`date_modified` DATETIME NOT NULL,
	PRIMARY KEY (`api_idempotency_id`),
	UNIQUE KEY `uniq_api_idempotency` (`api_key_id`, `idempotency_key`, `method`, `path`),
	KEY `idx_api_idempotency_date` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_api_require_if_match', '0', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_api_require_if_match'
);
