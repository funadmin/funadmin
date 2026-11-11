<div style="padding:18px;max-width: 1024px;margin:0 auto;background-color:#fff;color:#333">
<h1>webman</h1>

基于<a href="https://www.workerman.net" target="__blank">workerman</a>开发的超高性能PHP框架


<h2>应用入口与配置</h2>

<p><code>app/functions.php</code> 是公共函数入口，由 <code>config/autoload.php</code> 加载；<code>app/AppService.php</code> 实现 Webman Bootstrap，由 <code>config/bootstrap.php</code> 启动。全局中间件、事件和异常处理分别使用 <code>config/middleware.php</code>、<code>config/event.php</code> 和 <code>config/exception.php</code>；请求类为 <code>support/Request.php</code>。<code>app/BaseController.php</code> 与 <code>app/ExceptionHandle.php</code> 是仍在使用的 Webman 自定义基础类。旧的 <code>app/common.php</code>、<code>app/Request.php</code>、<code>app/event.php</code>、<code>app/provider.php</code>、<code>app/service.php</code>、<code>app/middleware.php</code> 不再作为入口保留。</p>

<h1>数据库部署</h1>

<p>生产数据库仅支持 MySQL，PHP 8.5 环境需要 pdo_mysql 扩展。复制 .env.example 后设置 DB_HOST、DB_PORT（默认 3306）、DB_DATABASE、DB_USERNAME、DB_PASSWORD 和 DB_PREFIX。SQLite 仅用于轻量测试。</p>
<p>推荐通过 /#/admin/install 安装。CLI 可运行 php bin/console.php install，从标准输入读取 JSON 对象：db 包含 host、port、database、username、password、prefix（均为字符串）；admin 包含 username、password、repassword、email；debug 为可选布尔值。通过受保护的标准输入提供密码，不把密码写入命令行参数。安装不覆盖已有管理员。已存在的数据库不需要建库权限；数据库不存在时才尝试创建。</p>
<p>运行 php bin/console.php migrate 应用核心 SQL（admin/database/core/migrations）。插件继续使用 database/migrations/ 目录。CRUD_CONNECTIONS 可显式限制可用连接；未设置时跟随已安装连接。系统升级沿用 MySQL 一致性快照与 shadow schema 校验恢复，不跳过备份。</p>

<h2>前端部署</h2>

<p>支持两种部署方式。在同级 <code>web</code> 中执行 <code>npm run build:webman</code>，产物直接写入 <code>admin/public/</code> 根目录，使用后端 IP 或域名访问 <code>/</code>，后台登录地址为 <code>/#/admin/login</code>。构建保留 <code>install.lock</code>、<code>storage/</code> 与插件资源，不清空 public。PHP 路由更新后执行 <code>php start.php reload</code>。</p>
<p>独立前端域名部署执行 <code>npm run build:standalone</code>，将 <code>web/dist/</code> 部署到前端域名的网站根目录；在该域名将后台、会员、安装、身份认证和插件等接口及上传资源路径代理到 Webman。完整 Nginx 示例见 <code>web/README.md</code> 的生产部署部分。<code>VITE_APP_PROXY_TARGET</code> 仅控制开发服务器代理，生产代理由 Nginx 等服务器配置。</p>

<h2>Webman 插件开发与部署</h2>

<p>核心 CRUD 生成的 PHP 源码位于 <code>app/admin/business/{entity}/</code>，前端 API 与页面写入同级 <code>web/src/admin/api/business/</code> 和 <code>web/src/admin/views/business/</code>。可通过 <code>PLUGIN_ADMIN_WEB_PATH</code> 指定前端源码根目录；前后端源码须处于同一文件系统，保证文件提交及失败回滚。预览或恢复过程中变更该目录会阻断旧计划，须恢复原配置或重新预览。页面生成后重新构建前端。正式生成成功后后端会自动向 Webman 主进程发送 reload 信号以加载新增控制器的注解路由（读取 <code>config/server.php</code> 的 <code>pid_file</code>，需要 posix 扩展）；无法自动重载时生成结果返回 <code>reloadRequested: false</code> 与原因，请手动执行 <code>php start.php reload</code>。</p>

