# CMS Enterprise Theme Pack Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans inline; one independent final review. Do not commit or push shared work without a user request.

**Goal:** Add three distinct complete enterprise website themes and expose friendly choices in site settings.
**Architecture:** BuiltinThemes defines known presentation-enabled themes and assets; Renderer and PublicSite share the current presentation flow for those themes. Theme folders each contain full templates with individual homepage/layout assets. Existing custom theme semantics stay intact.
**Tech Stack:** PHP/Webman SSR, CMS template tags, HTML/CSS/local SVG, existing Vue admin controls.
**Spec:** docs/superpowers/specs/2026-11-06-cms-enterprise-theme-pack-design.md

## Global Constraints

Preserve current site themes, data, menus, permissions, user edits and untracked dependencies in the shared workspace. No third-party downloads or dependencies; no template traversal changes. Update source and runtime pairs only for task files. User delegates routine design choices; proceed with the three proposed styles unless steered.

## Review Focus

- Non-default bundled themes must receive home, UI and list presentation data.
- Explicit custom detail templates and third-party themes must retain behavior.
- Empty sites, unpublished records and another language/site must not leak invented content.
- Nested navigation and wide content must fit desktop/mobile layouts.
- Theme choices must only list installed directories; current site selection must stay unchanged.

## Task 1: Theme contracts and presentation integration

Files: add app/cms/service/BuiltinThemes.php; modify TemplateCatalog.php, Renderer.php and PublicSite.php in plugin/runtime; tests/Cms/EnterpriseThemesTest.php.
- [x] Write failing SSR tests for friendly installed theme options, three homepages and per-model detail pages, scoped data, SEO/search/pagination, navigation/config modules and explicit templates.
- [x] Observe RED; implement BuiltinThemes::definitions()/get() and presentation integration for known built-in themes.
- [x] Verify targeted tests as templates become available in Task 2; preserve existing custom-theme tests.

## Task 2: Three enterprise website designs

Files: app/cms/view/{corporate,industrial,studio}/ complete templates in source/runtime; public/.../themes/{corporate,industrial,studio}.css and local hero SVGs; shared enterprise.css if appropriate.
- [x] Create independent hero and homepage structures per design; use honest site configuration and real published records.
- [x] Adapt full inner-page templates and local styles, retain navigation/search and optional home modules.
- [x] Verify desktop/mobile rendering, readable empty states and long content; run complete CMS suites and admin build/type checks.

## Task 3: Publish installed choices and verify visually

Files: CMS README, verification note; task-specific preview artifact under ignored runtime.
- [x] Synchronize only new/changed files to installed runtime, reload backend, inspect live site-options route.
- [x] Render current site data into temporary loopback previews without updating its DB theme; browse desktop/narrow layouts.
- [x] Independent narrow final review; fix important findings, record checks and deliver usage with preview artifacts.

## Execution ledger

Working in the current shared checkout preserves required untracked CMS and runtime dependencies. Separate file paths and source/runtime checks provide task isolation; no checkout, reset, commit or push. No implementation delegates; final read-only review only.

Task 1: EnterpriseThemesTest observed RED (missing choices/503) then GREEN; BuiltinThemes and rendering/catalog integration implemented.
Task 2: Three complete themes (22 templates each), four CSS files and three SVGs installed; scoped pages/modules tested; desktop and narrow-layout browser checks completed. Industrial mobile menu contrast fixed during visual QA.
Task 3: 117 readonly preview pages generated under ignored runtime, loopback preview on 8795. Existing DB site themes were not changed. Independent reviewer found one important sticky-header/long-menu issue; bounded viewport scrolling added to source/runtime CSS. No unrelated shared work committed or reset.
