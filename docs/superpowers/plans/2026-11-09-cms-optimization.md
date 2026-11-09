# CMS Optimization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** 按用户选择优化 CMS 公开性能、安全、SEO 和体验基础设施。
**Architecture:** 先增加独立缓存/SEO/提交幂等服务，再接入现有 CMS PublicSite、ContentService、FormService、Seo 和主题；保留 Blade 主题结构和现有权限/站点隔离。
**Tech Stack:** PHP 8.5, Webman Cache, Blade, Vue 3, PHPUnit, Vitest.
**Spec:** docs/superpowers/specs/2026-11-09-cms-optimization-design.md

## Global Constraints
- 不改变真实站点主题、内容和权限，不提交或重置其他会话修改。
- 搜索、预览、表单和带身份请求不使用公开页面缓存。
- 所有缓存失效必须按站点隔离；所有表单校验继续在服务端执行。

## Review Focus
- 条件请求命中时必须返回304且不泄露其他站点页面。
- 发布/下架/配置/导航改变后旧页面不能继续命中缓存。
- OG/Twitter/JSON-LD输出必须转义且不把用户字段当成脚本。
- 表单重复提交只去重同一表单/站点/用户意图，不能误伤不同内容。
- 无障碍检查不破坏原生降级和主题视觉。

### Task 1: Public page cache and validators
- [x] Write failing tests for site-scoped cache keys, invalidation, ETag and 304 responses.
- [x] Implement `PublicPageCache` and connect `PublicSite` only for cacheable GET page routes.
- [x] Invalidate from publish/offline/site configuration/category/menu changes.
- [x] Run CMS public tests and cache tests.

### Task 2: SEO and delivery metadata
- [x] Write failing tests for OG/Twitter, model JSON-LD, RSS and pagination canonical metadata.
- [x] Extend `Seo::head`, `PublicSite::sitemap`/RSS and Blade head partials.
- [x] Verify escaping, noindex search, multilingual alternates and public routes.

### Task 3: Form idempotency and audit
- [x] Write failing tests for submission fingerprint, retry behavior, rate limits and duplicate response.
- [x] Add short-lived idempotency token/fingerprint storage and audit fields using existing form submission service.
- [x] Verify JSON and no-JS responses, CSRF/captcha, site isolation.

### Task 4: Accessibility and mobile baseline
- [x] Add contract/browser checks for focus visibility, alt, heading order, reduced motion, drawer focus and contrast tokens.
- [x] Fix shared CSS/Blade attributes and run all theme browser checks.

### Task 5: Follow-up editor and public template refactor
- [ ] Add autosave, batch publish/offline and conflict diff as separate reviewable increments.
- [ ] Extract common Blade data/partials only after behavior tests cover all eight themes.
