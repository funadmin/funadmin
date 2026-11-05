# CRUD corrections verification

## Implemented

- Nonempty module/history lists use explicit Eloquent pagination.
- Generated controller groups include /admin once; frontend API paths rely on the HTTP client's existing base. Default, explicit /admin/generated/..., and exact /admin configurations execute correctly.
- Core frontend artifacts target sibling web/src/admin/{api,views}/generated. Planning, writing, conflict detection, safe commits and managed recovery use the same mapping. Roots are hash-bound to signed plans and WAL. Traversal and symlinks are rejected; cross-filesystem roots are rejected before writes.
- Resource synchronization preserves unchanged permission/menu IDs, role grants and field grants. Removed resources are pruned, and resource rollback remains effective.

## Passing checks

- New regression files, independently rerun: 10 tests, 46 assertions.
- CRUD/business Console suite: 93 tests, 68 executed passing, 25 dedicated MySQL tests skipped, 549 assertions.
- BusinessModuleLayoutTest: 54 tests, 789 assertions.
- Real generation of a title/decimal-price product module into a temporary sibling frontend; vue-tsc and full Vite production build passed, including the generated page chunk.
- Emitted TypeScript API methods executed with Axios URL resolution: default and prefixed paths resolve to /admin/generated/api-review; exact root resolves to /admin.
- PHP syntax scan: 797 files passed; final modified path/plan/transaction files rechecked separately. git diff --check passed.
- Backend reloaded; root page and health return 200, business API still requires authentication (401).

## Full-suite results

PHP: 824 tests, 6290 assertions, 5 errors, 2 failures, 108 skipped. Full output: [/tmp/funadmin-crud-fix-php-suite-final.log](/tmp/funadmin-crud-fix-php-suite-final.log).

Remaining PHP errors:

- Console\AiProfileImageConsumerTest::test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request — private storage symlink rejection.
- Console\CoreDatabaseAuditTest::test_manifest_reads_only_root_baseline_declarations_without_executing_seeds — missing authoritative SQL source directory.
- Console\CoreMigrationSourceTest::test_default_source_is_repository_root_not_webman_php_migrations — missing authoritative SQL source directory.
- Console\CoreMigrationSourceTest::test_upgrade_manifest_requires_complete_signed_authoritative_core_sql — same missing directory.
- Console\CoreMigrationSourceTest::test_signed_zip_is_rejected_without_core_contract_and_uses_mounted_sql_not_local_php — same missing directory.

Remaining PHP failures:

- AdminRouteInventoryTest::test_module_route_contracts_do_not_disappear_during_migration — authorization route digest mismatch.
- LoadLangPackContractTest::test_application_runtime_contains_no_thinkphp_class_references — ThinkPHP references remain in existing generated/form/upload files.

Frontend full suite: 146 files, 62 failed / 84 passed; 907 tests, 118 failed / 789 passed. Failure names and stacks: [/tmp/funadmin-crud-fix-web-suite.log](/tmp/funadmin-crud-fix-web-suite.log). Existing old source references, missing backend fixtures and parallel migration changes remain outside these four CRUD fixes. The generated product's isolated typecheck/build passed.

No production database data or schema was modified. Tests used SQLite memory and temporary source directories; dedicated MySQL integration tests were not run.
