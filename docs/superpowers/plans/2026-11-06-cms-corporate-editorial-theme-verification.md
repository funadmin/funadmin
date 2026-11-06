# CMS 1.2.0 前台主题与站点自定义配置验收

2026-11-06。当前共享 Rbac 工作区，未提交、重置暂存区、合并或推送其他会话的修改。

## 交付

统一企业官网与文章资讯的 default 主题：企业首页、栏目列表、文章详情、产品详情、案例详情、企业单页、资料下载、搜索结果；桌面双列品牌区、本地 SVG 插画、产品/案例/资讯卡片、阅读布局与手机原生展开菜单。仍使用 PHP SSR、普通 Vue/Element Plus 本地脚本，公开前台不需要 npm 构建。自定义模板保持优先及原有分页条数。

站点管理增加配置面板，预置15个品牌/首页/联系/版权备案配置，支持自定义键、名称、类型、分组、默认值及可翻译标记。支持9种类型：text、textarea、image、url、email、number、boolean、color、select。数据在既有 settings JSON，无新 SQL。模板支持 `{$config.key}` 与 `{cms:config name="key" /}`。

全站默认值与语言覆盖分离，0/false/空字符串保留。本站 detail/edit/locale 权限分别校验查看、站点配置写入、语言覆盖写入；通用 settings 写入也校验字段类型和保留译文。内置地址字段不能改成普通文本绕过 URL 校验。配置用于公开展示，不保存秘密凭据。

## 验证结果

| 项目 | 结果 |
| --- | --- |
| 最后完整 CMS PHPUnit | 98 tests / 446 assertions，无失败，4 个默认 MySQL skip |
| CMS + 宿主授权/SDK 集成（末次空 settings 回归前） | 137 tests / 576 assertions，无失败，4 个默认 MySQL skip |
| 隔离 MySQL 配置 JSON/语言覆盖/站点隔离 | 1 test / 5 assertions，通过 |
| 隔离 MySQL 站点锁（新增 configuration 写入） | 1 test / 24 assertions，通过 |
| CMS Vue/Vitest | 30 tests / 8 files，通过 |
| 源包 + 发布目录 vue-tsc | 通过 |
| 8 个后台页面入口生产编译 | 通过 |
| 插件 Manifest 校验 | valid=true，1.2.0 |
| git diff --check | 通过 |

MySQL 测试使用随机 funadmin_cms_test_<hex> 库，结束删除。没有对真实站点做测试写入。旧全仓失败记录见 2026-11-05-cms-site-menu-permissions-verification.md，本轮不声称无关模块的全量检查通过。

## 审查与回归

一次独立只读审查无 Critical，提出的重要问题均在回归中先重现后修复：

- 自定义栏目模板 limit=6 的第3页不再被内置 limit12 提前判404；由选中模板控制其分页。
- 修改内置 URL/image/email 字段定义不能绕过其语义校验。
- 通用站点 settings 保存与专用配置接口共享语言覆盖校验/清理，避免类型变更留下失效译文。
- 编辑站点/语言元信息不再提交旧 settings 快照，避免覆盖刚保存的自定义配置。
- 自查补充：取消语言覆盖立即显示全站值、保存时重新检查最新权限、晚到响应不污染另一站点、NULL settings 可正常新增配置。
- Minor 搜索页空语言菜单已补齐本地化搜索链接，保留 q 并使用各语言正式首页路径；单语言隐藏冗余语言选择。没有实质性延期小项。

## 本地部署与访问

通过正式 PluginService disable/update(true)/enable 升级到1.2.0，db_version 保持002_site_roles，enabled，migration_pending=0，随后重载 Webman。最终 NULL settings 修复通过正常原生 App 发布器发布并再次重载。

升级前后对17个既有CMS表和 casbin_rule 共18个表计算规范化逐行哈希，完全一致。保留 ces 及原域名，不给真实账号追加角色/权限，不注入演示内容。PluginModificationDetector modified=false。

当前可直接访问 `http://www.demotest.com:8088/`；根路径和搜索HTTP200、CSS HTTP200、未知Host404、无登录的配置API401。8088 是独立Nginx进程，仅监听127.0.0.1，不修改系统全局Nginx配置。hosts 已包含127.0.0.1 www.demotest.com。无端口80配置也已准备，但本机绑定80需要管理员密码，故启用不需特权的8088入口。

后台：`http://127.0.0.1:5175/#/admin/plugin/cms/sites`，查看“网站基础与自定义配置”。配置保存无须worker重载。

## 实际浏览器检查

通过 CUA 在原生 Edge 新标签中验证：

- 隔离样例数据库8099：首页品牌区、企业模块与资讯模块均为真实SSR结果；文章页标题、作者、日期、正文层级与相关推荐显示正常。
- 真实站点 www.demotest.com:8088：首页直接打开，无内部/cms前缀，展示新主题及合理空状态。
- Edge响应模式390×844：恢复正常页面缩放后，首页单列、图片、按钮和排版正常；原生手机菜单可展开并提供可用搜索与各模块入口。

样例数据库在 .superpowers/sdd/2026-11-06-cms-corporate-editorial-theme/preview.sqlite，与真实站点隔离；8099仅本机监听。示例内容明确用于主题效果预览。没有通过浏览器保存真实配置/改变真实账号授权。自定义配置面板的保存、继承、权限、加字段及异步切站由真实Vue组件测试覆盖，源/已发布组件类型与编译通过；真实API登录/权限/CSRF由PHP会话测试覆盖。

## 实施裁定

- 继续共享Rbac工作区并按路径隔离修改，因为原CMS与宿主依赖仍未提交；不触碰共享索引。代价是并行编辑风险，以最终校验和正式发布器约束。
- 配置存入现有站点/语言JSON，不创建每个自定义字段的SQL列；每站定义独立，语言只覆盖可翻译的值。代价是配置查询不适合作为大量可检索业务数据，应使用内容模型承载业务实体。
- 主动提供本机8088根入口以完成可访问前台，80权限不足不阻塞页面开发；生产应使用既有文档的HTTPS反代。代价是本机地址需要显式端口8088。
- 自定义主题/模板的结构与分页继续由其作者控制，只有内置模板启用固定展示数据分页；代价是自定义模板需自行声明其标签和版式。
