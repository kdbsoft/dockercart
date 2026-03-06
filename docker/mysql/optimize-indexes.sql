-- DockerCart Filter - Critical Indexes for High Load
-- Execute this SQL to optimize database for filter module
-- 
-- Usage:
--   mysql -u dockercart -p dockercart < docker/mysql/optimize-indexes.sql
--   OR
--   docker exec dockercart_mysql mysql -udockerart -pdockerart_password dockercart < optimize-indexes.sql

-- ============================================
-- PRODUCT TABLE (Most Critical)
-- ============================================

-- Search by status and manufacturer
CREATE INDEX IF NOT EXISTS idx_product_status_manufacturer 
  ON `oc_product` (`status`, `manufacturer_id`);

-- Price range queries
CREATE INDEX IF NOT EXISTS idx_product_status_price 
  ON `oc_product` (`status`, `price`);

-- Reverse index for faster lookups
CREATE INDEX IF NOT EXISTS idx_product_manufacturer_status 
  ON `oc_product` (`manufacturer_id`, `status`);

-- Status and availability
CREATE INDEX IF NOT EXISTS idx_product_status_date 
  ON `oc_product` (`status`, `date_available`);

-- Sort and pagination
CREATE INDEX IF NOT EXISTS idx_product_sort_order 
  ON `oc_product` (`sort_order`, `product_id`);


-- ============================================
-- PRODUCT TO CATEGORY TABLE (Critical)
-- ============================================

-- Category filtering (most common query)
CREATE INDEX IF NOT EXISTS idx_p2c_category_product 
  ON `oc_product_to_category` (`category_id`, `product_id`);

-- Reverse lookups
CREATE INDEX IF NOT EXISTS idx_p2c_product_category 
  ON `oc_product_to_category` (`product_id`, `category_id`);


-- ============================================
-- PRODUCT ATTRIBUTE TABLE (Critical)
-- ============================================

-- Main attribute filtering
CREATE INDEX IF NOT EXISTS idx_pa_product_attribute 
  ON `oc_product_attribute` (`product_id`, `attribute_id`, `language_id`);

-- Reverse lookup for attribute values
CREATE INDEX IF NOT EXISTS idx_pa_attribute_product 
  ON `oc_product_attribute` (`attribute_id`, `product_id`, `language_id`);

-- Text search in attributes
CREATE INDEX IF NOT EXISTS idx_pa_attribute_text 
  ON `oc_product_attribute` (`attribute_id`, `text`(100), `language_id`);


-- ============================================
-- PRODUCT OPTION VALUE TABLE (Critical)
-- ============================================

-- Option filtering
CREATE INDEX IF NOT EXISTS idx_pov_product_option 
  ON `oc_product_option_value` (`product_id`, `option_id`);

-- Reverse lookup for option values
CREATE INDEX IF NOT EXISTS idx_pov_option_value 
  ON `oc_product_option_value` (`option_id`, `option_value_id`);


-- ============================================
-- PRODUCT SPECIAL TABLE (Important)
-- ============================================

-- Special price date range queries
CREATE INDEX IF NOT EXISTS idx_ps_customer_date 
  ON `oc_product_special` (`customer_group_id`, `date_start`, `date_end`);

-- Customer group and product
CREATE INDEX IF NOT EXISTS idx_ps_customer_product 
  ON `oc_product_special` (`customer_group_id`, `product_id`);


-- ============================================
-- MANUFACTURER TABLE (Good to have)
-- ============================================

-- Manufacturer name lookups
CREATE INDEX IF NOT EXISTS idx_manufacturer_name 
  ON `oc_manufacturer` (`name`);


-- ============================================
-- VERIFY AND OPTIMIZE
-- ============================================

-- Show all indexes on critical tables
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME IN ('oc_product', 'oc_product_attribute', 'oc_product_option_value', 'oc_product_to_category')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- ============================================
-- OPTIMIZE TABLES (Optional but Recommended)
-- ============================================

-- Uncomment to run optimization
-- This rebuilds indexes and reclaims space
-- WARNING: Tables will be locked during optimization!

/*
OPTIMIZE TABLE `oc_product`;
OPTIMIZE TABLE `oc_product_attribute`;
OPTIMIZE TABLE `oc_product_to_category`;
OPTIMIZE TABLE `oc_product_option_value`;
OPTIMIZE TABLE `oc_product_special`;
OPTIMIZE TABLE `oc_manufacturer`;

-- Analyze statistics for query optimizer
ANALYZE TABLE `oc_product`;
ANALYZE TABLE `oc_product_attribute`;
*/

-- ============================================
-- CHECK TABLE STATISTICS
-- ============================================

SELECT 
    TABLE_NAME,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'Size (MB)',
    ROUND(DATA_FREE / 1024 / 1024, 2) AS 'Free (MB)',
    ROUND(100 * (DATA_FREE / (DATA_FREE + DATA_LENGTH)), 2) AS 'Fragmentation %',
    ROW_FORMAT,
    ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('oc_product', 'oc_product_attribute', 'oc_product_option_value', 'oc_product_to_category', 'oc_product_special', 'oc_manufacturer')
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
