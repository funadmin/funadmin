# Completeness Gaps Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Close the remaining "partial" items: real dashboard data, AI Fallback and Reasoning, a full QA plugin, OSS profile management with an S3 driver and a connection test, and OpenAPI coverage for plugin and generated controllers.

**Spec:** docs/superpowers/specs/2026-11-12-completeness-gaps-design.md

## Global Constraints

- **Soft-delete rules** follow `admin/README.md` "软删除":
  - Physical delete happens only through an explicit recycle-bin action that has its own permission code and a can't-undo confirmation.
  - A restore conflict returns 409.
  - `deleted_token` is used only on tables that have a unique key.
- **Permissions:** derived per-method permission codes. Site-scoped plugin operations are registered in CEILINGS and presets.
- **UI text:** every new admin string exists in both zh-CN and en-US.
- **Plugin code:** edit the source under `admin/plugins/<code>/`, then sync to the installed copies and check them with `cmp`. Bump the plugin version together with each new migration. Plugin tests are gitignored; any regression guard that must be committed goes in `admin/tests` root or `admin/tests/Console`.
- **Shared worktree:**
  - Commit only via `git commit --only -- <paths>`.
  - Never use `add -A`, stash, or reset.
  - Don't push.
  - Don't touch the dev DB.
  - Commit promptly.
- **Verification for each task:**
  - its focused tests;
  - the full backend suite (baseline 1918 OK / 121 skipped);
  - the web specs for what changed, plus `vue-tsc`;
  - MySQL under podman where the task touches SQL or migrations.

---

### Task 1: Dashboard overview
- Backend: `GET /admin/dashboard/overview` with per-block permission checks, soft-delete/data-scope-aware counts, a 7-day log trend, and the latest 10 logs (summary fields only). Cache for 60s, keyed per admin. Add OpenAPI annotation and a test.
- Frontend: `web/src/admin/views/dashboard/index.vue` uses the endpoint instead of hard-coded data.
  - Keep the plugin hook slots.
  - Show a loading state and an error state.
  - Gate shortcuts by permission.
  - Add bilingual strings.
  - Add a spec.
- Commit: `feat(dashboard): 仪表盘系统概览真实数据`.

### Task 2: AI Fallback and Reasoning
- Fallback:
  - Ordered list of 1–3 models.
  - Switch to the next model only on network errors, 5xx, 429, timeouts, or a model-unavailable response.
  - Never switch after streaming has started.
  - Record the switches in task events and message metadata.
- Reasoning:
  - Map effort per protocol: `openai-chat` uses `reasoning_effort`, Responses uses `reasoning.effort`, Messages uses `thinking.budget_tokens` (put the mapping in a constant and document it).
  - Allow only efforts that the admin has declared in model capabilities.
  - Remove the backend rejection of non-default values.
- Frontend: remove the forced-off/cleared behaviour and update the copy.
- Tests use mocked upstreams.
- Commit: `feat(ai): 备用模型切换与思考强度`.

### Task 3: QA plugin — delete, recycle bin, detail and answer management
- Soft-delete questions and answers, with a recycle bin (list, restore, permanent delete).
  - CMS submission data is not deleted on its own initiative.
  - A restore whose CMS submission is trashed returns 409.
- Admin question detail, status and pinning, and hide/delete answers.
- Migration `003` if schema changes are needed; version 1.2.0.
- Web pages and specs.
- Commit: `feat(qa): 问答删除回收站与问题详情回答管理`.

### Task 4: QA plugin — public SEO pages and member attachments
- Server-rendered list and detail pages, resolving the site by host the same way CMS does.
  - Show only approved, live content.
  - Include title, description, canonical and QAPage JSON-LD.
- Member attachments on questions and answers, reusing the CMS member attachment mechanism: limits, private storage, authorized download, and soft-delete together with the question. Files are removed only on permanent delete.
- Tests.
- Commit: `feat(qa): 问答前台 SEO 页面与会员附件`.

### Task 5: OSS plugin — profile management, S3 driver, connection test
- Profile CRUD with soft delete:
  - Can't delete the current profile (409).
  - Permanent delete returns 409 while attachments still reference `oss_<id>`.
  - `resolve` uses `withTrashed` for attachment read and delete.
  - Switching the current profile.
  - Recycle bin.
- S3-compatible provider via an optional `aws/aws-sdk-php`, guarded by `class_exists`.
  - Settings: endpoint, region, bucket, keys, path-style flag, public URL prefix.
  - Encrypted secrets.
- Connection test (upload, read and verify, delete) using the unsaved form values, with a 15s timeout.
- Migration `003` if needed; version 1.2.0. Web pages and specs.
- Commit: `feat(oss): 配置管理、S3 兼容驱动与连接测试`.

### Task 6: OpenAPI coverage
- Annotate every routed method in the plugin admin and public controllers (cms, document, qa, oss). Register their tags in `docs/openapi/ApiDocumentation.php`.
- The generator controller template emits annotations.
- Add a committed guard test `admin/tests/OpenApiCoverageTest.php`:
  - every routed controller method in core and plugins has an `@OA` operation;
  - the path matches the route;
  - operationIds are globally unique.

  Use swagger-php if it is available in vendor; otherwise do a static parse.
- Commit: `docs(api): 插件与生成控制器接口文档注释及覆盖测试`.

### Task 7: Docs and verification
- Update `admin/README.md` and the plugin READMEs.
- Write a verification record at `docs/superpowers/plans/2026-11-12-completeness-gaps-verification.md`.
- Run the full backend suite, the web suite plus `vue-tsc`, and MySQL console plus CMS under podman.
- Commit: `docs: 完整度补齐说明与验证记录`.
