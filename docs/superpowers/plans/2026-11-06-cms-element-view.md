# CMS Element View Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement inline.

**Goal:** Declare the public Element component structure in CMS theme files, leaving common initialization and interactions in public JavaScript.

**Architecture:** PHP emits literal component declarations and complete server content. A generic, non-compiling adapter mounts declared components and safely wires shared state. A theme partial defines recursive menu node markup.

**Tech Stack:** PHP, existing CMS template interpreter, local Vue 3 / Element Plus global scripts.

**Spec:** docs/superpowers/specs/2026-11-06-cms-element-view-design.md

## Global Constraints

Preserve all four themes, SEO and real links. No builds, dependencies, database changes or plugin activation. Preserve unrelated workspace changes and work inline. Synchronize only touched source/runtime files. No commit or push.

## Review Focus

- CMS expressions and component-like markup inside rich content remain inert.
- Nested menu URLs, new-window links and keyboard activation survive.
- Native fallbacks remain useful when libraries fail or components throw.
- Mobile drawer and language dropdown named slots work with production globals.
- Search query and pagination paths retain language prefixes and escaping.

## Tasks

- [x] Task 1: Change real-component fixtures to explicit declarations; add no-CSS-conversion, named-slot, static-content, anchor-pagination and PHP menu/template coverage. Observe expected failures.
- [x] Task 2: Implement generic adapter, template navigation rendering and all four themes; sync touched runtime files. Run focused suites and resolve regressions.
- [x] Task 3: Run complete CMS suites, check parity, inspect production previews, update documentation, obtain scoped review and fix important findings.

## Verification commands

`cd web && npx vitest run tests/cms/elementPublicUi.spec.ts --reporter=json --outputFile=/tmp/cms-element-view-focused.json`

`cd admin && vendor/bin/phpunit tests/Cms/EnterpriseThemesTest.php --no-progress`

`cd web && npx vitest run tests/cms --reporter=json --outputFile=/tmp/cms-element-view-final.json`

`cd admin && vendor/bin/phpunit tests/Cms --no-progress`

## Progress

Design reflects the user's explicit confirmation of the view/public split. Initial baseline from the immediately preceding work: frontend 153 passing; PHP 156 tests, 1114 assertions, 5 skips.

Task 1 RED: explicit-declaration runtime tests failed 22 cases against the old CSS-driven converter; the PHP component-template test failed because `<el-container>` was absent.

Task 2 GREEN: focused runtime/native fallback suites passed 42/42; enterprise themes passed 7 tests, 329 assertions. The carousel uses one declared track with native scrolling fallback, retaining eight actual slides within the existing render budget.

Scoped independent final review found nested landmarks and rejection of the existing maximum menu depth. Both were reproduced before fixes: mounted main count was 2 instead of 1, and an accepted 17-level menu returned HTTP 503. Direct layout component tags with SSR roles remove nested landmarks; recursion now skips empty children at the deepest permitted leaf. Both regressions are included in the final suites.

Final GREEN: full frontend CMS suite 161/161; PHP CMS suite 158 tests, 1170 assertions, 5 environment-dependent skips, no failures. Source/runtime parity verified for 103 pairs. Read-only preview regeneration produced 117 pages and confirmed site theme selections were unchanged. See `2026-11-06-cms-element-view-verification.md` for the browser and runtime boundaries.
