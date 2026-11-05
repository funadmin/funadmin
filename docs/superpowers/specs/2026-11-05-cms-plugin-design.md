# CMS 插件设计

日期：2026-11-05。状态：用户已确认，进入开发。本文整合当前会话已确认的需求；具体实现选择在文中明确列出。

## 目标与已确认需求

按 `docs/plugins.md` 的新插件目录规范开发 `cms`，复用 Manifest v2、插件生命周期、权限、资源发布与恢复机制。

- 网站前台服务端输出完整 HTML；Vue 和 Element Plus 使用普通 script/link 引入，前台无 npm 构建步骤。
- 同一实现支持单站点单语言、单站点多语言、多站点多语言，以及不同站点使用不同语言组合。
- 各站点独立配置域名、主题、栏目、导航和内容；各语言独立配置 SEO、访问路径和发布版本。
- 页面支持自定义完整路径，修改已发布路径时保留旧地址跳转。
- 内置文章、单页、产品展示、案例、下载五种内容模型；模型专属字段随内容版本保存。
- 支持 CMS 模板标签；同时支持开发者 PHP 标签扩展和后台配置自定义标签。
- 管理端复用现有后台框架、登录、功能权限与附件服务，管理端正常构建与网站前台无构建要求分别处理。

## Approaches

1. 在核心业务模块中直接实现 CMS：接入简单，但生命周期、数据归属与用户要求的插件边界不一致。
2. 新规范 CMS 插件，独立应用负责 SSR，管理端模块负责编辑（会话选择）：复用现有发布与权限，站点、语言和 URL 由 CMS 服务解析。
3. 独立 SPA 或独立建站服务：增加部署边界和发布链路，不符合已确认的普通引入与服务端模板方案。

采用第二种。实现者决定：以显式受限的模板解释器支持标签，既不更改全局视图配置，也不将数据库模板转为 PHP 执行。

## 交付边界

交付可安装插件源包、SQL 迁移、管理页面、默认主题、普通引入的前台资源、扩展示例、部署文档与自动化验证。

首版支持站点及语言管理、域名绑定、站点管理员授权、栏目管理、模型管理与字段配置、内容编辑与修订发布、导航、自定义标签、URL 跳转、sitemap 和 robots。内置模型统一走同一套编辑与发布流程。

域名管理保存绑定并提供准确的 DNS、Nginx 和证书配置指引；只有显式绑定的域名才能访问站点。首版不自动操作 DNS 提供商或申请证书，不将“已填写域名”等同于“已验证 HTTPS”。本地使用 Host 请求覆盖多域名验收，真实公网配置由部署环境完成。

不包含订单、支付、库存、会员投稿、自动翻译、可视化拖拽建站、主题市场或独立客户 SaaS 租户系统。产品模型仅为产品展示。不同客户的账户、存储、计费隔离需要另行设计。

## 插件与运行目录

```text
admin/plugins/cms/
  plugin.json
  Plugin.php
  config.php
  app/admin/plugins/cms/
    controller/ model/ service/ validate/ middleware/
  app/cms/
    controller/ model/ service/ middleware/ template/ tags/
    view/default/
    config/app.php
  web/src/admin/modules/cms/
    api.ts types.ts pages/ components/
  public/plugin-assets/cms/public/
    css/ js/ vendor/
  database/migrations/001_cms.sql
  storage/
  README.md
```

发布位置与上述项目相对路径相同，前端以配置的源码根目录为基准。私有运行数据位于 `runtime/plugins-data/cms/storage/`。

只对 CMS 必需的宿主接入点作窄修改；保留当前工作区中既有的插件规范调整。新公开入口、管理入口均执行插件激活检查，不能因自定义 URL 绕过启停。插件卸载保留数据，首版 `purge.supported=false`。

## 数据模型

以下为不带数据库前缀的逻辑表名，实际前缀沿用宿主。主键及关联 ID 使用 unsigned bigint；时间沿用 created_at/updated_at，可回收的业务实体使用 deleted_at。版本记录不可变，不软删除。JSON API 中 bigint ID 使用字符串。

### 站点、语言、域名与权限

- `cms_site`：id、code、name、default_locale、primary_domain_id、theme、settings、status、时间和软删除。code 唯一。
- `cms_site_locale`：id、site_id、locale、url_prefix、name、seo_title、seo_description、settings、status、时间。唯一 `(site_id,locale)` 及 `(site_id,url_prefix)`；允许一种语言的前缀为空。
- `cms_domain`：id、site_id、host、verification_status、status、时间。规范化 host 全局唯一，不接受协议、端口、路径和首版通配符。主域名必须属于同站点。
- `cms_site_admin`：site_id、admin_id，复合唯一；普通管理员在宿主功能权限之外还必须拥有该站点权限。配置的超级管理员使用宿主身份规则访问全部站点。

