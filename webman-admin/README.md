<div style="padding:18px;max-width: 1024px;margin:0 auto;background-color:#fff;color:#333">
<h1>webman</h1>

基于<a href="https://www.workerman.net" target="__blank">workerman</a>开发的超高性能PHP框架


<h1>数据库部署</h1>

<p>PHP 8.5 环境保留 MySQL 支持，PostgreSQL 要求 16 或以上版本及 pdo_pgsql 扩展。复制 .env.example 后设置 DB_CONNECTION=pgsql、DB_HOST、DB_PORT=5432、DB_DATABASE、DB_USERNAME、DB_PASSWORD；DB_SCHEMA 默认为 public，DB_SSLMODE 默认为 prefer。schema 仅支持单个非系统名称，数据库必须为 UTF8。Docker Compose 的数据库类型、主机和端口可通过环境变量覆盖。</p>
<p>推荐通过 /admin-web/#/admin/install 安装。CLI 可运行 php bin/console.php install，从标准输入读取 JSON 对象：db 包含 driver、host、port、database、username、password、prefix、schema、sslmode（均为字符串）；admin 包含 username、password、repassword、email；debug 为可选布尔值。通过受保护的标准输入提供密码，不把密码写入命令行参数。安装仅初始化全新数据库，不迁移已有 MySQL 数据，不覆盖已有管理员。既有 UTF8 数据库不需要建库权限；数据库不存在时才尝试创建。</p>
<p>运行 php bin/console.php migrate 应用当前驱动迁移。MySQL 使用现有根目录核心 SQL；PostgreSQL 初始化使用本地 PHP Schema 迁移。插件及签名升级的 PostgreSQL SQL 位于 database/migrations/pgsql/，缺少当前驱动制品将拒绝执行，不转换历史 MySQL SQL。CRUD_CONNECTIONS 可显式限制可用连接；未设置时跟随已安装数据库类型。</p>
<p>PostgreSQL 升级备份使用 pg_dump custom 格式，在临时验证库完成还原校验后事务化恢复目标 schema。运行账号须拥有目标 schema 及创建临时验证库权限；存在跨 schema 依赖时拒绝自动升级。需要与服务器主版本一致的 pg_dump、pg_restore、psql；当前 Docker 镜像内置 16 和 18 客户端，其他主版本需另行提供匹配工具。缺少工具或权限会在部署前拒绝升级，不跳过备份。</p>

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


