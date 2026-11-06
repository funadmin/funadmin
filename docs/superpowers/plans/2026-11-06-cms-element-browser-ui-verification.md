# CMS Element Plus browser UI verification

Date: 2026-11-06. Public themes load local global scripts; no public frontend build was run or required.

## Automated checks

- `cd web && npx vitest run tests/cms --reporter=json --outputFile=/tmp/cms-element-final.json`: exit 0, 153 passed, 0 failed. The report lists 40 passed suites.
- `cd admin && vendor/bin/phpunit tests/Cms --no-progress`: exit 0, 156 tests, 1114 assertions, 5 environment-dependent skips, no failures.
- The 26 public Element runtime cases exercise source and installed assets with real Vue/Element components, including the shipped production globals for failure recovery. Checks cover literal CMS expressions, page components, submenu and language keyboard activation, drawer links, carousel, GET query fields, language/category pagination paths, encoded search keywords and library/component failure fallbacks.
- PHP coverage checks all four themes retain complete server content, links, pagination metadata and deferred scripts outside the mounted root.
- All 12 modified theme partials and the shared runtime, stylesheet, fallback script, locale and version metadata match their installed copies byte for byte.

## Review fixes

The scoped independent review found swallowed keyboard link activation in Element menus and Vue production errors bypassing the outer catch. Both were reproduced with failing cases and fixed. Menu and dropdown keyboard handlers now activate the actual link; Vue's application error handler restores the complete server page after initialization or update failures.

## Browser observations

Native Turbo browser checks used independent PHP-rendered previews served on loopback port 8795. The three enterprise homepage palettes and layouts rendered successfully with the actual local production scripts. Checked a real Element desktop submenu, followed its product link, opened the narrow-screen drawer, navigated from that drawer to the search page, and observed Element search, card grids, breadcrumbs and pagination. Also checked the industrial drawer at 500% zoom (about 384 CSS pixels wide): dark background, readable labels and close control, and scrolling menu body. Restored zoom to 100% and closed the drawer afterward.

Edge control timed out; Turbo provided the browser verification. Static previews validate presentation and navigation targets; dynamic search and pagination results are covered by the PHP/runtime suites, not claimed from the static server.

## Runtime boundary

Read-only database verification returned CMS `status=0`, `lifecycle_state=disabled`, `deleted_at=null`. The live `/cms/` route returns 404. The plugin was left disabled and existing site theme selections were preserved. Corporate, industrial and studio preview entry pages each return HTTP 200. Live CMS acceptance requires enabling the plugin through its normal lifecycle.
