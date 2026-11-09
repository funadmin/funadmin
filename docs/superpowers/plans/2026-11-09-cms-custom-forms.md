# CMS 自定义表单 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** CMS 站点可配置访客表单，前台通过标签嵌入并匿名提交（含文件上传），站点内有权限的成员在后台跟进处理。

**Architecture:** 全部在 CMS 插件内实现。数据层用新的 `FormSchema`（四张表，迁移由 Schema 导出）；领域逻辑拆成字段目录与校验、表单配置、私有文件存储、防刷、提交处理五个服务；后台走 `Forms` 管理控制器并接入站点权限；前台由公开控制器 `FormEndpoint` 接收提交，标签 `form` 渲染可信的 Blade 片段；后台前端新增“表单”页。

**Tech Stack:** PHP 8.5、webman、illuminate/database 与 illuminate/view 13、webman/limiter、webman/captcha、webman/cache；Vue 3 + Element Plus + Vitest。

**Spec:** `docs/superpowers/specs/2026-11-09-cms-custom-forms-design.md`（实现前先通读）

## Global Constraints

- 插件源码为准：所有后端代码写在 `admin/plugins/cms/app/cms/...` 与 `admin/plugins/cms/app/admin/plugins/cms/...`，后台前端写在 `admin/plugins/cms/web/src/admin/plugins/cms/...`。每个任务完成后把改动同步到安装副本（`admin/app/cms`、`admin/app/admin/plugins/cms`、`web/src/admin/plugins/cms`），两份必须逐字节一致。测试的自动加载指向插件源码（`admin/tests/Cms/bootstrap.php`）。
- 不依赖宿主表单模块 `app/admin/form`；字段校验风格参照 `app\cms\service\ModelService`。
- 后台可编辑的任何文字（表单名、字段标签、说明、成功提示等）不得被编译为 Blade 或 PHP；渲染时只作为数据传入可信模板，纯文本一律转义；`consent` 文字与 `paragraph` 内容先经 `app\cms\service\HtmlSanitizer` 清洗再存储，渲染时原样输出清洗结果。
- 站点只由请求域名（`SiteService::byHost`）或后台 `site_id` + `SiteAccess`/`SiteAuthorization` 校验确定；任何查询都带 `site_id` 条件。
- 字段类型白名单（规格原文）：`text textarea email phone number radio checkbox select date datetime time consent file image hidden paragraph divider`。
- 长度上限：`text` 2000、`textarea` 20000、`hidden` 500；`phone` 只允许数字、空格、`+ - ( )`，长度 5–32。
- 上传硬上限：单文件 20MB、单次提交合计 50MB、单字段 10 个文件；拒绝 php、phtml、phar、html、htm、svg、js、mjs、xml、exe、sh、bat 等可执行或可被浏览器执行的类型；同时校验扩展名白名单与 `finfo` 嗅探的 MIME。
- 私有文件根目录：`runtime_path('plugins-data/cms/forms')`，文件名为随机 32 位十六进制，原文件名只存数据库；下载响应带 `Content-Disposition: attachment` 与 `X-Content-Type-Options: nosniff`。
- 防刷：蜜罐字段名 `_hp`（被填写则静默返回成功且不落库）；签名令牌最短 3 秒、最长 7200 秒有效，绑定 `site_id` 与 `form_id`；限流键 `cms_form:{site}:{form}:{ip}`，默认 10 分钟（600 秒）5 次，表单设置可调，系统上限 60 次。
- 签名密钥：环境变量 `CMS_FORM_SECRET`，未配置时生成 `runtime_path('plugins-data/cms/form.secret')`（目录 0700、文件 0600，写法参照 `app\common\crud\ConfirmationToken::resolveSecret`）。
- 验证码：答案存 `support\Cache`，键由签名验证码 ID 派生，300 秒有效，校验一次即删除。
- 状态固定为 `new`（新提交）、`processing`（跟进中）、`done`（已完成）、`invalid`（无效）；时间线只增不改。
- 能力权限码：`cms:form:list|manage|submissions|handle|export|delete`；后台控制器 `Forms` 的推导码为 `cms:forms:<方法>`。
- 新迁移文件：`admin/plugins/cms/database/migrations/004_forms.sql`，内容必须等于 `FormSchema::mysql()`；`plugin.json` 版本升到 `1.4.0`。
- 前后台所有新增界面文字同时提供中英文，键与插值参数一致。

## Review Focus

