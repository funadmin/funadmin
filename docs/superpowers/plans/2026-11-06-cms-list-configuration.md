# CMS configuration models and list tags

**Goal:** Support reusable site configuration lists for partners, contacts, banners and advertisements.
**Design:** Add a `list` configuration type with up to 20 scalar child fields and 100 rows. Rows have an explicit `enabled` flag and use array order for display. Definitions and values remain in the existing site settings JSON; whole-list language overrides retain existing inheritance semantics. Extend `cms:config name="..." as="item"` to expose enabled rows in stored order and accept a bounded limit. Keep scalar config tags compatible. No production seed data or database migrations.

## Execution

- [x] Add backend regression tests for schema/row validation, URL safety, list order, enable/disable, locale/site isolation and escaped template loops.
- [x] Implement bounded list definitions and values; extend config tag while preserving scalar behavior.
- [x] Add frontend repeatable row editor and field model editor with partner/contact/banner/advertisement presets, permission-aware edits and ordering.
- [x] Test real editor behavior and API payloads, synchronize plugin/live sources, typecheck/build and document usage.

Lists support one level of scalar children, avoid recursive configuration trees, reject unknown row fields and reserved/prototype keys. Image and URL fields retain existing URL validation. Empty language lists are explicit overrides, not inheritance. Existing rows incompatible with changed definitions must reject rather than silently discard data.

## Verification

- New backend tests reproduced unsupported list configuration before implementation; enabled=null acceptance was separately reproduced and corrected.
- CMS backend suite: 107 tests / 554 assertions, 103 executed passing and 4 dedicated MySQL tests skipped. Log: `/tmp/funadmin-cms-list-php-final.log`.
- Site configuration, list editor and tab regressions: 13 tests passed, including ordered payloads, presets, read-only editing, empty language lists and cross-tab input preservation. Log: `/tmp/funadmin-cms-list-web-final.log`.
- Plugin-inclusive typecheck and CMS compilation passed; full Webman mode typecheck/build passed and was served from `admin/public/`.
- All modified plugin backend and frontend sources match their published copies. Webman reloaded; root HTML and health returned 200. No production configuration records, seed data or schema changed.
- Independent read-only review reported no Critical/Important defects; its uncovered schema-boundary/locale cases were added to regression coverage.
- Earlier broad CMS frontend run encountered concurrent rich-text/attachment editor work in progress (missing RichTextEditor import and 3 upload tests). Those components are outside this change; final CMS results are recorded separately in `/tmp/funadmin-cms-list-web-all-final.log`.
- Final broad CMS frontend suite: 49 passed / 2 failed (51 tests, 13 files). Both failures are in `commonPermission.spec.ts`: `仅站点create权限即可查询附件，不要求全局旧save码` and `附件查询在站点权限撤销后使用最新上下文`, in the concurrently changed `AttachmentField.vue`. The configuration editor regression files remain green; no unrelated attachment implementation was modified.
