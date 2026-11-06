# CMS community verification — November 6, 2026

Implemented CMS1.3.0 member contributions/policy/review and independent QA1.0.0 questions, answers, moderation and author acceptance. New public routes use existing opaque member bearer; new member identity preserves string IDs. Admin permissions remain host+site scoped. Contribution policy defaults disabled and does not grant existing roles new rights.

## Final evidence

- Frontend full run: npm test -- --reporter=json --outputFile=/tmp/funadmin-community-vitest.json; success=true,1358passed/1358total,0failed.
- node scripts/check-cms.mjs: exit0, plugin-source and portal Vue/TS checks included.
- node scripts/build-cms.mjs: exit0 (CMS contribution page and QA moderation included).
- npm run build:webman: exit0; production output admin/public directly, existing uploads/resources preserved.
- PHP CMS+QA+BusinessModuleLayout targeted run:210tests/1718assertions/5skipped, exit0.
- Isolated real MySQL migration upgrade:1test14assertions, exit0. Used task-owned funadmin_cms_test_* DB, custom community_ prefix; original site/settings/grants preserved, repeat migrations no-op, request-key case sensitivity/uniqueness. Temporary DB dropped.
- Full PHP earlier run:1025tests/7357assertions/113skipped, three failures. QA compact-import parser failure corrected and1test1assertion green; two AttachmentIntegration watermark cases subsequently2tests5assertions green in current concurrent tree. Final full recheck: php vendor/bin/phpunit exit0,1045tests/7441assertions/113skipped/1deprecation, zero failures/errors. This supersedes the earlier full-run failure state.
- git diff --check: exit0.
- Manifest::fromDirectory validates cms1.3.0 andqa1.0.0; official CLI packages both successfully, storage directories retained.
- Exact source/mirror comparisons pass for CMS/QA backend/frontend trees. Actual Webman route construction and dispatch coexist with CMS catch-all; MemberHttp and route tests5tests27assertions green.
- Independent backend and final integration reviews approved after one scoped fix wave each. See .superpowers/sdd/2026-11-06-cms-community/*review.md.

## Packages

- /tmp/funadmin-cms-1.3.0.zip SHA2564c184743675c8b8d3861abfed4155348d4347debd63f78f28e5e99c69ac22170
- /tmp/funadmin-qa-1.0.0.zip SHA256615433f22f7ebb5e507e69da6024399f52c5eacb9466443aff19c7637fa4ab8f

## Runtime and operational boundary

Worker reloaded, homepage/health200; /cms/member/context returns200 {sites:[],ready:false}. Current local installed CMS database record remains1.2.0; QA dependency validation correctly rejects this old version and its inactive public endpoint404. No real business schema migration, plugin installation/activation or role grant was performed. New feature runtime requires the documented formal CMS1.3.0 upgrade and QA1.0.0 install/enable, then explicit per-site participation opt-in. See docs/cms-community.md.

Only scalar-only content models are eligible this release; models with optional/required image,file,gallery are excluded and member upload is deferred. The original persisted spec omitted this already-decided constraint; it has been reconciled and UI wording is explicit. If media contribution was expected now, this is the remaining product limitation.

No commit,push,merge or deletion of other working changes.
