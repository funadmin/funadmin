# Plugin frontend path rename verification

The plugin frontend source contract now uses `web/src/admin/plugins/{code}` globally. `adminWeb` remains the Manifest semantic key. Host source directories and all four plugin package source directories were moved. No active `admin/modules`, `src/admin/modules` or `@/modules` references remain in admin/web/docs scans (excluding unrelated dependency/package data).

Evidence:

- `php webman plugin:validate cms`, `qa`, `document`, `oss`: all valid. OSS reports its existing Composer compatibility warnings only.
- Plugin, manifest, lifecycle, publication, OSS and naming-focused PHP tests: 37 tests / 171 assertions passed.
- Frontend mine/business/CMS/document focused tests: 35 tests passed.
- `node scripts/check-cms.mjs`, `node scripts/build-cms.mjs`, document/OSS checks: passed.
- `npm run build:webman`: passed; production assets built to `admin/public`.
- `git diff --check`: passed.

The plugin source and host frontend mirrors were compared after the move. Existing unrelated dirty changes were preserved; no database or runtime migration was executed.
