-- Dashboard performance: add indexes for sparkline queries
-- Covers GROUP BY DATE/HOUR(date_added) on oc_order and oc_customer

CREATE INDEX IF NOT EXISTS idx_order_date_status
    ON oc_order (order_status_id, date_added);

CREATE INDEX IF NOT EXISTS idx_customer_date
    ON oc_customer (date_added);
