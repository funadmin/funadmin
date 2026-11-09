# CMS 内容管理插件

Manifest v2 插件，按仓库 `docs/plugins.md` 的项目相对路径发布。支持单站单语言、单站多语言、多站多语言以及各站点不同的语言组合；站点、语言、内容、域名与模板标签默认隔离。

## 安装与启用

1. 将完整的 `cms` 源包目录放入 `admin/plugins/cms/`。
2. 在后端目录运行 `php webman plugin:validate cms`。
3. 在插件中心的本地插件列表安装 CMS。安装通过宿主依次执行 `database/migrations/` 下的 `001_cms.sql` 至 `004_forms.sql`，必须使用 MySQL；不要手动修改已执行迁移。插件启用时补齐五个内置模型，不自动创建真实网站或域名。
4. 启用插件，按部署流程重载 Webman，让新注解路由进入 worker。
5. 插件安装会发布管理端源码；在前端项目执行 `npm run build:webman`。按宿主实际前端 base 打开后台的 CMS 菜单，根部署的站点管理页为 `/#/admin/plugins/cms/sites`；旧 `/manage` 书签自动跳转到第一个可访问菜单。
6. 超级管理员创建站点，配置域名、语言。在“站点权限”里维护本站角色，并给成员分配一个或多个角色；再由平台管理员在系统角色管理中授予相应 CMS 功能资格。实际权限为系统资格与本站启用角色权限并集的交集。

网站前台模板、CSS 和 JS 不需要 npm 构建。管理端是现有后台的一部分，需要正常构建。插件卸载保留数据库内容和修订记录；本版没有清空数据操作。

## 创建网站与语言

站点创建时生成默认语言、首页路由并启用五种内置模型。单站点或单语言时后台自动使用唯一选项，增加站点/语言后才显示切换器。启用语言例如 `zh-cn`、`en-us`，默认路径前缀例如空字符串、`en`。

增加语言不会改变已发布 URL；各语言内容独立编辑、发布、下架。中文与英文通过同一个内容身份关联，在内容编辑器中切换语言即可新增译文。栏目树共享，栏目名称、URL 与 SEO 分语言管理；未翻译的栏目在后台标为待翻译，公开页面不会回退为另一语言的正文。

每个站点可以绑定多个域名，选择一个主域名，其余作为别名跳转。后台的绑定状态不代表 DNS 或证书已配置完成；域名必须唯一，配置使用 ASCII 主机名，国际化域名先转换为 punycode，不包含协议、端口、路径或通配符。公开地址使用 HTTPS。

## 域名入口配置

以下示例假定后端监听 `127.0.0.1:8787`，项目位于 `/srv/funadmin`。替换域名、证书路径及目录后使用；管理员后台可以继续使用自己的独立虚拟主机。CMS 域名在数据库中也必须绑定到启用的站点。

```nginx
server {
    listen 80;
    server_name www.example.com example.com;
    return 301 https://$host$request_uri;
}
server {
    listen 443 ssl;
    server_name www.example.com example.com;
    ssl_certificate     /etc/nginx/certs/example/fullchain.pem;
    ssl_certificate_key /etc/nginx/certs/example/privkey.pem;

    location ^~ /plugin-assets/cms/public/ {
        root /srv/funadmin/admin/public;
        try_files $uri =404;
    }
    # 按实际附件配置开放公开附件目录。不要映射 runtime 或私有文件目录。
    location ^~ /uploads/ {
        root /srv/funadmin/admin/public;
        try_files $uri =404;
    }
    location ^~ /storage/ {
        root /srv/funadmin/admin/public;
        try_files $uri =404;
    }
    location / {
        proxy_pass http://127.0.0.1:8787/cms/;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $remote_addr;
    }
}
```

`X-Real-IP` 和 `X-Forwarded-For` 必须用 `$remote_addr` 覆盖，不要用 `$proxy_add_x_forwarded_for`：后者会把客户端自带的 `X-Forwarded-For` 原样保留在前面，访客就能自己决定“来源 IP”，绕过表单的按 IP 限流。CMS 只在直连地址是内网/回环地址（即本机或内网的反向代理）时才读取转发头：有 `X-Forwarded-For` 时只取它的最后一段，没有 `X-Forwarded-For` 时才使用 `X-Real-IP`；公网直连时一律使用直连地址。Nginx 前面还有 CDN 或负载均衡时，先用 `real_ip_header`/`set_real_ip_from` 还原真实 `$remote_addr`，再按上面覆盖。

先配置 A/AAAA 或 CNAME、证书和服务器入口，再检查站点。每个站点可以使用单独的 Nginx server 块；同一入口也可以接收多个明确列出的域名，CMS 按 Host 查找站点。未知或停用的域名返回 404，不自动转到第一个站点。

