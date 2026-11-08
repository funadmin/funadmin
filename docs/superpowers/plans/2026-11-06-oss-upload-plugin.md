# OSS Upload Plugin Implementation Plan

**Goal:** A single installable OSS plugin globally routes uniform public uploads to Alibaba, Tencent or Qiniu.
**Architecture:** Generic storage filter hooks select one immutable driver per upload; profile-bound driver identities preserve old files. Plugin owns encrypted profiles, cloud adapters and management UI.
**Tech Stack:** PHP/Webman, official PHP SDKs, Vue/Element Plus, PHPUnit/Vitest.
**Spec:** docs/superpowers/specs/2026-11-06-oss-upload-plugin-design.md

## Constraints

Preserve concurrent edits. No production migration, cloud credentials, commits or pushes. Private AI scope is pending optional clarification; preserve private storage meanwhile. Host upload_driver stays unchanged. No silent local fallback on active cloud errors.

## Review focus

Long-lived workers and mid-upload profile switches; historical cloud deletion and partial failures; credential redaction; vendor isolation; disabled/unconfigured plugin lifecycle.

### Task 1: Core storage integration
- [x] RED: real hook-selected upload, strict history lookup, disabled hook, and cloud failures without local writes.
- [x] Add StorageDriverRegistry::forUpload(?string): StorageDriverInterface and ::require(string): StorageDriverInterface; keep resolve compatibility. Extend all() through storage.drivers filter.
- [x] Freeze driver once in UploadService::attach, expose actual reuse in uploads result, remove AdminUpload precheck; avoid shadowing DB error during compensation.
- [x] Strict preflight and failure-preserving SystemAttachment deletion; expose managed metadata in SystemStorage.

### Task 2: OSS plugin
- [x] RED: cloud adapter protocol, encrypted settings and lifecycle tests.
- [x] Source admin/plugins/oss; canonical app/admin/plugins/oss services/controller. Schema installs oss_profile/oss_setting. Profile IDs are 16 hex chars, driver is oss_{id}; secrets sealed with Defuse and purpose/profile binding.
- [x] Hooks storage.upload.resolve, storage.driver.resolve, storage.drivers use ProfileService::driver/current/drivers. CloudDriver(array profile) implements StorageDriverInterface, source=oss, providers aliyun/tencent/qiniu. Keys generated and logged without secrets on uncertain failures.
- [x] ProfileService::listing/save/activate/current/driver/drivers/testConnection, with immutable storage target and mutable encrypted credentials. No profile deletion, no remote purge.
- [x] Manifest, lifecycle, private SDK vendor, migrations and package validation.

### Task 3: Admin UI and integration
- [x] RED: profile form retains masked secrets, provider fields switch, activation and connection result.
- [x] API /admin/plugin/oss/storage GET -> {profiles,current,managed}; POST /profiles -> sanitized profile; POST /activate {id}; POST /test {id}. Permission oss:storage:list/config/test. Fields id,name,provider,bucket,region,endpoint,domain,prefix,access_key,secret_key; responses omit credentials, include has_credentials.
- [x] Source web/src/admin/plugins/oss with list/config/activate/test UI. Host storage metadata adds managed:{source,label,driver}|null; disable host driver picker during takeover. Refresh attachments after partial deletion errors.
- [x] Verify isolated native installation/publication, SDK compatibility, focused suites, typecheck/build, full-suite snapshot, independent review and verification record.

## Execution notes

- Native implementation and two independent implementation scopes (cloud SDK adapter/UI) followed the parallel-agent skill. No live installation targets were copied manually: the native publisher rejects unregistered targets, so installation/publication was verified in an isolated temporary project instead.
- Lifecycle SQL is MySQL-only. The in-memory lifecycle test preinstalls the tested Schema builder and removes the fixture SQL declaration; actual production SQL migration has not run. Real cloud calls require user-provided test buckets and were not performed.
- Independent design review resolved upload snapshot consistency, partial deletion and credential persistence. Final code review found unreadable-cipher recovery blocked complete credential replacement; regression observed RED, fix and GREEN. Profile writes also take row locks. Reviewer reported no remaining Important/Critical issues.
- Full verification: frontend 1359/1359; backend1047 tests,7458 assertions,113skipped,1 upstream Qiniu deprecation, no failures/errors. See `2026-11-06-oss-upload-plugin-verification.md`.
