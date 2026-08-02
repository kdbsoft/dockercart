-- Migration: 20260805 - review criteria groups (flexible "what is rated")
-- Fully replaceable per category. Default group = Pros (text) + Cons (text).
-- All statements are idempotent: `make migrate` re-runs every migration file.
-- Language ids: 1=en-gb, 2=uk-ua, 3=ru-ua.

CREATE TABLE IF NOT EXISTS `oc_review_criteria_group` (
  `criteria_group_id` int(11) NOT NULL AUTO_INCREMENT,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`criteria_group_id`),
  KEY `is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_review_criteria_group_description` (
  `criteria_group_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`criteria_group_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_review_criteria` (
  `criteria_id` int(11) NOT NULL AUTO_INCREMENT,
  `criteria_group_id` int(11) NOT NULL,
  `type` enum('rating','text') NOT NULL DEFAULT 'text',
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`criteria_id`),
  KEY `criteria_group_id` (`criteria_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oc_review_criteria_description` (
  `criteria_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `help` text DEFAULT NULL,
  PRIMARY KEY (`criteria_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default criteria group (fixed system id 1) and its two text criteria.
INSERT INTO `oc_review_criteria_group` (`criteria_group_id`, `is_default`, `sort_order`)
SELECT 1, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_review_criteria_group` WHERE `criteria_group_id` = 1);

INSERT INTO `oc_review_criteria_group_description` (`criteria_group_id`, `language_id`, `name`) VALUES
(1, 1, 'Default (Pros / Cons)'),
(1, 2, 'За замовчуванням (Достоїнства / Недоліки)'),
(1, 3, 'По умолчанию (Достоинства / Недостатки)')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `oc_review_criteria` (`criteria_id`, `criteria_group_id`, `type`, `is_required`, `sort_order`) VALUES
(1, 1, 'text', 1, 1),
(2, 1, 'text', 0, 2)
ON DUPLICATE KEY UPDATE `type` = VALUES(`type`), `is_required` = VALUES(`is_required`);

INSERT INTO `oc_review_criteria_description` (`criteria_id`, `language_id`, `name`, `help`) VALUES
(1, 1, 'Pros', NULL),
(1, 2, 'Достоїнства', NULL),
(1, 3, 'Достоинства', NULL),
(2, 1, 'Cons', NULL),
(2, 2, 'Недоліки', NULL),
(2, 3, 'Недостатки', NULL)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Per-category override column (NULL => inherit default group).
ALTER TABLE `oc_category` ADD COLUMN IF NOT EXISTS `review_criteria_group_id` int(11) DEFAULT NULL;
