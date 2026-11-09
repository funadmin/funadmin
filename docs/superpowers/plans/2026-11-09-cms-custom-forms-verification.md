# CMS 自定义表单验证记录

日期：2026-11-09。规格见 `docs/superpowers/specs/2026-11-09-cms-custom-forms-design.md`，实现为分支 Rbac 上 cc342268..067ec433 的 CMS 提交。本任务把插件版本升到 1.4.0，补充 README 和 `docs/cms-blade.md` 的表单说明。

无截图（命令行验证）。

## 版本号

- `admin/plugins/cms/plugin.json`：`1.3.3` → `1.4.0`。
- 在 CMS 相关路径中搜索 `1.3.3`，结果如下：
  - `admin/tests/Cms/CommunityWiringTest.php` 断言了版本号，已同步改为 `1.4.0`。
  - `003_remove_legacy_site_role.sql` 的注释和 2026-11-09 主题验证记录是历史文字，不需要修改。
  - QA 插件要求 `cms >=1.3.0`，1.4.0 满足，无需改动。
- README 安装步骤里的迁移文件名原先写的是过时的 `002_site_roles` / `003_member_contributions`，已改为实际的 `001_cms.sql` 至 `004_forms.sql`。

## 第一步：完整验证

| 命令 | 结果 |
|---|---|
| `cd admin && vendor/bin/phpunit tests/Cms tests/Qa tests/AuthRequirementSnapshotTest.php tests/PluginManifestRouteContractTest.php` | 通过：280 个测试，2911 个断言，跳过 4 个。被跳过的是 MySqlCommunityUpgradeTest、MySqlLockTest、MySqlSchemaTest 和 MySqlSiteConfigurationTest，它们需要任务专用的 MySQL 库。 |
| `php webman plugin:validate cms` | `{"valid":true,"code":"cms","version":"1.4.0","message":"插件校验通过","warnings":[]}` |
| `cd web && npx vitest run tests/cms` | 共跑 5 次，3 次 25 个文件、166 个用例全部通过，2 次失败 1 个用例，失败项是 `tests/cms/richTextEditing.spec.ts > preserves legacy table/video markup…`。 |
| `npx vue-tsc --noEmit` | 无输出，退出码 0 |

关于 richTextEditing 的间歇失败，判断为与本功能无关，依据如下：

- 被测组件是宿主共享的 `web/src/shared/components/RichTextEditor/index.vue`，它和测试文件最后一次修改都在 61a3d17d。
- 工作区里这两处没有未提交的改动。
- 本功能 15 个提交改过的 78 个文件里不含 RichTextEditor，也不含该测试文件。
- 同一命令重复运行时有时通过、有时失败，是不稳定用例，本任务未修改它。

## 第二步：开发库端到端验证

开发环境：共享的 webman 服务 `127.0.0.1:8787`（master pid 11750），数据库 `funadmin_rbac`，站点 1 绑定域名 `www.demotest.com`。8088 端口的本地 Nginx 当时没有运行，因此 HTTP 请求直接发到 `127.0.0.1:8787/cms/...`，并带上 `Host: www.demotest.com`，效果与 Nginx 把 `/` 转发到 `/cms/` 相同。

### 插件生命周期（服务层）

后台插件接口需要登录会话和 CSRF，命令行下做不到。`php webman plugin:enable/disable` 是 webman 自带的命令，不走宿主的生命周期。所以这一步写了 `/tmp/cms-e2e/lifecycle.php`，直接调用宿主 `PluginService`。后台 `SystemPlugin` 控制器背后也是这个服务，调用方式相同：用 `withWorkerReload` 包住 `disablePlugin` → `updatePlugin('cms', true)` → `enablePlugin`。

1. 停用：成功，并请求了 worker 平滑重载。
2. 更新：迁移阶段成功执行了 `004_forms`，四张表 `cms_form`、`cms_form_submission`、`cms_form_file`、`cms_form_event` 已建好。随后在资源发布阶段失败，错误是“拒绝覆盖未登记的核心文件：public:plugin-assets/cms/public/cms-form.js”。插件因此进入 `failed` 状态，启用被拒绝，提示“插件数据库迁移尚未完成”。
   - 失败原因：开发工作区约定把插件源码手工同步到安装副本，这些文件没有登记到宿主的资源台账。
   - 用只读脚本 `/tmp/cms-e2e/conflicts.php` 检查，有 6 个已存在但未登记的发布目标：`cms-form.js`，以及 `web/src/admin/plugins/cms` 下的 FormSubmissionDrawer.vue、formFields.ts、FormsPanel.vue、FormEditor.vue、Forms.vue。它们的内容都与源码一致。
   - 原生 App 发布单元 `application:cms` 和 `admin-plugins:controller:cms` 中，手工同步进去的文件同样对不上台账（有修改过的，也有未登记的）。
   - 宿主没有提供“接管已有文件”的选项。要让发布通过，只能把大量已纳入 git 的安装副本先回退成旧内容，这会影响其他会话的工作区，所以没有这样做。
3. 恢复启用：脚本是 `/tmp/cms-e2e/recover.php`。
   - 先调用宿主的 `migratePlugin('cms')`。迁移已执行过，这次没有新执行任何文件；记录变为 `db_version=004_forms`，迁移待办和错误信息清空，状态为 `disabled`。
   - 再按宿主启用流程逐步执行，只跳过资源发布（安装副本已与 1.4.0 源码逐字节一致）：
     - 插件启用钩子 `Plugin::enabled()`，包含 `FormPermissionUpgrade::backfill` 和 `FormSchema::install`；
     - `registerResources`，登记权限和菜单；
     - 直接在数据库中把插件记录改为 `lifecycle_state=enabled`、`status=1`。说明：宿主没有“跳过资源发布”的启用入口，所以启用标记是用 SQL 直接写入的，并非经由宿主启用流程；
     - `refreshActivationCache` 重建激活缓存；
     - `WorkerReloader::production()->reload()`，即 SIGUSR1 平滑重载。
   - CMS 从约 17:54:50 停用到 17:58:35 恢复启用，期间公开站点不可用约 4 分钟。
