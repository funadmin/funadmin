# 项目完整度补齐：验证记录

日期：2026-11-12　分支：Rbac　验证时 HEAD：`bc1321596`（加上本次的文档与 `phpunit.xml` 改动）

计划：`docs/superpowers/plans/2026-11-12-completeness-gaps.md`　设计：`docs/superpowers/specs/2026-11-12-completeness-gaps-design.md`　计划提交：`94d683b20`

## 1. 各任务交付内容

### Task 1 仪表盘系统概览（`b96fb7775`）

- 新接口 `GET /admin/dashboard/overview`（`SystemDashboard` + `DashboardOverviewService`），只需登录。按权限返回管理员、会员、附件、操作日志（今日数、近 7 天趋势、最近 10 条摘要）、插件（已安装、已启用）五个数据块；超级管理员返回全部。统计都通过模型查询，排除软删除；按管理员缓存 60 秒，缓存键含授权版本。
- `SystemAdmin::manageableQuery()` 的逻辑移到 `RoleScopeService::manageableAdminQuery()`，仪表盘的管理员数与管理员列表总数一致。
- 前端仪表盘去掉全部示例数据，改为真实卡片、7 天趋势图、最近日志、按菜单与权限生成的快捷入口；有加载、错误（可重试）和空状态；保留插件扩展点 `dashboard.cards`、`dashboard.loaded`、`dashboard.quickActions`。
- 测试：`admin/tests/DashboardOverviewTest.php`（7 个），`web/tests/views/dashboard-overview.spec.ts`（7 个）。

### Task 2 AI 备用模型与思考强度（`eb175140e`、`70e1c0cc6`、`a6f5e80ff`）

- `OpenAiCompatibleGateway` 按主模型、备用模型顺序尝试：网络错误、超时、5xx、429、上游明确表示模型不可用时切换；其他 4xx 不切换；流式输出开始后不再切换。每次切换写任务事件 `provider.fallback`，助手消息元数据记录 `model`、`requested_model`、`fallbacks`。
- 思考强度按协议传参：Chat 用 `reasoning_effort`，Responses 用 `reasoning.effort`，Messages 用 `thinking.budget_tokens`（low 1024 … ultra 65536，必须小于输出预算）。只放行模型能力里为主模型与全部备用模型声明过的档位。
- 修复轮：只有 Guzzle 传输层异常才算超时；“模型不可用”判断放到“已开始输出”判断之后；前端提示改用新键名并显示在控件下方；README 补充计费、错误 Base URL 与档位声明的说明。
- `a6f5e80ff`：MySQL 下比较消息元数据前先排序 JSON 键（测试问题，不是产品缺陷）。
- 测试：`admin/tests/AiFallbackReasoningTest.php`（35 个）、`tests/Console/AiConsumerExecutionTest.php` 新增端到端用例、`aiComponents.spec.ts` 新增用例。

### Task 3 问答删除、回收站、问题详情与回答管理（`3ff9833a7`、`2cd0945b1`、`c3cd93901`）

- 问答 1.2.0，迁移 `003_question_admin.sql`：`qa_question.pinned` 及索引；新表 `qa_answer`（主键复用 CMS 回答投稿 id，`hidden`、`deleted_at`）；只在本次新建该表时为已有回答补行，重复执行不会补回已彻底删除的回答。
- 后台独立页面“问答社区 → 问题管理”（`qa:questions:index`）：问题列表与详情、关闭/重新开放、置顶（版本乐观锁，冲突 409）、删除；回答隐藏/取消隐藏、删除；问题与回答两个回收站（恢复、彻底删除，二次确认）。共 13 个按方法推导的权限码；站点内复用 CMS 的 `cms:submission:list` / `cms:submission:review`。
- 只处理问答侧状态行，不删除、不恢复 CMS 投稿；删除问题不级联回答。
- 修复轮：独立页面与菜单、003 补行守卫、回答计数与详情口径一致、管理员重新开放问题时取消采纳；公共逻辑改为 `service/AdminEndpoint` trait，使插件清单校验通过。
- 测试：`tests/Console/MySqlQaQuestionAdminUpgradeTest.php`（入库）；`tests/Qa/QaAdminTest.php` 等（本地忽略目录）；`web/tests/qa/admin.spec.ts`。

