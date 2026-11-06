-- funadmin-physical-prefix
-- Language pack namespace enhancement.
-- Adds a STORED generated `ns` column derived from `key`:
--   container-style keys (crud.*/plugin.*) keep the first two segments (crud.products)
--   legacy two-segment keys keep the first segment (aiComposer.approval -> aiComposer)
-- The generated column backfills all existing rows automatically and cannot drift
-- from `key` because MySQL maintains it. Indexed as (locale, ns) for equality
-- lookups (pack filtering, per-module cleanup, grouping).
-- Also adds `deleted_at` so language lines switch to soft delete: pack version
-- negotiation (MAX id / updated_at / deleted_at) stays monotonic across deletes.
ALTER TABLE `fun_language_line`
  ADD COLUMN `ns` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    GENERATED ALWAYS AS (
      CASE
        WHEN SUBSTRING_INDEX(`key`, '.', 1) IN ('crud', 'plugin') THEN SUBSTRING_INDEX(`key`, '.', 2)
        ELSE SUBSTRING_INDEX(`key`, '.', 1)
      END
    ) STORED NOT NULL AFTER `key`,
  ADD COLUMN `deleted_at` datetime NULL DEFAULT NULL AFTER `updated_at`,
  ADD INDEX `idx_locale_ns` (`locale`, `ns`);