<p>运行 <code>php webman plugin:make demo --title=示例</code> 生成 Manifest v2 插件。源包中的后台目录为 <code>plugins/demo/app/admin/plugins/demo/{layer}</code>，安装到 <code>app/admin/plugins/demo/{layer}</code>，目录与命名空间保持一致。前端源码为 <code>plugins/demo/web/src/admin/plugins/demo</code>，静态资源为 <code>plugins/demo/public/plugin-assets/demo/public</code>。完整目录见仓库根目录的 <code>docs/plugins.md</code>。后台路由使用 <code>/admin/plugin/demo</code>，独立应用使用 <code>/demo</code>。</p>
<p>后台插件控制器继承宿主后台控制器，权限码由控制器与方法自动推导，必须属于当前插件，并在 <code>plugin.json</code> 的 <code>adminWeb.permissions</code> 中声明并安装注册。自定义路由组使用 <code>PluginRoute::adminGroup()</code>，保留登录、权限、CSRF 和激活检查。禁用、忙碌或已卸载插件返回 404，激活清单损坏时返回 503。</p>
<p>升级已有系统时执行 <code>php bin/console.php migrate</code>，示例插件权限已包含在核心基线中（MySQL 为 <code>001_baseline.sql</code>，其他驱动为 <code>001_baseline.php</code>），仅注册示例权限，不自动授权普通角色。已登录管理员可发现自己有权访问的插件页面，无需额外授予插件中心管理权限。</p>
<p>前端源码发布到同级 <code>web/src/admin/plugins/demo</code>。部署目录不同可设置 <code>PLUGIN_ADMIN_WEB_PATH</code> 为前端源码项目的绝对路径。安装或升级含新页面的插件后，在前端项目执行 <code>npm run build:webman</code>；自定义部署还需配置构建输出目录。后台新增或更新 PHP 代码后执行 <code>php start.php reload</code>，让所有 Worker 重新加载类和路由。纯启停状态通过激活清单即时控制。</p>
<p>脚手架、CRUD、Manifest 校验、打包和发布统一使用项目相对目录。旧包结构、旧前端目录和旧发布记录不再兼容；操作会明确拒绝并保留原文件。统一目录内的发布继续支持归属校验、人工修改保护和失败恢复。</p>

<h1>学习</h1>

<ul>
  <li>
    <a href="https://www.workerman.net/webman" target="__blank">主页 / Home page</a>
  </li>
  <li>
    <a href="https://webman.workerman.net" target="__blank">文档 / Document</a>
  </li>
  <li>
    <a href="https://www.workerman.net/doc/webman/install.html" target="__blank">安装 / Install</a>
  </li>
  <li>
    <a href="https://www.workerman.net/questions" target="__blank">问答 / Questions</a>
  </li>
  <li>
    <a href="https://www.workerman.net/apps" target="__blank">市场 / Apps</a>
  </li>
  <li>
    <a href="https://www.workerman.net/sponsor" target="__blank">赞助 / Sponsors</a>
  </li>
  <li>
    <a href="https://www.workerman.net/doc/webman/thanks.html" target="__blank">致谢 / Thanks</a>
  </li>
</ul>

<div style="float:left;padding-bottom:30px;">

  <h1>赞助商</h1>

  <h4>特别赞助</h4>
  <a href="https://www.crmeb.com/?form=workerman" target="__blank">
    <img src="https://www.workerman.net/img/sponsors/6429/20230719111500.svg" width="200">
  </a>

  <h4>铂金赞助</h4>
  <a href="https://www.fadetask.com/?from=workerman" target="__blank"><img src="https://www.workerman.net/img/sponsors/1/20230719084316.png" width="200"></a>
  <a href="https://www.yilianyun.net/?from=workerman" target="__blank" style="margin-left:20px;"><img src="https://www.workerman.net/img/sponsors/6218/20230720114049.png" width="200"></a>


</div>


<div style="float:left;padding-bottom:30px;clear:both">

  <h1>请作者喝咖啡</h1>

