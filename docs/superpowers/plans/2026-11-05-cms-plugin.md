# CMS Plugin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Deliver the approved CMS as an installable Manifest v2 plugin, with SSR templates and both custom tag mechanisms.
**Architecture:** Plugin source mirrors published project paths. Small shared application services own site scope, immutable revisions, route publication, and template rendering; admin controllers delegate to them. Public routes use the existing annotation and activation mechanisms.
**Tech Stack:** PHP 8.5, Webman, Illuminate DB, MySQL SQL migration, isolated SQLite tests, Vue 3 and Element Plus (admin build, ordinary browser distribution on public pages).
**Spec:** docs/superpowers/specs/2026-11-05-cms-plugin-design.md

## Global Constraints

- Preserve all pre-existing working tree changes; no production migrations, DNS changes, certificate requests or service reloads.
- Source at admin/plugins/cms, matching docs/plugins.md. Keep global Raw view configuration unchanged.
- Explicit site and locale scope; published revisions only on public requests; backend-managed templates never execute PHP/SQL.
- Public front end has no npm build; use pinned local Vue/Element Plus distributions.
- IDs serialize as strings. Uninstall retains CMS data.

## Review Focus

- Encoded paths, trailing slashes, historical routes and pagination must not bypass route ownership (Tasks 2/4).
- Concurrent publication, stale versions and cross-site IDs must fail atomically (Tasks 2/3).
- Template recursion, attribute overrides and escaped/raw output must preserve scope and limits (Task 1).
- Domain aliases, inactive languages and draft-only translations must not generate indexable duplicate content (Task 4).
- Admin module must invoke real endpoints, distinguish draft/publish, and retain selection context (Task 5).

## File Map

All production paths below are relative to admin/plugins/cms. Tests live at admin/tests/Cms. No package files are implicitly installed into the running application.

### Task 1: Template language and extensible tag registry

**Files:** app/cms/template/{RenderContext,TrustedHtml,TagHandler,TagRegistry,TemplateEngine}.php; tests/Cms/TemplateTest.php; tests/Cms/bootstrap.php.
**Interfaces:** TagHandler exposes schema(), configurable(), resolve(array,RenderContext): mixed. Registry register(name,handler), resolve(name,params,context). Engine render(source,variables,context): string and renderFile(template,variables,context): string. Context carries immutable siteId/locale and shared request budget.
- [x] Write behavior tests for escaped variables, collections, nested conditions, include/literal, PHP providers, alias fragments, unknown parameters, denied scope override, recursion and budgets.
- [x] Run TEST_MYSQL=0 php vendor/bin/phpunit tests/Cms/TemplateTest.php; observe missing implementation failure.
- [x] Implement restricted AST parser/interpreter, typed safe HTML, explicit parameter validation and registry extension APIs.
- [x] Run the test file to green.

### Task 2: Plugin schema, sites, locales, domains, models and routes

**Files:** plugin.json, Plugin.php, config.php, database/migrations/001_cms.sql; app/cms/service/{Schema,Input,SiteService,ModelService,RouteService,SiteAccess}.php; tests/Cms/{FoundationTest,SchemaTest}.php.
**Interfaces:** Schema::install() supplies portable DDL also exported as prefix-aware MySQL SQL. SiteService create/update/locales/domains/grant; ModelService defines versioned schemas and validates data; RouteService::publish(site,locale,type,target,path) returns route ID, resolve and url methods return canonical route data. SiteAccess::assert(siteId,adminId).
- [x] Write tests for default-site/language invariants, domain ownership, model schema versions, route collisions, path normalization and admin scope.
- [x] Run foundation tests RED.
- [x] Implement services, schema and Manifest with complete declared permissions. Seed five models; avoid seeding real sites/domains on install.
- [x] Run foundation/schema tests GREEN, including Manifest validation.

### Task 3: Content revisions, categories, menus and backend tags

