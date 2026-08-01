-- Admin order status-change mail event (customer notification on admin status updates)
INSERT INTO `oc_event` (`code`, `trigger`, `action`, `status`, `sort_order`)
SELECT 'admin_mail_order', 'admin/model/sale/order/addOrderHistory/after', 'mail/order', 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `oc_event` WHERE `code` = 'admin_mail_order');