<img src="https://www.workerman.net/img/wx_donate.png" width="200">
<img src="https://www.workerman.net/img/ali_donate.png" width="200">
<br>
<b>如果您觉得webman对您有所帮助，欢迎捐赠。</b>


</div>


<div style="clear: both">
<h1>LICENSE</h1>
The webman is open-sourced software licensed under the MIT.
</div>

</div>




## 视图模板（Blade）

服务端渲染的页面使用 Blade 模板，`config/view.php` 的处理器为 `app\common\view\Blade`。

- 模板以 `.blade.php` 结尾，按 webman 规则定位：单应用在 `app/view/`，多应用在 `app/<应用>/view/`；以 `/` 开头的路径按项目相对路径定位。例如 `view('user/hello', ['name' => 'webman'])` 对应 `app/view/user/hello.blade.php`。
- `{{ $value }}` 自动转义，`{!! $html !!}` 原样输出，只用于已清洗的内容。编译结果缓存在 `runtime/views`。
- 处理器基于 `illuminate/view` 实现，因为 `webman/blade` 尚不支持 illuminate 13。自定义指令或组件在 `config/view.php` 的 `extension` 回调中通过 `BladeCompiler` 注册。
- 插件独立应用的模板放在 `app/<插件>/view/**/*.blade.php`，不需要 PHP namespace，但不能在模板中声明类。
- CMS 前台主题不使用 Blade，继续使用 CMS 自带的标签模板引擎：后台可编辑的模板必须在沙箱中渲染，不能执行 PHP。

## 数据库迁移目录

- 核心 SQL：`admin/database/core/migrations/`，目前只有 `001_baseline.sql`（2026-11-06 由原基线与 002–028 增量重新 squash，结构与数据和逐个重放等价；剔除了软删除/停用的菜单与权限墓碑行，以及已移除本地市场的业务表与旧会员 opaque 令牌表）。新的结构或种子变更从 `002_` 起追加增量。
- 历史归档位于 `archive/`，归档不直接执行：根目录是第一次 squash 前的 001–136，`archive/baseline-2026-11-06/` 是第二次 squash 前的旧基线和 002–028。
- PHP 迁移：`admin/database/migrations/`，目前只有 `001_baseline.php`（2026-11-07 由 001–037 合并，结构与种子和原链路终态等价，同样剔除停用权限）。末尾按当前代码同步宿主与插件权限，新增控制器方法在全新安装时也会登记。旧文件在 `archive/`，不执行。不与核心 SQL 混用。
- 插件迁移：CMS 合并为单个 `plugins/cms/database/migrations/001_cms.sql`，内容依次由 `Schema`、`SiteRoleSchema`、`ContributionSchema` 导出。
- 权限按方法细分的升级工具（`PermissionGrantMigration`、`HostPermissionMigration`、`PermissionMigrationPlan`、`tools/permission-migrations/`）已随基线合并移除；旧资源路径到新权限码的换算表也已删除，运行时只按权限码判断。
- 权限资源树由权限码推导：模块分组 → 资源分组 → 接口/能力权限，由 `PermissionTreeService` 在同步权限和插件登记资源后自动整理。管理员手工创建的权限保持原位，被菜单引用的分组不删除。两份基线都已按这个结构整理，并删除了没有对应后台方法的旧格式权限码。
- 只向前守卫（`MigrationService` + 共用词法器 `SqlLexer`，守卫检查的语句与实际执行的语句一致，词法出错即拒绝）：
  - 迁移 SQL 中的 `DROP` / `TRUNCATE` / `RENAME` 一律拒绝。唯一例外是 `PREPARE` 字符串内的 `DROP INDEX <名>` 与 `DROP PRIMARY KEY`，用于按 information_schema 重建索引、换主键。
  - 以任何形式引用 `sql_mode` 或修改客户端字符集（`SET NAMES`、`character_set_*`、`@@sql_mode`、`@@character_set_*` 等）都会被拒绝。代码中（字符串之外）含非 ASCII 字符的词或标识符也会被拒绝，因为 MySQL 会把 `ſql_mode` 一类写法折叠成系统变量名。
  - 用到 `PREPARE` / `EXECUTE` 的文件里，**每个**字符串常量都会当作 SQL 重新分词检查，包括 `COMMENT '…'` 和 INSERT 的数据值。其中含非 ASCII 文字（如中文注释）、`$`、落单的撇号或其他 DROP / TRUNCATE / RENAME 字样时，整个文件都会被拒绝。这类注释和数据应放在不含 `PREPARE` 的单独文件里。
  - 守卫只是防误操作的安全网，看不到 `CHAR()`、十六进制等拼出的动态 SQL。
