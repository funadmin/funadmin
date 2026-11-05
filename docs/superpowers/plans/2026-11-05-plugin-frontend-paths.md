# Plugin Frontend Paths Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Align plugin navigation, API links and development assets with current root deployment.

**Architecture:** Preserve existing Vite deployment decisions. Share admin API URL joining, keep public endpoints independent of asset base, and cover generated router hrefs and proxy boundaries.

**Tech Stack:** Vue Router, Vite, TypeScript, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-05-plugin-frontend-paths-design.md`

## Global Constraints

- Keep root deployment `/`, current hash routes and public build outputs.
- Preserve other-session changes and plugin source directory layout.
- Do not clear backend public files, migrate databases or restart services.

## Review Focus

- Absolute, relative and trailing-slash API bases produce correct URLs.
- Asset deployment bases never appear in API requests.
- Plugin asset proxies respect URL segment boundaries.
- Plugin route hrefs remain correct under root and subdirectory history bases.
- Existing public/member Hook authentication behavior remains unchanged.

### Task 1: API and Hook endpoint paths

**Files:** `web/src/shared/utils/apiUrl.ts`, `web/src/admin/api/system/plugin.ts`, `web/src/shared/plugins/hooks/startup.ts` and corresponding tests.

- [x] Add failing API-link/configuration tests; observe failures.
- [x] Implement shared API joining for downloads; retain the concurrently fixed Hook prefix implementation.
- [x] Run API URL and Hook startup tests.

### Task 2: Development assets and route hrefs

**Files:** `web/vite.config.ts`, configuration tests and `pluginModules.spec.ts`.

- [x] Add failing proxy and root/subdirectory href tests.
- [x] Add the missing plugin-assets proxy without widening other routes.
- [x] Verify plugin loader, Hook and form component tests, types and build.

### Task 3: Documentation and final verification

**Files:** `docs/plugins.md`, relevant README links, verification record.

- [x] Document current browser/API/asset addresses separately from filesystem directories.
- [x] Run full frontend tests and record baseline failures separately.
- [x] Perform independent review and address material findings.

Execution continues natively in the current authorized checkout; no extra confirmation is required for these fixes.