**Files:** app/cms/service/{ContentService,CategoryService,MenuService,TemplateTagService,HtmlSanitizer}.php; app/cms/tags/{BuiltinTags,CallbackTag}.php; tests/Cms/ContentTest.php, TagsTest.php.
**Interfaces:** ContentService::save(site,locale,input,admin): array, publish(site,localeId,revisionId,lockVersion): array, publicDetail(site,localeId): ?array and listing(site,locale,filters): array. Category/menu/tag services expose scoped list/save operations. TemplateTagService registers published scoped aliases into TagRegistry.
- [x] Write RED tests for independent translations, drafts, rollback, stale saves, publication transaction rollback, old addresses, HTML cleaning, cross-site references and custom-tag version publication.
- [x] Implement immutable saves, transactional publication, versioned model field validation, navigation and scoped built-in query handlers.
- [x] Run content/tag tests GREEN and repeat Tasks 1/2 tests after shared interface edits.

### Task 4: Public SSR application, theme and deployment

**Files:** app/cms/controller/Index.php; app/cms/service/{PublicSite,Seo,Renderer}.php; app/cms/view/default/*.html; public/plugin-assets/cms/public/{cms.css,cms.js,vendor/*}; README.md; tests/Cms/PublicTest.php.
**Interfaces:** PublicSite::handle(Request): Response checks domain/site/language, resolves routes and returns GET/HEAD pages, redirects, sitemap/robots. Renderer assembles scoped variables and built-in/custom registry. Public annotation routes use /cms and catch-all remainder, checked against real Webman dispatch.
- [x] Write RED tests using Host-based requests for two sites, canonical/hreflang, unknown hosts, redirects, pagination/search, raw HTML, disabled languages and HEAD.
- [x] Implement default templates, canonical URLs and local pinned browser resources; CSS/JS provide public navigation and local Element Plus search controls without taking over article DOM.
- [x] Run public and real annotation-dispatch tests GREEN. Document Nginx prefix mapping, Host handling, DNS/cert steps and install/build distinctions.

### Task 5: Admin APIs and working management UI

**Files:** app/admin/plugins/cms/controller/{Base,Sites,Content,Categories,Models,Menus,Tags}.php; web/src/admin/plugins/cms/{api.ts,types.ts,pages/Index.vue,components/*}; tests/Cms/AdminTest.php; web/tests/cms/*.spec.ts.
**Interfaces:** /admin/plugin/cms endpoints expose scoped sites/locales/domains/grants, models/versions, categories, content/revisions/save/publish/offline/preview, menus/items and tag providers/save/publish/preview. Reuse AdminApiController and declared PluginPermission attributes; responses use host envelope.
- [x] Write RED tests for real actions, site access and separate save/publish permissions; frontend selection and requests must exercise real exported API contracts.
- [x] Implement focused controllers and tabbed pages with site/language context, structured model fields and SEO/path/version controls.
- [x] Run API tests, frontend CMS tests, TypeScript check including plugin sources and production admin compilation check.

### Task 6: Lifecycle verification, review and delivery

**Files:** tests/Cms/LifecycleTest.php, MySqlSchemaTest.php; docs/superpowers/plans/2026-11-05-cms-plugin-verification.md; plugin README and extension examples.
- [x] Verify package Manifest, permissions, resource paths, native publication and activation on public/admin routes using temporary roots.
- [x] Run full backend suite and frontend suite once; report baseline failures by name, with targeted CMS suite remaining green.
- [x] Use isolated MySQL when available; otherwise explicitly record DDL verification limitation.
- [x] Perform fresh independent code review, address important findings with reproducing tests, and record final checks and delivery instructions.

## Execution Decisions

User approved design and explicitly requested development; proceed inline without another permission handoff. Work in the existing non-main Rbac branch, add CMS source/tests only, and avoid creating a worktree that would omit the user's uncommitted new plugin framework. Commit only CMS-owned files if committing; leave unrelated changes unstaged and untouched. No remote publication or running-environment install is implied.
