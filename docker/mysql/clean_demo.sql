-- DockerCart: clean-demo data cleanup.
-- Removes demo catalogue content (products, categories, manufacturers, blog,
-- banners, coupons, reviews, customers, orders, SEO URLs, analytics) while
-- KEEPING: schema, localisation reference data (countries, zones, languages,
-- currencies, order/return/stock statuses, tax classes/rates/rules, geo zones,
-- length/weight classes, customer groups, review criteria, voucher themes),
-- the primary warehouse (id=1) with its schedule and delivery windows,
-- store settings (oc_setting), extensions/events/layouts/modules, information
-- pages, admin user and applied migrations.
--
-- This script is idempotent: safe to run on an already-clean database.
-- Runs with FK checks off because product tables reference oc_product;
-- DELETE handles cascading child rows of oc_product automatically.

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- Catalog: products and everything tied to them
DELETE FROM `oc_product`;
DELETE FROM `oc_product_attribute`;
DELETE FROM `oc_product_discount`;
DELETE FROM `oc_product_special`;
DELETE FROM `oc_product_image`;
DELETE FROM `oc_product_option`;
DELETE FROM `oc_product_option_value`;
DELETE FROM `oc_product_recurring`;
DELETE FROM `oc_product_related`;
DELETE FROM `oc_product_reward`;
DELETE FROM `oc_product_to_category`;
DELETE FROM `oc_product_to_download`;
DELETE FROM `oc_product_to_layout`;
DELETE FROM `oc_product_to_store`;
DELETE FROM `oc_product_rating`;
DELETE FROM `oc_product_video`;
DELETE FROM `oc_product_accessory`;
DELETE FROM `oc_product_fbt`;
DELETE FROM `oc_product_upsell`;
DELETE FROM `oc_product_similar`;
DELETE FROM `oc_product_bundle`;
DELETE FROM `oc_product_bundle_product`;
DELETE FROM `oc_product_bundle_store`;
DELETE FROM `oc_product_gift`;
DELETE FROM `oc_product_bxgy`;
DELETE FROM `oc_product_configurable`;
DELETE FROM `oc_product_configurable_option`;
DELETE FROM `oc_product_variant`;
DELETE FROM `oc_product_variant_value`;
DELETE FROM `oc_dockercart_product_customer_group_price`;
DELETE FROM `oc_dockercart_product_option_value_customer_group_price`;
DELETE FROM `oc_dockercart_product_variant_customer_group_price`;
DELETE FROM `oc_dockercart_product_variant_discount`;
DELETE FROM `oc_dockercart_product_variant_special`;
DELETE FROM `oc_product_description`;

-- Categories & manufacturers (keep structure, clear content)
DELETE FROM `oc_category`;
DELETE FROM `oc_category_description`;
DELETE FROM `oc_category_path`;
DELETE FROM `oc_category_to_store`;
DELETE FROM `oc_category_to_layout`;
DELETE FROM `oc_category_banner`;
DELETE FROM `oc_category_related`;
DELETE FROM `oc_manufacturer`;
DELETE FROM `oc_manufacturer_description`;
DELETE FROM `oc_manufacturer_to_store`;

-- Options & attributes (reference content; clearing keeps the feature usable)
DELETE FROM `oc_option`;
DELETE FROM `oc_option_description`;
DELETE FROM `oc_option_value`;
DELETE FROM `oc_option_value_description`;
DELETE FROM `oc_option_set`;
DELETE FROM `oc_option_set_description`;
DELETE FROM `oc_option_set_option`;
DELETE FROM `oc_attribute`;
DELETE FROM `oc_attribute_description`;
DELETE FROM `oc_attribute_group`;
DELETE FROM `oc_attribute_group_description`;
DELETE FROM `oc_attribute_set`;
DELETE FROM `oc_attribute_set_attribute`;
DELETE FROM `oc_attribute_set_description`;