内部 `/cms/` 前缀保证经过宿主插件启停守卫。CMS 从内部请求路径去掉该前缀得到公开路径，不依赖客户端传入的站点 ID 或原始路径头。浏览器不显示 `/cms`。自定义 URL 由数据库解析，发布内容不需要修改 PHP 路由或重启 worker。

## 内容与发布

模型包含文章、单页、展示型产品、案例和下载，也可由平台管理员创建模型及字段定义，并在各站点启用。支持文本、多行文本、数字、布尔、选项、图片、附件、图库字段。文件引用宿主附件库；在附件库上传后，在 CMS 编辑器选择文件。

保存只创建不可变修订，线上页面继续使用已发布版本。发布时校验模型版本、字段、附件和访问路径，事务中切换发布指针。过期编辑通过 lock_version 拒绝，不会覆盖其他人的修改。编辑器发布按钮发布已保存的修订；未保存表单更改不会被自动发布。

各修订绑定对应模型定义。旧内容继续使用原字段定义，避免字段升级丢失历史数据。编辑器提供“采用新版字段定义”：先展示被移除或变更类型的值，再确认迁移兼容值；历史版本保留原数据。新增内容默认采用最新定义。公共栏目树保持一致，内容各语言的栏目归属随该语言修订发布。

详情模板按“内容显式指定 → 当前语言栏目默认 → 本站模型默认 → detail”的顺序选择。站点语言配置可显式修改首页路径，旧首页保留跳转。

访问路径填写完整站内路径，例如 `/company/about.html`、`/products/server-a`、`/en/about-us`；路径区分站点，不按内容类型或语言重复占用。同一站点的栏目、首页、文章和历史跳转共同检查冲突，系统路径与分页空间保留。改变标题、栏目树或默认语言不批量重写 URL。

修改并发布新路径后，旧路径成为 301，并直接指向当前正式地址。停用/下架不释放历史路径。路径统一为小写，百分号编码与中文使用同一规范；站点地址不得包含查询、片段、点路径或编码斜线。

## 公开页面缓存

首页、栏目、分页与内容详情渲染后的 HTML 按“站点 + 站点缓存版本 + 语言 + 规范路径 + 主题/站点/语言更新时间”写入共享缓存，响应带 `ETag` 与 `Cache-Control: public, no-cache`：浏览器和 CDN 每次回源校验，内容未变时返回 304 空响应，发布后不会继续展示旧页面。搜索结果、错误页、跳转、robots 与 sitemap 不缓存。

- 失效：后台 CMS 任一写接口成功后（事务提交之后）递增该站点缓存版本，整站旧页面不再命中；内容发布/下架、栏目、导航、站点、语言、域名与网站配置在服务层另行失效一次。旧条目随 TTL 自然过期。
- 配置：`app/cms/config/app.php` 的 `public_page_cache`，`enabled` 开关、`ttl` 秒数（1–3600，默认 300）、`store` 缓存 store 名（空为默认 store）。多进程部署必须使用 file/redis 等进程间共享的 store，`array` 只在单进程内有效。
- 定时发布等不经过后台写接口的变化，最晚在 TTL 后生效。页面内表单令牌由 `/forms/{code}/token` 刷新，缓存中的旧令牌不影响提交。

## 模板标签

默认主题在 `app/cms/view/default/`，发布后位于 `admin/app/cms/view/default/`。新主题可放在相邻目录，并在站点设置中选择主题标识。内置主题使用原生 Blade（`.blade.php`），支持标准 include、条件和循环，编译缓存位于 `runtime/views/cms/`。旧 `.html` 自定义主题继续使用受限解释器。完整开发指南见 [CMS Blade](../../../docs/cms-blade.md)。

```blade
@include('partials.header')
<h1>{{ $content['title'] ?? '' }}</h1>
<div>{{ $content['body'] ?? '' }}</div>
@foreach($cms->items('content', ['model' => 'article', 'category' => 'news', 'limit' => '10']) as $item)
  <a href="{{ $item['url'] ?? '' }}">{{ $item['title'] ?? '' }}</a>
@endforeach
@if(!empty($content['summary']))
  <p>{{ $content['summary'] ?? '' }}</p>
@else
  <p>暂无摘要</p>
@endif
@include('partials.footer')
```

变量默认 HTML 转义；`$content['body']` 是净化后显式标记的可信富文本。主题里的 Vue 插值使用 `@{{ query }}` 或 `@verbatim ... @endverbatim`。后台数据库展示片段保持受限 CMS 语法，不作为 Blade/PHP 编译。