- 同一页面放了两个表单，或同一表单嵌入两次：字段 id、错误显示、提交互不串扰（Task 9 测试 `test_two_forms_on_one_page_render_independent_ids`）。
- 后台修改或删除了某个字段后再看旧提交：仍按提交时的字段快照显示，导出列按快照合并（Task 6 测试 `test_detail_uses_snapshot_after_form_changes`、`test_export_merges_columns_across_snapshots`）。
- 提交中途某个文件写盘失败或数据库失败：不留下孤儿文件、不留下半条提交（Task 6 测试 `test_failed_submission_leaves_no_files_or_rows`）。
- 访客在停用表单后用旧页面提交，或用另一站点的域名提交本站表单 code：返回 404 级错误且不落库（Task 9 测试 `test_disabled_or_foreign_form_rejects_submission`）。
- 两个处理人同时改同一条提交的状态：后到者得到 409，时间线不重复（Task 6 测试 `test_concurrent_transition_conflicts`）。

---

## 文件结构

后端（路径相对 `admin/plugins/cms/app/cms/`，安装副本相对 `admin/app/cms/`）：

| 文件 | 职责 |
|---|---|
| `service/FormSchema.php` | 四张表的定义、`install()`、`mysql()` 导出 |
| `service/FormFieldCatalog.php` | 字段类型目录、表单字段定义的校验与规范化 |
| `service/FormValueValidator.php` | 按字段定义校验访客提交的值 |
| `service/FormService.php` | 表单配置的增删改查、多语言文字解析、新提交计数 |
| `service/FormFileStorage.php` | 上传文件的类型/大小校验、私有存储、读取、删除 |
| `service/FormGuard.php` | 签名令牌、蜜罐、限流 |
| `service/FormCaptcha.php` | 无状态验证码 |
| `service/FormSubmissionService.php` | 提交入库、列表筛选、详情、备注、状态、批量无效、删除、导出 |
| `service/FormPermissionUpgrade.php` | 已有站点“站点管理员”角色补表单权限 |
| `service/FormMessages.php` | 访客可见的按钮、提示与错误文案（中英文，按站点语言取用） |
| `controller/FormEndpoint.php` | 前台公开接口：提交、刷新令牌、验证码图片 |
| `view/_forms/form.blade.php` | 内置表单片段（主题未提供 `partials/form` 时使用） |
| `view/_forms/result.blade.php` | 无 JS 提交的结果页 |

后台（相对 `admin/plugins/cms/app/admin/plugins/cms/`）：`controller/Forms.php`。

前端（相对 `admin/plugins/cms/web/src/admin/plugins/cms/`）：`pages/Forms.vue`、`components/FormsPanel.vue`、`components/FormSubmissionDrawer.vue`、`components/FormEditor.vue`、`components/formFields.ts`，修改 `api.ts`、`types.ts`、`components/CmsPage.vue`。公开脚本：`admin/plugins/cms/public/plugin-assets/cms/public/cms-form.js`。

测试：`admin/tests/Cms/Form*Test.php`、`web/tests/cms/forms*.spec.ts`。

---

### Task 1: 数据表与迁移

**Files:**
- Create: `admin/plugins/cms/app/cms/service/FormSchema.php`
- Create: `admin/plugins/cms/database/migrations/004_forms.sql`
- Modify: `admin/plugins/cms/Plugin.php`（`enabled()` 中调用 `FormSchema::install()`）
- Modify: `admin/tests/Cms/CmsTestCase.php`（`setUp` 先删后建 `FormSchema::tables()`）
- Modify: `admin/tests/Cms/SchemaTest.php`
- Test: `admin/tests/Cms/FormSchemaTest.php`

**Interfaces:**
- Produces: `FormSchema::tables(): array` 返回 `['cms_form','cms_form_submission','cms_form_file','cms_form_event']`；`FormSchema::install(): void`（缺表才建，可重复执行）；`FormSchema::mysql(): string`（首行注释 `-- CMS 1.4.0 custom forms; generated from FormSchema.php.`，与 `TemplateTagRemarkSchema::mysql()` 同样的导出方式）。
- 列（命名可微调，但下游任务依赖这些字段）：
  - `cms_form`：`id, site_id, code(64), name(100), status(tinyint 1=启用), fields(json), texts(json：locale → 文字覆盖), settings(json), lock_version, created_at, updated_at`；唯一 `(site_id, code)`。
  - `cms_form_submission`：`id, site_id, form_id, locale(20), fields(json 快照), values(json), search_text(longText), status(20 默认 new), source_url(1024), ip(45), user_agent(255), member_id(nullable), lock_version, handled_at(nullable), created_at, updated_at`；索引 `(site_id, status, id)`、`(site_id, form_id, status, id)`。
  - `cms_form_file`：`id, site_id, submission_id, field(64), original_name(255), mime(100), size(unsigned bigint), sha256(64), path(255), created_at`；索引 `(site_id, submission_id)`。
  - `cms_form_event`：`id, site_id, submission_id, type(20：created|note|status), admin_id(nullable), body(text), from_status(20), to_status(20), created_at`；索引 `(site_id, submission_id, id)`。

