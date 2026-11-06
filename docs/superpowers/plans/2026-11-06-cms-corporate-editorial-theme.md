# CMS Corporate Editorial Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Inline execution; independent final review only.

**Goal:** Deliver an attractive unified corporate and editorial CMS public theme, with responsive SSR pages and no public npm build.
**Architecture:** Presentation service enriches existing render variables; HTML templates and shared CSS consume localized safe view data. Publish through existing lifecycle-aware resource/native publishers, preserve real site data.
**Tech Stack:** PHP/Webman, existing CMS tag interpreter, HTML/CSS/SVG, ordinary Vue/Element Plus script enhancement.
**Spec:** docs/superpowers/specs/2026-11-06-cms-corporate-editorial-theme-design.md

## Global Constraints

Preserve user/shared-index changes, real content, roles and domains. No public npm build or external CDN. No SQL/schema changes. Unknown locales fall back to English interface copy with per-language presentation overrides. Real published content only; demo content only in isolated fixtures.

## Review Focus

- Default detail must resolve layouts by actual model, while explicit custom templates remain authoritative.
- Missing/unsafe cover attachments never create broken or executable links; no extra table dependency on empty sites.
- Search/page2 keep real pagination and query; homepage corporate modules must not consume paginated article state accidentally.
- Empty sites, long titles and mobile layouts remain usable without JS.
- Runtime publication must preserve manual edits and DB content; fresh PHP loads source changes before rendering.

### Task 1: Safe localized presentation data

Files: new admin/plugins/cms/app/cms/service/ThemePresentation.php; Renderer.php, PublicSite.php; new admin/tests/Cms/PublicThemeTest.php.
Interfaces: ThemePresentation::build(site,language,page,content,category):array returns ui,home,listing,display with safe covers/localized labels; default layout selection based on model only when template=detail.
- [x] Write failing SSR tests for all five model layouts, English labels, empty homepage, search template, safe/missing cover and published-only homepage cards.
- [x] Run tests RED; implement presentation shaping, template resolution and search template dispatch.
- [x] Run PublicTheme/Public/Template tests GREEN, preserving custom paths, canonical and model fields.

### Task 2: Corporate/editorial HTML, CSS and progressive enhancement

Files: view/default/{index,category,detail,article,product,case,page,download,search}.html and partials; public assets cms.css/cms.js and local SVG illustrations.
Interfaces: consume Task1 ui/home/listing/display, current native navigation/languages and safe TrustedHtml body/model fields.
- [x] Implement shared header/footer/cards/fields, desktop/mobile hero, enterprise modules, editorial list/detail/search, per-model detail layouts, empty states.
- [x] Native details mobile navigation and native GET search remain functional without JS; no client-only content loading. Verify keyboard focus/reduced-motion styles.
- [x] Run PHP SSR regression GREEN and browser desktop/mobile/no-JS against isolated seeded fixtures; correct actual layout failures.

### Task 3: Review, publish and live verification

Files: CMS README, plugin manifest if required, verification report/screenshots.
- [x] Fresh independent read-only review; fix important findings with regressions.
- [x] Verify full CMS PHP suite; source/installed integrity and no public build dependency.
- [x] Publish source using normal publishers (or minor lifecycle upgrade if needed), reload workers, preserve legacy table snapshots and verify real empty ces homepage.
- [x] Start loopback-only nginx root-path preview on unprivileged8088; verify real homepage/assets/search/sitemap and unknown-host denial. State80-password limitation honestly.
- [x] Deliver actual URLs, screenshots and test results; no merge/commit/shared-index reset.

## Execution rulings

User selected combined enterprise/editorial use after modern warm-white/green proposal; implementation continues inline under existing autonomy. No extra approval flow for routine template/layout decisions. Current shared Rbac checkout contains required framework/CMS work; isolate edits by path and do not create a checkout that drops uncommitted dependencies.

### Added Task: Per-site configurable public settings (user steering, before final review/publish)

Files: SiteConfiguration.php; SiteService.php, Renderer.php/BuiltinTags.php/ThemePresentation.php; Sites controller; ConfigurationPanel.vue, SitePanel/api/types; SiteConfigurationTest.php and frontend configuration.spec.ts.
Interfaces: definitions typed and grouped under site.settings.configuration; site values merged by explicit language overrides; editor read/save endpoint with detail/edit/locale scope; template config/tag consumes resolved values. No SQL.
- [x] RED tests: two-site isolation, localized fallback and false/0/empty overrides, schema keys/types/select/unsafe URLs, bypass through general settings writers, scoped API rejection.
- [x] Implement typed validation/defaults/resolution and site-locked merge save; preserve unrelated JSON keys. Read-only configuration never leaks into minimal context.
- [x] Add grouped backend editor, custom field dialog and translated override toggles, permission gates and race guards; verify actual payloads and UI types.
- [x] Connect to frontend theme branding, hero/footer; SSR escapes config output and all custom fields remain template-accessible.

Verification: docs/superpowers/plans/2026-11-06-cms-corporate-editorial-theme-verification.md. Shared work preserved without commit/reset/merge/push.
