# CRUD migration corrections

> Execute inline with superpowers:executing-plans and test-driven-development.

**Goal:** Fix the four reproduced CRUD migration defects authorized by the user.
**Architecture:** Preserve native Webman generation, safe commits, and managed recovery. Map only generated frontend artifacts to the sibling frontend; backend artifacts and private journals stay in the backend. Synchronize owned resources without deleting unchanged permissions or role grants.
**Spec:** The four verified findings in this conversation: nonempty pagination, default API prefix, sibling frontend artifacts, role grants on regeneration.

## Constraints

- Work in the existing Rbac checkout because the fixes depend on current uncommitted migration work; preserve staged and unrelated changes, no commit/push.
- Never migrate or mutate the configured MySQL database during verification.
- Keep path traversal/symlink rejection and rollback for both source roots.
- Existing unrelated baseline failures must be reported.

## Tasks

- [x] Add nonempty module/history pagination tests; use Eloquent pagination with explicit page and size.
- [x] Add actual annotation route tests for factory defaults and already prefixed APIs; normalize backend group and frontend API paths once.
- [x] Add sibling frontend generation and rollback tests; update factory targets, resolve generated artifacts through a scoped path mapper, and make safe commits/recovery honor both roots.
- [x] Add repeated generation tests preserving permission/menu IDs, role grants, field permissions, pruning removed resources and rollback; replace wholesale removal with owned resource synchronization.
- [x] Run focused tests, PHP suite, frontend suite/build; independent final code review, resolve material findings.

## Review focus

Explicit API prefixes must not double /admin. Existing frontend sources must be detected for drift. A symlink or ../ must never redirect generation outside configured roots. Removed permissions must lose grants; unchanged permissions must retain grants and IDs. Failures after frontend writes must restore both roots.

## Ledger

- Baseline: targeted backend 83 tests, 58 executed passing, 25 MySQL skipped; frontend business 102 tests, 100 passing, 2 known failures (old navigation expectation and missing PHP fixture).
- Authorization: user explicitly requested fixing all four reviewed defects. Use existing dependencies; no production data changes.
- Directory mapping is bound by a signed artifactRootsHash in ordinary plans and the managed bundle/WAL. Root configuration changes fail closed before writes or recovery. Cross-filesystem roots are rejected during preview because the existing safe-commit helper requires atomic rename.
- New regressions: 10 tests / 46 assertions passed, independently rerun by reviewer. Focused CRUD/business suite: 93 tests, 68 executed passing, 25 dedicated MySQL tests skipped, 549 assertions. Layout suite: 54 tests / 789 assertions passed.
- Isolated actual generation of regression_product (title and decimal price) into a sibling frontend passed vue-tsc and production Vite build. Generated API functions were executed through TypeScript transpilation and Axios URL resolution: default and explicit /admin prefixes resolve once; exact /admin resolves to /admin.
- Independent final review: no remaining Critical/Important findings.
- Final full PHP suite: 824 tests / 6290 assertions, 5 errors, 2 failures, 108 skipped. The obsolete generated-route expectation was updated and its 54-test layout suite passes. Remaining failures are listed in the verification report; no production MySQL migrations were executed.
- Reloaded Webman; /health and / return HTTP 200, unauthenticated business modules API returns HTTP 401.
