# CMS community implementation plan

> Use superpowers:subagent-driven-development for isolated backend tasks and inline frontend integration; test-driven-development applies to each task.

**Goal:** Deliver member content submission/review and a separate basic Q&A plugin.
**Spec:** docs/superpowers/specs/2026-11-06-cms-community-design.md

## Task 1 — Common contribution backend

- [x] Read shared interfaces in spec; reproduce missing behavior in SQLite tests.
- [x] Create CMS schema/migration, policy, contribution service, member base/controller, moderation controller and permission catalog operations.
- [x] Verify ownership, rate limits, idempotency, site/locale, review CAS and ContentService publication.

## Task 2 — Q&A backend

- [x] Use the fixed common interface, create QA schema/service/controllers and tests.
- [x] Verify pending visibility, closed questions, cross-site/parent checks, acceptance CAS and hide/reopen behavior.

## Task 3 — Frontend and release integration

- [x] Build public member contribution/Q&A pages and client using existing member identity.
- [x] Add CMS policy/review UI, QA moderation plugin manifest/page and routes.
- [x] Synchronize source/published files, run migrations in isolated fixtures, validate manifests, test and build.

## Task 4 — Independent review and deployment readiness

- [x] Review domain/security boundaries; fix material findings with regression tests.
- [x] Document actual entry points, upgrade/install requirements and limits. No real site participation settings or role grants are changed automatically.

## Ledger

- User explicitly authorized implementation of the last proposed first-release workflow; answered their intervening CMS navigation question while continuing this task.
- Existing frontend member API uses opaque v3 tokens; v2 MApi uses JWT and must not be substituted.
- Preserve concurrent changes in dirty Rbac checkout; no commits/pushes or production migration during tests.
