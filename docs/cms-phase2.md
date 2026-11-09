# CMS 二期：对照学习与首批交付

研究日期：2026-11-06。对照 PbootCMS 官方手册，优先借鉴能提高建站效率的工作流，在现有 Webman 插件、站点权限、语言隔离和模板协议上实现。

## 对照结果和分期

| 能力 | 官方手册中的启发 | 本项目现状与安排 |
|---|---|---|
| 公共标签格式化 | 默认值、日期、文本截取、去除 HTML | 二期首批已提供 `cms:text`、`cms:date` |
| 内容列表 | 排序、跳过若干条、分页 | 首批提供六种白名单排序、offset、分页主列表约束 |
| 循环信息 | 模板循环序号 | 首批提供 loop 序号、首尾、数量、父循环 |
| 轮播与友情链接 | 后台维护数据，模板取数展示 | 使用已有列表配置，首批提供轮播、伙伴、联系人和首页广告模块 |
| 标签配置体验 | 通过后台配置可复用标签 | 沿用已存在的模板标签编辑器，新增标签参数会进入处理器描述 |
| 复杂筛选 | 多条件、自定义字段筛选 | 后续二期增量；需要先定义允许筛选的字段、索引及前端交互 |
| 留言与表单 | 网站线索收集 | 后续增量；需独立实现防滥用、权限、存储和通知流程 |
| 缓存与交付维护 | 动态页面缓存、备份和升级 | 后续增量；需验证失效机制、恢复和真实负载，不由框架选择推断性能 |

本次完成的是二期“建站效率”首批。没有新增表单收集、自动备份或整页缓存，也没有向客户站点插入示例数据。

> 2026-11-09 更新：内置主题已迁移为 Blade。本文下方 `{cms:...}` 示例保留为旧 HTML 主题与后台受限片段的兼容参考；新主题请使用 [CMS Blade 开发指南](cms-blade.md) 的 `$cms` 调用和标准 Blade 循环。

## 1. 模板格式化

```html
<!-- 去掉 HTML 后按字符截取，最终输出仍 HTML 转义 -->
{cms:text value="$item.summary" strip_html="true" length="80" suffix="…" default="暂无摘要" /}

<!-- 日期接受 SQL/ISO 日期或 Unix 时间戳，无效日期显示默认文字 -->
{cms:date value="$item.published_at" format="Y年m月d日" default="未发布" /}
```

`text` 的 length 默认为0（不截取），范围0至10000；只在缺失/空文本时使用 default，不把0或false当成缺失。日期不接受 tomorrow 等相对表达式。不能调用任意 PHP 函数；格式化结果不会获得可信 HTML 身份。

## 2. 循环和取数

```html
{cms:content model="article" limit="6" order="title_asc" offset="1" as="article"}
  <article>
    <span>{$loop.number} / {$loop.count}</span>
    <h2><a href="{$article.url}">{$article.title}</a></h2>
    {cms:text value="$article.summary" length="60" /}
  </article>
{/cms:content}
```

loop 提供 `index`（从0开始）、`number`（从1开始）、`first`、`last`、`count`；嵌套循环使用 `loop.parent.number` 等读取外层信息。`loop` 和页面上下文名称不能作为 as 的别名。

order 可选：`published_desc`（默认）、`published_asc`、`title_asc`、`title_desc`、`id_asc`、`id_desc`。公开列表始终按已发布修订取数，不把草稿标题用于排序；相同排序值用 ID 稳定排序。当前站点、语言、模型启用、栏目及发布过滤继续生效。

offset 范围0至10000，取数 limit 为1至100；offset 不改变完整匹配 total。`config` 列表也支持 offset，先过滤停用条目再偏移。同一页面只能声明一个分页主列表（包括自定义标签间接调用），分页列表不允许非零 offset。

```html
{cms:content model="article" paginate="true" limit="12" as="article"}
  <a href="{$article.url}">{$article.title}</a>
{/cms:content}
{cms:pagination /}
```

## 3. 默认首页模块

进入“CMS → 站点管理 → 网站配置 → 添加常用列表”，选择预设，保存定义、添加条目并保存站点配置。若同名配置已存在，会打开原定义，不覆盖原字段或记录。

| 模块 | 固定配置标识 | 主要字段 | 默认首页最多展示 |
|---|---|---|---:|
| 轮播图 | slides | title、image、mobile_image、url | 8 |
| 合作伙伴 | partners | name、logo、url | 12 |
| 联系人 | contacts | name、phone、email、qrcode | 6 |
| 首页广告 | ads | name、image、url、position | 4 |

列表仍可保存100条，默认主题仅展示上述数量，以给正文和导航预留模板预算。广告 position 留空或填 `home` 才在首页显示；其他广告位可以在自定义模板调用配置标签。

条目按后台顺序展示，停用项不展示；字段为空或没有合适的图片时按模块规则跳过。配置由本站及当前语言解析，空语言列表可以显式隐藏整个模块。轮播存在有效图片时替换默认装饰头图；没有有效轮播时保留原头图。其余模块无配置时不渲染空容器。

轮播采用原生横向滚动和 CSS scroll-snap，支持触屏与键盘聚焦；JavaScript 只增强前后按钮及位置提示，无自动播放，并尊重减少动态效果设置。内容可由服务端完整输出，不依赖 Vue 初始化。

自定义模板可继续使用任意自定义标识，也可直接读取完整配置列表：

```html
{cms:config name="partners" limit="24" as="partner"}
  <a href="{$partner.url}">{$partner.name}</a>
{/cms:config}
```

## 官方参考

- 公共标签参数：`https://www.pbootcms.com/docs/296.html`
- 内容列表标签：`https://www.pbootcms.com/docs/221.html`
- 多条件筛选：`https://www.pbootcms.com/docs/232.html`
- 幻灯片：`https://www.pbootcms.com/docs/228.html`
- 友情链接：`https://www.pbootcms.com/docs/229.html`
- 自定义表单：`https://www.pbootcms.com/docs/231.html`
- 多语言：`https://www.pbootcms.com/docs/234.html`