一个站点必须始终保留一个启用的默认语言。站点和语言选择器按可访问的启用记录数量显示；只有一个时使用唯一值，不能通过隐藏选择器跳过后端范围校验。停用不删除数据，不自动改变旧 URL。

### 模型、栏目、内容与修订

- `cms_content_model`：id、code、name、current_version、status。内置 article/page/product/case/download；code 唯一。
- `cms_model_version`：id、model_id、version、field_schema、created_at；唯一 `(model_id,version)`，定义不可变。支持 text/textarea/number/boolean/select/image/file/gallery 等明确类型、校验、默认值与是否可翻译声明，不接受任意代码表达式。
- `cms_site_model`：site_id、model_id、default_template、status；复合唯一。模型定义为平台级，站点独立选择启用模型和模板；模型设计功能只授予明确权限。
- `cms_category`：id、site_id、parent_id、model_id（可空）、sort_order、status、时间及软删除。首版各语言共用栏目树；防止循环和跨站父节点。
- `cms_category_locale`：id、site_id、category_id、locale、name、description、cover_attach_id、list_template、detail_template、seo_title、seo_description、canonical_route_id、status、时间。唯一 `(category_id,locale)`。
- `cms_content`：id、site_id、model_id、category_id、created_by、updated_by、时间及软删除。公共身份由所有译文共用；该处栏目作为新译文默认值，各语言实际公开栏目随修订发布。
- `cms_content_locale`：id、site_id、content_id、locale、status、canonical_route_id、published_revision_id、draft_revision_id、published_at、published_updated_at、lock_version、时间及软删除。唯一 `(content_id,locale)`。
- `cms_content_revision`：id、site_id、content_locale_id、revision_no、model_version_id、category_id、title、summary、body_html、desired_path、cover_attach_id、author_name、template、seo_title、seo_description、robots、data、created_by、created_at。唯一 `(content_locale_id,revision_no)`。

首版专属字段均按语言修订分别保存，后台不提供不可翻译开关；跨语言共享设置仅限稳定身份与栏目树。未来如需不可翻译的可编辑字段，需要增加明确的公共修订及发布绑定，不能直接修改公共 JSON 导致草稿影响线上。

标题、正文、SEO 和专属 data 随修订版本保存。修订绑定模型定义版本，修改模型字段不能破坏历史读取。模型修改先生成新版本，已有内容只有经编辑校验并再次发布才采用新版本；禁止静默丢失未知旧字段。

内置字段：article 包含来源；page 无额外必填字段；product 包含型号、参数和图库；case 包含客户、行业和项目日期；download 包含附件和版本号。附件引用复用宿主 attach，ID 只引用真实可访问文件；不因删除 CMS 内容而删除共享附件。

### 路由与导航

- `cms_route`：id、site_id、path、locale、kind（page/redirect）、target_type（home/category/content）、target_id、时间。唯一 `(site_id,path)`，path 最长 512 字符并采用明确区分大小写的唯一键规则。目标分别为 site_locale/category_locale/content_locale 的 ID；服务层校验站点与语言一致。
- `cms_menu`：id、site_id、locale、code、name、status、时间。唯一 `(site_id,locale,code)`。
- `cms_menu_item`：id、site_id、menu_id、parent_id、title、target_type、target_id、external_url、open_in_new_tab、sort_order、status、时间。

内容、栏目和首页共用完整路径命名空间，旧跳转同样占用路径。每个语言目标记录只指向一个正式 page 路由；历史 redirect 指向目标身份，再解析其当前正式路由，因此多次改名不会形成重定向链。

普通内容列表索引至少覆盖 `(site_id,status,deleted_at,published_at,id)`；栏目筛选结合 content 的 `(site_id,category_id,deleted_at,id)`。数据量增长后按查询计划增加索引，不对所有 JSON 字段盲目建索引。

### 后台自定义模板标签

- `cms_template_tag`：id、site_id、locale（空字符串表示站点通用）、name、current_version、status、时间及软删除；唯一 `(site_id,locale,name)`。
- `cms_template_tag_version`：id、tag_id、version、provider、parameters、template_fragment、created_by、created_at；唯一 `(tag_id,version)`，定义不可变。

后台编辑保存新版本，通过明确发布操作切换 current_version。provider 只能选择注册器中声明允许后台使用的处理器标识。parameters 根据处理器声明的参数 schema 校验。可配置常量输出或内容、栏目、菜单等数据源，并编辑由相同模板引擎解释的 HTML 片段。语言专属标签覆盖同站点通用标签；不同站点互不访问。

## URL 与域名解析

