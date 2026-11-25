-- `oc_session.expire` needs an index now that expiry is purged by the hourly
-- `session_gc` scheduler task (bin/dockercart_session_gc.php) instead of
-- probabilistically inside a request.
--
-- Every app follows SESSION_ENGINE (system/config/catalog.php used to pin the
-- stock OpenCart `db` engine, which made this table grow by one row per
-- visitor session); with `SESSION_ENGINE=db` the janitor still runs
-- `DELETE ... WHERE expire < NOW()`, and without this index that is a full scan.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_session`
	ADD INDEX IF NOT EXISTS `idx_session_expire` (`expire`);
