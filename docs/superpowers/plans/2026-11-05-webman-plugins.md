# Webman Plugins Implementation Plan

> Historical plan: directory decisions were superseded by `2026-11-05-plugin-layout.md`. Current layout: `docs/plugins.md`.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Make generated plugins run through the current Webman backend and Vue admin application.

**Architecture:** Keep Manifest v2 and lifecycle/publication services. Align middleware, generated namespaces, routes, permissions and frontend publication with current module ownership.

**Tech Stack:** PHP 8.5, Webman, Eloquent, PHPUnit, Vue 3, Vue Router, Vite, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-05-webman-plugins-design.md`

## Global Constraints

- Preserve user changes and existing lifecycle recovery and resource ownership guarantees.
- No production database migration or new dependencies.
- Backend API: `/admin/plugin/{code}/…`; backend module: `app/admin/plugin/business/{code}/{layer}`.
- Frontend module: sibling `web/src/admin/plugins/{code}`; new sources require frontend rebuild.

## Review Focus

- Disabled, busy or corrupt activation must block published HTTP routes before controller execution.
- Ordinary administrators receive exactly the declared plugin permission and retain CSRF checks.
- Source publication refuses unrelated files and remains recoverable after interruption.
- Plugin pages use the admin route prefix and are removed after disable/logout.
- Existing built-in market/example routes remain usable with their existing permission registrations.

### Task 1: Runtime HTTP boundary

**Files:** `admin/config/middleware.php`, `app/common/middleware/PluginApplicationGuard.php`, `app/admin/plugin/middleware/CheckPluginPermission.php`, `app/common/plugin/sdk/PluginRoute.php`, tests under `admin/tests`.

**Interfaces:** Webman `process(Request $request, callable $next): Response`; `PluginRoute::adminGroup` retains its public signature.

- [x] Add failing tests for global guard registration, enabled/disabled/busy/corrupt snapshots and real middleware execution.
- [x] Run PHPUnit for these tests and observe failures.
- [x] Implement the native guard and permission metadata path while retaining built-in plugin behavior.
- [x] Run runtime and plugin HTTP tests; expect PASS.

### Task 2: Generated and published backend code

**Files:** `PluginScaffolder.php`, `Manifest.php`, `PluginTemplateContext.php`, `ProductionTemplateContext.php`, `PluginAppPublicationService.php`, `PluginExecutionGate.php`, example business files and related tests.

**Interfaces:** source `app/admin/{layer}` publishes to `app/admin/plugin/business/{code}/{layer}`; class and route prefixes match the spec.

- [x] Add/adjust failing generation and publication tests, including real annotated route dispatch and ordinary-user authorization.
- [x] Run targeted tests; observe old path/prefix failures.
- [x] Align generation, validation, publication and execution ownership; migrate the example to native code patterns.
- [x] Run generation, publication, lifecycle and HTTP tests; expect PASS.

### Task 3: Frontend publication and module routing

**Files:** `PluginInfrastructureService.php`, `PluginResourcePublisher.php`, `config/plugins.php`, `web/src/admin/router/pluginStartup.ts`, `pluginModules.ts`, component loader and corresponding tests.

**Interfaces:** enabled-module DTO stays compatible; published components resolve under `src/admin/plugins` and routes use `/admin`.

- [x] Add failing Webman startup/route synchronization and frontend publication tests.
- [x] Run targeted PHPUnit/Vitest tests; observe failures.
- [x] Align configurable frontend root, module destination and route mounting/removal.
- [x] Run plugin frontend tests, type check and Webman build; expect PASS or record pre-existing failures precisely.

### Task 4: Integration and review

**Files:** integration tests and `admin/README.md`.

**Interfaces:** generated plugin completes install → enable → HTTP access → disable → upgrade → uninstall, with resource ownership preserved.

- [x] Add missing end-to-end regression coverage and run it before the final relevant fix.
- [x] Run the full backend/frontend suites and distinguish baseline failures.
- [x] Document commands, deployment/rebuild behavior and legacy plugin migration.
- [x] Review the complete diff and address material findings; report exact verification results.

Execution: native in this session, authorized by the user's instruction to make the agreed adjustments. Changes remain reviewable in the current checkout; preserve the existing staged user changes and do not commit them.