- [ ] **Step 1: 写失败测试** `FormSchemaTest::test_install_is_idempotent_and_creates_all_tables`（调用两次 `install()`，断言四张表都存在）；`SchemaTest` 中迁移清单断言改为 `['001_cms.sql','002_template_tag_remark.sql','003_remove_legacy_site_role.sql','004_forms.sql']`，并新增 `self::assertSame(FormSchema::mysql(), file_get_contents(.../004_forms.sql))`。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormSchemaTest.php tests/Cms/SchemaTest.php`，预期失败（类不存在）。
- [ ] **Step 3: 实现 `FormSchema`**，用 `php -r` 输出 `FormSchema::mysql()` 写入 `004_forms.sql`；`Plugin::enabled()` 增加 `\app\cms\service\FormSchema::install();`；`CmsTestCase::setUp` 增加对应的删表与建表。
- [ ] **Step 4: 运行** 同上命令，预期全部通过；再跑 `vendor/bin/phpunit tests/Cms` 确认无回归。
- [ ] **Step 5: 同步安装副本并提交** `git commit -m "feat(cms): 自定义表单数据表与迁移"`

### Task 2: 字段目录与值校验

**Files:**
- Create: `service/FormFieldCatalog.php`、`service/FormValueValidator.php`
- Test: `admin/tests/Cms/FormFieldCatalogTest.php`、`admin/tests/Cms/FormValueValidatorTest.php`

**Interfaces:**
- Produces:
  - `FormFieldCatalog::TYPES`（规格的 17 种类型）；`FormFieldCatalog::DATA_TYPES`（去掉 `paragraph`、`divider`）。
  - `FormFieldCatalog::normalize(array $fields): array`：校验并返回规范化字段列表，非法时抛 `\InvalidArgumentException`（code 422）。字段结构：`['name','type','label','placeholder','help','required','default','width'(full|half),'options'(list<['value','label']>),'multiple'(bool,仅 select),'min','max'(数字/长度/选择数量/日期),'integer'(bool,仅 number),'accept'(list<string> 类别或扩展名,仅 file/image),'max_size'(字节),'max_files'(int),'value'(hidden 固定值),'from_query'(bool,hidden),'html'(paragraph/consent 已清洗 HTML)]`。字段数上限 100，`name` 匹配 `^[a-z][a-z0-9_]{0,63}$` 且表单内唯一（展示类字段自动生成 `_p1`、`_d1` 名称）。
  - `FormValueValidator::validate(array $fields, array $input, array $fileCounts): array{values: array<string,mixed>, errors: array<string,string>}`：只处理 `DATA_TYPES` 中的非文件字段；`$fileCounts` 为“字段名 → 已上传个数”，用于文件字段的必填与数量校验；错误文案用中文键名（由调用方按语言翻译），例如 `required`、`too_long`、`invalid_email`、`invalid_phone`、`invalid_option`、`out_of_range`、`invalid_date`、`must_accept`、`too_many_files`。
- [ ] **Step 1: 写失败测试**
  - `test_normalize_rejects_unknown_type_duplicate_name_and_limits_over_hard_caps`（`max_size` 大于 20MB、`max_files` 大于 10 时抛异常）。
  - `test_normalize_sanitizes_consent_and_paragraph_html`（`<script>` 被去掉，`<a href>` 保留）。
  - 每种值类型各一个用例：`test_email_phone_number_and_options_are_validated_server_side`、`test_checkbox_min_max_selections`、`test_date_datetime_time_formats_and_range`、`test_required_consent_must_be_true`、`test_hidden_from_query_is_truncated_to_500`、`test_text_lengths_cap_at_2000_and_20000`。
  - `test_file_required_and_max_files_use_counts`。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormFieldCatalogTest.php tests/Cms/FormValueValidatorTest.php`，预期失败。
- [ ] **Step 3: 实现两个类**；HTML 清洗调用现有 `HtmlSanitizer`；文本截断与去控制字符参照 `Input::text`。
- [ ] **Step 4: 运行** 同上，预期通过。
- [ ] **Step 5: 同步并提交** `git commit -m "feat(cms): 表单字段目录与服务端校验"`

### Task 3: 表单配置服务

**Files:**
- Create: `service/FormService.php`
- Test: `admin/tests/Cms/FormServiceTest.php`

