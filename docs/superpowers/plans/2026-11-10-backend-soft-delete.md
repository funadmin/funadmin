# 后台数据全部软删除 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every core back-office table soft-deletes. A `deleted_token` column keeps unique keys usable after deletion. The operation log page gains real deletion and a recycle bin.

**Architecture:**
- Core migration 005 adds `deleted_at` and `deleted_token` to every core table and rebuilds each unique key as "original columns + `deleted_token`".
- The shared trait `app\common\model\concern\LaravelSoftDelete` writes both columns on delete (single and batch) and clears both on restore.
- Every core model adopts the trait. Query-builder, join and raw-SQL paths get explicit filters.
- The operation log controller and page gain force-delete and recycle-bin views.

**Tech Stack:** PHP 8.5, webman, illuminate/database 13 (Eloquent SoftDeletes), workerman/crontab, SQLite (tests) / MySQL, Vue 3 + Element Plus, vitest.

**Spec:** docs/superpowers/specs/2026-11-10-backend-soft-delete-design.md

## Global Constraints

- Column definitions:
  - `deleted_at DATETIME NULL`, indexed.
  - `deleted_token BIGINT UNSIGNED NOT NULL DEFAULT 0`. It is 0 while the row is live; on soft delete it becomes the row's own `id`; on restore it goes back to 0.
- Every non-primary UNIQUE KEY on a core table is rebuilt as `(original columns..., deleted_token)`, keeping its index name.
- Migration file names:
  - `admin/database/core/migrations/005_soft_delete.sql` is the authoritative core source.
  - `admin/database/migrations/005_soft_delete.php` is the equivalent PHP version.
  - Both must be idempotent (safe to re-run).
- Scope:
  - Core tables in `admin/database/core/migrations/*.sql`.
  - Models under `admin/app/admin`, `admin/app/common` and `admin/app/api`.
  - Excluded: plugin tables and code (`admin/plugins/*`, `admin/app/{cms,document,qa}`, `admin/app/admin/plugins/*`) and generated business modules (`admin/app/admin/business/*`). CMS is being refactored by another session; do not touch it.
- `forceDelete()` is the only way to delete physically, and only from an admin's recycle-bin action.
- No automatic or scheduled deletion of soft-deleted rows. Real deletion is only `forceDelete()` triggered by an admin from a recycle bin.
- Operation log routes, under `#[RouteGroup('/admin/system/log/operation')]`:
  - `GET ''` accepts `trashed=1`.
  - `DELETE /force` takes `ids`.
  - `DELETE /clear-force`.
  - Each new method gets its derived permission code per existing conventions.
- All new admin UI text is provided in both `zh-CN` and `en-US`.
- The worktree is shared with other sessions.
  - Commit only with `git add -N <new files>` and then `git commit --only -m "..." -- <paths>`.
  - Never use `git add -A`, `git stash` or `git reset --hard`.
  - Do not push.
  - Plugin test dirs and plugin installed copies are gitignored, so don't commit them.
- Run targeted suites plus `cd admin && vendor/bin/phpunit` once per task. Compare against the baseline failure list recorded in the ledger, because other sessions' work in progress may already fail.

## Review Focus

1. **Recreate after delete.** Deleting then recreating the same natural key must succeed: an admin username, a menu `(app_name, href, query)` across plugin disable→enable, a permission code, a Casbin `rule_hash`, a plugin code and its `plugin_resource.target_path`. Two live duplicates must still be rejected.
2. **Batch delete.** `Model::where(...)->delete()` must soft-delete and set `deleted_token = id` per row. It must not hard-delete, and must not set the same token on every row.
3. **Paths outside the model.** Joins, `Db::table`, `Db::select` and raw SQL do not get the global scope. Any such read of a core table that is now soft-deleted must filter `deleted_at IS NULL`.
4. **No automatic deletion.** Nothing deletes soft-deleted rows automatically; real deletion happens only from an admin's explicit recycle-bin action.
5. **Tables without `deleted_token`.** The trait must keep working on these (plugin tables, simplified test tables) by writing only `deleted_at`.