### Task 4 问答前台 SEO 页面与会员附件（`73534856e`、`d02ed8c7d`）

- 问答 1.3.0，迁移 `004_attachments.sql`：新表 `qa_attachment`（软删除，无唯一键）。
- 会员提问与回答可用 multipart `attachments[]` 上传附件，复用 CMS `FormFileStorage` 的类型、嗅探与私有存储；每次最多 5 个、单个 5MB、合计 8MB；会员下载需登录并按公开状态鉴权；后台下载需 `qa:questions:attachment`。附件随问题或回答软删除、恢复，只在回收站彻底删除时删除记录与文件。
- 服务端渲染的 SEO 页面 `GET /qa/questions`、`/qa/questions/{id}`（Blade）：title、description、canonical、Open Graph、`rel=prev/next`、schema.org `QAPage` 结构化数据；站点按域名识别，与 CMS 前台相同。
- 会员门户（`QuestionsPortal.vue`）支持选择、显示和下载附件。
- 说明：主提交 `73534856e` 不是实现者创建的，提交信息为英文、未按约定格式；未改写历史。`d02ed8c7d` 拆分分组导入，使静态导入检查通过。
- 测试：`tests/Console/MySqlQaAttachmentUpgradeTest.php`（入库）；`tests/Qa/QaAttachmentTest.php`、`QaPublicPagesTest.php`（本地忽略目录）；`web/tests/qa/*.spec.ts`。

### Task 5 OSS 配置管理、S3 兼容驱动与连接测试（`380f7864a`）

- OSS 1.2.0，迁移 `003_s3_path_style.sql`：`oss_profile.path_style`。
- 配置软删除（当前配置 409）、回收站（恢复、彻底删除；仍有附件引用 `oss_<id>` 时 409，彻底删除不删云端对象）。驱动解析包含已删除配置，历史附件仍能读取和删除。
- 新供应商 `s3`（AWS S3、MinIO、R2 等），基于 `aws/aws-sdk-php`（`class_exists` 检测）；Endpoint 只接受 HTTPS，拒绝回环、链路本地、保留地址与混淆写法；只用静态凭据、不重试、关闭重定向。
- 连接测试：上传随机对象 → 读回校验 → 删除，逐步返回结果；用表单当前值，无需先保存；单请求 3 秒、整体不超过 15 秒。
- 页面全部 i18n，新增回收站对话框与逐步结果组件。
- 测试：`tests/Console/MySqlOssS3UpgradeTest.php`（入库）；`tests/Oss/` 新增 3 个文件（本地忽略目录）；`web/tests/oss/storagePage.spec.ts`。

### Task 6 接口文档覆盖（`fa16effe2`、`4a2fa3e1e`、`bc1321596`）

- CMS、文档、问答、OSS 的 22 个控制器补 145 个 `@OA` 操作；核心补 10 处（含安装向导、`AdminAuth` 路径参数、`FrontendHooks`、默认入口 `/admin/auth/*`）；`ApiDocumentation.php` 登记插件标签、`PluginEnvelope`、`PluginError`。
- 代码生成器为后台、插件与会员控制器生成注释；写入注释的实体标题经过转义，不能提前结束注释或注入注解。
- 防回退：`admin/tests/OpenApiCoverageTest.php` 与 `tests/Console/CrudGeneratedOpenApiTest.php`，检查器在 `admin/tests/Support/OpenApiCoverage.php`。也解析 `config/route.php` 中指向控制器方法的路由（含链式写法与 `Route::group`），不支持的写法直接报错。
- 临时目录用 swagger-php 5.8.3 解析：378 个 path、452 个操作，无 warning。

### Task 7 文档与验证（本次）

