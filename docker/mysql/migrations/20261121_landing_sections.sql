-- Landing page builder: sections with columns (nested blocks).
--
-- Design notes:
--   * oc_landing_block becomes a two-level tree. Top-level rows (parent_id = 0)
--     render as full-width sections exactly as before; a `section` container
--     block carries its widgets as children (parent_id = the section's block_id,
--     slot = the 0-based column index). `sort_order` is the position inside the
--     own (parent_id, slot) group.
--   * Sections stay top-level only: ModelDesignLanding::saveBlocks() normalises a
--     submitted section with a parent back to the top level, and a child whose
--     parent is not part of the saved page is promoted to the top level instead
--     of being dropped — user content never disappears over a malformed payload.
--   * Widgets inside a column render without their own <section> wrapper
--     (ControllerLandingPage::renderBlockInner()): container, padding and
--     background belong to the section, the widget keeps its panel styles.
--
-- Idempotent: `make migrate` re-runs every *.sql file.

ALTER TABLE `oc_landing_block`
	ADD COLUMN IF NOT EXISTS `parent_id` INT(11) NOT NULL DEFAULT 0 AFTER `landing_id`,
	ADD COLUMN IF NOT EXISTS `slot` INT(11) NOT NULL DEFAULT 0 AFTER `parent_id`,
	ADD INDEX IF NOT EXISTS `idx_landing_block_parent` (`landing_id`, `parent_id`, `slot`, `sort_order`);
