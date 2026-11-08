# Plugin Route Namespace and Overrides Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make plugin-owned admin URLs use `/plugins/{code}` while preserving the isolated `plugins/{code}` filesystem boundary and adding safe, manifest-declared route overrides.

**Architecture:** Keep plugin source and published PHP code under the existing isolated directories. Normalize new manifests and frontend routes to the plural namespace, generate singular Webman/Vue aliases during loading, and let the activation-gated route loader replace only explicitly declared route callbacks.

**Tech Stack:** PHP 8.5, Webman 2.2 annotation routes, Composer PSR-4, Vue Router, Vitest, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-08-plugin-route-namespace-and-overrides-design.md`

## Global Constraints

- Keep source at `admin/plugins/{code}` and published backend code at `admin/app/admin/plugins/{code}`.
- Plugin-owned admin routes use `/admin/plugins/{code}/...`; singular `/admin/plugin/{code}/...` is rejected.
- Never publish plugin files into host controller or common directories.
- Route overrides must be declared in `plugin.json`, belong to the declaring plugin namespace, and require an existing target route.
- Do not modify independent application routes `/{code}/...` or public asset paths.

## Review Focus

- A legacy manifest using `/plugin/{code}` is rejected by schema validation.
- A plural route registers exactly once without a compatibility redirect.
- A disabled plugin blocks both aliases; test the plugin application guard.
- An override cannot point at another plugin or a missing target; test manifest and loader rejection.
- Uninstall removes only plugin-owned files; test the publication registry remains scoped to `admin/app/admin/plugins/{code}`.

### Task 1: Route namespace helpers and manifest validation

**Files:**
- Modify: `admin/app/common/plugin/sdk/Manifest.php`
- Modify: `admin/app/common/plugin/sdk/schema/plugin.schema.json`
- Create: `admin/app/common/plugin/sdk/PluginRouteNamespace.php`
- Test: `admin/tests/PluginRouteNamespaceTest.php`
- Test: `admin/tests/PluginManifestRouteContractTest.php`

**Interfaces:**
- `PluginRouteNamespace::canonical(string $path, ?string $code = null): string`
- `PluginRouteNamespace::isPluginAdminPath(string $path, ?string $code = null): bool`
- `Manifest::validateAdminWeb()` accepts both prefixes and validates `routeOverrides`.

- [ ] Write failing tests for plural canonicalization, singular compatibility, wrong-plugin paths, and valid/invalid `routeOverrides`.
- [ ] Run the focused PHPUnit tests and confirm they fail for the missing helper/manifest fields.
- [ ] Implement the helper with segment-aware prefix replacement only; never rewrite `/plugin-assets` or `/plugins/hooks`.
- [ ] Extend the JSON schema with optional `routeOverrides` and dual route/menu prefix validation.
- [ ] Add Manifest validation for methods, absolute target paths, controller namespace `app\\admin\\plugins\\{code}\\controller\\`, public action names, and non-negative priority.
- [ ] Run the focused PHPUnit tests and confirm they pass.

### Task 2: Webman route aliases and guarded overrides

**Files:**
- Modify: `admin/app/common/plugin/sdk/PluginRouteLoader.php`
- Modify: `admin/app/common/middleware/PluginApplicationGuardMiddleware.php`
- Modify: `admin/support/bootstrap.php` only if route registration ordering requires it
- Create: `admin/app/common/plugin/sdk/PluginRouteOverride.php`
- Test: `admin/tests/PluginRouteLoaderTest.php`
- Test: `admin/tests/PluginRouteOverrideTest.php`

**Interfaces:**
- `PluginRouteLoader::definitionsFor()` returns active plugin definitions plus one compatibility alias per plugin route.
- `PluginRouteOverride::apply(array $definitions, array $manifests): array` replaces exact method/path callbacks while retaining the original route methods and middleware metadata.

- [ ] Add a failing loader test proving a plural route registers once and a singular route is rejected.
- [ ] Add a failing override test for a valid replacement, duplicate target, missing target, cross-plugin controller, and inactive plugin.
- [ ] Run the focused tests and confirm the failures are about missing alias/override behavior.
- [ ] Implement alias generation after annotation definition discovery, using the same callback and name only for the alternate path.
- [ ] Implement override collection from active manifests, exact method/path matching, duplicate detection, controller ownership checks, and route replacement before registration.
- [ ] Extend the guard to recognize only `/admin/plugins/{code}` while preserving controller-based ownership checks.
- [ ] Run the focused tests and the existing plugin lifecycle/route suites.

### Task 3: Canonicalize bundled plugin source and generated output

**Files:**
- Modify: `admin/app/common/plugin/sdk/PluginScaffolder.php`
- Modify: `admin/app/common/plugin/sdk/Manifest.php` error messages and route checks
- Modify: `admin/app/common/crud/PluginTemplateContext.php`
- Modify: `admin/app/common/crud/FormCrudDefinitionFactory.php`
- Modify: `admin/plugins/cms/plugin.json` and controller annotations
- Modify: `admin/plugins/document/plugin.json` and controller annotations
- Modify: `admin/plugins/oss/plugin.json`
- Modify: `admin/plugins/qa/plugin.json` and controller annotations
- Modify: `admin/app/admin/plugins/example/controller/*.php`
- Test: `admin/tests/Console/PluginScaffoldExecutionTest.php`
- Test: `admin/tests/Document/DocumentPermissionManifestTest.php`
- Test: `admin/tests/Cms/CommunityWiringTest.php`

- [ ] Update failing contract assertions and fixture manifests to expect `/plugins/{code}`.
- [ ] Change scaffolder/templates and CRUD route contexts to generate plural paths.
- [ ] Update bundled manifests and annotation groups to plural paths without changing PHP namespaces or install directories.
- [ ] Run the focused PHP tests and lint all changed PHP files.

### Task 4: Frontend dynamic route normalization and compatibility aliases

**Files:**
- Modify: `web/src/admin/router/pluginModules.ts`
- Modify: `web/src/admin/router/dynamic.ts`
- Modify: `web/src/admin/router/pluginModules.spec.ts`
- Modify: `web/src/admin/router/menu-tree.spec.ts`
- Modify: `web/src/shared/utils/route.spec.ts`
- Modify: `web/tests/contracts/plugin-route-helper.spec.ts`
- Modify: `admin/plugins/*/plugin.json` frontend menu/routes as needed

- [ ] Add failing Vitest assertions that plural DTO paths mount at `/admin/plugins/...` and expose singular aliases.
- [ ] Validate only plural DTO paths and preserve plugin-code/name checks.
- [ ] Add Vue Router aliases for the singular path on layout routes and update placeholder detection to both prefixes.
- [ ] Update frontend fixtures and route contracts to plural canonical paths.
- [ ] Run the focused frontend suites and typecheck/build checks.

### Task 5: Documentation, migration notes, and full verification

**Files:**
- Modify: `docs/plugins.md`
- Modify: `admin/plugins/document/resources/seed/documents/plugin-backend-routes.json`
- Modify: `admin/plugins/document/resources/seed/documents/third-party-production-checklist.json`
- Create: `admin/tests/PluginRouteMigrationTest.php`

- [ ] Document canonical plural URLs, rejection of singular paths, filesystem isolation, and `routeOverrides` examples.
- [ ] Add a migration test that scans bundled manifests/controllers for plural canonical routes and rejects direct host-directory override paths.
- [ ] Run PHP lint, focused PHPUnit suites, focused Vitest suites, `git diff --check`, and the frontend build.
- [ ] Review the diff for unrelated concurrent changes before committing.