| 标签 | 参数/返回 |
|---|---|
| `content` | model、category、limit(1–100)、paginate、q；返回当前站点/语言的已发布内容 |
| `category` | parent，默认 `0` 表示顶级；返回栏目与正式 URL |
| `menu` | code，默认 header；parent=0 返回顶级项目，每项包含 children；可按 parent ID 调用子项 |
| `navigation` | code；输出包含子级的完整导航 HTML，停用分支不会输出 |
| `site` | 当前站点对象；需要块体读取字段 |
| `languages` | 当前页面可访问的译文链接 |
| `breadcrumb` | 当前内容/栏目对应的栏目路径 |
| `fields` | 当前内容专属字段的 label/value，默认详情主题会展示 |
| `pagination` | 主列表 paginate=true 后输出普通链接分页 |
| `text` | value，返回转义文本 |
| `date` | value、format（默认 Y-m-d）、default；日期格式化，最终输出仍转义 |

集合用 `$cms->items(标签名, 参数数组)` 配合 Blade foreach 展示；标量或后台标签片段使用 `$cms->tag(...)`。参数可直接引用 PHP 数组变量，如 `['category' => $category['id']]`，数据处理器仍校验参数白名单，不接受 SQL 表达式。模板默认变量包含 site、locale、page、content、category、seo。

二期首批补充文本默认值/去 HTML/截取、日期格式化、loop 循环信息、内容排序/offset、分页主列表校验，以及默认首页的列表配置模块。站点配置可通过“添加常用列表”快速创建 slides / partners / contacts / ads。完整参数、首页数量限制和升级后的用法见仓库 `docs/cms-phase2.md`。

## 后台自定义标签

后台展示片段继续使用受限 CMS 语法。在“模板标签”中新建名称，例如 featured；选择 content 处理器，设置 model=article、limit=6，展示片段填写：

```html
<article><a href="{$item.url}">{$item.title}</a><p>{$item.summary}</p></article>
```

保存生成标签草稿；发布后 Blade 主题使用 `{{ $cms->tag('featured') }}`。自行展示时使用 `$cms->items('featured', ['limit' => 3])` 配合 Blade foreach。下面是旧 HTML 主题/数据库片段的兼容写法：

```html
{cms:featured limit="3" as="row"}
  <a href="{$row.url}">{$row.title}</a>
{/cms:featured}
```

调用块体优先于保存的片段。标签可以限定当前语言，也可供本站全部语言使用；语言专属定义覆盖本站通用定义。后台仅可选择已注册的处理器，不能填 PHP 类名、SQL 或绕过站点/语言/发布过滤。模板片段允许嵌套标签，循环引用会拒绝；最大嵌套16层、单列表100项、整页200次调用。

## PHP 自定义标签

实现一个可信 provider，在 `app/cms/config/app.php` 的 tag_providers 中显式注册。随插件发布该类，修改代码后按宿主部署流程重载。

```php
namespace app\cms\tags;
use app\cms\template\{TagRegistry, RenderContext};
final class CompanyTags
{
    public function register(TagRegistry $tags): void
    {
        $tags->register('company_note', new CallbackTag(
            ['label' => ['type' => 'string', 'default' => '欢迎访问']],
            static fn (array $parameters, RenderContext $context): string => $parameters['label'],
            true // 允许后台把本处理器配置为命名标签
        ));
    }
}
```

配置：`return ['tag_providers' => [\app\cms\tags\CompanyTags::class]];`。Blade 模板调用 `{{ $cms->tag('company_note', ['label' => '联系我们']) }}`。也可以直接实现 TagHandler 的 schema/configurable/resolve 三个方法。

RenderContext 中 siteId 和 locale 不可变；数据库查询必须使用该上下文限定范围。处理器可返回标量、对象数组或列表。只有可信 PHP 代码生成并显式包装为 TrustedHtml 的结果才能直接输出 HTML。内置名称和模板控制名称受保护。

## SEO 与验收

页面源代码包含正文、导航、title、description、canonical、语言与结构化数据；关闭 JS 仍可阅读和导航。四套内置主题使用本地 Vue/Element Plus 增强布局、导航、卡片、搜索与分页，具体加载方式见下方“Element Plus 浏览器直载”。浏览器资源为本地固定版本发行文件，版本及许可证见 `public/plugin-assets/cms/public/vendor/VERSIONS.txt` 与 LICENSE 文件。

分享与结构化数据由 `Seo::head` 统一输出，所有主题共用：

- Open Graph（`og:type/title/description/url/site_name/locale`）与 Twitter Card；内容有图片封面时输出绝对地址的 `og:image`（附件记录有宽高时附带尺寸）并使用 `summary_large_image`，否则为 `summary`。文章另输出 `article:published_time/modified_time`（UTC，ISO 8601）。
- JSON-LD 按模型区分：文章为 `Article`（headline、发布/修改时间、封面、publisher 机构，机构 logo 取网站配置 `logo`），产品为 `Product`（品牌、封面、`sku` 字段），语言首页为带站内搜索 `SearchAction` 的 `WebSite`，其余为 `WebPage`。品牌名优先取网站配置 `brand_name`。
- 分页列表第 2 页起 canonical 指向自身并输出 `rel="prev"`（不输出 next，搜索引擎已不再依赖该提示）。
- 每种语言首页下提供 RSS 2.0：`/feed.xml`、`/en/feed.xml` 等，列出最近 20 篇已发布且未设 noindex 的内容，页面 head 自动声明订阅地址；与公开页面共用缓存和 ETag。已发布的同名路由优先于 feed。

