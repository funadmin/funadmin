# Local market module removal verification

Date: 2026-11-06.

## Delivered

Removed the local operator Vue module, PHP operator controllers/services, local store pages, payments, server catalogue/download/telemetry handlers and /market aliases. Preserved the framework plugin center's external-market UI, NativeMarketplaceAdapter, remote account/search/detail/authorization/download/update/signature checks, packaging and installation.

Generic v3 member account/token logic was rehomed to common/service/member and api/controller/v3/Auth. Existing /api/v3/auth URLs, envelopes, opaque tokens, legacy market_token rows, session and throttle keys remain compatible. CMS source/installed MemberEndpoint imports the common token service and both files match.

Forward PHP035/coreSQL026 migrations deactivate only local market permissions and menus. Historical migrations/maps and data were not rewritten/dropped. Only the new retirement closure was applied to the working database: 8 permission records disabled, no local menus existed. No other pending migration was executed by this task. Worker reload succeeded; HTTP verified health200, local /market/store/server/operator endpoints404, generic v3 login validation422, external-market client categories401 when anonymous.

## Evidence

- Baseline member and external-client suites: 37 tests/306 assertions passed.
- New removal tests RED before implementation: existing local dirs/routes/controller ownership and missing retirement migration.
- After extraction: 34 tests/257 assertions passed.
- Adjusted backend test suites: 102 tests/4241 assertions passed, 1 conditional MySQL skip.
- Frontend targeted client/menu/router: 37 tests passed; type checking passed.
- Full frontend: 1484 passed, zero failures.
- Scoped independent production review: no actionable findings.
- Authorization delta checked before the concurrent host permission migration: 80 local routes removed and retained v3 callbacks relocated; zero unexpected policy changes on 450 remaining routes. Frozen auth-before fixture remains unchanged.
- Final focused removal/public-member/external-client suite: 14 tests/88 assertions passed, using the current derived permission code for the preserved client resource.
- Final CMS contribution + QA controller endpoint suite passed; see /tmp/market-removal-endpoint-final.log for counts.
- Syntax, source/runtime endpoint parity, directory absence/client presence and scoped whitespace checks passed.

## Whole-suite boundary

The first full backend run completed 1170 tests with one obsolete route-count floor failure (396 remaining admin codes vs >400). That assertion was concurrently adjusted to >350 and its focused suite passed (16 tests/442 assertions).

During the fresh full rerun, a separate work stream added 036_host_permission_per_method and changed host permission lookup/seeding. That run completed 1174 tests with 17 failures and 113 condition-dependent skips: legacy host permission-code assertions plus explicit route loss. The new removal test's legacy client-code assumption was corrected to use PermissionCode::derive and its final focused suite passed. A CMS/QA broad rerun had one unrelated duplicate CMS annotation route error (177 tests, 5 skips).

Read-only diagnosis confirmed the explicit-route issue: the new migration calls PermissionSyncService::sync -> Route::load; vendor Route::load clears dispatcher then require_once's config/route.php. A subsequent load loses explicit /api/v2 and hook routes while rebuilding annotation routes. This task did not change that concurrent migration, permission policy or vendor loader, and does not claim a green full backend suite. Those failures remain for the parallel permission/routing work.

No build publication, commit or push was performed by this task. Existing user/concurrent changes were retained; temporary backups are under /tmp/remove-local-market-before.
