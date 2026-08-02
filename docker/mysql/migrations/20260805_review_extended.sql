-- Migration: 20260805 - extended review fields + review media + rating cache
-- Adds fractional rating, verified-purchase flag, IP, criteria values,
-- review images (max 3), review video (youtube|mp4) and a per-product rating
-- cache used by the storefront summary / schema markup.
-- All statements are idempotent: `make migrate` re-runs every migration file.

-- Fractional overall rating (e.g. 4.2). MODIFY is naturally idempotent.
ALTER TABLE `oc_review`
  MODIFY `rating` decimal(3,1) NOT NULL DEFAULT '0.0',
  ADD COLUMN IF NOT EXISTS `verified` tinyint(1) NOT NULL DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `ip` varchar(40) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS `criteria_group_id` int(11) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `oc_review_criteria_value` (
  `review_id` int(11) NOT NULL,
  `criteria_id` int(11) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`review_id`,`criteria_id`),
  KEY `criteria_id` (`criteria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_review_image` (
  `review_image_id` int(11) NOT NULL AUTO_INCREMENT,
  `review_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`review_image_id`),
  KEY `review_id` (`review_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_review_video` (
  `review_video_id` int(11) NOT NULL AUTO_INCREMENT,
  `review_id` int(11) NOT NULL,
  `video_type` enum('youtube','mp4') NOT NULL DEFAULT 'youtube',
  `video` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`review_video_id`),
  KEY `review_id` (`review_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_product_rating` (
  `product_id` int(11) NOT NULL,
  `rating` decimal(5,2) NOT NULL DEFAULT '0.00',
  `review_count` int(11) NOT NULL DEFAULT '0',
  `distribution` json DEFAULT NULL,
  `date_modified` datetime DEFAULT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill existing reviews into the default criteria group (id 1).
UPDATE `oc_review` SET `criteria_group_id` = 1
WHERE `criteria_group_id` IS NULL
  AND EXISTS (SELECT 1 FROM `oc_review_criteria_group` WHERE `criteria_group_id` = 1);

-- Default review settings (store 0). Only inserted when missing so manual
-- customizations are preserved on re-runs.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_images_enabled', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_images_enabled');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_max_images', '3', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_max_images');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_video_enabled', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_video_enabled');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_image_max_size', '5242880', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_image_max_size');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_video_max_size', '52428800', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_video_max_size');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_image_dimension', '1600', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_image_dimension');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_auto_approve', '0', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_auto_approve');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_verify_purchase', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_verify_purchase');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_show_distribution', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_show_distribution');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_per_page', '10', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_per_page');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_rate_limit_count', '5', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_rate_limit_count');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_rate_limit_minutes', '60', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_rate_limit_minutes');

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_review_honeypot', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'config' AND `key` = 'config_review_honeypot');

-- Include review pages in the generated sitemap by default.
INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'dockercart_sitemap', 'dockercart_sitemap_reviews', '1', 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'dockercart_sitemap' AND `key` = 'dockercart_sitemap_reviews');

-- Rebuild the rating cache for products that already have approved reviews.
INSERT INTO `oc_product_rating` (`product_id`, `rating`, `review_count`, `distribution`, `date_modified`)
SELECT r.product_id,
       ROUND(AVG(r.rating), 2),
       COUNT(*),
       JSON_OBJECT(
         '5', SUM(CASE WHEN ROUND(r.rating) = 5 THEN 1 ELSE 0 END),
         '4', SUM(CASE WHEN ROUND(r.rating) = 4 THEN 1 ELSE 0 END),
         '3', SUM(CASE WHEN ROUND(r.rating) = 3 THEN 1 ELSE 0 END),
         '2', SUM(CASE WHEN ROUND(r.rating) = 2 THEN 1 ELSE 0 END),
         '1', SUM(CASE WHEN ROUND(r.rating) = 1 THEN 1 ELSE 0 END)
       ),
       NOW()
FROM `oc_review` r
WHERE r.status = '1'
GROUP BY r.product_id
ON DUPLICATE KEY UPDATE
  `rating` = VALUES(`rating`),
  `review_count` = VALUES(`review_count`),
  `distribution` = VALUES(`distribution`),
  `date_modified` = NOW();
