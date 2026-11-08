# Dynamic Admin Login Entry Implementation Plan

**Goal:** Configure the admin login entry during install and allow a super administrator to rotate it later.
**Architecture:** Persist a validated system setting, register dynamic Webman auth routes with path equality checks, and make the Vue router/API derive the entry from the route/local state.
**Tech Stack:** Webman PHP, Illuminate models, Vue/Vite, PHPUnit, Vitest.
**Spec:** docs/superpowers/specs/2026-11-07-dynamic-admin-login-entry-design.md

## Review focus

Old routes must return 404; invalid/reserved entries must be rejected; install/default compatibility; CSRF and super-admin authorization; login redirects/API prefixes; stale local entry after rotation.

### Task 1: Backend entry setting and dynamic auth
- [ ] RED: setting validation, install persistence, dynamic route dispatch, old route rejection.
- [ ] Add `AdminLoginPath` service with current/validate/set and default fallback; install writes the setting from validated input.
- [ ] Move AdminAuth routes to `/admin/{entry}/auth`, validate the path before every action, and return 404 for stale entries.
- [ ] Restrict `system.admin_login_path` updates to the configured super administrator.

### Task 2: Frontend route/API and installation
- [ ] RED: helper resolves default/custom paths, login API uses route entry, guard redirects with the selected entry.
- [ ] Add dynamic login route, entry-aware auth API/store, installation input/result and system config display.
- [ ] Update docs and stale login links to use the default compatibility path while custom entries are returned by installation/configuration.

### Task 3: Verification
- [ ] Run focused backend/frontend tests, typecheck, build, lint and route inventory.
- [ ] Record full-suite baseline failures without modifying unrelated parallel work.
