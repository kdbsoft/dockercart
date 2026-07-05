-- Add is_system flag to scheduler task table for hiding internal/system tasks from admin UI

ALTER TABLE `oc_dockercart_scheduler_task`
  ADD COLUMN IF NOT EXISTS `is_system` tinyint(1) NOT NULL DEFAULT 0;
