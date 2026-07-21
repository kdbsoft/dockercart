-- Migration: 20260720 - Remove color images tables for product option values
DROP TABLE IF EXISTS `oc_product_option_value_color_image`;
DROP TABLE IF EXISTS `oc_dockercart_product_option_value_image`;
