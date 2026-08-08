-- Migration: 20260808 - Call for Price "Request" order status
-- The Request mode of the "Call for Price" button creates a real order
-- with a dedicated status so requests are visible separately from
-- regular orders. Idempotent: `make migrate` re-runs every migration file.

INSERT INTO `oc_order_status` (`order_status_id`, `language_id`, `name`) VALUES
(135, 1, 'Awaiting request'),
(135, 2, 'Чекає запиту'),
(135, 3, 'Ожидает запроса')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