**Interfaces:**
- Consumes: `FormFieldCatalog::normalize`（Task 2）。
- Produces:
  - `FormService::list(string $siteId): array`（含每个表单的 `submission_count` 与 `new_count`）。
  - `FormService::get(string $siteId, string $formId): array`。
  - `FormService::save(string $siteId, array $input): array`：新建（无 `id`）或更新（带 `id` 与 `lock_version`，版本不一致抛 409）；`code` 用 `Input::code`，站点内唯一（冲突抛 409）；`settings` 规范化为 `['success_message','submit_text','captcha'(bool),'rate_limit'(1–60, 默认 5)]`；`texts` 为 `locale → ['name','success_message','submit_text','fields' => [name => ['label','placeholder','help','options' => [value => label]]]]`，`locale` 必须属于站点语言。
  - `FormService::setStatus(string $siteId, string $formId, bool $enabled, int $version): array`。
  - `FormService::delete(string $siteId, string $formId): void`：存在提交时抛 409（`表单已有提交，只能停用`）。
  - `FormService::resolve(string $siteId, string $code, string $locale): ?array`：只返回启用的表单，字段与文字已按“请求语言 → 站点默认语言 → 字段原值”合并。
  - `FormService::newCount(string $siteId): int`。
- [ ] **Step 1: 写失败测试** `test_save_validates_code_uniqueness_and_optimistic_lock`、`test_texts_fall_back_to_default_locale`、`test_resolve_hides_disabled_and_other_site_forms`、`test_delete_refuses_when_submissions_exist`、`test_settings_rate_limit_is_clamped_to_60`。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormServiceTest.php`，预期失败。
- [ ] **Step 3: 实现**；写操作放在事务内并先 `SiteService::lock($siteId)`（与现有服务一致）。
- [ ] **Step 4: 运行** 预期通过。
- [ ] **Step 5: 同步并提交** `git commit -m "feat(cms): 表单配置服务"`

### Task 4: 私有文件存储

**Files:**
- Create: `service/FormFileStorage.php`
- Test: `admin/tests/Cms/FormFileStorageTest.php`

**Interfaces:**
- Produces:
  - `FormFileStorage::__construct(?string $root = null)`（默认 `runtime_path('plugins-data/cms/forms')`，测试传临时目录）。
  - `FormFileStorage::inspect(array $field, array $upload): array{ext:string,mime:string,size:int,name:string}`：`$upload` 形如 `['tmp' => 路径, 'name' => 原名, 'size' => 字节]`；按字段 `accept`、`max_size` 与全局黑名单、`finfo` 嗅探校验，失败抛 `\InvalidArgumentException` 并带错误键 `invalid_file_type` 或 `file_too_large`。类别：`document`（pdf doc docx xls xlsx ppt pptx txt csv rtf odt ods）、`image`（jpg jpeg png gif webp bmp）、`archive`（zip rar 7z tar gz）；`image` 字段只接受 `image` 类别。
  - `FormFileStorage::store(string $siteId, array $inspected, string $tmpPath): array{path:string,sha256:string}`：复制到 `{root}/{siteId}/{Ym}/{32位随机hex}`，目录 0700、文件 0600；返回相对 `root` 的路径。
  - `FormFileStorage::absolute(string $siteId, string $path): string`（拒绝 `..`、符号链接，且必须位于 `{root}/{siteId}/` 下）。
  - `FormFileStorage::delete(string $siteId, string $path): void`。
- [ ] **Step 1: 写失败测试** `test_rejects_executable_types_even_with_allowed_extension`（内容为 `<?php` 的 `.pdf`）、`test_image_field_rejects_documents`、`test_size_over_field_limit_is_rejected`、`test_store_writes_private_file_outside_public_dir`（断言路径不在 `public_path()` 下、权限 0600）、`test_absolute_refuses_traversal_and_foreign_site`。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormFileStorageTest.php`，预期失败。
- [ ] **Step 3: 实现**
- [ ] **Step 4: 运行** 预期通过。
- [ ] **Step 5: 同步并提交** `git commit -m "feat(cms): 表单附件私有存储"`

### Task 5: 防刷与验证码

**Files:**
- Create: `service/FormGuard.php`、`service/FormCaptcha.php`
- Test: `admin/tests/Cms/FormGuardTest.php`