---

### Task 1: Soft-delete trait writes `deleted_token`

**Files:**
- Modify: `admin/app/common/model/concern/LaravelSoftDelete.php`
- Create (if needed): `admin/app/common/model/concern/TokenSoftDeletingScope.php`
- Test: `admin/tests/SoftDeleteTraitTest.php`

**Interfaces:**
- Produces: trait `LaravelSoftDelete`. Its single delete, batch delete (builder), single restore and batch restore all maintain `deleted_token` when the table has the column. It keeps `forceDelete()`, `withTrashed()` and `onlyTrashed()`.
- Produces: `LaravelSoftDelete::hasDeletedToken(): bool`, cached per connection+table.

- [ ] **Step 1: Write failing tests.** Use SQLite tables built in the test:
  - `test_delete_sets_deleted_at_and_token_to_id`
  - `test_recreate_same_unique_value_after_delete_succeeds`: unique `(code, deleted_token)`. A second live duplicate still throws.
  - `test_batch_delete_sets_each_row_token_to_its_id`
  - `test_restore_clears_deleted_at_and_token`, single and batch.
  - `test_force_delete_removes_row`
  - `test_restore_conflicting_with_live_row_throws_clear_error_and_changes_nothing`: delete `zhangsan`, create a new `zhangsan`, then restore the old one. Expect `app\common\model\concern\RestoreConflictException` (a `\RuntimeException`, code 409, message "已存在相同的记录，无法恢复，请先修改或删除现有记录"). Both rows must be unchanged afterwards. Detect the conflict by catching the unique-violation `QueryException` (MySQL 1062 / SQLite `UNIQUE constraint failed`) on the restore, for both single and batch restore.
  - `test_table_without_token_column_only_sets_deleted_at`
- [ ] **Step 2: Run** `cd admin && vendor/bin/phpunit tests/SoftDeleteTraitTest.php`. Expect FAIL.
- [ ] **Step 3: Implement.**
  - Override `runSoftDelete()` and the restore path.
  - For builder-level delete and restore, register a scope subclass of `SoftDeletingScope` from the trait's boot method instead of the stock scope.
  - For batch delete, set the token with the column expression `deleted_token = <key column>`.
- [ ] **Step 4: Run** the same command. Expect PASS. Then run `vendor/bin/phpunit` and compare with the baseline.
- [ ] **Step 5: Commit** with `feat: 软删除写入 deleted_token，删除后可重建唯一值`.

### Task 2: Core migration 005

**Files:**
- Create: `admin/database/core/migrations/005_soft_delete.sql`
- Create: `admin/database/migrations/005_soft_delete.php`
- Modify: whatever core-migration manifest or checksum list the project keeps, if any. Check `CoreDatabaseAudit`, `MigrationService` and the install and upgrade manifests.
- Test: `admin/tests/SoftDeleteMigrationTest.php`. Extend the MySQL-only core migration test if one exists (`tests/Console/MySqlCoreMigrationTest.php`).

**Interfaces:**
- Produces: after 001–005, every core table has `deleted_at` (indexed) and `deleted_token`, and every non-primary unique key ends with `deleted_token`.

- [ ] **Step 1: Write failing tests.**
  - `test_every_core_table_has_soft_delete_columns_after_migration`: parse table names from the core SQL files.
  - `test_every_unique_key_includes_deleted_token`
  - `test_migration_is_idempotent`
  - `test_existing_rows_keep_token_zero_and_soft_deleted_rows_get_their_id`: rows that already have `deleted_at` set get `deleted_token = id`, so existing deleted rows stop blocking.
