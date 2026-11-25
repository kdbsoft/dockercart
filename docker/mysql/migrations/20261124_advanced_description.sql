-- Advanced Description: section-based descriptions for products, categories
-- and manufacturers (the lightweight composer, independent of the landing
-- page builder).
--
-- Design notes:
--   * <entity>.use_advanced_description switches the storefront description slot
--     between the classic WYSIWYG copy (0) and the section-based Advanced
--     Description (1). The WYSIWYG copy stays untouched — it keeps feeding the
--     search index and the product-card snippets, acts as the fallback when the
--     mode is switched off, and seeds the first text section on the initial
--     switch (conversion happens in the catalog models' save flow).
--   * <entity>.advanced_description stores the section structure shared by
--     every language: {"sections":[{"sid":"s1","type":"text_image","settings":{…}}]}.
--     Images, icons, column counts and ordering live here, so adding a section
--     once makes it available for every language.
--   * <entity>_description.advanced_content stores the per-language section
--     texts keyed by section id: {"s1":{"heading":"…","body":"…"}. Empty fields
--     fall back to the default language at render time
--     (Dockercart\Description\Sections::forView()).
--   * Everything is normalised on save through
--     Dockercart\Description\Sections::normalizeDocument() (types and icons are
--     whitelisted, text fields are stripped, rich-text is sanitized, counts are
--     clamped), so neither the admin form nor a hand-crafted POST can store
--     anything the storefront renderer does not expect.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_product`
	ADD COLUMN IF NOT EXISTS `use_advanced_description` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
	ADD COLUMN IF NOT EXISTS `advanced_description` MEDIUMTEXT NULL AFTER `use_advanced_description`;

ALTER TABLE `oc_category`
	ADD COLUMN IF NOT EXISTS `use_advanced_description` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
	ADD COLUMN IF NOT EXISTS `advanced_description` MEDIUMTEXT NULL AFTER `use_advanced_description`;

ALTER TABLE `oc_manufacturer`
	ADD COLUMN IF NOT EXISTS `use_advanced_description` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
	ADD COLUMN IF NOT EXISTS `advanced_description` MEDIUMTEXT NULL AFTER `use_advanced_description`;

ALTER TABLE `oc_product_description`
	ADD COLUMN IF NOT EXISTS `advanced_content` MEDIUMTEXT NULL AFTER `description`;

ALTER TABLE `oc_category_description`
	ADD COLUMN IF NOT EXISTS `advanced_content` MEDIUMTEXT NULL AFTER `description`;

ALTER TABLE `oc_manufacturer_description`
	ADD COLUMN IF NOT EXISTS `advanced_content` MEDIUMTEXT NULL AFTER `description`;
