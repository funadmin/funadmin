# 插件与生成业务表软删除 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the core soft-delete rules to the four plugins (document, qa, oss, cms) and to the code generator and form designer.

**Architecture:**
- Each plugin gets one new 005-style migration plus a Schema-class SQLite twin.
- Its models adopt `LaravelSoftDelete`.
- Ordinary deletes become soft, relation saves become diff-based, and raw reads filter `deleted_at`.
- Admin recycle-bin endpoints (list, restore, permanent delete) are the only physical delete path.
- The generator emits the same schema.

**Tech Stack:** PHP 8.5, webman, illuminate/database 13, MySQL 8.0 (podman harness), SQLite tests, Vue 3 + Element Plus.

**Spec:** docs/superpowers/specs/2026-11-11-plugin-soft-delete-design.md (core rules: docs/superpowers/specs/2026-11-10-backend-soft-delete-design.md, `admin/README.md` "软删除" section)

## Global Constraints

**Schema rules for every non-exempt table:**
- An `id` auto-increment primary key.
- `deleted_at DATETIME NULL` with an index whose leading column is `deleted_at`.
- Tables with a unique key also get `deleted_token BIGINT UNSIGNED NOT NULL DEFAULT 0`, and every non-primary unique key ends with `deleted_token` and keeps its name.
- Tables with no unique key do NOT get `deleted_token`.
- Plugin `deleted_at TIMESTAMP` columns are changed to `DATETIME`.

**Exempt tables** get no soft-delete columns:
- `cms_form_event`, `cms_site_auth_audit`, `cms_model_version`, `cms_content_revision`, `cms_template_tag_version`
- `document_events`, `document_revisions`
- `oss_setting`

**Former-PK tables** get a surrogate `id` primary key, and their old PK or unique key becomes `uk_<table>_pk (old cols, deleted_token)`:
- CMS `site_admin`, `site_model`, `site_role_permission`, `site_role_member` (these had no PK, only a unique key: they get a surrogate `id` and keep their existing unique key name with `deleted_token` appended — ruling during Task 3)
- document `document_category_links`
- oss `oss_profile` (its string PK becomes the unique key)

**Migration file names and plugin versions:**

| Plugin | Migration | Version |
|---|---|---|
| CMS | `007_soft_delete.sql` | 1.6.0 |
| document | `004_soft_delete.sql` | 1.5.0 |
| qa | `002_soft_delete.sql` | 1.1.0 |
| oss | `002_soft_delete.sql` | 1.1.0 |

Each must be:
- Idempotent, using information_schema guards and one `ALTER` per table, in the same style as `admin/database/core/migrations/005_soft_delete.sql`.
- ASCII-only. Inside PREPARE strings only `DROP INDEX` and `DROP PRIMARY KEY` are allowed. No `DROP COLUMN`, `RENAME` or `TRUNCATE`.
- Never edit existing migrations, because they are checksum-locked.

**Behaviour rules:**
- Models use `app\common\model\concern\LaravelSoftDelete`. Restore conflict throws `RestoreConflictException`, which the global handler maps to 409.
- Never revive a soft-deleted row by `withTrashed()->update`/`updateOrCreate`. Recreate with a new row.
- Relation set saves use diff-based sync (`app\admin\authorization\service\RelationRowSync` pattern) and lock the owning row first, the same way core does with `OwnerLock`.
- Raw `Db::table`, join and SQL reads filter `deleted_at IS NULL`.
- Nothing deletes automatically. Physical delete happens only in explicit admin recycle-bin endpoints, each with its own derived permission code and a can't-undo confirmation in the UI. Uploaded files are removed only in that permanent-delete step.
- Plugin source is under `admin/plugins/<code>/`. Installed copies (`admin/app/<code>`, `admin/app/admin/plugins/<code>`, `web/src/admin/plugins/<code>`) and `admin/tests/{Cms,Document,Qa,Oss}` are gitignored. After each task, sync the edited plugin files to their installed copies with `cmp`. Commit only plugin source and core files.