- [ ] **Step 2: Run.** Expect FAIL.
- [ ] **Step 3: Implement** both files. Follow the conventions of 002–004: header comments, and the PHP file returning a closure. The SQL must be idempotent on MySQL; follow the guard pattern the existing core SQL uses. Backfill `deleted_token = id WHERE deleted_at IS NOT NULL`. Also drop the plain indexes `username`, `mobile` and `email` on `fun_member`: they duplicate the first column of the unique keys. After running 005 in-process, call `TokenSoftDeletingScope::flushDeletedTokenCache()` because the column cache lasts for the life of the process.
- [ ] **Step 4: Run** the new test plus `tests/MigrationTest.php`, `tests/MigrationForwardOnlyGuardTest.php` and `tests/Console/CoreMigrationSourceTest.php`. Expect PASS. If `TEST_MYSQL=1` with a test database is available, also run the MySQL core migration test and record the result.
- [ ] **Step 5: Commit** with `feat: 核心迁移 005 补软删除列并把唯一索引改为含 deleted_token`.

### Task 3: Authentication and authorization models soft-delete

**Files:**
- Modify the models under `admin/app/admin/authentication/model/` and `admin/app/admin/authorization/model/`: Admin, AdminAuthToken, BrowserSession, LoginAttempt, AdminDepartment, AdminMenu, AuthGroupDepartment, AuthGroupFieldPermission, AuthGroupInherit, CasbinRule, Permission, PermissionField. Add the trait where it is missing.
- Modify callers that hand-filter or bypass the model: `AdminSessionService`, `AdminProfile`, `AuthorizationService`, `AdminAuthorizationService`, `RoleScopeService`, `PermissionTreeService`, `PermissionSyncService`, `CasbinService`, `CheckAdminApiRoleMiddleware`, `SystemMenu`, `McpService`, and `admin/app/admin/authorization/repository/casbin/DatabaseAdapter.php`.
- Modify test fixtures: every `admin/tests/*.php` that builds these tables gets `deleted_at` (nullable). It gets `deleted_token` only where the test asserts recreate behavior.
- Test: `admin/tests/SoftDeleteAuthorizationRegressionTest.php`

**Interfaces:**
- Consumes: the Task 1 trait.

- [ ] **Step 1: Write failing tests.**
  - `test_deleted_admin_cannot_log_in_and_username_can_be_reused`
  - `test_casbin_policy_removed_then_re_added_succeeds`
  - `test_menu_and_permission_resync_after_plugin_disable_enable`: use the existing plugin sync service entry points with a scaffolded test plugin.
  - `test_relation_rows_are_soft_deleted_and_not_loaded`
- [ ] **Step 2: Run.** Expect FAIL.
- [ ] **Step 3: Implement.**
  - Add the trait to these models.
  - Remove `whereNull('deleted_at')` on model queries that the scope now covers.
  - Keep explicit filters on joined tables, `Db::table` and raw SQL, and add them where missing.
  - Turn any query-builder delete on these tables into a trait-based soft delete.
  - Update the test fixtures.
- [ ] **Step 4: Run** the new test plus `tests/CasbinAdapterTest.php`, `AuthTest`, `AuthHttpTest`, `AuthorizationIntegrationTest`, `MenuHierarchyIntegrationTest` and `PluginDerivedPermissionTest`, then the full suite. Compare with the baseline.
- [ ] **Step 5: Commit** with `feat: 鉴权与权限相关模型统一软删除`.

### Task 4: Remaining core models soft-delete

**Files:**
- Modify the remaining core models that lack the trait:
  - `admin/app/admin/ai/model/*`: AiConfigurationProfile, AiOutbox, AiStreamNonce, AiTaskEvent, AiToolCall.
  - `admin/app/admin/development/model/CrudGeneration.php`.
  - `admin/app/admin/form/model/{FormPublishAttempt,FormSchemaVersion}.php`.
  - `admin/app/admin/system/model/{MemberGroupRelation,MemberTagRelation}.php`.
  - `admin/app/common/model/{MemberApiSession,MemberApiSessionUsed,MemberGroupRelation,MemberTagRelation,PhpMigration,SystemMigration,UpgradeManifest,UpgradeTask}.php`.
  - `admin/app/common/plugin/model/{PluginOperation,PluginResource,PluginVersionHistory}.php`.
  - Any other core-table model found by `grep -rL LaravelSoftDelete` over model directories.
