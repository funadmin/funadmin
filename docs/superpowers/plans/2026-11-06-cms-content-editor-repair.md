# CMS Content Editor Repair Implementation Plan

> Execute inline using superpowers:executing-plans with red/green regression checks.

**Goal:** Repair file upload, rich text body editing and detail template selection.
**Architecture:** Reuse host upload validation/storage/hooks behind CMS site authorization, add theme template lookup, use a shared Tiptap editor and keep source/published copies consistent.
**Tech Stack:** PHP, Webman, Vue, Tiptap, Vitest, PHPUnit.
**Spec:** docs/superpowers/specs/2026-11-06-cms-content-editor-repair-design.md

## Constraints and review focus

Preserve concurrent changes. No database migration, commit, push or reload. Check unauthorized upload, upload failures, selected IDs after multi-upload, late results after switching content, HTML persistence, and template containment/symlinks.

### Task 1: CMS upload and template references
- [x] Add failing endpoint/template tests and run.
- [x] Include string attachment ID in host upload result, authorize CMS adapter before delegating, add real theme template options to lookups.
- [x] Verify endpoint authorization, missing files and template traversal rejection with PHPUnit.

### Task 2: Content form editing
- [x] Add failing form/upload/editor tests and run.
- [x] Add upload controls, previews, errors and ID binding; integrate shared Tiptap editor and template select.
- [x] Sync only changed files to installed copies; verify source/published parity.

### Task 3: Verification
- [x] Run CMS backend/frontend suites, type check, production compilation and diff checks.
- [x] Review changed boundaries and record remaining baseline failures.

独立只读审查发现旧表格/视频 HTML 会被 StarterKit 简化，已补充无损比较与源码保护，并通过 RED→GREEN 回归验证。新建空正文仍默认使用富文本。详细结果见同目录 `2026-11-06-cms-content-editor-repair-verification.md`。