-- Marketing content
DELETE FROM `oc_banner`;
DELETE FROM `oc_banner_description`;
DELETE FROM `oc_banner_image`;
DELETE FROM `oc_coupon`;
DELETE FROM `oc_coupon_description`;
DELETE FROM `oc_coupon_category`;
DELETE FROM `oc_coupon_product`;
DELETE FROM `oc_coupon_history`;
DELETE FROM `oc_voucher`;
DELETE FROM `oc_voucher_history`;
DELETE FROM `oc_voucher_theme`;
DELETE FROM `oc_voucher_theme_description`;
DELETE FROM `oc_dockercart_newsletter_description`;
DELETE FROM `oc_dockercart_newsletter_subscriber`;
DELETE FROM `oc_marketing`;

-- Demo payment/shipping methods created via the universal method editor
-- (fixed-price demo shipping rates, demo payment methods).
DELETE FROM `oc_dockercart_universal_payment`;
DELETE FROM `oc_dockercart_universal_payment_description`;
DELETE FROM `oc_dockercart_universal_shipping`;
DELETE FROM `oc_dockercart_universal_shipping_description`;

-- Blog
DELETE FROM `oc_blog_author`;
DELETE FROM `oc_blog_category`;
DELETE FROM `oc_blog_category_description`;
DELETE FROM `oc_blog_category_to_store`;
DELETE FROM `oc_blog_post`;
DELETE FROM `oc_blog_post_description`;
DELETE FROM `oc_blog_post_tag`;
DELETE FROM `oc_blog_post_to_category`;
DELETE FROM `oc_blog_post_to_product`;
DELETE FROM `oc_blog_post_to_product_category`;
DELETE FROM `oc_blog_post_to_manufacturer`;
DELETE FROM `oc_blog_post_to_store`;
DELETE FROM `oc_blog_comment`;
DELETE FROM `oc_blog_event`;
DELETE FROM `oc_blog_seo_url`;
-- oc_blog_setting holds module settings — keep it.

-- Warehouses: keep the primary warehouse (id=1) with its schedule/windows,
-- remove demo dropship warehouses and all stock/movement/holiday/transfer data
-- (stock rows reference deleted demo products; no FK constraints on these tables).
DELETE FROM `oc_warehouse_stock`;
DELETE FROM `oc_warehouse_stock_movement`;
DELETE FROM `oc_warehouse_holiday`;
DELETE FROM `oc_warehouse_holiday_description`;
DELETE FROM `oc_warehouse_transfer`;
DELETE FROM `oc_warehouse_transfer_item`;
DELETE FROM `oc_warehouse_description` WHERE `warehouse_id` <> 1;
DELETE FROM `oc_warehouse` WHERE `warehouse_id` <> 1;

-- Customers, orders, and activity
DELETE FROM `oc_customer`;
DELETE FROM `oc_customer_activity`;
DELETE FROM `oc_customer_approval`;
DELETE FROM `oc_customer_affiliate`;
-- oc_customer_group* / oc_customer_group_description* — keep as reference config.
DELETE FROM `oc_customer_history`;
DELETE FROM `oc_customer_ip`;
DELETE FROM `oc_customer_login`;
DELETE FROM `oc_customer_reward`;
DELETE FROM `oc_customer_search`;
DELETE FROM `oc_customer_transaction`;
DELETE FROM `oc_customer_wishlist`;
DELETE FROM `oc_address`;
DELETE FROM `oc_cart`;
DELETE FROM `oc_order`;
DELETE FROM `oc_order_history`;
DELETE FROM `oc_order_option`;
DELETE FROM `oc_order_payment`;
DELETE FROM `oc_order_product`;
DELETE FROM `oc_order_product_discount`;
DELETE FROM `oc_order_product_override`;
DELETE FROM `oc_order_recurring`;
DELETE FROM `oc_order_recurring_transaction`;
DELETE FROM `oc_order_shipment`;
DELETE FROM `oc_order_shipment_item`;
DELETE FROM `oc_order_total`;
DELETE FROM `oc_order_voucher`;
DELETE FROM `oc_order_claim`;
DELETE FROM `oc_order_document`;
-- Trash holds soft-deleted records (incl. demo orders with full JSON payload).
DELETE FROM `oc_trash`;
DELETE FROM `oc_return`;
DELETE FROM `oc_return_history`;
DELETE FROM `oc_return_product`;
DELETE FROM `oc_review`;
DELETE FROM `oc_review_image`;
DELETE FROM `oc_review_video`;
DELETE FROM `oc_review_vote`;
-- oc_review_criteria* — keep as reference config.