- `admin/README.md`：软删除一节更新问答 1.3.0（003、004）与 OSS 1.2.0（003）；新增“仪表盘系统概览”“接口文档覆盖检查”“后端测试”三节。
- 插件 README：CMS 1.6.0 升级说明里问答的版本改为 1.3.0；问答 README 补充 JSON-LD 只覆盖前 100 条回答；OSS README 补充 S3 Endpoint 校验不覆盖的地址与连接测试权限的风险。
- `admin/phpunit.xml` 新增 `<php><ini name="memory_limit" value="512M"/></php>`：全量后端主进程峰值约 125MB，PHP 默认 128M 会在 `Tests\Oss\CloudDriverTest` 处内存耗尽；改后直接运行 `vendor/bin/phpunit` 即可，不再需要 `-d memory_limit=…`。

## 2. 测试结果

所有命令都在本工作树上运行；任务开始时 `git status` 干净，运行期间工作树里只有本次的文档与 `phpunit.xml` 改动。运行期间机器负载很高（load average 约 25–31，来自其他会话），这是前端全量出现超时的原因，见下文。日志在 `/tmp/t7/`。

| 命令 | 结果 |
| --- | --- |
| `cd admin && vendor/bin/phpunit`（不加 `-d`，靠 `phpunit.xml` 的 512M） | OK：2079 个测试，29382 个断言，跳过 127 个，0 失败；耗时 13 分 02 秒，内存峰值 125.00 MB |
| `cd admin && vendor/bin/phpunit tests/Cms tests/Document tests/Qa tests/Oss` | OK：628 个测试，13141 个断言，跳过 4 个（只在 MySQL 下运行的 CMS 用例）；耗时 2 分 39 秒，74.50 MB。`phpunit.xml` 的测试目录是整个 `tests`（只排除 `tests/business`、`tests/generated`），所以这些插件测试已包含在上面的全量中 |
| `php webman plugin:validate cms` / `document` / `qa` / `oss` | 全部 `valid: true`：cms 1.6.0、document 1.5.0、qa 1.3.0 无警告；oss 1.2.0 只有原有的两条 Composer 版本告警（`guzzlehttp/uri-template`、`psr/http-message` 与宿主版本范围不同） |
| `cd web && npx vitest run`（第 1 次，与后端全量同时运行） | 238 个文件 / 2039 个测试：2020 通过，19 失败（14 个文件）。失败原因：14 个“Test timed out in 5000ms”、1 个 30000ms 超时、1 个“Vite startup timed out”，其余 3 个是断言失败或 `vi.spyOn` 找不到对象（同样发生在耗时异常的文件里） |
| `cd web && npx vitest run`（第 2 次，单独运行） | 238 个文件 / 2039 个测试：2028 通过，11 失败（7 个文件），全部是“Test timed out in 5000ms”；另有 1 个未捕获错误（`tests/document/editor-actions.spec.ts` 中 prosemirror 的 `target.getClientRects is not a function`）。两次失败的用例集合不同 |
| 两次失败涉及的 19 个文件（加上出现未捕获错误的文件）单独重跑：`npx vitest run <19 个文件>` | 19 个文件 / 263 个测试全部通过，无未捕获错误，耗时 31 秒。结论：全量中的失败是高负载下的超时，不是回归 |
| `cd web && npx vue-tsc --noEmit` | 0 个错误 |
| `cd admin && PATH=/tmp/fa-podman/bin:$PATH php tests/Console/run-mysql.php`（podman，`mysql:8.0` + `funwebman-console:8.5`，网络 `funwebman-test`） | OK：111 个测试，1616 个断言；耗时 15 分 48 秒。包含问答 003/004、OSS 003 的 MySQL 升级测试；`MySqlPluginLifecycleTest` 这次通过，没有出现已知的跨秒不稳定 |
| CMS MySQL 测试（`/tmp/t7/run-cms-mysql.sh`，沿用 `/tmp/fa-final/run.sh` 的做法：每个文件一个独立 `mysql:8.0` 容器，在 `funwebman-console:8.5` 中运行） | 第 1 次：`MySqlLockTest` OK（24 个断言）、`MySqlSchemaTest` OK（3）、`MySqlSiteConfigurationTest` OK（5）；`MySqlCommunityUpgradeTest` 失败：期望问答迁移只有 `001_qa`、`002_soft_delete`，实际还执行了 `003_question_admin`、`004_attachments` |
| 修正后重跑 `MySqlCommunityUpgradeTest` | OK：1 个测试，19 个断言 |

