-- Password reset codes ("code" column) previously never expired: a leaked
-- reset link stayed valid forever until used. Add an expiry timestamp set at
-- code creation; consumers treat missing/expired codes as absent.
-- Nullable column: legacy rows (and rows written before the code path was
-- updated) get NULL = "no expiry recorded", handled explicitly in queries.

ALTER TABLE `oc_customer`
	ADD COLUMN IF NOT EXISTS `code_expire` datetime NULL DEFAULT NULL AFTER `code`;

ALTER TABLE `oc_user`
	ADD COLUMN IF NOT EXISTS `code_expire` datetime NULL DEFAULT NULL AFTER `code`;
