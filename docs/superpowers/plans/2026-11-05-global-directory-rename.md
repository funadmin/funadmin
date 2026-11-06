# Global directory rename implementation plan

> Execute inline with superpowers:executing-plans.

**Goal:** Rename the frontend project directory to `web` and the Webman backend project directory to `admin`, including the plugin path protocol and all repository references.

**Architecture:** Physical paths use `web/` and `admin/`. The plugin manifest source contract becomes `web/src/admin/modules/{code}` and backend project paths become `admin/...`. Semantic keys such as `adminWeb` and `admin_web_path` remain because they describe the admin surface, not a directory.

## Tasks

- [x] Rename the two root directories while preserving their contents.
- [x] Rewrite physical path references in source, config, manifests, tests, docs, IDE metadata and scripts; update package build paths and defaults.
- [x] Update path validators, plugin scaffolding, publication, CRUD artifact mapping and test fixtures to the new contract.
- [x] Run stale-reference scans, syntax checks, focused path/plugin tests, frontend typecheck/build and backend smoke checks.

## Verification

- `web`: `npm run type-check` passed; `npm run build:webman` passed and wrote to `admin/public/`.
- `admin`: PHP syntax scan passed; plugin/runtime/path focused suite passed (24 tests, 89 assertions; path/CRUD suite 40 tests, 185 assertions).
- Webman reload passed; `/health` and `/` returned HTTP 200.
- Old physical directory references are absent from source/config/docs scans. Semantic `adminWeb` and `admin_web_path` remain intentionally.
- One selected frontend contract group still has baseline path assumptions unrelated to this rename (`web/src/api` vs the current `web/src/admin/api`, and repository-root fixtures); see the command output for details.

## Constraints

- Preserve all existing user changes; do not reset or clean unrelated files.
- Do not rename semantic `adminWeb` or `admin_web_path` identifiers.
- Do not modify production database data.
- Old physical path references must be absent outside historical notes that explicitly document migration compatibility.
