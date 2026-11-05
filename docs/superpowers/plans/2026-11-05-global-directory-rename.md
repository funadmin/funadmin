# Global directory rename implementation plan

> Execute inline with superpowers:executing-plans.

**Goal:** Rename the frontend project directory to `web` and the Webman backend project directory to `admin`, including the plugin path protocol and all repository references.

**Architecture:** Physical paths use `web/` and `admin/`. The plugin manifest source contract becomes `web/src/admin/modules/{code}` and backend project paths become `admin/...`. Semantic keys such as `adminWeb` and `admin_web_path` remain because they describe the admin surface, not a directory.

## Tasks

- [ ] Rename the two root directories while preserving their contents.
- [ ] Rewrite physical path references in source, config, manifests, tests, docs, IDE metadata and scripts; update package build paths and defaults.
- [ ] Update path validators, plugin scaffolding, publication, CRUD artifact mapping and test fixtures to the new contract.
- [ ] Run stale-reference scans, syntax checks, focused path/plugin tests, frontend typecheck/build and backend smoke checks.

## Constraints

- Preserve all existing user changes; do not reset or clean unrelated files.
- Do not rename semantic `adminWeb` or `admin_web_path` identifiers.
- Do not modify production database data.
- Old physical path references must be absent outside historical notes that explicitly document migration compatibility.
