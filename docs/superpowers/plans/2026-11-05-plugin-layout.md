# Unified Plugin Layout Implementation Plan

> Historical plan: backend paths were superseded by `2026-11-05-plugin-sibling-directories.md`; current guide is `docs/plugins.md`.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Use identical project-relative paths in plugin source packages and published applications, with no legacy layout compatibility.

**Architecture:** Keep Manifest v2 and current lifecycle and permissions. Update scaffold/CRUD/archive, strict manifest layout validation, publishers and ownership detection together.

**Tech Stack:** PHP 8.5, Webman, Eloquent, PHPUnit, Vue/Vite.

**Spec:** `docs/superpowers/specs/2026-11-05-plugin-layout-design.md`

## Global Constraints

- Preserve existing user work; no production database operations or automatic deletion of old installed resources.
- Canonical backend `app/admin/plugin/business/{code}`; frontend `admin-web/src/admin/modules/{code}`; public source `public/plugin-assets/{code}/public`.
- Old packages and resource paths must be rejected, never translated or silently discarded.

## Review Focus

- Empty directories survive packaging and stage validation.
- CRUD and business-module target discovery use the canonical layout.
- Extra admin trees, another plugin's code, and old source paths are rejected.
- Old resource registries are rejected before any file mutation.
- Canonical publish, rollback, enable/disable and uninstall remain functional.

### Task 1: Canonical package generation and validation

**Files:** `PluginScaffolder.php`, `Manifest.php`, `PluginArchiveService.php`, `PluginCrudTarget.php`, `DevPluginService.php`, `BusinessTargetService.php` and generation tests.

**Interfaces:** Plugin manifest `adminWeb.source` is `admin-web/src/admin/modules/{code}`; public source is `public/plugin-assets/{code}/public`; admin namespace matches its file path under `app/admin/plugin/business/{code}`.

- [x] Write failing scaffold and old-layout rejection tests; run and observe failures.
- [x] Implement canonical paths in generation, strict validation and archive empty directories.
- [x] Update CRUD and target discovery; run plugin generation/package tests.

### Task 2: Publication and removal of compatibility

**Files:** `PluginAppPublicationService.php`, `PluginResourcePublisher.php`, `PluginModificationDetector.php`, `CheckAdminApiRole.php`, publication/lifecycle tests.

**Interfaces:** Only `admin-business` native resources and `admin-web:src/admin/modules` frontend resources are accepted.

- [x] Replace legacy migration test with rejection-before-mutation and canonical rollback/conflict tests; observe failure on old compatibility code.
- [x] Publish from canonical paths and remove all old directory/identifier/namespace branches.
- [x] Run publication and lifecycle HTTP tests.

### Task 3: Documentation and verification

**Files:** `docs/plugins.md`, `webman-admin/README.md`, prior design supersession notices and verification record.

- [x] Document one canonical directory tree and fixed publication destinations.
- [x] Run scoped plugin/development tests, PHP lint and full backend/frontend suites; report existing baseline failures separately.
- [x] Request independent read-only review and fix material findings with regression tests.

Execution continues natively in the existing user-authorized checkout. Do not commit unrelated work or re-ask approval for the explicit directory-unification request.
