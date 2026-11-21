-- One-time repair for the landing builder language race (fixed in the admin
-- builder JS): a debounced editor write used the language tab selected *when the
-- timer fired*, so a quick language switch after typing could wipe the body of
-- the language the operator had just edited. The damage shows on the seed `text`
-- block of an information page's owned landing (created by
-- ModelCatalogInformation::enableBuilder(): heading = page title, body = the
-- classic WYSIWYG copy, per language).
--
-- For every language where that seed block's body is empty while the
-- information page's classic description is not, the classic copy is restored
-- into the block's content JSON. Rows with invalid JSON are skipped, never
-- destroyed. A deliberately emptied body is identified by its heading no longer
-- matching the page title and is left alone.
--
-- Runs once, guarded by an oc_setting marker row (code = 'dockercart_migrations'
-- is never touched by editSetting('config', …), so the guard survives store
-- settings saves). `make update` additionally records the file in
-- oc_schema_migrations.
--
-- Requires the hierarchy columns from 20261121_landing_sections.sql (parent_id).

UPDATE `oc_landing_block_description` d
INNER JOIN `oc_landing_block` b ON b.block_id = d.block_id
INNER JOIN `oc_landing` l ON l.landing_id = b.landing_id
INNER JOIN `oc_information_description` i ON i.information_id = l.information_id AND i.language_id = d.language_id
SET d.content = JSON_SET(d.content, '$.body', i.description)
WHERE l.information_id IS NOT NULL
	AND b.code = 'text'
	AND b.parent_id = 0
	AND i.description <> ''
	AND JSON_VALID(d.content)
	AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(d.content, '$.body')), '') = ''
	AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(d.content, '$.heading')), '') = i.title
	AND NOT EXISTS (
		SELECT 1 FROM `oc_setting` s
		WHERE s.store_id = 0 AND s.code = 'dockercart_migrations' AND s.`key` = 'landing_language_repair_20261122'
	);

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'dockercart_migrations', 'landing_language_repair_20261122', '1', 0
WHERE NOT EXISTS (
	SELECT 1 FROM `oc_setting` WHERE `store_id` = 0 AND `code` = 'dockercart_migrations' AND `key` = 'landing_language_repair_20261122'
);