`/sitemap.xml` 仅包含当前站点已发布且允许索引的正式地址；`/robots.txt` 使用站点配置并输出主域 sitemap。不存在或未发布页面使用真实 404；公开请求不返回服务器堆栈；GET/HEAD 状态与头一致。

从仓库执行：

```sh
cd admin
TEST_MYSQL=0 php vendor/bin/phpunit tests/Cms
cd ../web
npx vitest run tests/cms
node scripts/check-cms.mjs
node scripts/build-cms.mjs
```

测试不会安装到正在运行的站点或执行生产迁移。MySqlSchemaTest 仅在显式提供以 funadmin_cms_test_ 开头的隔离数据库时执行；验证记录见仓库 docs/superpowers/plans/2026-11-05-cms-plugin-verification.md。

## 1.1.0 升级与站点菜单/按钮授权

已安装 1.0.0 的站点应在插件中心停用 CMS、更新并执行迁移，再启用并重载 Webman。迁移是新增 `002_site_roles.sql`，不要修改或重跑已执行的 001。安装与更新使用正式生命周期发布源码、记录资源归属和恢复日志；前台模板仍无需 npm 构建。

后台包含站点、栏目、内容、导航、模板标签、内容模型、站点权限七个子菜单。进入各页面时只显示账号有该菜单查看权限的站点，站点和语言选择跨页面保留。内容页面只获取最小站点上下文，不要求站点设置权限。“刷新”会重新加载后台菜单与本站按钮权限。

每站预置只读、编辑、发布、站点管理员及升级兼容角色；新站点不会自动给任何真实账号分配角色。已有成员只在首次升级时迁移为兼容角色，保留原系统上限内的能力，不新增授权管理或平台能力。撤销后再次启用插件不会恢复该成员角色。

“站点权限”中可创建自定义角色，逐项勾选菜单查看、新建、编辑、启停、预览、发布、下架及设置操作；分配成员支持多个角色并展示预计有效权限与缺少的系统资格。菜单查看与写入操作需分别勾选。角色停用和成员撤销在下一次 API 请求生效。

系统角色仍使用已有 `cms:content:save` 等保存资格，站点内再细分 create/edit/status；站点成员通过「站点权限」中的角色分配加入站点，没有单独的站点管理员名单授权。新能力不会静默写入真实账号的系统角色。普通授权管理员只能给宿主允许其管理的账号授予不超过自己本站有效权限的角色，也不能修改会影响本人或不可管理账号的共享角色。全局模型定义与新建站点保留平台管理员权限；模型的本站启停与模板可独立授权。

授权变更记录在 `cms_site_auth_audit`。本轮粒度为站点、菜单、按钮；不包含按栏目、内容语言、字段或“仅本人内容”的数据范围。

## 1.2.0 企业官网与资讯主题

内置 default 主题现在同时覆盖企业首页、栏目列表、文章详情、产品详情、案例详情、企业单页、资料下载和搜索结果页。风格为暖白、深绿与浅绿色点缀，本地 SVG 作为缺图时的视觉背景；不依赖外部图片或 CDN。公开内容仍由 PHP 服务端输出，关闭 JavaScript 也能阅读内容、展开手机菜单和提交搜索，无需前台 npm 构建。

首页的产品/案例/资讯区域分别取当前站点、当前语言的已发布对应模型。没有数据时显示空状态，不会自动给真实站点写入演示文章。发布内容采用默认 detail 模板时按真实模型呈现 article/product/case/page/download 布局；显式填写的自定义模板保持优先。栏目默认 category，搜索使用 search。自定义模板可以继续使用自己的分页条数。

## 网站基础与自定义配置

进入“CMS → 站点管理 → 网站基础与自定义配置”。每站预置15项：品牌简称/短句、Logo/favicon、首页主副标题和介绍、电话/邮箱/地址、联系链接、页脚文案、版权及ICP备案号/链接。可以修改名称、分组、默认值，也可添加新配置项。

站点管理按“基本信息 / 网站配置 / 语言设置 / 域名绑定”四个标签页展示。网站配置按已有分组切换标签，例如品牌形象、首页展示、联系信息和版权备案；新增分组会自动成为标签。切换标签保留当前输入，“保存站点配置”保存所有分组的值。

配置标识以小写字母开头，只能包含小写字母、数字和下划线，站点内唯一。可选类型：单行文本、多行文本、图片地址、链接、邮箱、数字、开关、颜色和下拉选择。填写新分组名称即可创建分组。Logo/图片使用公开图片 URL 或 `/storage/...` 路径；联系链接可使用本站自定义访问地址，例如 `/contact.html`。图片/链接/邮箱这些内置字段的类型受约束，以确保模板输出地址安全。

