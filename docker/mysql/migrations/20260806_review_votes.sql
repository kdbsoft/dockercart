-- Migration: 20260806 - review likes/dislikes voting
-- Adds a per-customer vote table for product reviews. A customer can have
-- at most one vote per review (composite PK), value 1 = like, 0 = dislike.
-- Toggle / switch semantics are handled by the application layer.
-- Idempotent: `make migrate` re-runs every migration file.

CREATE TABLE IF NOT EXISTS `oc_review_vote` (
  `review_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `vote` tinyint(1) NOT NULL DEFAULT '1',
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`review_id`,`customer_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