**`MySqlCommunityUpgradeTest` 的修正**：这是 Task 3、Task 4 新增问答迁移后没有同步更新的测试期望，不是产品缺陷。已把期望改为 `001`–`004`，并补充断言：自定义前缀下 003、004 建出的是 `community_qa_answer`、`community_qa_attachment`，没有任何 `fun_` 开头的表。该文件位于本地忽略目录 `admin/tests/Cms/`（`admin/.gitignore` 第 44 行），无法入库，所以只在本地修改，没有单独提交。

**内存上限**：`admin/phpunit.xml` 新增 `<php><ini name="memory_limit" value="512M"/></php>`。此前在 PHP 默认 128M 下全量会在 `Tests\Oss\CloudDriverTest::test_tencent_sdk_serializes_safe_download_headers`（`new \finfo` 加载 magic 数据库）处内存耗尽；这次不加 `-d` 直接运行全量通过，峰值 125 MB。

没有运行 `php tests/Console/run-core-mysql.php`（核心迁移，本计划没有改核心 SQL）。

## 3. 已知限制与遗留项

以下来自执行台账 `progress.md` 中评审后留下的次要问题，均评估为可延后，不阻塞本次交付。2026-11-13 的遗留修复（提交信息“fix: 遗留限制修复（附件权限、结构化数据、元数据地址、错误码、路由检查、仪表盘）”）处理了其中 7 项，下文标注“已修复（2026-11-13）”；详细记录见 `.superpowers/sdd/2026-11-13-followups/report.md`。

**仪表盘**

- 部门、数据范围等变化不改变授权版本，缓存最多 60 秒后才反映（角色、权限变化即时生效）。
- “管理员数”是当前管理员可管理的人数（含数据范围与角色层级），不是全局总数；“已启用插件”只看 `lifecycle_state`，不另查 `status`。
- 前端：快捷入口重新计算时也会触发 `dashboard.loaded`（与钩子说明不完全一致）。
- 已修复（2026-11-13）：空状态考虑插件在 `dashboard.cards` 注册的卡片；keep-alive 重新激活时刷新概览（首次挂载不重复请求）；数字（含卡片副标题中的计数与附件大小）按当前界面语言格式化。
- 后端测试没有经过鉴权中间件注入权限的路径（直接调服务）。

**AI**

- 超时切换时上游可能已处理首次请求并计费（README 已说明）；TLS 错误也会切换，切换到的可能是同一上游主机。
- `stream_fallback` 已声明为支持，但目前生产代码里没有调用方依赖它。
- 带签名的 thinking 块会原样发给备用模型，备用模型可能不接受并返回 400，且 400 不会再切换（终审修复后 `admin/README.md` 注意事项已说明）。
- 基线里旧提示文案的 `fun_language_line` 行已不再被引用，留在库里无害；en-US 提示的渲染没有单独测试。

**问答**

