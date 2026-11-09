# CMS Style Themes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Track steps with checkboxes.

**Goal:** 新增 editorial/minimal/aurora/heritage 四套不同风格、功能完整的 Blade 主题。
**Architecture:** 每套独立主题目录、首页结构、CSS 和本地 SVG；其余页面复用现有主题结构并使用新主题样式。主题注册沿用 BuiltinThemes，数据沿用 ThemePresentation，自动进入 TemplateCatalog。
**Tech Stack:** PHP 8.5, Blade, CSS, local SVG, Element Plus browser enhancement, PHPUnit.
**Spec:** docs/superpowers/specs/2026-11-09-cms-style-themes-design.md

## Global Constraints
- 不创建真实站点/内容，不改变当前站点主题，不提交或覆盖其他会话改动。
- 源包、运行模板、公共静态资源三处同步；所有资源本地提供。
- 新主题必须支持原有 CMS 展示功能和窄屏、键盘、无 JS 降级。

## Review Focus
- 暗色主题的弹出菜单、抽屉、表单和卡片文字对比度。
- 长站点名、长标题、手机宽度和多级菜单不得横向溢出。
- 空数据时真实空状态；有数据时不混入其他站点、语言或草稿。
- 配置轮播与自定义品牌后仍展示真实配置，正文/模型字段保留。
- 站点主题列表、资源发布以及完整页面/partials 都可用。

### Task 1: 注册和完整主题回归
- [x] 将四个主题加入 EnterpriseThemesTest 的选择、全部内容模型、SEO、导航、站点/语言过滤、分页、模块回归。
- [x] 运行观察未安装主题导致失败。
- [x] 在 BuiltinThemes 中登记四个主题；创建四个24文件的独立 Blade 目录，覆盖对应首页及头尾主题标记。
- [x] 制作各套 CSS 和独立 SVG，确保功能回归转绿。

### Task 2: 浏览器验证与同步
- [x] 使用隔离内存数据库生成四个主题的预览 HTML；提供桌面和手机截图，不写生产数据。
- [x] 检查布局、选中内容、菜单/抽屉、搜索、无 JS 降级和横向溢出，修复实际问题。
- [x] 仅同步本次主题和资源到运行目录及 public，检查字节一致。

### Task 3: 交付验证
- [x] 文档列出四种主题、适用场景、选择方式和可用功能。
- [x] 运行 CMS/Blade/插件校验、源码 lint、相关前端与宿主验证；必要时记录无关失败。
- [x] 独立代码审查，修复重要问题；记录最终验证结果。

## Verification ledger
- Added four themes, each with24 Blade files, CSS and local SVG; eight total builtin themes.
- Functional regression: EnterpriseThemesTest 9 tests /804 assertions passed. CMS plus Blade/manifest suite:185 tests /1706 assertions, 4 environment skips, no failures.
- Desktop1440 and mobile390: all four enhanced layouts, content, overflow, submenus and drawers passed. Native no-JS Aurora menu and GET search verified.
- Independent review: Aurora active pagination/search/category contrast defect reproduced and fixed. Broader browser checks also found quote/code/ad surface contrast and light-footer inheritance; all fixed and verified. Aurora controls/content minimum contrast12.42:1; footers and Heritage intro minimum4.54:1.
- All192 Blade templates compile and parse. Manifest valid at1.3.3 (version updated by another session), no warnings. New theme assets return200 from local CMS入口. Source/runtime assets match.
- Full backend executed:1283 tests /11779 assertions;5 errors,3 failures,107 skips outside the theme change (LocalMarketplaceRemovalTest, MemberIntegrationTest, AuthRequirementSnapshotTest, BundledLanguageTest). Full log /tmp/funadmin-style-themes-full-backend.log. No unrelated authentication/language edits made.
- Preview image /tmp/funadmin-cms-style-themes-overview.png. Isolated SQLite preview data only; real site theme/content unchanged.