**Process rules for the shared worktree:**
- Commit only via `git commit --only -- <paths>`.
- Never use `add -A`, stash or reset.
- Don't push.
- Don't touch the dev DB.

**Verification:**
- Each task runs its plugin's tests (`vendor/bin/phpunit tests/<Plugin>`) and the full backend suite.
- MySQL tests run under podman, using the docker shim at `/tmp/fa-podman/bin/docker` (or `/tmp/podman-docker-shim/docker`) and the existing images `funwebman-console:8.5` and `mysql:8.0` on network `funwebman-test`.

## Review Focus
1. **Recreate after delete:** the same slug, code, host, path, menu code or role code can be recreated after a soft delete, and live duplicates are still rejected.
2. **Old rows untouched:** writes that used `withTrashed()` (CMS site/category/tag upserts) no longer revive or overwrite trashed rows.
3. **Cascades:** deleting a parent (document → category links, form → submissions → files/events, menu → items, site role → members/permissions) soft-deletes its children. Restore brings back only the children deleted by that action.
4. **Recycle-bin scope:** recycle-bin endpoints only touch trashed rows inside the actor's site/data scope.
5. **Upgrade with data:** upgrading each plugin from its current version with existing data keeps every row and is safe to re-run.

---

### Task 1: Document plugin

**Files:**
- Create: `admin/plugins/document/database/migrations/004_soft_delete.sql`
- Modify: `admin/plugins/document/app/document/service/Schema.php`. Its `install()`/`mysql()` must produce the post-004 structure for SQLite tests. Keep the existing parity test meaningful, because exported baseline files must still match their files.
- Modify: models under `admin/plugins/document/app/document/model/`: Document, DocumentCategory, DocumentCategoryLink, DocumentFeedback, DocumentSeedEntry.
- Modify: `DocumentService`, `DocumentCategoryService`, `SeedService`, `PublicDocumentService`, `FeedbackService`.
- Modify: admin controllers `DocumentController` and `CategoryController`, adding recycle, restore and destroy. Add the matching web pages under `admin/plugins/document/web/src/admin/plugins/document/`.
- Modify: `admin/plugins/document/plugin.json` (version 1.5.0, plus any new permission/route entries).
- Test: `admin/tests/Document/DocumentSoftDeleteTest.php`, plus MySQL upgrade coverage.

Steps:
- [ ] **1.** Write failing tests:
  - `test_migration_adds_soft_delete_columns_and_token_only_where_unique`
  - `test_document_slug_reusable_after_delete`
  - `test_deleting_document_soft_deletes_category_links_and_restore_brings_them_back`
  - `test_restore_conflict_returns_409`
  - `test_category_delete_is_soft`
  - `test_seed_retire_categories_is_soft`
  - `test_recycle_bin_endpoints_only_touch_trashed_rows`
  - `test_upgrade_from_003_with_data_keeps_rows`
- [ ] **2.** Run `cd admin && vendor/bin/phpunit tests/Document`. Expect FAIL.
- [ ] **3.** Implement:
  - `documents` replaces hand-rolled `scopeAlive` and update-based delete with the trait.
  - Links are diff-synced, and soft-deleted together with their document.
  - Restore brings back only the links whose `deleted_at` equals the document's.
- [ ] **4.** Run `vendor/bin/phpunit tests/Document`, then the full suite, then the MySQL upgrade under podman.
- [ ] **5.** Sync installed copies. Commit `feat(document): 文档插件统一软删除与回收站`.

### Task 2: QA and OSS plugins