“全站默认值”维护配置定义和默认内容；选择某语言后，只显示允许翻译的配置。打开“覆盖”填写该语言的值，关闭“覆盖”即恢复继承全站值。空字符串、0 和关闭开关都属于明确设置，不会被误当成继承。

这些配置用于公开网站展示，不用于保存登录密码、API 密钥等凭据。数据写入现有站点/语言 settings JSON，不需要为新增字段执行 SQL。模板可直接读取：

```blade
<h2>{{ $config['company_mission'] ?? '' }}</h2>
<p>{{ $cms->tag('config', ['name' => 'phone']) }}</p>
@if(!empty($config['show_phone']))<span>{{ $config['phone'] ?? '' }}</span>@endif
```

文本自动转义。配置项的图片/链接类型在保存时校验 URL；自定义模板应将地址字段定义为相应类型，不把普通文本直接作为 URL 或样式代码。

### 列表配置与配置模型

合作伙伴、联系人、轮播图、广告等多条数据可直接使用站点配置的“列表 / 配置模型”类型，无需建立内容详情页或新增数据表。

1. 在“网站配置”点击“添加配置项”，填写名称、标识（例如 `partners`）及分组。
2. 类型选择“列表 / 配置模型”，使用“合作伙伴 / 联系人 / 轮播图 / 广告”预设，或自行定义条目字段。子字段支持文本、图片、链接、邮箱、数字、开关、颜色和选项，最多20个，不支持嵌套列表。`enabled` 是启停保留标识。
3. 保存配置项后，点击“添加一条”，填写内容；图片可以上传或填写公开地址。使用上移/下移排序，开关控制条目是否对模板展示，最多100条。
4. 点击“保存站点配置”保存条目。选择语言并开启覆盖后，可维护该语言的完整列表；空列表表示此语言不展示任何条目，关闭覆盖恢复全站列表。

模板使用已有 `config` 标签循环，按后台顺序返回启用条目，`limit` 可选（1至100，默认100）：

```blade
@foreach($cms->items('config', ['name' => 'partners', 'limit' => '12']) as $partner)
  <a href="{{ $partner['url'] ?? '' }}">
    @if(!empty($partner['logo']))<img src="{{ $partner['logo'] ?? '' }}" alt="{{ $partner['name'] ?? '' }}">@endif
    <span>{{ $partner['name'] ?? '' }}</span>
  </a>
@endforeach
```

字段仍自动转义，图片/链接地址保存时校验。模型调整若与已有站点值或语言覆盖不兼容，保存会拒绝，避免静默丢弃数据。已有电话等标量在 Blade 中使用 `{{ $config['phone'] }}` 或 `{{ $cms->tag('config', ['name' => 'phone']) }}`，旧 HTML 主题保留原语法。自定义列表不会自动加入主题首页，需要把循环代码放入相应主题片段。

查看配置需要本站“查看设置”，保存全站值和定义需要“修改基本设置”，保存语言覆盖需要“管理语言”；继续受宿主 CMS 功能资格限制。编辑站点名称、域名等元信息不会把旧配置快照回写覆盖新值。

## 当前本地访问

当前开发机 hosts 已配置 `127.0.0.1 www.demotest.com`，可使用 `http://www.demotest.com:8088/` 直接访问 CMS 根路径。8088 为项目独立、仅监听本机的 Nginx 入口，转发到后端内部 `/cms/`，CSS/JS/图片仍从公开资源路径访问。

配置：`admin/runtime/cms-local-nginx/nginx-8088.conf`。启动/停止命令（从本仓库根目录执行，路径按本机替换）：

```sh
nginx -p "$PWD/admin/runtime/cms-local-nginx/" -c nginx-8088.conf
nginx -p "$PWD/admin/runtime/cms-local-nginx/" -c nginx-8088.conf -s quit
```

使用无端口的域名需要监听80/443并配置证书。当前准备的 nginx.conf 为80端口配置；本机绑定80需要管理员权限，hosts 本身不会启动端口或配置反向代理。不要将本机 HTTP 预览地址当作生产 HTTPS 部署。

## 内容编辑表单

新增和编辑按八种模型字段类型渲染控件，分为“基本内容 / 模型字段 / SEO 与展示”三个标签页。模型字段支持标量默认值、填写说明、占位提示、数字最小/最大值，以及文本最大字符数（单行上限2000，多行上限20000）。新建时初始化默认值，既有0、false、显式null保持原值；缺失键才应用默认值。必填布尔false和数字0均有效。后端按修订绑定的字段版本校验，不改写历史定义。

栏目只展示启用且与当前模型匹配的选项，编辑语言切换会重新加载对应栏目名称。无效原栏目需重新选择。保存失败会在对应字段显示错误并切换到所在标签页。

