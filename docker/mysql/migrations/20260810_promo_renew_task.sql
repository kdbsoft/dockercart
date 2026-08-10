-- Scheduler task: renew expired auto-renewable promos (specials, variant
-- specials, quantity discounts, gifts, coupons) once per day — independent
-- of storefront visits.

INSERT INTO `oc_dockercart_scheduler_task` (`task_type`, `task_name`, `worker_command`, `source_id`, `cron_enabled`, `cron_schedule`, `status`, `date_added`, `date_modified`)
SELECT 'promo_renew', 'Renew auto-renewable promotions', 'php /var/www/html/bin/dockercart_promo_renew.php', '0', '1', '23 3 * * *', '1', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `oc_dockercart_scheduler_task` WHERE `task_type` = 'promo_renew' AND `source_id` = '0');
