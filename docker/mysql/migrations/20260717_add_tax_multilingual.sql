-- Add multilingual support for tax classes and tax rates
-- Pattern: row-per-language (language_id in the main table)

-- 1. oc_tax_class: add language_id column + composite primary key

ALTER TABLE `oc_tax_class` MODIFY `tax_class_id` int(11) NOT NULL;

ALTER TABLE `oc_tax_class` ADD COLUMN IF NOT EXISTS `language_id` int(11) NOT NULL DEFAULT 1 AFTER `tax_class_id`;

ALTER TABLE `oc_tax_class` DROP PRIMARY KEY, ADD PRIMARY KEY (`tax_class_id`, `language_id`);

ALTER TABLE `oc_tax_class` MODIFY `tax_class_id` int(11) NOT NULL AUTO_INCREMENT;

-- 2. oc_tax_rate: add language_id column + composite primary key

ALTER TABLE `oc_tax_rate` MODIFY `tax_rate_id` int(11) NOT NULL;

ALTER TABLE `oc_tax_rate` ADD COLUMN IF NOT EXISTS `language_id` int(11) NOT NULL DEFAULT 1 AFTER `tax_rate_id`;

ALTER TABLE `oc_tax_rate` DROP PRIMARY KEY, ADD PRIMARY KEY (`tax_rate_id`, `language_id`);

ALTER TABLE `oc_tax_rate` MODIFY `tax_rate_id` int(11) NOT NULL AUTO_INCREMENT;