关闭、离开页面和切换语言前提醒未保存修改。预览、发布和历史版本发布前须先保存当前修改。保存响应保留继续输入的内容，同时回填内容标识、乐观锁和字段版本。

## 企业与内容展示主题

在“站点管理 → 编辑站点 → 主题”选择并保存：

| 主题 | 标识 | 主要风格 |
| --- | --- | --- |
| 商务蓝白 | `corporate` | 蓝白色调、正式清晰的分栏首屏，适合综合企业、咨询与企业服务 |
| 工业科技 | `industrial` | 深色工程网格、橙色点缀，适合制造、工程与技术企业 |
| 现代品牌 | `studio` | 暖白赤陶色、衬线大字、错位作品布局，适合品牌与专业服务 |
| 杂志资讯 | `editorial` | 纸白墨黑与报刊红、刊头和新闻优先布局，适合资讯、媒体、知识分享 |
| 极简黑白 | `minimal` | 黑白大字、清晰细线、作品先行，适合设计机构、作品集与简洁品牌 |
| 数字科技 | `aurora` | 深靛背景、青紫光晕和数字空间图形，适合软件、技术服务与数字产品 |
| 东方雅韵 | `heritage` | 米纸墨绿、宋体标题和山水意境，适合文化、生活方式与传统品牌 |

每套自带首页、栏目、文章、产品、案例、单页、下载和搜索模板，兼容当前网站配置、Logo、导航、多语言、轮播、合作伙伴、联系人和广告。产品、案例和资讯继续取本站、当前语言的已发布数据；缺图使用本地 SVG，空站显示空状态。页面不依赖外部字体、图片或 CDN，无需额外前端构建。

默认 `detail` 会按实际内容模型选择内页布局；显式指定的页面模板继续优先。主题名称由内置描述服务统一提供，只有实际安装且有首页模板的目录会进入选择列表；第三方主题可继续使用自己的展示逻辑。切换主题不会改动已有内容或导航项。

模板位于 `app/cms/view/{corporate,industrial,studio,editorial,minimal,aurora,heritage}/`，主题样式与插画位于 `public/plugin-assets/cms/public/themes/`。共享展示数据和基础交互，各主题首页和样式独立维护。长手机菜单在固定页头下独立滚动，可访问屏幕下方的链接。

## Element Plus 浏览器直载

八套内置主题（`default`、`corporate`、`industrial`、`studio`、`editorial`、`minimal`、`aurora`、`heritage`）共用 `public/plugin-assets/cms/public/cms-element.js` 与 `cms-element.css`。页面通过普通 `defer` 脚本依次加载本地 Vue、Element Plus、中文语言包和共享交互脚本；修改官网 Blade、CSS、JS 后无需 npm、Vite 或打包。管理端仍沿用现有 Vue 工程。

页面布局、响应式栅格、内容卡片和封面、主按钮、桌面下拉导航、手机抽屉、语言切换、轮播、面包屑、搜索输入和分页使用真实 Element Plus 组件。各主题继续保留自己的配色和企业展示版式。

组件结构直接声明在 `app/cms/view/{主题}/` 的 `.blade.php` 模板中；`cms-element.js` 只解析声明、挂载组件并连接公共交互，不再按 CSS 类拼出菜单、卡片或布局。例如：

```blade
<el-row gutter="24" class="cms-el-grid">
  @foreach($cms->items('theme', ['name' => 'products']) as $item)
  <el-col xs="24" sm="12" lg="8">
    <el-card shadow="hover">
      <h3><a href="{{ $item['url'] ?? '' }}">{{ $item['title'] ?? '' }}</a></h3>
      <p>{{ $item['summary'] ?? '' }}</p>
    </el-card>
  </el-col>
  @endforeach
</el-row>
```

属性值是字面量，数值、布尔属性会按组件声明转换，例如 `gutter="24"`、`autoplay="false"`。具名插槽写成 `<span data-cms-slot="append">…</span>`；表单和抽屉用 `data-cms-model` 连接状态，按钮用 `data-cms-open` 打开对应抽屉。不要写 `v-*`、`:属性`、`@事件` 或 JavaScript 表达式，这些不会执行。原生降级内容用 `data-cms-fallback` 标记，成功挂载时才移除；仅增强时显示的控件用 `data-cms-enhance` 标记。

导航节点的组件结构位于每套主题的 `partials/navigation-item.blade.php`，通过 `{{ $cms->tag('navigation', ['code' => 'header', 'template' => 'partials/navigation-item']) }}` 使用，支持现有全部嵌套层级与新窗口链接。不传 `template` 时继续输出原生导航。卡片、轮播、面包屑和分页分别维护在对应的 `partials/` 文件，搜索输入框在 `search.blade.php` 中。

