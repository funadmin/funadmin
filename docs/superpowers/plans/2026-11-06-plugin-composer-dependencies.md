# Plugin Composer Dependencies Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement bundled vendor loading and host requirements, with confirmable, remembered host/plugin conflicts.
**Architecture:** Pure SDK services read package metadata and detect/validate dependencies. Lifecycle checks run before mutation; a scoped acceptance context spans a package operation. Frontend API wraps all seven mutation endpoints in one confirmation helper.
**Tech Stack:** PHP 8.5, Webman, PHPUnit; Vue 3, Element Plus, Vitest.
**Spec:** docs/superpowers/specs/2026-11-06-plugin-composer-dependencies-design.md

## Global Constraints
- No Composer commands or scoper executed by the server; no new Composer dependency.
- Host-only package metadata, never aggregate InstalledVersions; append plugin class loaders.
- No git commit; preserve existing working-tree changes.
- Persist confirmation fingerprint; changed conflicts require reconfirmation.
- Cleanup operations must not require Composer dependencies.

## Review Focus
- A rejected confirmation must leave neither last_error nor failed history nor a new installation record.
- Worker restart and hook-only paths must load vendor without calling PluginEntryFactory.
- Loaded plugin vendor must not satisfy host requirements.
- Changed conflict sets and same-request nested validation must not bypass/repeat acknowledgement incorrectly.
- Cancellation must reject the frontend operation, without success UI or generic error toast.

### Task 1: Metadata, dependency checks and conflicts
Files: new HostPackageRepository.php, ComposerConflictDetector.php, ComposerConflictException.php in SDK; modify Manifest.php, DependencyValidator.php, schema/plugin.schema.json; tests/PluginComposerTest.php.
Interfaces: HostPackageRepository::packages(): array; ComposerConflictDetector::detect(string $directory, array $otherPluginDirectories=[]): array; ::fingerprint(array): string; DependencyValidator::assertComposerSatisfied(Manifest): void.
- [x] Write PHPUnit tests for host-only metadata, Composer 1/2 metadata, stable/0.x/dev conflicts, fingerprint sorting, missing/version mismatched requirements and invalid constraints.
- [x] Run `cd admin && vendor/bin/phpunit tests/PluginComposerTest.php` and confirm red.
- [x] Implement services and schema/accessor; no package execution during inspection.
- [x] Run tests, confirm green.

### Task 2: Runtime autoloading
Files: new SDK PluginVendorAutoloader.php; modify SDK Service.php, PluginEntryFactory.php, hook/HookRuntime.php, middleware/PluginApplicationGuard.php; extend tests/PluginComposerTest.php and PluginHookRuntimeTest.php.
Interface: PluginVendorAutoloader::ensure(string $directory): void, ::loadEnabled(PluginActivationReader, string $pluginsPath): void.
- [x] Test idempotent appended loaders, no vendor, host precedence and hook-only runtime.
- [x] Run tests to confirm red, implement loader and call sites, rerun green.

### Task 3: Lifecycle, acknowledgement and HTTP
Files: PluginService.php, PluginPackagePipeline.php, SystemPlugin.php; new migrations PHP 032_plugin_composer_conflict_ack.php and core SQL 023_plugin_composer_conflict_ack.sql; command/PluginValidate.php; tests in Console and PluginCenter.
Interfaces: PluginService::withComposerConflictAcceptance(bool, callable): mixed; ::assertComposerDependencies(Manifest): array; pipeline optional staged preflight callable, before coordinator changes state; confirmation exception has public conflicts.
- [x] Test package conflict preflight occurs before coordinator/deploy, cleans staging without failed history; enable refusal is precondition; remember fingerprints; missing B blocks only install/update/enable/redeploy.
- [x] Run tests red; implement scoped flag, preflight, deferred acknowledgement on successful operation, audit warnings, migration and controller 409 payload.
- [x] Run focused lifecycle tests green; CLI B errors and A warnings.

### Task 4: Frontend confirmation and docs
Files: new shared API helper and tests; web/src/admin/api/system/plugin.ts; shared/utils/http/index.ts; docs/plugins.md.
- [x] Vitest helper tests: ordinary success/error, structured conflict confirm/retry, cancel reject, no repeat loop.
- [x] Run focused Vitest red; implement safe VNode conflict dialog and API wrappers; suppress only structured conflict generic toasts.
- [x] Run Vitest and vue-tsc; document dependency syntax, packaging, limitations, migration/reload requirement.

### Task 5: Verification
- [x] Run PHP lint on touched files, focused PHPUnit including package/lifecycle/runtime tests, frontend helper tests and type-check.
- [x] Review diff for unrelated modifications and requirement coverage. Report exact outcomes and outstanding environment limitations.

## Execution notes

- Controller execute scopes confirmation around existing service/pipeline calls, avoiding redundant parameter threading in marketplace methods.
- Full-suite failures are listed in `.superpowers/sdd/2026-11-06-plugin-composer-dependencies/verification.md`; focused feature tests are green.
- Database migration was tested on isolated in-memory fixtures; no migration or Composer command was run against the deployed application database. Docker-based MySQL runner is unavailable on this host.

- Final review fixes: avoid global publication lock on a no-op precondition rollback; activate-aware annotation discovery before reflecting vendor-based controllers. New PluginComposerRouteTest verifies disabled/enabled/untrusted controller cases plus a genuinely isolated startup subprocess and shared framework dispatch.