-- SEO & analytics.
-- Only remove SEO URLs that point at removed entities (products, categories,
-- manufacturers, blog). URLs for information pages, checkout, modules,
-- payment routes etc. are application config and must stay.
DELETE FROM `oc_seo_url` WHERE `query` LIKE 'product_id=%'
   OR `query` LIKE 'category_id=%'
   OR `query` LIKE 'manufacturer_id=%'
   OR `query` LIKE 'blog_%';
DELETE FROM `oc_blog_seo_url`;
DELETE FROM `oc_redirect_manager`;
DELETE FROM `oc_search_query_mapping`;
DELETE FROM `oc_dockercart_seo_log`;
DELETE FROM `oc_dockercart_traffic_source`;
DELETE FROM `oc_dockercart_viewed_product`;
DELETE FROM `oc_dockercart_checkout_abandoned`;
DELETE FROM `oc_dockercart_checkout_analytics`;

-- Sessions and carts
DELETE FROM `oc_session`;
DELETE FROM `oc_upload`;
DELETE FROM `oc_download`;
DELETE FROM `oc_download_description`;
DELETE FROM `oc_stock_reservation`;

-- Reset auto-increment counters so fresh content starts at 1.
ALTER TABLE `oc_product` AUTO_INCREMENT = 1;
ALTER TABLE `oc_product_image` AUTO_INCREMENT = 1;
ALTER TABLE `oc_product_to_store` AUTO_INCREMENT = 1;
ALTER TABLE `oc_product_description` AUTO_INCREMENT = 1;
ALTER TABLE `oc_product_option` AUTO_INCREMENT = 1;
ALTER TABLE `oc_product_option_value` AUTO_INCREMENT = 1;
ALTER TABLE `oc_product_variant` AUTO_INCREMENT = 1;
ALTER TABLE `oc_product_variant_value` AUTO_INCREMENT = 1;
ALTER TABLE `oc_category` AUTO_INCREMENT = 1;
ALTER TABLE `oc_category_description` AUTO_INCREMENT = 1;
ALTER TABLE `oc_category_path` AUTO_INCREMENT = 1;
ALTER TABLE `oc_manufacturer` AUTO_INCREMENT = 1;
ALTER TABLE `oc_manufacturer_description` AUTO_INCREMENT = 1;
ALTER TABLE `oc_customer` AUTO_INCREMENT = 1;
ALTER TABLE `oc_order` AUTO_INCREMENT = 1;
ALTER TABLE `oc_order_product` AUTO_INCREMENT = 1;
ALTER TABLE `oc_order_total` AUTO_INCREMENT = 1;
ALTER TABLE `oc_cart` AUTO_INCREMENT = 1;
ALTER TABLE `oc_address` AUTO_INCREMENT = 1;
ALTER TABLE `oc_review` AUTO_INCREMENT = 1;
ALTER TABLE `oc_banner` AUTO_INCREMENT = 1;
ALTER TABLE `oc_banner_image` AUTO_INCREMENT = 1;
ALTER TABLE `oc_coupon` AUTO_INCREMENT = 1;
ALTER TABLE `oc_seo_url` AUTO_INCREMENT = 1;
ALTER TABLE `oc_blog_author` AUTO_INCREMENT = 1;
ALTER TABLE `oc_blog_category` AUTO_INCREMENT = 1;
ALTER TABLE `oc_blog_post` AUTO_INCREMENT = 1;
ALTER TABLE `oc_blog_comment` AUTO_INCREMENT = 1;
ALTER TABLE `oc_warehouse` AUTO_INCREMENT = 1;
ALTER TABLE `oc_dockercart_universal_payment` AUTO_INCREMENT = 1;
ALTER TABLE `oc_dockercart_universal_shipping` AUTO_INCREMENT = 1;
ALTER TABLE `oc_trash` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
