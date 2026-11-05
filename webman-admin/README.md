<div style="padding:18px;max-width: 1024px;margin:0 auto;background-color:#fff;color:#333">
<h1>webman</h1>

基于<a href="https://www.workerman.net" target="__blank">workerman</a>开发的超高性能PHP框架


<h1>数据库部署</h1>

<p>生产数据库仅支持 MySQL，PHP 8.5 环境需要 pdo_mysql 扩展。复制 .env.example 后设置 DB_HOST、DB_PORT（默认 3306）、DB_DATABASE、DB_USERNAME、DB_PASSWORD 和 DB_PREFIX。SQLite 仅用于轻量测试。</p>
<p>推荐通过 /#/admin/install 安装。CLI 可运行 php bin/console.php install，从标准输入读取 JSON 对象：db 包含 host、port、database、username、password、prefix（均为字符串）；admin 包含 username、password、repassword、email；debug 为可选布尔值。通过受保护的标准输入提供密码，不把密码写入命令行参数。安装不覆盖已有管理员。已存在的数据库不需要建库权限；数据库不存在时才尝试创建。</p>
<p>运行 php bin/console.php migrate 应用根目录核心 SQL。插件继续使用 database/migrations/ 目录。CRUD_CONNECTIONS 可显式限制可用连接；未设置时跟随已安装连接。系统升级沿用 MySQL 一致性快照与 shadow schema 校验恢复，不跳过备份。</p>

<h2>前端部署</h2>

<p>支持两种部署方式。在同级 <code>admin-web</code> 中执行 <code>npm run build:webman</code>，产物直接写入 <code>webman-admin/public/</code> 根目录，使用后端 IP 或域名访问 <code>/</code>，后台登录地址为 <code>/#/admin/login</code>。构建保留 <code>install.lock</code>、<code>storage/</code> 与插件资源，不清空 public。PHP 路由更新后执行 <code>php start.php reload</code>。</p>
<p>独立前端域名部署执行 <code>npm run build:standalone</code>，将 <code>admin-web/dist/</code> 部署到前端域名的网站根目录；在该域名将后台、会员、安装、身份认证和插件等接口及上传资源路径代理到 Webman。完整 Nginx 示例见 <code>admin-web/README.md</code> 的生产部署部分。<code>VITE_APP_PROXY_TARGET</code> 仅控制开发服务器代理，生产代理由 Nginx 等服务器配置。</p>

<h2>Webman 插件开发与部署</h2>

<p>核心 CRUD 生成的 PHP 源码位于 <code>app/admin/generated/{entity}/</code>，前端 API 与页面写入同级 <code>admin-web/src/admin/api/generated/</code> 和 <code>admin-web/src/admin/views/generated/</code>。可通过 <code>PLUGIN_ADMIN_WEB_PATH</code> 指定前端源码根目录；前后端源码须处于同一文件系统，保证文件提交及失败回滚。预览或恢复过程中变更该目录会阻断旧计划，须恢复原配置或重新预览。页面生成后重新构建前端，并 reload Webman 以加载新增控制器。</p>

<p>运行 <code>php webman plugin:make demo --title=示例</code> 生成 Manifest v2 插件。源包中的后台目录为 <code>plugins/demo/app/admin/plugins/demo/{layer}</code>，安装到 <code>app/admin/plugins/demo/{layer}</code>，目录与命名空间保持一致。前端源码为 <code>plugins/demo/admin-web/src/admin/modules/demo</code>，静态资源为 <code>plugins/demo/public/plugin-assets/demo/public</code>。完整目录见仓库根目录的 <code>docs/plugins.md</code>。后台路由使用 <code>/admin/plugin/demo</code>，独立应用使用 <code>/demo</code>。</p>
<p>后台控制器继承 <code>AdminApiController</code>，并在控制器或方法上声明 <code>#[\app\common\plugin\sdk\PluginPermission('demo:item:list')]</code>。权限码必须属于当前插件，且在 <code>plugin.json</code> 的 <code>adminWeb.permissions</code> 中声明并安装注册。自定义路由组使用 <code>PluginRoute::adminGroup()</code>，保留登录、权限、CSRF 和激活检查。禁用、忙碌或已卸载插件返回 404，激活清单损坏时返回 503。</p>
<p>升级已有系统时执行 <code>php bin/console.php migrate</code>，新增的 <code>022_webman_example_plugin_permissions.sql</code> 仅注册示例权限，不自动授权普通角色。已登录管理员可发现自己有权访问的插件页面，无需额外授予插件中心管理权限。</p>
<p>前端源码发布到同级 <code>admin-web/src/admin/modules/demo</code>。部署目录不同可设置 <code>PLUGIN_ADMIN_WEB_PATH</code> 为前端源码项目的绝对路径。安装或升级含新页面的插件后，在前端项目执行 <code>npm run build:webman</code>；自定义部署还需配置构建输出目录。后台新增或更新 PHP 代码后执行 <code>php start.php reload</code>，让所有 Worker 重新加载类和路由。纯启停状态通过激活清单即时控制。</p>
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


