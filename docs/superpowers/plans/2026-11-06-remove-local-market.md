# Remove local marketplace server

User explicitly requested removing the local market operations module because the marketplace will be hosted online and the framework will connect as a client.

## Design and boundaries

Remove local operator Vue module, app/market storefront/protocol/payments and app/admin/plugins/market. Keep plugin center external marketplace client, signature checking/package installation, cloud account and telemetry adapters/configuration. Generic member v3 opaque authentication currently resides in market: move the existing account/token behavior into common/service/member and Auth into api/controller/v3, retaining /api/v3/auth endpoints. Keep legacy market_token storage and existing tokens; no data purge or table drop. CMS/QA switch to the common token service. New token TTL config replaces missing market package config, retaining old defaults. Historical migration files/maps and frozen auth-before fixture stay unchanged. Add forward, idempotent permission/menu retirement migrations only. Review current auth snapshot changes for removed endpoints and relocated v3 callbacks.

Work inline in the existing checkout; preserve unrelated changes and concurrent commits. Backups: /tmp/remove-local-market-before. No build, commit, push, data deletion or blanket migration execution.

- [x] Capture baseline and failing removal/member/client regression tests.
- [x] Rehome generic member behavior, remove market server/admin/routes/bundled flag; update CMS dependency and add forward resource retirement migrations.
- [x] Remove frontend local operations and update affected tests without weakening client/security coverage (delegated frontend scope).
- [x] Update backend mixed tests, verify current auth route snapshot delta, run targeted/full checks; scoped review and docs.

## Evidence

Baseline member + external-market client suites passed 37 tests/306 assertions. New removal tests failed on existing market directories/routes and v3 controller ownership before implementation.

Rehomed member/removal/client tests passed 34 tests/257 assertions; the 13 adjusted backend regression files passed 102 tests/4241 assertions with 1 conditional MySQL skip. Frontend targeted client/menu/router tests passed 37 cases, type checking passed, and full frontend suite passed 1484 cases.

The current authorization snapshot was compared structurally: exactly 80 local market routes removed; 8 retained v3-auth callbacks moved to api/controller/v3; zero unexpected authorization changes on 450 remaining routes. Frozen auth-before fixture and historical permission maps/migrations remained intact.

Scoped independent production review found no actionable defects. Worker reload and live HTTP checks confirm health200, local storefront/server/operator routes404, generic member login validation422 and authenticated external-market client route401. The new idempotent resource-retirement closure was applied alone to the existing database (8 permissions, 0 menus disabled), without running other pending migrations or changing order/token tables. Forward SQL/PHP migration files remain available for normal upgrades.

A full backend run found only an obsolete minimum route-count assertion (396 remaining vs >400); the concurrent workspace already updated its floor to350. All route uniqueness/format checks still apply to every route. Fresh full backend rerun is recorded in the verification report.

Final scoped removal/public-member/client: 14 tests/88 assertions passed; CMS/QA endpoint tests passed. Full-suite concurrency boundary and live HTTP/resource evidence are recorded in 2026-11-06-remove-local-market-verification.md; full backend suite is not claimed green.
