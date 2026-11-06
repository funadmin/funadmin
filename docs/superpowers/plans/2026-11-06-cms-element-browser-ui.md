# CMS Element Plus Browser UI Plan

User requests the corporate public frontend use Element Plus without bundling, and explicitly says continue. Implement inline in the existing shared checkout; preserve unrelated work and all site data.

## Design

Local Vue and Element Plus global builds already ship. Add one shared plain-JavaScript frontend runtime and stylesheet for all four built-in themes. PHP still emits a complete page, canonical metadata and readable fallback links. The runtime constructs Vue render-function nodes from a snapshot of the server DOM; it never compiles site names, configuration or rich HTML as a Vue template.

Use real Element Plus components for page containers, content rows/columns/cards/images, desktop menu/submenus, mobile drawer/menu, language dropdown, carousel, breadcrumb, pagination, primary buttons and search input/button. Native HTML remains for text, links and article semantics. Vue/Element loading failure retains the server page and existing native fallback behavior. Keep each theme's palette; reduce excessive hero height and spacing.

## Tasks

- [x] Add failing real-component browser-runtime tests: safe literal content, layout/cards, desktop submenu, mobile drawer, carousel, search query, pagination URLs, missing-library fallback.
- [x] Add runtime and CSS; mount through local script tags in default/corporate/industrial/studio. Add explicit pagination metadata; avoid duplicate legacy handlers.
- [x] Verify source and installed copies, isolated PHP and frontend CMS suites. No frontend npm build is required to serve public themes.
- [x] Reload backend, regenerate readonly previews, inspect actual navigation and pages in browser. CMS is disabled, so browser checks use independent previews without changing plugin or site state.
- [x] Independent scoped review and fix important issues; record verification and deliver.

## Boundaries

No new dependencies, CDN requests, routing framework, database changes or automatic theme switching. Do not execute template-like user strings. Retain native href targets and GET search behavior. Pagination must retain language/custom base paths and encoded search query. Theme controls remain in existing admin UI.

## Verification

See `2026-11-06-cms-element-browser-ui-verification.md` for fresh suite results, scoped review fixes and browser observations.
