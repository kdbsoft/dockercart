-- The REST API is disabled by default (config_api_status = 0).
--
-- The 20261022_admin_rest_api.sql migration seeded `config_api_status` = '1',
-- which silently enabled the API on every upgraded store. The API is an
-- opt-in integration surface: until an administrator explicitly turns it on,
-- every /api/v1/... request must answer 503 api_disabled. Re-enable it in the
-- panel (System -> API -> Keys -> "API enabled") when an integration is needed.
--
-- Idempotent and corrective: it flips a still-default '1' to '0'. A store that
-- deliberately switched the API on after the seed migration and kept using it
-- would also be switched off here — re-enable it afterwards if that applies.

UPDATE `oc_setting`
SET `value` = '0'
WHERE `store_id` = 0
  AND `code` = 'config'
  AND `key` = 'config_api_status'
  AND `value` = '1';