首版选择 Nginx 将站点域名的公开动态请求内部代理到 `/cms/` 前缀，保留 Host 和原始公开路径；浏览器中的路径不变，静态资源只开放 CMS 资源及实际公开附件。原始 URI 头只由受控本机反向代理设置，应用不信任任意客户端传入的站点 ID 或协议。部署文档给出覆盖写入相关头的配置。

Webman 只注册 CMS 统一入口（使用现有注解路由，覆盖 `/cms`、`/cms/`、剩余路径和 GET/HEAD），由数据库动态路由解析内容；发布文章不生成 PHP 路由文件、不要求 worker 重启。若宿主注解路由不支持该入口形式，仅在宿主 config/route.php 增加显式的惰性注册入口，调用不存在或插件未激活时保持 404，不重新引入未加载的 route/app.php 约定。

处理顺序：插件启停 → 规范化域名 → 查询启用站点及域名 → 别名主域跳转 → 规范化公开路径 → 解析站点路由 → 校验语言启用 → 解析发布目标 → 渲染。

- host 小写并移除合法请求端口，拒绝不合法 host；站点域名配置只接受 ASCII 域名（国际化域名使用 punycode）。
- 页面路径必须以单个 `/` 开始，不含协议、主机、查询、片段、反斜线和点路径段；拒绝编码斜线、编码反斜线、双重编码歧义，采用一次解码和一致的重新编码策略。
- 默认小写 ASCII 路径片段；允许中文路径但需要一致的 Unicode/百分号编码规范。尾斜杠由配置路径决定；对应非正式形式跳到唯一正式地址，且占用冲突在发布前检查。
- 保留系统管理、身份、API 和静态目录命名空间；栏目分页 `/栏目路径/page/N/` 作为占用空间，避免内容路径覆盖。首页、sitemap.xml、robots.txt 有明确保留入口。
- 根路径由明确的默认语言首页处理。默认语言可为空前缀；增加语言不改动已有路径。更改默认语言不会自动重新分配已发布路径。
- 未绑定域名返回 404；停用站点/语言、未发布内容返回 404；服务不可用时使用真实 503；HEAD 与 GET 状态及头一致、无正文。

## 修订与发布

保存草稿只追加修订并更新 draft 指针；已发布页面始终读取 published 指针。更新草稿使用 lock_version 避免覆盖他人修改。

发布在事务中锁定语言记录，检查站点、语言、栏目、模型与版本、附件、字段和路径；占用新正式路由、更新旧路由为 redirect、切换发布指针、更新时间。唯一键处理并发路径争用，失败时全部回滚。发布旧版本作为回滚操作，保留历史。下架和删除不释放旧路径，不为失效目标输出 200。

首版不使用公共整页缓存，避免先引入失效一致性问题；仅可缓存模板解析结果，键包含模板内容哈希和注册器版本。后续整页缓存必须包含站点、语言、正式路径和发布版本，发布后使用可重试机制失效。

## 模板与标签扩展

CMS 使用独立的服务端模板渲染器，解析为节点树并解释执行；不使用 eval、不将标签拼接为 PHP、不修改全局 Raw 处理器。支持普通 HTML、变量、循环标签、条件、include、布局片段和分页。

示例语法（正式文档与实现保持一致）：

```html
<h1>{$content.title}</h1>
{cms:content model="article" category="news" limit="10" as="item"}
  <a href="{$item.url}">{$item.title}</a>
{/cms:content}
{cms:featured limit="6" as="item"}
  <a href="{$item.url}">{$item.title}</a>
{/cms:featured}
{cms:pagination /}
```

内置站点信息、当前内容、栏目、导航、内容列表、面包屑、语言切换、分页。变量只能按声明的路径取数组字段，不能调用函数或对象方法。属性支持字面量或变量引用，不支持任意表达式。限定 include 根目录，拒绝路径穿越和递归 include。

默认转义变量；正文的 HTML 只在经过专门净化并标记为可信片段后原样输出。富文本清除脚本、危险 URL、事件属性等；代码扩展是受信开发者能力，后台配置不会获得任意 PHP 或 SQL 执行入口。

开发者扩展接口由 `TagHandler`、`TagRegistry`、不可变 `RenderContext` 组成。处理器声明参数 schema、输出为标量/对象/集合/可信 HTML，以及是否允许后台配置。注册器显式加载应用配置中允许的 provider，禁止数据库直接填写任意 PHP 类名。内置标签名受保护，重复注册报错。

后台自定义标签通过命名 alias 直接调用，例如 `{cms:featured ...}`；执行时合并默认参数和允许覆盖的调用参数，再校验 schema。调用不能覆盖 site_id、locale、published_only 等隐式上下文。自定义标签片段可以调用其他标签，检测循环并限制深度（最多 16）、单次列表大小（最多 100）、整页标签调用次数（最多 200），出现错误返回明确的模板错误，公开页面不显示服务器路径与异常堆栈。