4. 最终状态：`version=1.4.0`，`db_version=004_forms`，`migration_pending=0`，`last_error=NULL`，`lifecycle_state=enabled`。10 个 CMS 菜单全部启用，`cms:form:*` 能力权限和 `cms:forms:*` 接口权限都已登记。
5. 权限补齐：站点 1 和站点 2 的 `administrator` 角色各补上 6 个 `cms:form:*` 权限，只读、编辑、发布角色没有变化，符合预期。

遗留问题：这个工作区的资源台账仍是 1.4.0 之前的哈希，以后对 CMS 做停用→启用、更新或重部署时，会在资源发布阶段遇到同样的冲突，需要先处理台账与安装副本的差异。

### 公开提交（HTTP）

测试表单通过服务层 `FormService::save` 创建在站点 1，`code=e2e_contact`，名称“E2E 联系表单（可删除）”。字段为姓名（必填）、邮箱（必填）、留言、附件（文档类，1 个）。

1. 页面嵌入（服务层）。站点里没有任何主题或页面嵌入表单。为了不改共享的主题文件、不新建会长期占用路径的页面，嵌入效果用真实标签注册表渲染 `{cms:form name="e2e_contact" /}` 来检查，脚本是 `/tmp/cms-e2e/e2e-setup.php`。
   - 输出含 1 个 `data-cms-form`，各字段的 `data-field`，4 个 `data-error`，以及 `_hp`、`_token`、`action="/forms/e2e_contact"`，并自动追加了 `cms-form.js`。
   - 不存在的表单输出空串。
   - 另用临时 Blade 模板确认：`{{ $cms->tag('form', …) }}` 正常输出，`{!! … !!}` 会抛出“TrustedHtml could not be converted to string”。文档已按前者编写。
2. `GET /cms/forms/e2e_contact/captcha`：该表单未开启验证码，返回 404 JSON。
3. `POST /cms/forms/e2e_contact/token`：返回 107 字符的令牌。
4. 立即提交：返回 422“提交过快，请稍候再试”。
5. 等 4 秒后用 multipart 提交，带 `Accept: application/json` 和 PDF 附件 `e2e-attachment.pdf`：返回 200 `{"code":0,"msg":"success","data":{"message":"E2E 已收到"}}`。
6. 不带 JSON 请求头提交（模拟关闭 JS）：返回 200 `text/html` 结果页，含 `nosniff`、成功提示，返回链接为 `/contact.html`。
7. 填写蜜罐 `_hp=bot` 后提交：返回 200 成功，数据库里没有对应记录。

### 后台处理（服务层）

后台接口同样需要登录会话，这部分用 `/tmp/cms-e2e/e2e-admin.php` 直接调用 `FormSubmissionService`，管理员 id 记为 1。

- 新提交数为 2。查看附件提交的详情：状态 `new`，来源 `/`。
- 附件 `e2e-attachment.pdf`（`application/pdf`，45 字节）以随机 32 位十六进制文件名保存在 `runtime/plugins-data/cms/forms/` 下，用 `file()` 可以取回。
- 添加备注“E2E 备注：已电话联系”，再改为“已完成”：状态变为 `done`。时间线依次为 created → note → status(new→done)。新提交数降到 1。
- 导出：默认筛选“新提交 + 跟进中”时，只导出表头和 1 行未完成的提交，与后台“清空状态即全部、默认看未完成”的设计一致。

## 更正：旧 nginx 配置下的访客 IP

终审修复记录里曾写“仍使用 `$proxy_add_x_forwarded_for` 的旧配置结果仍然正确”，这是错的：当时 `ip()` 在受信任代理后优先读取 `X-Real-IP`，而只设置 `X-Forwarded-For` 的旧配置（旧 README 示例、nginx 常见写法、本机 `admin/runtime/cms-local-nginx/nginx*.conf`）会把访客伪造的 `X-Real-IP` 原样透传，访客可以轮换该头绕过按 IP 限流。

现已改为：直连地址为内网/回环时，有 `X-Forwarded-For` 就只取最后一段，没有时才用 `X-Real-IP`。`$proxy_add_x_forwarded_for` 追加的最后一段就是 nginx 看到的地址；新 README 示例把两个头都设为 `$remote_addr`，最后一段同样正确。README 升级步骤已要求把两个头改为 `$remote_addr`。本机两份 nginx 配置已补上 `proxy_set_header X-Real-IP $remote_addr;`（未启动或重载 nginx；该目录被 git 忽略）。

## 跳过的部分

- 浏览器里加载主题页面并截图：Nginx 8088 没有运行，也没有主题嵌入表单。页面渲染改为服务层检查，提交走 HTTP。
- 后台 HTTP 接口：没有管理员会话，改为服务层调用。
- 宿主生命周期中的资源发布：被资源台账冲突拦下，见上文。

## 需要清理的测试数据

- 站点 1（www.demotest.com）的表单 `e2e_contact`（id 1）和它的 2 条提交（id 1 已完成且带附件，另一条是“NoJS”）。已有提交的表单不能直接删除，要先在“提交”标签删除这两条提交（附件文件会一起删除），再到“表单管理”删除表单。
- 两个 `administrator` 角色新补的表单权限是升级的正常结果，不需要清理。