- `CORE_DATABASE_PATH` 可覆盖核心 SQL 的父目录（其下必须有 `migrations/001_baseline.sql`）；未设置时默认 `admin/database/core/`。
- 在 `admin/` 中执行 `php bin/console.php migrate`。MySQL 使用核心 SQL，其他受支持驱动沿用 PHP 迁移。
- 系统升级包以 `admin/` 为应用根，核心 SQL 文件键改为 `database/core/migrations/<文件名>.sql`，并纳入 `coreMigrations` 校验。升级前需部署匹配的核心 SQL 源；Docker 使用 `./database/core:/core-database:ro` 挂载该目录。
- 仓库根目录不再保留 `database/`，旧部署若设置了 `CORE_DATABASE_PATH` 或自定义挂载，应同步调整位置。

## 软删除

后台核心数据全部软删除（核心迁移 `005_soft_delete`）。

**新增核心表必须遵守**

- 主键为 `id`；带 `deleted_at DATETIME NULL` 及其索引。
- **有唯一索引的表**再加 `deleted_token BIGINT UNSIGNED NOT NULL DEFAULT 0`（0 表示未删除，软删除时写入该行 `id`），且唯一索引以 `deleted_token` 结尾，例如 `UNIQUE KEY username (username, deleted_token)`，这样删除后可以用相同的值重建。没有唯一索引的新表只需 `deleted_at`（已有核心表统一带 `deleted_token`，不回退）。
- 模型使用 `app\common\model\concern\LaravelSoftDelete`（内含 `TokenSoftDeletingScope`，删除时写 `deleted_token`，恢复时清零）。
- 绕过模型的读取（`Db::table`、join、原生 SQL）必须自行加 `deleted_at IS NULL`；通过 `Db::table` 或原生 SQL 删除时，必须同时写入 `deleted_at` 和 `deleted_token = 令牌来源列`（主键不是整数且表上有 `row_no` 时为 `row_no`，否则为主键，通常是 `id`），不能调用物理删除。
- 关联表保存按差异处理（参考 `RelationRowSync`）：只软删除被移除的行，只插入缺少的行，不要先全删再全插。
- 系统不会自动物理删除数据，没有定时物理清理。`auth:prune` 只软删除过期且尚未删除的令牌、会话和登录尝试，记录一直保留；普通配置、语言（含译文）和附件删除也只做软删除，附件文件保留。真实删除只能由管理员在回收站明确操作时执行 `forceDelete`（操作日志的“彻底删除 / 清空回收站”只作用于已删除记录，会员回收站的“彻底删除”同理）。

**恢复冲突**：恢复时如果相同唯一值已被现有记录占用，抛出继承 `DomainException` 的 `RestoreConflictException`（code 409），所有恢复接口（含通用 `Crud::restore`）统一返回 409“已存在相同的记录，无法恢复，请先修改或删除现有记录”。

**令牌来源**：`deleted_token` 只存数字，默认取主键（`getDeletedTokenSourceColumn()` 返回主键名，批量删除同样按该列写入）。主键不是整数的表（例如代码生成器的 UUID 主键表）在有唯一字段时另加自增序号列 `row_no`，生成的模型覆盖 `getDeletedTokenSourceColumn()` 返回 `row_no`；没有覆盖的字符串主键模型在表上有 `row_no` 时也自动以它为来源。`deleted_token`、`row_no` 是保留字段名，生成器与表单设计器都不允许作为业务字段；只有从现有表推断、且定义与生成器一致（`bigint unsigned NOT NULL`、不可写、非唯一）的受管字段可以同名。