模板变量使用 `{$...}`，Vue 模板使用 `{{...}}`，两者不冲突。JS/CSS 文本中的模板标记有明确 literal 块，方便嵌入示例代码。前台 Vue/Element Plus 只挂载表单等局部区域，正文、导航、分页、SEO 不依赖客户端请求完成。

模板控制语法固定为 `{cms:if test="$content.summary"}...{cms:else /}...{/cms:if}`（仅取字段真假值）、`{cms:include file="partials/header" /}`、`{cms:literal}...{/cms:literal}`。集合标签通过 as 定义单项变量；分页通过同页指定的主列表生成。后台自定义标签调用提供块体时优先渲染调用块体，没有块体时使用已发布 template_fragment；集合按项渲染片段，对象/标量渲染一次。无片段的标量自闭合标签返回转义文本，集合/对象不得隐式转为字符串。只有类型化的可信 HTML 结果可直接输出。

## 管理端与权限

管理页面包含站点与语言、域名、栏目、内容、模型字段、导航和自定义标签；根据可访问站点与语言数量显示选择器。内容编辑包含公共栏目设置、语言草稿、模型字段、SEO、访问路径、历史版本与发布按钮。默认主题提供首页、栏目、详情、单页、404 和简单搜索入口。

管理端 API 位于 `/admin/plugin/cms/...`，声明并使用 `cms:{resource}:{action}` 权限。读取、保存、发布、下架、授权、模型管理分别控制，CSRF 保留。所有入口查询都经过站点授权，关联 ID 不能跨站；站点列表仅返回有权记录。超级管理员可创建首站点并配置站点管理员。

后台标签支持 provider 选择、参数表单、模板片段编辑、草稿保存、发布和受限预览；提供可复制调用代码。预览在授权站点上下文运行，不允许匿名读取草稿。

## SEO 与资源

服务端输出 title、description、html lang、self canonical、仅已发布可索引译文之间的互相 hreflang、结构化数据和普通 href 分页。语言缺失不将其他语言正文填到当前 URL。公开 robots 为站点配置生成，sitemap 只输出当前站点已发布且允许索引的正式地址，并使用最近发布更新时间。

Vue 和 Element Plus 普通发行资源锁定到当前项目可用版本，作为插件静态资源本地提供，不使用 latest CDN。开发前确认现有 node_modules 中对应普通发行文件；若不可用，记录依赖获取方式并使用固定版本，不为网站前台引入打包步骤。

## 验证与验收

- Manifest v2 与新目录校验、安装发布、权限、启停、保留数据的卸载测试。
- 单站单语言 → 加语言 → 加站点 → 各站不同语言组合；停用保持数据与旧路径。
- 两个 Host 相同 path 返回各自站点内容；未绑定域名、跨站关联与普通管理员跨站访问被拒绝。
- 不同语言独立发布；草稿不影响线上；并发保存/发布及路径争用不产生部分提交。
- 栏目与内容路径冲突、保留路径、Unicode/编码路径、旧地址多次修改、别名主域跳转、真实 404/503、HEAD。
- 五个模型字段保存、版本升级、历史读取、附件引用、模型禁用行为。
- 标签嵌套/分页/include/转义、PHP provider、后台自定义别名与片段、默认参数覆盖、禁止越权上下文、循环和资源上限、两站同名标签隔离。
- 页面响应源代码含正文与 SEO；关闭 JS 仍能浏览导航和内容；局部 Element Plus 表单可运行。
- 后端使用隔离 SQLite 验证业务行为；插件 SQL DDL 使用独立 MySQL 测试库验证。没有 MySQL 时明确列出未验证项目，不能以 SQLite 结果替代。
- 前端定向交互测试与类型检查，宿主必要回归；将已知工作区基线失败与新增失败分开报告。

## 实施约束与尚待审阅事项

不覆盖工作区已有修改，不操作生产数据库，不重载正在运行的服务，不申请或更改真实域名配置。源包可安装交付，是否安装到当前开发环境按既有用户授权范围与数据库环境判断；没有隔离数据库时不直接安装执行 SQL。

用户已要求按新插件模式开发，并确认 PHP 模板、三种站点语言组合、域名、自定义 URL、内容模型和两类自定义模板标签。本文件将这些讨论收敛为可审阅设计；新增的实现选择包括受限模板解释器、版本化后台标签、首版无整页缓存、模型字段首版均可翻译，以及域名的配置式接入。

独立 builder check 已完成：未发现阻碍制定实施计划的重要未决问题。后续语法细化与测试用例在不改变本设计范围的前提下由实施者完成。
