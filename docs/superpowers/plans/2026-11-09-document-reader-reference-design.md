# Document Reader Reference Design Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make the public document reader resemble a polished help-center layout while preserving the existing backend contract.

**Architecture:** Keep the Blade reader as the server-rendered shell. Add only semantic header/search/toc markup, consolidate visual treatment in the existing CSS asset, and extend the existing vanilla JS search handling to address both desktop and mobile search fields.

**Tech Stack:** Webman Blade, semantic HTML, CSS media queries, vanilla JavaScript.

**Spec:** `docs/superpowers/specs/2026-11-09-document-reader-reference-design.md`

## Global Constraints

- Do not change document routes, database schema, publishing rules, or content sanitization.
- Keep `admin/plugins/document/app/document/...` and `admin/app/document/...` synchronized.
- Keep public pages usable without a session and preserve existing keyboard and dark-mode behavior.

## Review Focus

- Desktop header search and mobile search must submit the same `keyword` query.
- Expanded category state and current document highlighting must survive the visual changes.
- Long titles, code blocks and tables must remain horizontally usable.
- Right-hand TOC must disappear or become the mobile TOC without leaving inaccessible content.
- Existing icon paths and CSP-safe inline markup must remain valid.

### Task 1: Update public reader markup

**Files:**
- Modify: `admin/plugins/document/app/document/view/public/reader.blade.php`
- Mirror: `admin/app/document/view/public/reader.blade.php`

- [x] Add a desktop header search form with a keyboard hint and mark both search inputs with a shared `data-docs-search` attribute.
- [x] Add a visible title to the right-side table of contents while preserving existing outline links.
- [x] Keep all existing routes, escaping, and conditional rendering unchanged.

### Task 2: Apply the help-center visual system

**Files:**
- Modify: `admin/plugins/document/app/document/view/public/reader.css`
- Mirror: `admin/app/document/view/public/reader.css`

- [x] Add the reference visual layer after existing rules: centered header content, pill search, blue active tree item, white article surface, stronger typography rhythm, code/table/callout treatment, and responsive breakpoints.
- [x] Ensure dark theme and print rules remain coherent.

### Task 3: Synchronize search behavior

**Files:**
- Modify: `admin/plugins/document/app/document/view/public/reader.js`
- Mirror: `admin/app/document/view/public/reader.js`

- [x] Update search focus handling to select the visible/shared input while keeping `/` and `Ctrl/Cmd+K` shortcuts.
- [x] Preserve theme, category state, copy, TOC, and reading-progress behavior.

### Task 4: Verify the public reader

- [x] Run `admin/vendor/bin/phpunit -c admin/phpunit.xml admin/tests/Document/PublicReaderTest.php --display-deprecations`.
- [x] Run `npm --prefix web test -- tests/document/public-reader-ui.spec.ts tests/contracts/login-brand-spacing.spec.ts`.
- [x] Run `npm --prefix web run build:webman and verify both Blade trees and reader assets are synchronized.
- [x] Run `git diff --check` on the changed document reader files.


## Verification

The public reader CSS/JS/Blade mirrors match. PublicReaderTest passed with 8 tests and 34 assertions. Focused frontend tests passed with 6 tests across 2 files. `npm --prefix web run build:webman` exited 0; Vite emitted the existing large vendor chunk warning. Scoped diff check passed. No database or route changes were made.