PHP 输出完整正文与链接。浏览器挂载不使用 Vue 模板编译器，Blade 在服务端先完成解析，站点名称和配置字符串中的花括号保持字面文本；正文和模型字段通过 `data-cms-static` 边界保留为普通 HTML，其中组件标签与交互标记不会生效。组件库缺失或组件执行异常时保留或恢复服务端页面；`cms.js` 仅处理原生导航和轮播交互。搜索保持原生 GET 表单，分页将 PHP 生成的真实 `<a href>` 放入 Element 默认插槽，挂载后仍保留栏目路径、语言前缀和编码后的关键词。


## 新增主题的选择与维护

在“CMS → 站点管理 → 编辑站点 → 主题”选择并保存，栏目、内容、导航和网站配置继续使用原数据。新增主题不插入演示记录，也不会自动替换当前站点主题。新主题各自有完整的24个 Blade 页面/片段、独立首页结构、CSS 与 SVG。可以在主题目录中直接调整布局；共享数据过滤和分页规则沿用 CMS。

主题模板和公共资源更新不需要前台 npm 构建，发布/更新插件并按 Webman 流程重载 PHP worker 即可。资源与运行模板在本地工作区已同步。

## 1.4.0 自定义表单

站点可在后台搭建联系、报名、询价等表单，主题用标签嵌入，访客提交后在后台跟进处理。字段类型：单行/多行文本、邮箱、电话、数字、单选、多选、下拉、日期、日期时间、时间、同意条款、文件、图片、隐藏字段、说明段落、分隔线。字段标识不能使用系统保留的 `captcha`、`captcha_id`、`hp`、`token`、`locale`；页面访问路径同样不能占用 `/forms` 与 `/member` 前缀。

### 升级

已安装 1.3.x 的站点：在插件中心停用 CMS → 更新（执行 `004_forms.sql`，只新增 `cms_form`、`cms_form_submission`、`cms_form_file`、`cms_form_event` 四张表）→ 启用 → 按部署流程重载 Webman，再执行 `npm run build:webman`。升级后第一次启用时，只给各站点已有的 `administrator`（站点管理员）角色补齐全部表单权限；只读、编辑、发布及自定义角色不会自动变化，需要在“站点权限”中按需勾选。补齐对每个站点只做一次：完成后在该站点的 `cms_site.settings` 中记下 `form_permissions_backfilled_at`（与补齐在同一事务中写入），之后的停用→启用、更新都不会再补；1.4.0 之后新建的站点在创建时已按预设授权并直接记下该标记。因此站点有意收回的表单权限（例如站点管理员的导出或删除）会保持收回。

升级时同时修改反向代理配置：把站点 `location` 中的转发头改为 `proxy_set_header X-Forwarded-For $remote_addr; proxy_set_header X-Real-IP $remote_addr;`（见“域名入口配置”）。原因：只设置 `X-Forwarded-For $proxy_add_x_forwarded_for` 的旧写法会把访客自己带来的 `X-Real-IP` 原样透传、把访客自带的 `X-Forwarded-For` 留在前面，两个头都改为 `$remote_addr` 后访客无法伪造。CMS 读取的是 `X-Forwarded-For` 的最后一段（没有 `X-Forwarded-For` 时才读 `X-Real-IP`），所以未修改的旧配置下表单限流仍按代理看到的地址计数，但宿主其他使用 `getRealIp()` 的功能仍会受伪造头影响，请务必修改。

| 权限码 | 含义 | 新站点预置角色 |
|---|---|---|
| `cms:form:list` | 查看表单 | 只读、编辑、发布、站点管理员 |
| `cms:form:submissions` | 查看提交、下载附件 | 只读、编辑、发布、站点管理员 |
| `cms:form:handle` | 备注、改状态、批量标为无效 | 编辑、发布、站点管理员 |
| `cms:form:manage` | 新建、编辑、启停、删除表单 | 站点管理员 |
| `cms:form:export` | 导出 CSV | 站点管理员 |
| `cms:form:delete` | 删除提交 | 站点管理员 |

与其他 CMS 能力相同，系统角色还需在宿主角色管理中授予对应资格，实际权限取两者交集。

### 后台处理

“CMS → 表单”分为两个标签页。“提交”标签显示新提交数量，可按表单、状态、日期和关键词筛选；默认只看“新提交”和“跟进中”，清空状态筛选即查看全部。详情抽屉展示提交时的字段快照、附件、来源页面与 IP，可添加备注，并在“新提交 / 跟进中 / 已完成 / 无效”之间切换状态；备注与状态变化记入时间线，只增不改。导出按当前筛选生成 UTF-8 BOM 的 CSV，Excel 可直接打开，单元格做公式注入防护。删除提交会同时删除附件文件。“表单管理”标签维护字段、多语言文字、成功提示、提交按钮文字、验证码与提交频率；已有提交的表单只能停用，不能删除。

### 主题调用与公开接口

