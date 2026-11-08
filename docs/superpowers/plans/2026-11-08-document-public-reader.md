# Document Public Reader Implementation Plan

**Goal:** Expose the document plugin's published public revisions to anonymous readers at `/docs`.
**Architecture:** Plugin-owned Webman HTML controller and view, querying published revisions. Reuse the existing visibility column and activation guard. Source and installed plugin copies stay synchronized.
**Spec:** docs/superpowers/specs/2026-11-08-document-public-reader-design.md

## Task 1: Public query and visibility
- [x] Add failing service tests for visibility, status, immutable revisions, filters and private directory exclusion.
- [x] Implement PublicDocumentService, visibility input validation/DTO and safe defaults.
- [x] Run document service tests.

## Task 2: Reader rendering and routes
- [x] Add failing rendering/controller tests for escaped content, missing documents, anonymous GETs and forbidden mutations.
- [x] Add PublicContentRenderer, ReaderPage and `/docs` controller with responsive reading template.
- [x] Configure the development proxy for `/docs` and verify live anonymous requests.

## Task 3: Backend controls and validation
- [x] Add visibility choices and public links to the document backend, update localized copy and tests.
- [x] Synchronize installed plugin files, reload Webman, run relevant PHP/Vue tests and build.
- [x] Inspect the actual public page at desktop/mobile widths and remove preview artifacts.

## Verification

- PHPUnit: document, visibility, public rendering, activation guard, auth snapshot and reload coverage — 66 tests, 289 assertions passed.
- Vitest: public visibility controls, document workspace/store/content and localized copy — 39 tests passed.
- TypeScript check passed; Vite build passed with output isolated in `/tmp/funadmin-docs-public-build`.
- Anonymous live HTTP: `/docs` and literal keyword search 200; private/missing document 404; POST `/docs` 405; invalid query 422.
- Browser inspection: desktop reader and 390px mobile viewport render correctly; mobile client/scroll widths both 390px.
- Source plugin and installed application/frontend files match; local plugin upgraded to 1.1.0 and remains enabled. Existing visibility values preserved.
- Rendering scope: Mermaid blocks currently expose their readable source, as documented in the plugin README.