- Modify callers with manual filters, query-builder deletes, joins or raw SQL on these tables: `FormDataService`, `DatabaseAiAttachmentRepository`, `DevPluginService`, `PluginValidate`, `BusinessTargetService`, `SchemaInspector`, `MigrationService`, and the plugin lifecycle and resource-ledger services.
- Make expiry cleanup (session, token, nonce, login attempt) soft-delete via the trait.
- If a core table has no model and a third-party library deletes from it directly (e.g. `jobs`, `failed_jobs`), leave it and list it in the report.
- Test: `admin/tests/SoftDeleteCoreModelsTest.php`

- [ ] **Step 1: Write failing tests.**
  - `test_every_core_table_model_uses_soft_delete`: reflection over the model directories, mapping each model to its table and asserting the table is a core table and the model uses the trait. The excluded paths are not scanned.
  - `test_plugin_resource_reregistered_after_uninstall_reinstall`: with the plugin ledger services.
  - `test_expired_sessions_are_soft_deleted`
- [ ] **Step 2: Run.** Expect FAIL.
- [ ] **Step 3: Implement** and update the affected test fixtures.
- [ ] **Step 4: Run** the new test, then the plugin lifecycle tests (`PluginCenterIntegrationTest`, `PluginHttpActivationTest`, `PluginComposerPipelineTest`), the AI and form tests, then the full suite. Compare with the baseline.
- [ ] **Step 5: Commit** with `feat: 其余核心模型统一软删除`.

### Task 5: (removed)

The user decided that soft-deleted records are always kept, with no automatic deletion and no scheduled purge. Real deletion happens only when an admin deletes manually from a recycle bin (Tasks 6–7 for operation logs; the existing member recycle bin, see Task 8).

### Task 6: Operation log backend: recycle bin and real deletion

**Files:**
- Modify: `admin/app/admin/system/controller/SystemOperationLog.php`
- Modify: `admin/tests/fixtures/auth-requirements.json` via `php tests/fixtures/update-auth-requirements.php`. Stage only this task's entries if the file has others' uncommitted changes.
- Modify: the permission registration for the operation-log menu, if derived codes need explicit registration. Follow how existing `index`/`delete`/`clear` codes are registered.
- Test: extend `admin/tests/OperationLogIntegrationTest.php`

**Interfaces:**
- Produces: `GET /admin/system/log/operation?trashed=1` (only soft-deleted rows, same shape as the normal list), `DELETE /admin/system/log/operation/force` with `{ids}` (real deletion of live or trashed rows within the data scope; returns `{removed}`), and `DELETE /admin/system/log/operation/clear-force` (real deletion of all rows within the data scope, including trashed; returns `{removed}`).

- [ ] **Step 1: Write failing tests.**
  - `test_soft_delete_moves_log_to_recycle_bin`
  - `test_force_delete_removes_live_and_trashed_logs`
  - `test_clear_force_removes_everything_in_scope_only`
  - `test_force_endpoints_require_their_own_permission`
- [ ] **Step 2: Run.** Expect FAIL.
- [ ] **Step 3: Implement.** Use `withTrashed()`/`onlyTrashed()` together with `AdminLog::scopedQuery()`, and use `forceDelete()`.
- [ ] **Step 4: Run** `vendor/bin/phpunit tests/OperationLogIntegrationTest.php tests/AuthRequirementSnapshotTest.php` and the full suite.
- [ ] **Step 5: Commit** with `feat: 操作日志回收站与彻底删除接口`.

### Task 7: Operation log page: recycle bin and real deletion

**Files:**
- Modify: `web/src/admin/api/system/log.ts`. Add `trashed?: 0 | 1` to the query, plus `forceRemove(ids)` and `clearForce()`.
- Modify: `web/src/admin/views/system/log/operation.vue`.
- Modify: the zh-CN and en-US locale files the page already uses.
- Test: `web/tests/views/operation-log-force-delete.spec.ts`. Add it to the `web/.gitignore` allowlist under `/tests/views/` so it is committed, following the existing exception pattern.

