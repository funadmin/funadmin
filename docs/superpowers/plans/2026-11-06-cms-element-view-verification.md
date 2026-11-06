# CMS Element declarations in view: verification

Date: 2026-11-06. This supersedes the earlier CSS-driven component construction described in the browser UI plan.

## Result

All four themes declare their layout, rows/columns, cards, images, buttons, desktop/mobile menus, language dropdown, carousel, breadcrumb, search input and pagination directly in `cms/view`. Shared JavaScript resolves declared components and literal properties, named slots and common interaction state. It contains no feature-specific HTML construction or CSS-class-to-component conversion. The legacy script now handles native menu/slider interactions only.

Native anchors remain in page source and after enhancement, including pagination numbers. Rich content and model fields have a static boundary. There is no template compilation, expression evaluation, CDN dependency, npm build, site change or plugin activation.

## Tests

- RED before implementation: 22 runtime failures against the old converter; the new PHP view assertion failed on missing component declarations.
- Focused GREEN: 42 runtime/native fallback cases; 7 enterprise theme tests with 329 assertions before the final review fixes.
- Independent scoped review found two P2 issues. Both regression tests failed before their fixes: nested main count 2, and the existing maximum-depth navigation returning 503.
- Final `cd web && npx vitest run tests/cms --reporter=json --outputFile=/tmp/cms-element-view-final.json`: exit 0, 161 passed, no failures or pending cases. This includes 34 source/runtime component cases, literal content, static boundaries, slots, interactions, keyboard navigation, persistent pagination anchors and production-Vue failure recovery.
- Final `cd admin && vendor/bin/phpunit tests/Cms --no-progress`: exit 0, 158 tests, 1170 assertions, 5 environment-dependent skips, no failures. Includes all four themes, native SSR roles/content/links, recursive view navigation with escaped titles and new-window links, the accepted 17-level tree, and the existing complete home-module render budget.
- JavaScript and PHP syntax checks passed. All 103 scoped source/runtime pairs matched, including 24 templates per theme, four PHP files and three shared assets.

## Browser and runtime boundaries

The local production scripts mounted the new declarations in a real Turbo browser on the corporate preview. Its desktop header, menu roles, primary buttons, content and styling rendered correctly. Further interactive UI checks were stopped because the browser reported concurrent user changes; this task does not claim a new full mobile visual pass. Component interactions and failure handling are covered by real-component tests.

Read-only previews regenerated 117 pages and confirmed existing site theme selections were unchanged. CMS remains `status=0`, `lifecycle_state=disabled`; no live plugin activation was performed. The preview is a static rendering aid; dynamic search and pagination results are verified in isolated PHP/runtime tests.

Both independent-review issues were fixed and the final suites are green. No deferred findings or design rulings remain. No commit, push or frontend build was performed.
