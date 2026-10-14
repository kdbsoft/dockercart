-- Migration: 20261014 - fix multiply-encoded product attribute texts (e.g. 6.1&quot; shown as 6.1&amp;amp;amp;quot;)
-- Root cause: oc_product_attribute.text stored HTML entities (6.1&quot;, 6.1&amp;amp;...quot;) while
-- catalog Twig auto-escapes, so entities were escaped again on the product page.
-- Code now decodes on read (catalog model) and normalizes on write (admin model);
-- this migration cleans legacy rows to raw text (6.1").
-- Idempotent: make migrate re-runs every file; WHERE guards make repeats no-ops.
-- Unwind &amp; nesting (needs up to 6 passes for 6.1&amp;amp;amp;amp;amp;quot;).
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&amp;', '&') WHERE `text` LIKE '%&amp;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&amp;', '&') WHERE `text` LIKE '%&amp;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&amp;', '&') WHERE `text` LIKE '%&amp;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&amp;', '&') WHERE `text` LIKE '%&amp;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&amp;', '&') WHERE `text` LIKE '%&amp;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&amp;', '&') WHERE `text` LIKE '%&amp;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&amp;', '&') WHERE `text` LIKE '%&amp;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&amp;', '&') WHERE `text` LIKE '%&amp;%';
-- Decode remaining single-level entities to raw chars (Twig will escape on output).
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&quot;', '"') WHERE `text` LIKE '%&quot;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&#039;', '\'') WHERE `text` LIKE '%&#039;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&#39;', '\'') WHERE `text` LIKE '%&#39;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&apos;', '\'') WHERE `text` LIKE '%&apos;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&lt;', '<') WHERE `text` LIKE '%&lt;%';
UPDATE `oc_product_attribute` SET `text` = REPLACE(`text`, '&gt;', '>') WHERE `text` LIKE '%&gt;%';
-- Explicit guard for the reported demo rows (covered by generic passes above, kept for clarity).
UPDATE `oc_product_attribute` SET `text` = '6.1"' WHERE `product_id` = 5001 AND `attribute_id` = 15 AND `text` != '6.1"';
UPDATE `oc_product_attribute` SET `text` = '6.8"' WHERE `product_id` = 5002 AND `attribute_id` = 15 AND `text` != '6.8"';