Blade 主题使用 `{{ $cms->tag('form', ['name' => 'contact']) }}`（返回值是可信 HTML，`{{ }}` 不会再次转义；不要写成 `{!! !!}`，那样会报错），旧 HTML 主题和后台展示片段使用 `{cms:form name="contact" /}`。表单不存在或已停用时输出空字符串。主题如何覆盖表单结构见 [CMS Blade](../../../docs/cms-blade.md) 的“自定义表单”。

浏览器提交到 `POST /forms/{code}`。另有 `POST /forms/{code}/token` 刷新令牌、`GET /forms/{code}/captcha` 获取验证码图片。开启验证码的表单在页面渲染时不签发验证码（避免每次匿名访问都生成图片和缓存），`cms-form.js` 初始化时也不请求，访客第一次聚焦或点按该表单（或尚未取到就提交）时才请求受限流的 `/captcha` 接口，浏览页面不占用签发额度；获取失败（如 429 或网络异常）时在表单上方显示提示，可点“换一张”重试；关闭 JavaScript 的访客会看到“需要启用 JavaScript 才能显示验证码”的提示，无法完成带验证码的提交。按“域名入口配置”，Nginx 把 `/` 转发到内部 `/cms/`，所以后端实际路由是 `/cms/forms/...`。站点只由请求域名确定，表单代码只在该站点内查找。

请求头带 `Accept: application/json` 时返回 JSON：成功为 `{"code":0,"data":{"message":"…"}}`；失败时 HTTP 状态与 `code` 一致，`data.errors` 是“字段标识 → 错误文字”（表单级错误的键为 `_form`）。状态码含义：404 表示表单不存在或已停用，419 表示令牌无效或过期（`cms-form.js` 会自动刷新令牌重试一次），422 表示校验失败或提交过快，429 表示超过频率限制。不带该请求头的普通表单提交（关闭 JavaScript）返回独立的结果页，页面显示成功提示或错误列表，并提供返回来源页的链接。来源页只接受站内相对地址。

### 防刷与密钥

- 蜜罐：隐藏字段 `_hp` 一旦被填写，接口仍返回成功，但不保存提交。
- 签名令牌：渲染时签发，绑定站点与表单。签发 3 秒内提交视为过快；超过 7200 秒（2 小时）后令牌过期。
- 频率限制：按站点 + 表单 + IP 计数，窗口 600 秒，默认 5 次，可在表单设置中调整，最多 60 次。验证码接口另按站点 + IP 限制为每 600 秒 30 次。两处限流中 IPv6 地址都按 /64 前缀归并计数，轮换同一 /64 内的地址不能绕过限制。
- 验证码：可选，在表单设置中开启。答案缓存 300 秒，校验一次即失效。
- 密钥：令牌与验证码 ID 的签名优先使用环境变量 `CMS_FORM_SECRET`；未配置时自动生成 `runtime/plugins-data/cms/form.secret`（目录 0700、文件 0600）。多台服务器部署时应配置相同的 `CMS_FORM_SECRET`。

频率限制复用 `webman/limiter`。`config/plugin/webman/limiter/app.php` 的 `driver` 为 `auto` 时，装有 APCu 则用 APCu，否则退回每个 worker 进程独立的内存计数，实际上限会变成“设定次数 × worker 数”。生产环境建议安装 APCu，或把 `driver` 设为 `redis`。访客 IP 不使用 `getRealIp()`（它信任 `X-Forwarded-For` 的第一段，可被伪造）：直连地址为内网/回环时，有 `X-Forwarded-For` 则取其最后一段（`$proxy_add_x_forwarded_for` 追加的就是代理自己看到的地址），没有才取 `X-Real-IP`；否则使用直连地址。反向代理必须按“域名入口配置”中的写法用 `$remote_addr` 覆盖这两个头。

### 附件

上传限制为单个文件 20MB、单次提交合计 50MB、每个字段 10 个文件。扩展名白名单与 `finfo` 嗅探的 MIME 必须同时通过校验，可执行或可被浏览器执行的类型一律拒绝。文件以随机名私有保存在 `runtime/plugins-data/cms/forms/`，原文件名只记在数据库中，不能通过公开地址访问；只能在后台经 `/admin/plugins/cms/forms/files/download` 下载，需要“查看提交”权限。下载默认以 `attachment` 返回；只有 JPEG、PNG、GIF、WebP、BMP 图片在后台预览（`preview=1`）时以 `inline` 返回。所有下载响应都带 `X-Content-Type-Options: nosniff` 与 `Content-Security-Policy: default-src 'none'; sandbox`。

宿主 `config/server.php` 的 `max_package_size` 默认为 10MB，超过它的请求会被 Workerman 直接拒绝。需要接收大附件时，把它调到 50MB 以上并留出余量（例如 64MB），Nginx 也要设置对应的 `client_max_body_size`（Nginx 默认只允许 1MB）。