**Interfaces:**
- Produces:
  - `FormGuard::__construct(?string $secret = null, ?\Closure $clock = null)`（密钥解析见 Global Constraints；`$clock` 返回秒级时间戳，供测试）。
  - `FormGuard::issue(string $siteId, string $formId): string`；`FormGuard::verify(string $token, string $siteId, string $formId): void`（失败抛 `\InvalidArgumentException`，错误键 `token_invalid`、`too_fast`、`token_expired`）。
  - `FormGuard::isHoneypot(array $input): bool`（`_hp` 非空）。
  - `FormGuard::throttle(string $siteId, string $formId, string $ip, int $limit): void`（超限抛错误键 `too_many_requests`，code 429；内部 `Limiter::attempt("cms_form:{$siteId}:{$formId}:{$ip}", $limit, 600)`）。
  - `FormCaptcha::issue(): array{id:string, image:string}`（`image` 为 data URI 或二进制，由实现者定）；`FormCaptcha::verify(string $id, string $answer): bool`（一次性）。
- [ ] **Step 1: 写失败测试** `test_token_binds_site_and_form`、`test_token_rejects_under_3_seconds_and_over_7200_seconds`、`test_tampered_token_is_rejected`、`test_throttle_blocks_sixth_attempt_in_window`、`test_captcha_is_single_use`、`test_secret_file_is_generated_with_0600`。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormGuardTest.php`，预期失败。
- [ ] **Step 3: 实现**
- [ ] **Step 4: 运行** 预期通过。
- [ ] **Step 5: 同步并提交** `git commit -m "feat(cms): 表单防刷与验证码"`

### Task 6: 提交与处理服务

**Files:**
- Create: `service/FormSubmissionService.php`
- Test: `admin/tests/Cms/FormSubmissionServiceTest.php`

**Interfaces:**
- Consumes: `FormService::resolve`、`FormValueValidator::validate`、`FormFileStorage::inspect/store/delete/absolute`、`FormGuard::*`、`FormCaptcha::verify`。
- Produces:
  - `FormSubmissionService::submit(array $site, string $locale, string $code, array $input, array $uploads, array $meta): array`：返回 `['status' => 'ok', 'message' => 成功提示]` 或抛校验异常 `FormValidationException`（新建于同文件或 `service/FormValidationException.php`，携带 `errors: array<string,string>`）。`$uploads` 为“字段名 → list<['tmp','name','size']>”；`$meta` 为 `['ip','user_agent','source_url','member_id','token','captcha_id','captcha']`。流程：蜜罐 → 令牌 → 限流 → 验证码（表单启用时）→ 值校验 → 文件 inspect（合计不超过 50MB）→ 事务内写提交、`created` 事件、逐个 `store` 文件并写 `cms_form_file`；任何异常时删除已写文件再抛出。`search_text` 为所有文本类值的拼接（截断到 20000）。
  - `FormSubmissionService::list(string $siteId, array $filters, int $page): array{list, total, page, pageSize}`；`filters`：`form_id`、`status`（数组，默认 `['new','processing']`）、`from`、`to`、`keyword`（匹配 `search_text`）。列表项含 `summary`（前两个文本类字段值）。
  - `FormSubmissionService::detail(string $siteId, string $id): array`（字段按快照排列、`files`、`events`）。
  - `FormSubmissionService::note(string $siteId, string $id, string $adminId, string $body): array`（备注 1–2000 字）。
  - `FormSubmissionService::transition(string $siteId, string $id, string $adminId, string $status, int $version): array`（版本不一致抛 409；写 `status` 事件；`done`/`invalid` 时更新 `handled_at`）。
  - `FormSubmissionService::invalidate(string $siteId, array $ids, string $adminId): int`。
  - `FormSubmissionService::delete(string $siteId, array $ids): int`（连同文件、事件）。
  - `FormSubmissionService::export(string $siteId, array $filters): \Generator`（逐行产出 CSV 行数组；列为“提交时间、表单、状态、来源页面”+ 按所有涉及快照合并的字段标签；文件列输出原文件名）。
  - `FormSubmissionService::file(string $siteId, string $fileId): array{absolute,name,mime}`。
- [ ] **Step 1: 写失败测试** `test_submit_stores_values_snapshot_files_and_created_event`、`test_honeypot_returns_ok_without_rows`、`test_validation_errors_are_reported_per_field`、`test_total_upload_over_50mb_is_rejected`、`test_failed_submission_leaves_no_files_or_rows`、`test_list_filters_by_status_form_time_and_keyword`、`test_detail_uses_snapshot_after_form_changes`、`test_note_and_transition_append_events`、`test_concurrent_transition_conflicts`、`test_export_merges_columns_across_snapshots`、`test_delete_removes_files`、`test_other_site_cannot_read_submission_or_file`。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormSubmissionServiceTest.php`，预期失败。
- [ ] **Step 3: 实现**
- [ ] **Step 4: 运行** 预期通过。
- [ ] **Step 5: 同步并提交** `git commit -m "feat(cms): 表单提交与跟进处理服务"`

### Task 7: 权限与升级补齐