**代码生成器与表单设计器**：生成的建表 SQL 带 `deleted_at` 索引；有唯一字段时才加 `deleted_token`，唯一键以它结尾。表单设计器给已有表新增唯一字段而表上还没有 `deleted_token` 时，先补令牌列（主键不是整数且没有 `row_no` 时另加自增 `row_no` 及 `idx_<表>_row_no` 索引），再把已删除行的 `deleted_token` 回填为令牌来源，最后加唯一键。表单设计器配置的外键（`ON DELETE` 级联 / 置空 / 禁止）在软删除父记录时由程序执行：级联则以同一时间软删除子记录（逐级向下），置空则把子记录外键置空，禁止则仍有未删除子记录时拒绝；恢复父记录时一并恢复同一时间级联删除的子记录，撞唯一值的子记录跳过并在结果 `conflicts` 中返回。数据库里的真实外键只在回收站彻底删除时生效；子表没有对应真实外键（追加字段或采纳的表）时，彻底删除由程序按同样规则执行。插件 CRUD 若在此之前已生成过带唯一字段的表，重新生成只补 `deleted_at` 索引，原有单列唯一键与无 `deleted_token` 的结构保持不变，需要运行升级命令后才能重建。

**已发布业务表升级**：软删除规则之前发布的业务表（例如订单表）用 `php webman business:soft-delete-upgrade [--table=<表>] [--dry-run]` 升级。只处理 `business_module` 登记且已发布的表，核心表、插件基线表和未登记的表一律拒绝；没有 `deleted_at` 的表（生成时关闭软删除）只报告并跳过，不自动加软删除。每张表按现状只发一条 `ALTER`：补 `deleted_at` 索引；有唯一键时补 `deleted_token`（主键不是整数时另加自增 `row_no` 作为令牌来源），已删除行回填 `deleted_token`，唯一键按原名重建为「原列 + `deleted_token`」。可重复执行，全部到位时不做任何改动；先用 `--dry-run` 查看计划。`table_name` 按物理表名原样使用（采纳的无前缀表不补前缀），去掉前缀后与核心表或插件基线表重名的一律拒绝。插件目标的生成表不直接 `ALTER`：先检查插件迁移目录可写、插件没有进行中的操作、版本号未占用（`--dry-run` 同样检查），再写出可重复执行的插件迁移（`NNN_soft_delete_<表>.sql`，带新的结构快照）并经迁移运行器执行、登记到 `system_migration`；其他安装随插件迁移升级，重新生成代码也不会回到旧形状。主键不是整数的模型无需重新生成：软删除 trait 在主键为字符串、表上有 `row_no` 时自动以 `row_no` 作为令牌来源（单条与批量删除都是）；表单数据接口（`FormDataService` 的单条、批量删除、外键级联与子表移除行）按同一规则写 `deleted_token = row_no`，恢复时令牌归零。**加 `row_no`（AUTO_INCREMENT）会复制整表、期间阻塞写入，请在维护窗口执行升级**；其他子句（加列、加索引、重建唯一键）在 MySQL 8 上通常可在线执行，但同一条 ALTER 里有 `row_no` 时整条按复制表执行。

**插件与生成表**：官方插件表遵守同样的规则（CMS 007 / 1.6.0、技术文档 004 / 1.5.0、问答与 OSS 002 / 1.1.0，已安装插件必须走插件更新执行迁移）。
- 只追加的历史表与单行设置表豁免，不加软删除列：`cms_form_event`、`cms_site_auth_audit`、`cms_model_version`、`cms_content_revision`、`cms_template_tag_version`、`document_events`、`document_revisions`、`oss_setting`。
- 其余表都有 `deleted_at` 及以它开头的索引，模型使用 `LaravelSoftDelete`；只有存在非主键唯一键的表才加 `deleted_token`，唯一键保持原名并以它结尾。
- 原来没有主键的 CMS 4 张关系表与 `document_category_links` 新增自增 `id`（后者的原主键改为 `uk_document_category_links_pk`）。OSS 配置保留字符串主键、问答保留原主键，两者只加 `deleted_at`。
- 插件代码只在回收站的“彻底删除”里物理删除（只删已删除的行，仍有未删除的关联行时 409）；附件文件也只在彻底删除时删除。恢复撞唯一值返回 409。
- 生成器（代码生成器、表单设计器、插件目标）按上面“代码生成器与表单设计器”的规则建表；非整数主键且有唯一字段时另加 `row_no`。
- `tests/PluginSoftDeleteGuardTest.php` 守住这些规则：插件表结构、物理删除白名单、模型 trait 与三种生成器的建表 SQL。新增插件表、豁免表或彻底删除方法时同步更新该测试。

