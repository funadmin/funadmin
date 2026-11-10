# Core Layer Optimizations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Fix the top-10 findings of the core audit: authorization performance, N+1 queries, cache invalidation, CORS, two silent bugs, the plugin publication/migration pain points, and test-suite speed.

**Architecture:** Each task maps to findings in `.superpowers/core-optimization-audit-2026-11-10.md`. That audit has the file:line evidence and proposed fixes, and serves as the spec. Measurements were taken with the scripts in `/tmp/fa-audit/`. Re-run them, or an equivalent `DB::listen` query counter, to prove each perf task.

**Tech Stack:** webman on PHP 8.5, illuminate/database, php-casbin, PHPUnit 11 (sqlite by default).

**Spec:** `.superpowers/core-optimization-audit-2026-11-10.md` (top-10 table plus sections A–H).

## Global Constraints

- Behaviour must not change except where a finding is a bug or a security fix. The permission decisions for every admin must stay identical.
- Unreleased project: internal APIs may change, but no compat shims are allowed.
- Every perf task adds a test that pins the query count or call count, so the regression can't return.
- Backend: `cd admin && vendor/bin/phpunit`, full suite green with 0 failures. Frontend: run `cd web && npx vitest run` only if a task touches API shapes.
- Implementers never run git, never touch the real MySQL DB, never touch `admin/.env`, and never dispatch subagents.
- Write with `fill()->save()`, never `save([...])`.

## Review Focus

1. Authorization parity: for super admin, a multi-role admin, a department-scoped admin and a disabled role, the permission codes and menu trees must equal the old output.
2. Cache staleness: after any role, permission, policy, menu or admin-role write, the next request sees the change. This applies within one worker and across workers.
3. Long-running worker safety: no per-request state stored in statics without a reset; no unbounded growth.
4. CORS: admin routes never echo arbitrary origins with credentials; legitimate configured origins still work.
5. Plugin commands are safe by default: rebaseline/adopt refuse when the live state does not match what they would record.

---

### Task 1: Permission codes in one pass (top 1)
Files: `admin/app/admin/authorization/service/CasbinService.php` (`permissionCodesForAdmin`) and its callers; a new test.
- [ ] Write a parity test: for each fixture admin (super, multi-role, scoped, a role with a disabled permission), record the old `permissionCodesForAdmin` output and assert the new code returns the same set.
- [ ] Add a counter test: one call evaluates the implicit permissions once and does not call `enforce()` per permission.
- [ ] Implement with `getImplicitPermissionsForUser()` once, matched against active permission rows.
- [ ] Measure before and after; record the numbers in the report.

### Task 2: N+1 in the admin list and the menus (top 3, 4)
Files: `admin/app/admin/authorization/controller/SystemAdmin.php` (`adminData`) and `AuthorizationService.php` (`menus`/`menuData`/`permissionCode`).
- [ ] Add query-count tests: the admin list with 20 rows uses a constant number of queries, and `/admin/auth/menus` does too. Assert the counts.
- [ ] Batch roles with one `casbin_rule whereIn`, departments with one `whereIn`, and permission id→code with one map load.
- [ ] Add output-parity tests against the old responses.

### Task 3: Targeted cache invalidation (top 7)
Files: every `Cache::clear()` call site listed in the audit, plus `MemberAccountService.php:136` throttling.
- [ ] Add a test showing that an admin role or permission write no longer wipes the member login throttle counter.
- [ ] Replace `Cache::clear()` with deletion of the specific keys or tags actually affected. Where nothing is cached, drop the call. Keep the throttle counters safe, either with dedicated keys or by moving them to the `login_attempts` table, whichever the audit recommends as smaller.

### Task 4: CORS hardening (top 8)
Files: `admin/config/plugin/webman/cors/*` and the middleware wiring.
- [ ] Add tests: an arbitrary Origin on an admin route gets no `Access-Control-Allow-Origin` and no credentials header. A configured origin on `/api/*` gets correct CORS headers. Preflight works.
- [ ] Implement an env allow-list (for example `CORS_ALLOWED_ORIGINS`), apply it to `/api/*` only, and document it in `.env.example` and in docs.

### Task 5: Two silent bugs (top 9)
- [ ] `UpgradeService::claimStaleTask`: compute the lease expiry once. Add a test that freezes or advances time across a second boundary.
- [ ] `Ai.php:884`: replace `whereOr` with `orWhere`. Add a `toSql()`/behaviour test proving expired cleanup leases are reclaimed. Grep the whole core for other ThinkPHP-only builder methods (`whereOr`, `field(`, `find(` on builders, `value` misuse, and so on) and fix each one with a test.

### Task 6: Request and worker authorization cache (top 2)
Files: the CasbinService enforcer construction, `DatabaseAdapter.php:56` (the `hasTable`), the userInfo resolution, and the session-resolve dedup (audit A).
- [ ] Add a test that one request builds the enforcer at most once.
- [ ] Add a test that bumping the authz version through any policy, role, permission or admin-role write makes the next request rebuild.
- [ ] Implement request-scoped reuse plus a per-worker cache keyed by an authz version stored in the shared cache or the DB. Every write path bumps the version. Drop `hasTable` from the hot path.
- [ ] Measure queries per non-super admin request before and after.

### Task 7: Plugin publication rebaseline and sync (top 5)
Files: `PluginAppPublicationService::assertCurrentUnmodified`, and new console commands under the plugin command namespace.
- [ ] Accept installed files that already equal the incoming source as not modified. Add a test.
- [ ] Add `plugin:rebaseline <code> [--from=installed|source]`. It rewrites the plugin's resource registry rows (path, sha256, tree hash) from the chosen side. With `--from=installed`, it refuses unless the installed files equal the plugin source, or unless `--force` is given. Add tests.
- [ ] Add a dev-only `plugin:sync <code>` that copies source to installed and rebaselines. It refuses outside dev or debug. Add tests.
- [ ] Update docs.

### Task 8: Plugin DDL in hooks plus migrate --adopt (top 6)
- [ ] `plugin:validate`, or the manifest validator, rejects schema-changing calls inside lifecycle hooks, using the detection rule the audit proposes. Add a test.
- [ ] Add `plugin:migrate <code> --adopt=<version>`. For each statement in that migration, it verifies that the effect already exists in the live schema (columns, indexes, tables), then records the migration with its checksum. It refuses if any effect is missing. Add tests on sqlite.
- [ ] Move the CMS `Plugin.php` enable-time schema installs to proper migrations, or document why they are idempotent no-ops. Coordinate with the CMS owner via the controller, and report instead of editing if this is out of scope.

### Task 9: Test-suite speed and a single baseline source (top 10)
- [ ] Build the schema once into a template sqlite file, and have tests copy it instead of re-running migrations. Reduce `RunTestsInSeparateProcesses` usage where it is not needed for isolation.
- [ ] Add a parity test between the SQL and PHP baselines, and close the 28-table gap, or generate the test schema from the SQL baseline. Choose per the audit.
- [ ] Report the full-suite wall time before and after.