**Files:**
- Modify: `service/PermissionCatalog.php`（新增 `form` 组、`CEILINGS`、`presets()`）
- Create: `service/FormPermissionUpgrade.php`
- Modify: `admin/plugins/cms/Plugin.php`（`enabled()` 在 `SiteRoleSchema::bootstrap()` 之后调用 `FormPermissionUpgrade::backfill()`）
- Modify: `admin/plugins/cms/plugin.json`（`adminWeb.permissions` 增加 6 个能力权限及名称；`permissionGroups` 增加 `"form": "表单", "forms": "表单"`）
- Test: `admin/tests/Cms/FormPermissionTest.php`

**Interfaces:**
- Produces:
  - `PermissionCatalog::groups()['form'] = ['name'=>'表单','menu'=>'list','actions'=>['list'=>'查看表单','manage'=>'管理表单','submissions'=>'查看提交','handle'=>'跟进处理','export'=>'导出提交','delete'=>'删除提交']]`。
  - `CEILINGS`：`cms:form:list` → `['cms:forms:index','cms:forms:summary','cms:sites:context']`；`cms:form:manage` → `['cms:forms:save','cms:forms:status','cms:forms:delete']`；`cms:form:submissions` → `['cms:forms:submissions','cms:forms:detail','cms:forms:file']`；`cms:form:handle` → `['cms:forms:note','cms:forms:transition','cms:forms:invalidate']`；`cms:form:export` → `['cms:forms:export']`；`cms:form:delete` → `['cms:forms:remove']`（方法名与 Task 8 一致）。
  - `presets()`：`administrator` 自动包含（它取全部 `operations()`）；`editor`、`publisher` 增加 `cms:form:list`、`cms:form:submissions`、`cms:form:handle`；`viewer` 增加 `cms:form:list`、`cms:form:submissions`。
  - `FormPermissionUpgrade::backfill(): int`：为每个未删除站点中 `code='administrator'` 的角色补齐缺失的 `cms:form:*`，返回新增行数；重复执行返回 0。
- [ ] **Step 1: 写失败测试** `test_presets_grant_form_operations_per_role`、`test_backfill_only_touches_existing_administrator_roles_and_is_idempotent`（预先建站点并删掉管理员角色的表单权限；编辑角色不变）、`test_ceilings_map_to_forms_controller_codes`（断言每个 CEILING 码都能由 `PermissionCode::derive(Forms::class, 方法)` 得到）。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormPermissionTest.php`，预期失败。
- [ ] **Step 3: 实现**（`CEILINGS` 的接口码要等 Task 8 的控制器存在后才能通过推导断言，因此本任务先建 `Forms` 控制器的空方法骨架，带 `#[Get]/#[Post]` 路由注解，方法体返回 501，Task 8 再填充）。
- [ ] **Step 4: 运行** 同上与 `vendor/bin/phpunit tests/Cms/SiteCeilingTest.php tests/Cms/PermissionTest.php tests/Cms/SchemaTest.php`，预期通过。
- [ ] **Step 5: 同步并提交** `git commit -m "feat(cms): 表单站点权限与已有站点管理员补齐"`

### Task 8: 后台管理接口

**Files:**
- Modify: `admin/plugins/cms/app/admin/plugins/cms/controller/Forms.php`
- Test: `admin/tests/Cms/FormAdminTest.php`

**Interfaces:**
- Consumes: Task 3、Task 6 的服务；`Base::site()`、`Base::action()`、`Base::actor()`、`Base::locale()`。
- Produces（`#[RouteGroup('/admin/plugins/cms')]` 下，路由前缀 `/forms`，方法名即推导码中的方法段）：
  - `GET /forms` → `index`（`site(cms:form:list)`，返回 `FormService::list`）；`GET /forms/summary` → `summary`（返回 `['new_count' => …]`）。
  - `POST /forms/save` → `save`、`POST /forms/status` → `status`、`POST /forms/delete` → `delete`（均 `cms:form:manage`）。
  - `GET /forms/submissions` → `submissions`、`GET /forms/submissions/detail` → `detail`、`GET /forms/files/download` → `file`（`cms:form:submissions`；`file` 直接返回文件响应，头见 Global Constraints）。
  - `POST /forms/submissions/note` → `note`、`POST /forms/submissions/transition` → `transition`、`POST /forms/submissions/invalidate` → `invalidate`（`cms:form:handle`）。
  - `GET /forms/submissions/export` → `export`（`cms:form:export`；流式 CSV，UTF-8 BOM，文件名 `forms-{site_code}-{Ymd}.csv`）。
  - `POST /forms/submissions/remove` → `remove`（`cms:form:delete`）。
