# Middleware suffix implementation plan

Use superpowers:executing-plans inline for a mechanical project-wide rename. User requested every middleware name end in Middleware for clarity.

## Scope
Project-owned PHP middleware classes and matching filenames under admin/app, source plugin/examples, configuration/annotations, generators, tests and live docs. Already-suffixed names stay unchanged. Keep framework middleware.php/config directory names and third-party/vendor classes; unrelated UI route/locale keys such as Install and SystemLog retain their own meaning. No aliases, behavioral changes, data migrations, role changes, commits or pushes. Preserve the dirty shared checkout. Historical audit docs, runtime snapshots/history and immutable database migrations stay unchanged.

## Mapping
- CheckAdminApiRole -> CheckAdminApiRoleMiddleware
- CheckAdminApiCsrf -> CheckAdminApiCsrfMiddleware
- SystemLog -> SystemLogMiddleware
- CheckPluginPermission -> CheckPluginPermissionMiddleware
- IdentityProtocolThrottle -> IdentityProtocolThrottleMiddleware
- StaticFile -> StaticFileMiddleware
- LoadLangPack -> LoadLangPackMiddleware
- PluginApplicationGuard -> PluginApplicationGuardMiddleware
- Install -> InstallMiddleware

## Tasks
- [x] Add failing naming inventory/registration test.
- [x] Rename files/symbols and update all runtime/generator/test references; inspect residual matches and non-middleware collisions.
- [x] Run PHP lint and behavioral/route/plugin/generation suites, relevant frontend contracts, then full suites as appropriate; fresh read-only review.
- [x] Reload worker and verify actual health/root and documentation.

Verification: PHPfull1074tests/7871assertions/113skipped/1deprecation, exit0,no failures/errors; changed-domain45tests328assertions and16frontendcontracts pass;892PHPfileslintpassed. Residualactivecode scan contains onlynegativeThinkPHPassertion. Freshread-onlyreviewAPPROVED. Workerreloaded; root+health200, adminuser/role+identityresource401withoutlogin. NoDB/schema/roles/commit/pushchanges.
