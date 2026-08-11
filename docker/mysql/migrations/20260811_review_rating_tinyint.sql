-- Migration: 20260811 - review rating is a whole number 1-5 (tinyint).
-- Previously oc_review.rating was decimal(3,1) to store fractional averages,
-- but review scores are whole numbers only. Normalize existing fractional
-- values (rounded) and narrow the column type.
-- All statements are idempotent: `make migrate` re-runs every migration file.

-- Round any legacy fractional ratings to the nearest whole number.
UPDATE `oc_review`
SET `rating` = ROUND(`rating`)
WHERE `rating` != FLOOR(`rating`);

-- Narrow to tinyint(1). MODIFY is naturally idempotent.
ALTER TABLE `oc_review`
  MODIFY `rating` tinyint(1) NOT NULL DEFAULT 0;
