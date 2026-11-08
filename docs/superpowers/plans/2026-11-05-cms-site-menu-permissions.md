# CMS Site Menu Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Deliver independent CMS child pages with per-site role grants for every menu and button.
**Architecture:** Generic recursive Manifest menus and subtractive permission hooks remain in the host. CMS owns the operation catalog, site roles, scoped checks and UI. Existing global save codes remain compatibility ceilings for split create/edit actions; all enforcement intersects host rights with the current site's enabled roles.
**Tech Stack:** PHP 8.5/Webman/Illuminate/MySQL, Vue 3/Element Plus, Manifest v2.
**Spec:** docs/superpowers/specs/2026-11-05-cms-site-menu-permissions-design.md

## Global Constraints

Preserve user changes and existing 001_cms.sql. Ship forward-only 002_site_roles.sql, version 1.1.0. Never silently expand a real account's system grants. Use scoped role union intersected with host ceiling. Keep current site/content data and native recovery/file ownership. No category/language/own-content scope in this increment.

## Review Focus

- Updating IDs/status through save must not bypass split operation checks (Task 3).
- Assigning/modifying shared roles must not elevate actor or users outside manageable host scope (Task 2).
- Missing schema, inactive plugin/role, revoked membership and stale UI fail closed (Tasks 2/3/4).
- Nested menus survive source pruning and cannot hide unauthorized permission references (Task 1).
- Cross-site menu union is only discovery, never request authorization (Tasks 3/4).

### Task 1: Recursive menus and generic subtractive permission hook

Files: admin/app/common/plugin/sdk/{Manifest.php,schema/plugin.schema.json}; admin/app/admin/plugin/service/PluginInfrastructureService.php; admin/app/admin/authorization/service/AuthorizationService.php; admin/tests/Cms/MenuContractTest.php.
Interfaces: Manifest adminWeb.menu supports recursive menulist, directory paths and protected leaf route/permission checks; resource pruning collects all descendant hrefs. Host Hook::filter('admin.permission_codes', codes, ['admin_id'=>id]) output is intersected with original code list and never adds rights.
- [x] Write and observe RED tests for valid nested menu, invalid nested reference, depth, installation repeat/prune and non-escalating hook.
- [x] Implement recursive validation/registration compatibility and subtractive filtering.
- [x] Run menu and existing plugin/authorization tests GREEN.

### Task 2: Scoped role catalog, schema and grant service

Files in admin/plugins/cms: app/cms/service/{PermissionCatalog,SiteRoleSchema,SiteAuthorization,SiteRoleService}.php, database/migrations/002_site_roles.sql, Plugin.php; tests admin/tests/Cms/SiteRoleTest.php, MySqlSchemaTest.php.
Interfaces: PermissionCatalog::operations(), menus(), ceilings(op); SiteAuthorization::effective(site,actor), assert(site,actor,operation), acrossSites(actor,globalCodes); SiteRoleService::roles/members/saveRole/assign/remove with transaction, per-site lock, manageable-host-user and subset validation, audits. SiteRoleSchema::install/mysql(), bootstrap existing members without privilege expansion.
- [x] RED tests: A editor on site1/viewer on site2, B publisher site2 only, multi-role union, host ceiling, revocation, cross-site role IDs, overgrant/self elevation and audits.
- [x] Implement four new tables, seeding presets/legacy role, scope service and explicit grant operations; seed no real account assignments beyond compatibility migration.
- [x] Verify SQLite and isolated MySQL DDL/lock semantics GREEN.

### Task 3: API enforcement and permission discovery

Files: CMS controllers Base/Sites/Content/Categories/Menus/Models/Tags and new Access; SiteAccess/SiteService; app/cms/hook/PermissionFilter.php; plugin.json; tests SiteRoleApiTest.php and PermissionTest.php.
Interfaces: Base::site(operation) validates site and action; context returns minimal site/languages/effective operations; full settings needs site detail; resource creation/edit/status intent checked against stored records. Access endpoints list catalog/roles/members, save role/assign member/revoke. CMS permission hook removes unavailable CMS codes from host userInfo/menu/page discovery, leaves unrelated permissions untouched.
- [x] Write RED real session/API tests for context read, site mismatch, create/edit/status bypass, historical publication, inaccessible settings and role delegation.
- [x] Implement endpoint-specific operations and compatibility ceilings without trusting frontend flags.
- [x] Run CMS and host authorization tests GREEN.

### Task 4: Seven child pages and role/member UI

Files: CMS web/src/admin/plugins/cms/pages/{Index,Sites,Content,Categories,Menus,Tags,Models,Access}.vue; components/{CmsPage,AccessPanel,...}.vue; api.ts/types.ts; web/tests/cms.
Interfaces: shared CmsPage loads authorized sites by menu operation and context, persists site/locale, passes per-site can() to focused panels. Index legacy manage redirect selects first allowed child. AccessPanel shows roles + tree of operation checks and member role assignments, read-only host ceilings and effective preview.
- [x] RED frontend tests for child routing, context by operation, role assignment payload, button restrictions and site switching.
- [x] Replace Tab overview with seven explicit menu routes/components. Split action gating and prevent hidden bulk status edits; keep native PageWrapper/DataTableShell.
- [x] Run CMS Vitest, source-inclusive type check and production module compilation GREEN.

### Task 5: Upgrade, integration and final review

Files: plugin manifest/lifecycle/README, tests upgrade + scoped MySQL integration, docs verification.
- [x] Validate package/lifecycle in isolated roots and databases; existing grants migrate idempotently without expansion.
- [x] Run full CMS suite, relevant host tests, full suites once and record failures by name.
- [x] Fresh independent read-only review; fix important findings with regression tests.
- [x] Publish/upgrade current local development plugin through normal recovery-aware lifecycle after source tests; do not change existing real role assignments, ensure sidebar seven children and site context work in browser.

## Execution rulings

User approved design and requested development; proceed inline, no repeated permission handoff. Current Rbac checkout includes framework changes needed by CMS, so preserve it and isolate edits by path rather than snapshotting into another worktree. Shared index and unrelated changes must not be committed/reset. Exact per-button catalog codes can map old global save codes as compatibility ceilings; this preserves existing effective capabilities while site role checks remain split. Catalog migration/permission filtering must not grant new global codes to existing accounts.

Verification: docs/superpowers/plans/2026-11-05-cms-site-menu-permissions-verification.md. Work preserved uncommitted on Rbac; no shared-index reset, merge, or push.
