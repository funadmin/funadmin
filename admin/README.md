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

<p>运行 <code>php webman plugin:make demo --title=示例</code> 生成 Manifest v2 插件。源包中的后台目录为 <code>plugins/demo/app/admin/plugins/demo/{layer}</code>，安装到 <code>app/admin/plugins/demo/{layer}</code>，目录与命名空间保持一致。前端源码为 <code>plugins/demo/web/src/admin/modules/demo</code>，静态资源为 <code>plugins/demo/public/plugin-assets/demo/public</code>。完整目录见仓库根目录的 <code>docs/plugins.md</code>。后台路由使用 <code>/admin/plugin/demo</code>，独立应用使用 <code>/demo</code>。</p>
<p>后台插件控制器继承宿主后台控制器，权限码由控制器与方法自动推导，必须属于当前插件，并在 <code>plugin.json</code> 的 <code>adminWeb.permissions</code> 中声明并安装注册。自定义路由组使用 <code>PluginRoute::adminGroup()</code>，保留登录、权限、CSRF 和激活检查。禁用、忙碌或已卸载插件返回 404，激活清单损坏时返回 503。</p>
<p>升级已有系统时执行 <code>php bin/console.php migrate</code>，示例插件权限已包含在核心基线中（MySQL 为 <code>001_baseline.sql</code>，其他驱动为 <code>001_baseline.php</code>），仅注册示例权限，不自动授权普通角色。已登录管理员可发现自己有权访问的插件页面，无需额外授予插件中心管理权限。</p>
<p>前端源码发布到同级 <code>web/src/admin/modules/demo</code>。部署目录不同可设置 <code>PLUGIN_ADMIN_WEB_PATH</code> 为前端源码项目的绝对路径。安装或升级含新页面的插件后，在前端项目执行 <code>npm run build:webman</code>；自定义部署还需配置构建输出目录。后台新增或更新 PHP 代码后执行 <code>php start.php reload</code>，让所有 Worker 重新加载类和路由。纯启停状态通过激活清单即时控制。</p>
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




## 数据库迁移目录

- 核心 SQL：`admin/database/core/migrations/`，目前只有 `001_baseline.sql`（2026-11-06 由原基线与 002–028 增量重新 squash，结构与数据和逐个重放等价；剔除了软删除/停用的菜单与权限墓碑行，以及已移除本地市场的业务表，会员令牌仍使用的 `market_token` 保留）。新的结构或种子变更从 `002_` 起追加增量。
- 历史归档位于 `archive/`，归档不直接执行：根目录是第一次 squash 前的 001–136，`archive/baseline-2026-11-06/` 是第二次 squash 前的旧基线和 002–028。
- PHP 迁移：`admin/database/migrations/`，目前只有 `001_baseline.php`（2026-11-07 由 001–037 合并，结构与种子和原链路终态等价，同样剔除停用权限）。末尾按当前代码同步宿主与插件权限，新增控制器方法在全新安装时也会登记。旧文件在 `archive/`，不执行。不与核心 SQL 混用。
- 插件迁移：CMS 合并为单个 `plugins/cms/database/migrations/001_cms.sql`，内容依次由 `Schema`、`SiteRoleSchema`、`ContributionSchema` 导出。
- 权限按方法细分的升级工具（`PermissionGrantMigration`、`HostPermissionMigration`、`PermissionMigrationPlan`、`tools/permission-migrations/`）已随基线合并移除；`database/permission-maps/host-per-method.php` 仍保留，供运行时把旧资源路径换算为新权限码。
- 权限资源树由权限码推导：模块分组 → 资源分组 → 接口/能力权限，由 `PermissionTreeService` 在同步权限和插件登记资源后自动整理。管理员手工创建的权限保持原位，被菜单引用的分组不删除。两份基线都已按这个结构整理，并删除了没有对应后台方法的旧格式权限码。
- 只向前守卫没有白名单：迁移 SQL 中任何 `DROP` / `TRUNCATE` / `RENAME` 都会被拒绝。
- `CORE_DATABASE_PATH` 可覆盖核心 SQL 的父目录（其下必须有 `migrations/001_baseline.sql`）；未设置时默认 `admin/database/core/`。
- 在 `admin/` 中执行 `php bin/console.php migrate`。MySQL 使用核心 SQL，其他受支持驱动沿用 PHP 迁移。
- 系统升级包以 `admin/` 为应用根，核心 SQL 文件键改为 `database/core/migrations/<文件名>.sql`，并纳入 `coreMigrations` 校验。升级前需部署匹配的核心 SQL 源；Docker 使用 `./database/core:/core-database:ro` 挂载该目录。
- 仓库根目录不再保留 `database/`，旧部署若设置了 `CORE_DATABASE_PATH` 或自定义挂载，应同步调整位置。


## 对接线上插件市场

框架只保留插件市场客户端：后台插件中心、云端账号、远程分类/搜索/授权/下载、更新检查、签名验签及安装流程。通过 `.env` 的 `PLUGIN_MARKETPLACE_URL`（市场根地址，不含 `/api/v3`）和 `PLUGIN_MARKETPLACE_PUBLIC_KEY` 配置线上市场。

本地运营市场的 `web/src/admin/modules/market/`、`app/admin/plugins/market/` 和 `app/market/` 已移除，不再提供本地商城、订单支付、插件上架及市场服务端接口。核心基线（MySQL 与 PHP）不再包含本地市场的权限、菜单和业务表（原 `026_remove_local_market.sql`、`035_remove_local_market.php` 见归档）；会员令牌仍沿用 `market_token` 表。

通用会员注册、资料、改密与 opaque 令牌服务已移到 `app/common/service/member/`，公开会员入口继续使用 `/api/v3/auth/*`，CMS/QA 鉴权使用相同的公共令牌服务。为兼容已有会员登录，仍沿用历史 `market_token` 表、会话键和限流键；这不提供商城功能。新令牌有效期可通过 `MEMBER_OPAQUE_ACCESS_TOKEN_TTL`、`MEMBER_OPAQUE_REFRESH_TOKEN_TTL` 设置。

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