- [ ] **Step 1: Write failing tests.**
  - The "回收站" toggle requests `trashed=1`.
  - The "彻底删除" button is shown and calls `forceRemove` after confirming.
  - "清空回收站"/"彻底清空" calls `clearForce` after confirming.
  - The normal "删除" still calls `remove`.
  - Buttons are hidden without the matching permission.
- [ ] **Step 2: Run** `cd web && npx vitest run tests/views/operation-log-force-delete.spec.ts`. Expect FAIL.
- [ ] **Step 3: Implement.** Confirmation dialogs must state that the deletion cannot be undone.
- [ ] **Step 4: Run** the spec, `npx vue-tsc --noEmit` and `npm run check:i18n`. Expect no new failures.
- [ ] **Step 5: Commit** with `feat: 操作日志页面增加回收站与彻底删除`.

### Task 8: Member deletion cooling period

**Files:**
- Create: `admin/config/soft_delete.php` with `member_cooling_days` (default 30).
- Modify the member write paths:
  - `admin/app/admin/system/controller/SystemMember.php` (create, update, destroy, recycle, restore)
  - the member service/model under `admin/app/admin/system/` and `admin/app/common/model/Member.php`
  - the API registration and profile paths under `admin/app/api/` that check username/mobile/email uniqueness or create members
- Modify `SystemMemberLevel`, `SystemMemberGroup` and the member tag delete paths so they refuse while live members still reference the record.
- Modify the migration from Task 2 only if it is not yet committed. Otherwise the redundant plain indexes `username`, `mobile` and `email` on `fun_member` were already dropped there.
- Test: `admin/tests/MemberCoolingPeriodTest.php`

**Interfaces:**
- Produces `app\common\service\member\MemberIdentityGuard` (or a similarly named class next to the existing member services). It has:
  - `assertAvailable(array $identity, ?int $exceptId = null): void`. It throws `\InvalidArgumentException` with code 422 when a value is taken by a live member, or by a member deleted within `member_cooling_days`. The message says the account was cancelled and can be recovered within the period.
  - `normalize(array $identity): array`. It turns empty username/mobile/email into `null`.

- [ ] **Step 1: Write failing tests.**
  - `test_identity_of_member_deleted_within_cooling_period_cannot_be_reused`: admin create and API registration.
  - `test_identity_is_released_after_cooling_period`
  - `test_empty_mobile_and_email_are_stored_as_null_and_do_not_collide`
  - `test_deleting_member_soft_deletes_sessions_and_relations`
  - `test_restoring_member_restores_relations_but_not_sessions`
  - `test_restore_conflicting_with_new_member_returns_conflict_message`
  - `test_member_level_group_or_tag_in_use_cannot_be_deleted`
- [ ] **Step 2: Run.** Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run.** Run the new test plus the existing member tests (`grep -l Member admin/tests/*.php`), then the full suite.
- [ ] **Step 5: Commit** `feat: 会员注销冷静期、关联连带软删除与引用保护`

### Task 9: Docs and full verification

**Files:**
- Modify: the project docs that describe models and deletion. Find them with `grep -rl "软删除\|deleted_at" docs`. Document:
  - the `deleted_token` rule
  - that new tables must include both columns and that unique keys must end with `deleted_token`
  - that soft-deleted rows are kept; real deletion only from recycle bins
- Create: `docs/superpowers/plans/2026-11-10-backend-soft-delete-verification.md`

- [ ] **Step 1: Run** the full `cd admin && vendor/bin/phpunit`, `cd web && npx vitest run && npx vue-tsc --noEmit`. Compare against the baseline.
- [ ] **Step 2: On the dev database,** run the core migration through the project's normal upgrade or migrate path (no plugin lifecycle operations). Confirm 005 applied and that the admin login and menus still work, using HTTP checks against `127.0.0.1:8787`.
- [ ] **Step 3: Write the verification record.**
- [ ] **Step 4: Commit** with `docs: 软删除规则与验证记录`.