- 站点层面没有单独的恢复、彻底删除权限，写操作统一用 CMS 的 `cms:submission:review`（要区分需要改 CMS）；按接口区分只体现在问答自己的权限码上。
- 003 若在建表之后、补行之前中断，重新执行不会补行，需要人工补齐（README 已说明）。SQLite 等价实现缺少“CMS 已删除回答的补行”用例（只有 MySQL 测试覆盖）；`Schema::questionAdmin` 的补行用模型默认连接而不是传入的连接。
- 附件：除大小错误外的其他上传错误码（部分上传、无临时目录、写入失败、扩展中止）原先都提示为“类型不允许”，终审修复后改为提示“附件上传失败，请重新上传”（`admin/plugins/qa/app/qa/service/AttachmentService.php:58`）；会员门户原先没有在前端检查文件大小，终审修复后选择文件时按单个 5MB、合计 8MB 检查（`web/src/web/views/QuestionsPortal.vue` 的 `pickFiles`）。后台附件下载：已修复（2026-11-13），附件、所属问题或回答在问答侧或 CMS 侧任一已删除时，还需要问答回收站查看权限 `qa:questions:recycle`，否则 404；记录已彻底删除时一律 404。
- JSON-LD 只覆盖详情页前 100 条回答；已修复（2026-11-13）：已采纳回答排在 100 条之后且仍公开（审核通过、未隐藏、问答侧与 CMS 侧都未删除）时单独取出作为 `acceptedAnswer`，并在详情页置顶显示（带 `answer-{id}` 锚点，JSON-LD 的 `url` 都能在页面上定位）。canonical 固定使用 https（README 已说明）。
- 嵌套事务：外层事务在问答写入后回滚时，已落盘的附件文件不会清理（目前没有这种调用方）。
- 提交 `73534856e` 的信息为英文、格式与约定不符，未改写历史。

**OSS**

- S3 Endpoint 只在字面量层面校验。已修复（2026-11-13）：显式拒绝阿里云元数据 `100.100.100.200`、AWS IPv6 元数据 `fd00:ec2::254`，以及内嵌被拒绝 IPv4 的 `64:ff9b::/96` 地址（阿里云 Endpoint 限定 `*.aliyuncs.com`、腾讯云不填 Endpoint，没有共用的 IP 校验）。仍未覆盖：解析到内网或元数据地址的主机名；内网地址本来就允许，持有保存权限的管理员可以让服务器访问内网 HTTPS 地址（README 已说明）；终审修复后，测试未保存的配置（不带 id）除测试权限外也需要保存权限，只有测试权限不能再指定任意 Endpoint。更严格需要在请求时解析 DNS 并固定 IP。
- 彻底删除与上传的竞态：已缩小（2026-11-13），上传驱动在云端写入成功、返回宿主之前再确认配置存在且未删除，已删除时删除刚上传的对象并让上传失败。剩余窗口：宿主在驱动返回后到写附件行（`UploadService` 中 getID3 与 `Attach::forceCreate`）之间不再回调插件，完全关闭需要宿主在同一事务中加锁，按约定没有改宿主。
- 后端 409/404 提示：已修复（2026-11-13），配置接口的 404/409 在 `data.code` 返回稳定原因（`OSS_PROFILE_NOT_FOUND`、`OSS_PROFILE_NOT_IN_RECYCLE`、`OSS_PROFILE_CURRENT`、`OSS_PROFILE_IN_USE`，沿用宿主 `data.code` 的写法），页面按原因显示中英文提示，未知原因显示服务端提示；页面请求关闭宿主的原文弹窗。其他 422 校验提示仍只有中文。（连接测试和保存的 `id` 传非字符串时，终审修复后返回 422。）
- OSS 在本工作区未安装，权限快照里没有 OSS 路由；新路由只经 `plugin:validate` 与控制器约定校验。
- 用 `--no-scripts` 重建 vendor 不会裁剪 aws-sdk（约 69MB）。

**接口文档**

- `config/route.php` 顶层按白名单检查（终审修复）：`require`/`include`、动态 `Route` 调用、`call_user_func`、`new Route`、顶层 `PluginRouteLoader::get` 都会报错。已修复（2026-11-13）：整文件扫描对 `Webman\Route` 及其子类（如 `PluginRouteLoader`）上未被识别为顶层或 group 闭包路由语句的静态调用一律报错（如 `->middleware(PluginRouteLoader::get(...))`），以字符串（`'Webman\\Route::get'`）或 `::class` 引用这些类也报错。剩余：闭包路由的函数体里如果出现上述写法会误报；只识别能自动加载的子类。
- 格式正确的注入内容只靠生成器转义拦截，检查器不查重复键；控制器自身 trait 中作为 config 路由目标的方法会误报；`Route::add` 的命名参数 `method` 与属性缓存按类名缓存属于边角情况。
- 生成控制器的标签用实体标题，不在 `ApiDocumentation.php` 登记；create、update 的请求体是 `DynamicObject`，不逐字段展开。
- 插件安装副本没有同步 Task 6 的注释（只差注释，运行行为相同）。

