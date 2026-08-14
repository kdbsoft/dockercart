-- Seed default RU/UK display names for known scheduler tasks.
-- Names are stored per (task_type, source_id, language_id); users can
-- override them later from the admin Scheduler UI. Language ids 2=uk-ua,
-- 3=ru-ua in the default install.
-- Idempotent: ON DUPLICATE KEY UPDATE keeps existing custom names untouched.

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Обновление курсов валют'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'currency_refresh'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Оновлення курсів валют'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'currency_refresh'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Генерация карты сайта'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'dockercart_sitemap_generate'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Генерація карти сайту'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'dockercart_sitemap_generate'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Переиндексация поиска Manticore'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'manticore_search_reindex'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Переіндексація пошуку Manticore'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'manticore_search_reindex'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Продление автопродлеваемых акций'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'promo_renew'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Продовження автопродовжуваних акцій'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'promo_renew'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Начисление бонусных баллов'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'reward_auto_award'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Нарахування бонусних балів'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'reward_auto_award'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Очистка брошенных корзин'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'abandoned_cart_cleanup'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Очищення покинутих кошиків'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'abandoned_cart_cleanup'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Очистка резервов товаров'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'reservation_cleanup'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Очищення резервів товарів'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'reservation_cleanup'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Проверка лицензии'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'license_check'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Перевірка ліцензії'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'license_check'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 3, 'Очистка источника трафика'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'traffic_source_cleanup'
ON DUPLICATE KEY UPDATE `name` = `name`;

INSERT INTO `oc_dockercart_scheduler_task_name` (`task_type`, `source_id`, `language_id`, `name`)
SELECT t.`task_type`, t.`source_id`, 2, 'Очищення джерела трафіку'
FROM `oc_dockercart_scheduler_task` t
WHERE t.`task_type` = 'traffic_source_cleanup'
ON DUPLICATE KEY UPDATE `name` = `name`;