**会员注销冷静期**：天数由 `config/soft_delete.php` 的 `member_cooling_days` 设置（默认 30）。冷静期内，被删会员的用户名、手机号、邮箱仍然占用，只有管理员能在回收站恢复。期满后这些值放开，旧账号仍保留为软删除。删除会员会连带软删除其接口会话与分组、标签关联（已登录设备立即失效）；恢复时关联一起恢复，会话不恢复。会员等级、分组、标签仍被未删除会员使用时不能删除。会员等级已删除时拒绝恢复；已删除的分组、标签在恢复时跳过。

**升级说明**

- 005 给 12 张主键不是 `id` 的表（令牌、会话、登录尝试、关联表、系统密钥等）以 `AUTO_INCREMENT … FIRST` 新增 `id` 主键，原主键改为唯一索引 `uk_<表>_pk`。这会复制整张表，执行期间登录、会话、令牌表会短暂阻塞写入，大表应在低峰期升级。
- 存量行的 `id` 编号在基于语句的 binlog（SBR）下不确定，主从可能不一致，建议使用 ROW 格式。
- 005/006 尚未在 MySQL 8.0 上实测，发布前须在 `admin/` 执行 `php tests/Console/run-core-mysql.php`（需要 docker）。


## 对接线上插件市场

框架只保留插件市场客户端：后台插件中心、云端账号、远程分类/搜索/授权/下载、更新检查、签名验签及安装流程。通过 `.env` 的 `PLUGIN_MARKETPLACE_URL`（市场根地址，不含 `/api/v3`）和 `PLUGIN_MARKETPLACE_PUBLIC_KEY` 配置线上市场。

本地运营市场的 `web/src/admin/plugins/market/`、`app/admin/plugins/market/` 和 `app/market/` 已移除，不再提供本地商城、订单支付、插件上架及市场服务端接口。核心基线（MySQL 与 PHP）不再包含本地市场的权限、菜单和业务表（原 `026_remove_local_market.sql`、`035_remove_local_market.php` 见归档）。

通用会员注册、资料与改密服务位于 `app/common/service/member/`。公开会员只走会员 API v2：`POST /api/v2/token` 登录、`POST /api/v2/token/refresh` 刷新、`POST /api/v2/logout` 登出、`POST /api/v2/member` 注册、`GET|PUT /api/v2/member/profile` 资料、`PUT /api/v2/member/password` 改密（吊销该会员全部会话）。会话存于 `member_api_session`，CMS/QA 通过 `MemberApiSessionService::authenticate()` 识别同一会员。令牌有效期由 `MEMBER_ACCESS_TOKEN_TTL`、`MEMBER_REFRESH_TOKEN_TTL` 设置。

## 本地开发

在项目根目录（`admin/` 与 `web/` 的上一级）执行一条命令即可同时启动前后端：

```sh
npm run dev
```

- 后端：未运行时以前台模式启动 webman（`php start.php start`，监听 `http://127.0.0.1:8787`）；已在运行则直接复用，退出时不会停止它。
- 前端：在 `web/` 运行 Vite（webman 模式，接口代理到后端），端口取 `web/.env.webman.local` 的 `VITE_APP_PORT`；该端口已有 Vite 在运行时直接复用。
- 后端日志：跟随 `admin/runtime/logs/webman-<当天日期>.log` 新增内容（含复用已运行后端时），以 `[后端日志]` 前缀输出，跨天自动切换；设置 `DEV_BACKEND_LOGS=0` 关闭。
- 打开 `http://localhost:<端口>/#/admin/login`，按 Ctrl+C 停止由本命令启动的进程。

只启动其中一个：后端 `cd admin && php start.php start`，前端 `cd web && npm run dev`。