**Files:**
- Create: `admin/plugins/qa/database/migrations/002_soft_delete.sql` and `admin/plugins/oss/database/migrations/002_soft_delete.sql`
- Modify: the Schema classes, `QaQuestion`, `OssProfile` (`id` becomes the surrogate key, and lookups by the former string id keep working via a column rename-free approach), and each `plugin.json` (version 1.1.0).
- Test: `admin/tests/Qa/QaSoftDeleteTest.php` and `admin/tests/Oss/OssSoftDeleteTest.php`

Steps:
- [ ] **1.** Write failing tests:
  - QA: columns exist, the model soft-deletes, the PK is unchanged.
  - OSS: the `id` surrogate PK, the former string key unique with `deleted_token`, `oss_setting.current_profile_id` still resolves, `oss_setting` exempt.
  - Upgrade with data for both.
- [ ] **2.** Run the two plugin suites. Expect FAIL.
- [ ] **3.** Implement. Keep how `OssSetting.current_profile_id` references a profile working: it references the string column, not the new `id`.
- [ ] **4.** Run the plugin suites, the full suite, and the MySQL upgrade.
- [ ] **5.** Sync. Commit `feat(qa,oss): 问答与 OSS 插件软删除结构`.

### Task 3: CMS migration and models

**Files:**
- Create: `admin/plugins/cms/database/migrations/007_soft_delete.sql`
- Modify: the CMS Schema classes (SQLite twin), and all CMS models under `admin/plugins/cms/app/cms/model/` except the exempt ones.
- Modify: `admin/plugins/cms/plugin.json` (1.6.0)
- Test: `admin/tests/Cms/SoftDeleteSchemaTest.php`, plus `SchemaTest` (the file list now includes 007) and `MySqlCommunityUpgradeTest` (add 007; keep funadmin-56's local edits).

Steps:
- [ ] **1.** Write failing tests:
  - Every non-exempt table has `id`/`deleted_at`, and `deleted_token` iff it has a unique key.
  - Every unique key ends with `deleted_token`.
  - The 4 relation tables have a surrogate `id`.
  - TIMESTAMP becomes DATETIME.
  - Upgrade from 006 with data.
- [ ] **2.** Run `vendor/bin/phpunit tests/Cms`. Expect FAIL.
- [ ] **3.** Implement the migration and the model trait adoption. Don't change service behaviour yet, beyond what's needed to keep tests green.
- [ ] **4.** Run `tests/Cms`, the full suite, and the CMS MySQL tests under podman (`/tmp/fa-final/run.sh` pattern).
- [ ] **5.** Sync. Commit `feat(cms): CMS 软删除表结构与模型`.

### Task 4: CMS services and recycle bins

**Files:**
- Modify: CMS services (Site, Category, Content, Menu, TemplateTag, SiteRole, Form, FormSubmission, Route, Domain, and any other services with deletes or `withTrashed` writes), admin controllers, and web pages.
- Test: `admin/tests/Cms/SoftDeleteBehaviourTest.php`. Update `QueryBuilderAllowlistTest` and `ModelParity*` intentionally.

Steps:
- [ ] **1.** Write failing tests, one per Review Focus item 1–4 for CMS resources:
  - site code and domain host reuse
  - route path reuse
  - menu delete cascades to items, and restore
  - form delete cascades to submissions, files and events, files stay on disk, and permanent delete removes files
  - site role delete cascades to members and permissions
  - category and content delete, and restore
  - `withTrashed` upserts no longer revive rows
  - recycle-bin scope by site
- [ ] **2.** Run. Expect FAIL.
- [ ] **3.** Implement:
  - Diff-based saves for menu items, role permissions and member roles, locking the owner first.
  - Add `deleted_at` filters to the 28 `Db::table` calls where they read soft-deletable tables.
  - Recycle-bin endpoints per resource that has a delete action. Each has its own permission code and is registered in `plugin.json` and PermissionCatalog CEILINGS if site-scoped.
  - Web recycle-bin views.
- [ ] **4.** Run `tests/Cms`, the full suite, the web CMS specs, vue-tsc, and the CMS MySQL tests.
- [ ] **5.** Sync. Commit `feat(cms): CMS 普通删除改软删除并新增回收站`.

### Task 5: Code generator and form designer

**Files:**
- Modify: `admin/app/common/crud/ProductionTemplateContext.php`, `admin/app/common/crud/PluginCrudTarget.php`, `admin/app/admin/form/service/FormDesignerService.php`, the `assertPendingCreateTables` index check, and `FieldInference::MANAGED_FIELDS`.
- Modify: model templates, so they add `deleted_token` to `$hidden`.
- Test: extend `tests/CodeGeneratorMigrationTest.php`, `tests/FormDesignerSaveTest.php` (or equivalents), and add `tests/GeneratedSoftDeleteSchemaTest.php`.

Steps:
- [ ] **1.** Write failing tests:
  - generated create SQL has a `deleted_at` index;
  - `deleted_token` and unique keys ending in it only when there is a unique field;
  - UUID PK with a unique field gets the `row_no` auto-increment column used as the token source, and the trait uses it;
  - the plugin target check accepts the new index shape;
  - form designer FK: soft-deleting a parent cascades, sets null, or refuses according to the FK rule;
  - the current generated output still installs.
- [ ] **2.** Run. Expect FAIL.
- [ ] **3.** Implement. For the trait token source, make the token column value come from an overridable model method, defaulting to the key, so UUID models return `row_no`. Check `TokenSoftDeletingScope` for the batch path.
- [ ] **4.** Run the focused tests and the full suite.
- [ ] **5.** Commit `feat(crud): 生成表与表单设计器遵循软删除规则`.

### Task 6: Upgrade command for already-published business tables

**Files:**
- Create: `admin/app/admin/development/command/BusinessSoftDeleteUpgrade.php` (or follow the existing command location), registered as `business:soft-delete-upgrade [--table=] [--dry-run]`.
- Test: `admin/tests/BusinessSoftDeleteUpgradeTest.php`

Steps:
- [ ] **1.** Write failing tests:
  - dry-run lists the planned changes and changes nothing;
  - a real run adds the `deleted_at` index, and adds `deleted_token` plus rebuilt unique keys only where there are unique keys;
  - it is idempotent;
  - it refuses non-business tables and core or plugin tables;
  - it keeps data.
- [ ] **2.** Run. Expect FAIL.
- [ ] **3.** Implement using the same single-`ALTER`-per-table approach as the PHP twin of core 005 (`admin/database/migrations/005_soft_delete.php` MySQL branch). Business tables are identified through the `business_module` registry.
- [ ] **4.** Run the focused tests and the full suite. Dry-run against the dev DB's `fun_order` (read-only), and record the output.
- [ ] **5.** Commit `feat(business): 已发布业务表软删除升级命令`.

### Task 7: Regression guard, docs and verification

**Files:**
- Create: `admin/tests/PluginSoftDeleteGuardTest.php`. It checks:
  - every non-exempt plugin table (from the plugin Schema classes) has `deleted_at`, and `deleted_token` iff it has a unique key;
  - no plugin service calls `forceDelete`/`->delete()` on `Db::table` outside recycle-bin code (allowlist).
- Modify: `admin/README.md` soft-delete section (plugins and generator), and each plugin README (upgrade notes for its new migration).
- Create: `docs/superpowers/plans/2026-11-11-plugin-soft-delete-verification.md`

Steps:
- [ ] **1.** Write the guard test.
- [ ] **2.** Run the full backend suite, all plugin suites, the web full suite plus vue-tsc, the console MySQL suite under podman (`PATH=/tmp/fa-podman/bin:$PATH php tests/Console/run-mysql.php`), and the CMS MySQL tests.
- [ ] **3.** Write the verification record. Do NOT migrate the dev DB; record the command for the user.
- [ ] **4.** Commit `docs: 插件软删除规则与验证记录`.
