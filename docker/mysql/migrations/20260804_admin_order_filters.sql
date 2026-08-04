-- Generic per-admin saved filters (Shopify-style filter tabs) for any
-- admin list page.
--
-- One table serves all entities: `entity` is a page key such as "order",
-- "product", "customer", etc. Each admin user can create named filters per
-- entity. A filter is a set of conditions stored as JSON in `conditions`:
--   [
--     {"field":"order_status","operator":"in","value":[1,2]},
--     {"field":"payment_status","operator":"eq","value":"unpaid"},
--     {"field":"total","operator":"gte","value":"100"},
--     {"field":"date_added","operator":"lt","value":"2026-01-01"},
--     {"field":"customer","operator":"contains","value":"john"}
--   ]
-- Builtin tabs (e.g. All / Unfulfilled / Unpaid) are rendered by each
-- controller and are not stored in this table.
-- (Idempotent: make migrate re-runs every file.)

CREATE TABLE IF NOT EXISTS `oc_admin_filter` (
	`filter_id` INT(11) NOT NULL AUTO_INCREMENT,
	`user_id` INT(11) NOT NULL,
	`entity` VARCHAR(64) NOT NULL,
	`name` VARCHAR(128) NOT NULL,
	`conditions` TEXT NOT NULL,
	`sort_order` INT(11) NOT NULL DEFAULT 0,
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`filter_id`),
	KEY `idx_admin_filter_user_entity` (`user_id`, `entity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