- [ ] **Step 1: 写失败测试**（参照现有 `tests/Cms` 中对 CMS 后台控制器的调用方式）：`test_each_endpoint_requires_its_site_operation`（数据驱动：无权限返回 403）、`test_editor_can_handle_but_not_export_or_delete`、`test_file_download_headers_and_site_scope`、`test_export_streams_bom_csv`。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormAdminTest.php`，预期失败。
- [ ] **Step 3: 实现**
- [ ] **Step 4: 运行** 同上，并运行 `vendor/bin/phpunit tests/AuthRequirementSnapshotTest.php`；新增路由需要先人工确认鉴权要求（均为 `guard=plugin`、`login=true`、对应推导码），再运行 `php tests/fixtures/update-auth-requirements.php` 更新快照，确认 diff 只新增 `forms` 相关路由。
- [ ] **Step 5: 同步并提交** `git commit -m "feat(cms): 表单后台管理接口"`

### Task 9: 前台渲染与公开提交

**Files:**
- Create: `controller/FormEndpoint.php`、`service/FormMessages.php`、`view/_forms/form.blade.php`、`view/_forms/result.blade.php`
- Create: `admin/plugins/cms/public/plugin-assets/cms/public/cms-form.js`
- Modify: `controller/Index.php`（兜底路由的排除改为 `(?!(?:member|forms)(?:/|$))`）
- Modify: `tags/BuiltinTags.php`（注册标签 `form`）

> 与设计的差异：设计写“四套内置主题各提供 `partials/form`”，现在内置主题已有八套。改为内置一个共享片段 `view/_forms/form.blade.php`（使用中性 class，交给主题 CSS 定样式），主题需要时可提供 `partials/form` 覆盖。
- Modify: `admin/plugins/cms/plugin.json`（如公开资源需登记 `cms-form.js`，按现有 `cms.js` 的方式）
- Test: `admin/tests/Cms/FormPublicTest.php`、`web/tests/cms/formsPublic.spec.ts`

**Interfaces:**
- Consumes: `FormService::resolve`、`FormSubmissionService::submit`、`FormGuard::issue`、`FormCaptcha::issue`。
- Produces:
  - `FormMessages::for(string $locale): array`：返回访客可见文案（提交按钮默认文字、各错误键的说明、页面过期、提交过于频繁、返回链接等）；内置 `zh-cn` 与 `en-us`，其他语言回退 `en-us`。`FormValueValidator` 与 `FormGuard` 产出的错误键在这里翻译。
  - 标签 `form`：参数 `name`（必填，字符串）。表单不存在或停用时返回空 `TrustedHtml('')`。优先渲染当前主题 `partials/form`（若存在），否则渲染 `view/_forms/form.blade.php`。传入模板的变量：`form`（已解析文字的字段与设置）、`action`（`/cms/forms/{code}`）、`token`、`uid`（每次渲染唯一的 DOM 前缀，避免同页重复 id）、`captcha`（启用时的 `['id','image']`）、`ui`（当前语言的按钮与错误文案）。
  - `POST /cms/forms/{code}`：`multipart/form-data`。请求头 `Accept: application/json` 时返回 JSON：成功 `{code:0, data:{message}}`，校验失败 `422 {code:422, data:{errors:{field: 文案}}}`，令牌问题 `{code:419}`，限流 `429`；否则渲染 `result.blade.php`（成功提示或错误列表 + 返回链接）。站点由 `SiteService::byHost($request->host())` 确定，找不到返回 404。`member_id` 仅在 `Authorization: Bearer` 能被 `MemberApiSessionService::authenticate` 识别时记录。
  - `POST /cms/forms/{code}/token` → `{code:0, data:{token}}`；`GET /cms/forms/{code}/captcha` → 验证码图片（`Cache-Control: no-store`）。
  - `cms-form.js`：查找 `form[data-cms-form]`，以 `fetch` 提交；收到 419 时先调用 `/token` 刷新再重试一次；按 `errors` 在对应字段旁显示；成功后替换为成功提示。无框架依赖。
- [ ] **Step 1: 写失败测试**（PHP）`test_tag_renders_fields_with_escaped_admin_text`（标签含 `<script>` 时被转义）、`test_tag_returns_empty_for_missing_or_disabled_form`、`test_database_template_tag_renders_form`（`{cms:form name="contact" /}` 经受限解释器渲染出同样的表单）、`test_visitor_messages_follow_site_locale`、`test_two_forms_on_one_page_render_independent_ids`、`test_theme_partial_overrides_builtin_form`、`test_json_submission_success_and_field_errors`、`test_html_fallback_renders_result_page`、`test_disabled_or_foreign_form_rejects_submission`、`test_catch_all_route_no_longer_shadows_form_endpoints`；（前端）`formsPublic.spec.ts`：在 jsdom 中加载 `cms-form.js`，断言 422 时错误显示到对应字段、419 时先刷新令牌再重试、成功时替换为提示。
- [ ] **Step 2: 运行** `vendor/bin/phpunit tests/Cms/FormPublicTest.php` 与 `npx vitest run tests/cms/formsPublic.spec.ts`，预期失败。
- [ ] **Step 3: 实现**；模板中所有后台文字用 `{{ }}`，只有已清洗的 `html` 字段用 `{!! !!}`。
- [ ] **Step 4: 运行** 同上，并运行 `vendor/bin/phpunit tests/Cms`，预期全部通过。
- [ ] **Step 5: 同步（含发布 `cms-form.js` 到 `admin/public/plugin-assets/cms/public/`）并提交** `git commit -m "feat(cms): 表单前台渲染与公开提交"`

### Task 10: 后台“表单”页面

**Files:**
- Create: `pages/Forms.vue`、`components/FormsPanel.vue`、`components/FormSubmissionDrawer.vue`、`components/FormEditor.vue`、`components/formFields.ts`
- Modify: `api.ts`、`types.ts`、`components/CmsPage.vue`（`page` 增加 `'forms'`，`v-if="page==='forms'"` 渲染 `FormsPanel`）
- Modify: `admin/plugins/cms/plugin.json`（`components.Forms`、路由 `/plugins/cms/forms`（名称 `Plugin_cms_Forms`，`meta.permission: cms:forms:index`）、菜单项“表单”放在“会员投稿”之后）
- Modify: `web/src/shared/locales/zh-CN/cms.ts`、`web/src/shared/locales/en-US/cms.ts`
- Test: `web/tests/cms/forms.spec.ts`

**Interfaces:**
- Consumes: Task 8 的接口。
- Produces：
  - `cmsApi` 新增：`forms(site_id)`、`formSummary(site_id)`、`saveForm(data)`、`formStatus(data)`、`deleteForm(data)`、`formSubmissions(params)`、`formSubmission(site_id, id)`、`noteFormSubmission(data)`、`transitionFormSubmission(data)`、`invalidateFormSubmissions(data)`、`removeFormSubmissions(data)`、`formExportUrl(params)`、`formFileUrl(site_id, file_id)`。
  - `formFields.ts`：字段类型元数据（类型 → 中文名、可配置属性、默认值），`FormEditor` 与测试共用。
  - `FormsPanel`：两个标签“提交”（默认，标签显示 `新提交 N`）与“表单管理”；按 `can` 控制操作按钮显示（`cms:form:handle` / `export` / `delete` / `manage`）。
- [ ] **Step 1: 写失败测试** `forms.spec.ts`：`默认只列出新提交和跟进中并显示新提交数量`、`详情抽屉写备注并改状态后刷新时间线`、`没有导出和删除权限时不显示对应按钮`、`字段编辑器按类型显示可配置属性并校验字段标识`、`复制嵌入代码同时给出 Blade 与模板标签写法`。
- [ ] **Step 2: 运行** `npx vitest run tests/cms/forms.spec.ts`，预期失败。
- [ ] **Step 3: 实现**
- [ ] **Step 4: 运行** `npx vitest run tests/cms` 与 `npx vue-tsc --noEmit`，预期通过；`npm run check:i18n` 不新增缺失词条。
- [ ] **Step 5: 同步并提交** `git commit -m "feat(cms): 后台表单页面"`

### Task 11: 收尾与文档

**Files:**
- Modify: `admin/plugins/cms/plugin.json`（版本 `1.4.0`）、`admin/plugins/cms/README.md`、`docs/cms-blade.md`（表单标签用法与主题 `partials/form` 覆盖方式）
- Create: `docs/superpowers/plans/2026-11-09-cms-custom-forms-verification.md`

- [ ] **Step 1: 运行完整验证** `cd admin && vendor/bin/phpunit tests/Cms tests/Qa tests/AuthRequirementSnapshotTest.php tests/PluginManifestRouteContractTest.php`，`php webman plugin:validate cms`，`cd web && npx vitest run tests/cms && npx vue-tsc --noEmit`；预期全部通过。
- [ ] **Step 2: 在开发库执行插件更新**（停用 → 更新 → 启用，执行 `004_forms.sql` 与权限补齐），在浏览器中用一个主题嵌入表单完成一次带附件的提交，并在后台走完一次“备注 → 已完成”。
- [ ] **Step 3: 写验证记录**（命令与结果、截图路径）
- [ ] **Step 4: 提交** `git commit -m "docs(cms): 自定义表单使用说明与验证记录"`
