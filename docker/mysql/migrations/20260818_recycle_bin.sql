-- Migration: 20260818 - Admin recycle bin (корзина)
-- -------------------------------------------------------------
-- Adds a generic trash table for soft-deleted main entities plus the
-- system scheduler task that hard-purges records older than 30 days.
--
-- Idempotent: `make migrate` re-runs every file, so all statements guard
-- on existing state (IF NOT EXISTS / NOT EXISTS / ON DUPLICATE KEY).

CREATE TABLE IF NOT EXISTS `oc_trash` (
  `trash_id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `data` longtext,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime NOT NULL,
  `restored_at` datetime DEFAULT NULL,
  PRIMARY KEY (`trash_id`),
  KEY `idx_trash_entity` (`entity_type`, `deleted_at`),
  KEY `idx_trash_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recycle bin purge scheduler task (system task, daily)
INSERT INTO `oc_dockercart_scheduler_task`
  (`task_type`, `task_name`, `source_id`, `worker_command`, `cron_enabled`, `cron_schedule`, `status`, `is_system`, `date_added`, `date_modified`)
VALUES
  ('recycle_bin_cleanup', 'Recycle bin purge', 0, 'php /var/www/html/bin/dockercart_recycle_bin_cleanup.php', 1, 'daily', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `worker_command` = VALUES(`worker_command`),
  `is_system` = 1,
  `cron_schedule` = VALUES(`cron_schedule`),
  `date_modified` = NOW();

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Очищення кошика'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'recycle_bin_cleanup'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Очистка корзины'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'recycle_bin_cleanup'
ON DUPLICATE KEY UPDATE `name` = `name`;

-- Capture events: snapshot entity data into oc_trash BEFORE the core
-- delete runs so the rows are still present in the DB.
INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_trash_product', 'admin/model/catalog/product/deleteProduct/before', 'tool/recycle_bin/eventCapture', 1, -100
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_trash_product');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_trash_category', 'admin/model/catalog/category/deleteCategory/before', 'tool/recycle_bin/eventCapture', 1, -100
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_trash_category');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_trash_manufacturer', 'admin/model/catalog/manufacturer/deleteManufacturer/before', 'tool/recycle_bin/eventCapture', 1, -100
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_trash_manufacturer');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_trash_information', 'admin/model/catalog/information/deleteInformation/before', 'tool/recycle_bin/eventCapture', 1, -100
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_trash_information');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_trash_review', 'admin/model/catalog/review/deleteReview/before', 'tool/recycle_bin/eventCapture', 1, -100
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_trash_review');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_trash_customer', 'admin/model/customer/customer/deleteCustomer/before', 'tool/recycle_bin/eventCapture', 1, -100
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_trash_customer');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_trash_order', 'admin/model/sale/order/deleteOrder/before', 'tool/recycle_bin/eventCapture', 1, -100
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_trash_order');

INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'dockercart_trash_blog_post', 'admin/model/extension/module/dockercart_blog_post/deletePost/before', 'tool/recycle_bin/eventCapture', 1, -100
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'dockercart_trash_blog_post');

-- Grant the recycle bin permission (access + modify) to user groups.
UPDATE `oc_user_group`
SET `permission` = JSON_SET(
    `permission`,
    '$.access',
    JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.access'), JSON_ARRAY()), JSON_ARRAY('tool/recycle_bin'))
)
WHERE JSON_CONTAINS(`permission`, '"tool/recycle_bin"', '$.access') = 0;

UPDATE `oc_user_group`
SET `permission` = JSON_SET(
    `permission`,
    '$.modify',
    JSON_MERGE(COALESCE(JSON_EXTRACT(`permission`, '$.modify'), JSON_ARRAY()), JSON_ARRAY('tool/recycle_bin'))
)
WHERE JSON_CONTAINS(`permission`, '"tool/recycle_bin"', '$.modify') = 0;