**测试与环境**

- `MySqlPluginLifecycleTest` 偶尔在 `updated_at` 跨秒时失败，这是之前就有的不稳定用例，本计划没有改它。
- `admin/tests/{Cms,Document,Qa,Oss}` 是本地忽略目录，其中的新测试不入库；入库的覆盖是 `tests/Console/MySql*UpgradeTest.php`、`DashboardOverviewTest`、`AiFallbackReasoningTest`、`OpenApiCoverageTest` 等。
- 部分前端测试在组件之后编写，没有 RED 记录（问答 Task 3/4）。
- 没有在浏览器里手动走查，没有连真实云存储或真实 AI 上游。

## 4. 部署说明

- **问答 SEO 页面**：站点域名的 Nginx 需要在 `location /`（转发到 `/cms/`）之前加 `location ^~ /qa/questions`，转发到后端同一路径（示例见 `admin/plugins/qa/README.md`）。否则站点域名下访问不到问答页面；站点里如果有 CMS 路由 `/qa/questions` 会被这条规则覆盖。
- **问答 1.3.0、OSS 1.2.0**：已安装的插件需要在插件中心执行“更新”（问答执行 003、004，OSS 执行 003），之后重新启用，并把新权限分配给后台角色（问答 `qa:questions:*`、`qa:answers:*`、`qa:questions:attachment`；OSS `oss:storage:delete|recycle|restore|destroy`）。问答依赖 CMS ≥1.6.0。安装或更新含新页面的插件后在前端执行 `npm run build:webman`，后端执行 `php start.php reload`。
- **OSS S3**：S3 供应商需要 `aws/aws-sdk-php`。在 `admin/plugins/oss/` 执行 `composer install --no-dev --no-plugins`（不要加 `--no-scripts`）：composer.json 的脚本会只保留 S3 服务模型（vendor 约 13MB，不裁剪约 69MB）并运行 `tools/patch-vendor.php`。SDK 缺失时 S3 配置显示不可用、不能启用。
- 问答附件随提问或回答一起提交，受 webman `max_package_size`（默认 10MB）和 Nginx `client_max_body_size` 约束。

## 5. 开发环境说明

- 为了运行测试，Task 3、Task 4 用 `cp` 把问答插件源码同步到了安装副本（`admin/app/qa`、`admin/app/admin/plugins/qa`、`web/src/admin/plugins/qa`）；Task 6 的注释没有同步，所以安装副本目前与源码只差注释。插件中心为安装文件登记了哈希（`fun_plugin_resource`），因此以后通过插件中心更新或重新部署问答时，可能报“二次开发冲突”。OSS 在本工作区没有安装，Task 6 只改注释，没有同步安装副本。2026-11-13 的遗留修复又用 `cp` 同步了问答的 `AttachmentService.php`、`PublicPages.php`、`QuestionService.php` 与后台 `Questions.php`（`cmp` 一致，`Questions.php` 因此也带上了 Task 6 的注释），同样会让插件中心登记的哈希不一致；OSS 仍未安装，未同步。
- 开发库没有升级：本计划没有对开发库执行任何迁移或写入（问答 003、004，OSS 003 都没有执行，新权限也没有登记）；本次全部验证都在 SQLite 测试库和 podman 临时 MySQL 中进行。
- 恢复办法：先把安装副本恢复为插件中心登记的内容，再通过插件中心（`PluginService`：停用 → 更新/重新部署 → 启用）把问答升级到 1.3.0。这一步会写开发库，**需要用户同意后再执行**。
