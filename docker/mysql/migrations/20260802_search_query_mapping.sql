-- Migration: 20260802 - dedicated search query mappings table
-- Replaces the legacy single-text setting (module_dockercart_search_query_mappings)
-- with a proper table, so tens of thousands of mappings can be stored and managed
-- efficiently. Existing data is migrated lazily from the setting by the module
-- (admin model ensureMappingTable) on first use.
-- All statements are idempotent: `make migrate` re-runs every migration file.
-- Source is unique case-insensitively (utf8mb4_unicode_ci), matching the
-- catalog-side deduplication of mapping sources.

CREATE TABLE IF NOT EXISTS `oc_search_query_mapping` (
  `mapping_id` int(11) NOT NULL AUTO_INCREMENT,
  `source` varchar(255) NOT NULL,
  `target` varchar(255) NOT NULL,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `uq_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
