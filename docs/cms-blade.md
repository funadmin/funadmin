# CMS Blade 主题开发

CMS 的八套内置主题（default、corporate、industrial、studio、editorial、minimal、aurora、heritage）使用原生 Blade。源文件在 `admin/plugins/cms/app/cms/view/{theme}/`，安装后运行文件在 `admin/app/cms/view/{theme}/`。模板文件后缀为 `.blade.php`，编译缓存位于 `admin/runtime/views/cms/`。依赖宿主已有的 `illuminate/view`，无需另装 Laravel 或构建官网前端。

## 页面和布局

页面、栏目和模型中保存的模板标识仍填写 `detail`、`article`、`category` 或 `custom/landing`，不要带扩展名。主题选择器识别包含 `index.blade.php` 的主题；旧 `index.html` 自定义主题仍能使用。同名文件优先 Blade，模板列表不会重复列出两种扩展名。

```blade
{{-- article.blade.php --}}
@include('partials.header')
<h1>{{ $content['title'] ?? '' }}</h1>
@if(!empty($content['summary']))
    <p>{{ $content['summary'] }}</p>
@endif
<div data-cms-static>{{ $content['body'] }}</div>
@include('partials.footer')
```

支持标准 `@extends`、`@section`、`@yield`、`@include`、`@if`、`@foreach`。include/layout 的模板名支持点号或斜线，仅在当前主题内查找；拒绝点路径、绝对路径、跨主题查找及指向主题外的符号链接。

变量包括 `$site`、`$locale`、`$page`、`$content`、`$category`、`$seo`、`$config`。内置主题另有 `$ui`、`$home`、`$listing`、`$display` 等展示数据。数组通过 PHP 数组语法读取，缺失可选值使用 `?? ''`。变量自动 HTML 转义；`$content['body']`、`$seo['head']` 等由后端明确构建的 TrustedHtml 会保留 HTML，不要对普通用户输入使用原样输出。

## CMS 数据标签

每次渲染注入独立的 `$cms`，沿用原有标签注册表和 PHP provider。所有查询保持当前站点、语言、模型启用和发布状态过滤；不能通过参数覆盖 site_id/locale/published_only。

- `$cms->items('标签名', 参数数组)`：取得列表；对象或标量按一项返回。列表上限100项。
- `$cms->value('标签名', 参数数组)`：取得处理器原始结果，用于模板自行展示及条件判断；自定义标签的默认参数仍有效。
- `$cms->tag('标签名', 参数数组)`：返回可安全输出的结果；自定义标签使用后台已发布的展示片段。没有展示片段时，集合应改用 items 遍历。

```blade
@foreach($cms->items('content', ['model' => 'article', 'limit' => 6, 'order' => 'published_desc']) as $item)
    <article>
        <span>{{ $loop->iteration }} / {{ $loop->count }}</span>
        <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
        <p>{{ $cms->tag('text', ['value' => $item['summary'], 'strip_html' => true, 'length' => 80]) }}</p>
    </article>
@endforeach

{{ $cms->tag('navigation', ['code' => 'header', 'template' => 'partials/navigation-item']) }}
{{ $cms->tag('date', ['value' => $content['published_at'] ?? '', 'format' => 'Y-m-d']) }}
```

Blade 循环序号是 `$loop->iteration`，父循环是 `$loop->parent`；旧片段中的 `{$loop.number}` 保持原写法。

## 分页及配置列表

```blade
@foreach($cms->items('content', ['model' => 'article', 'limit' => 12, 'paginate' => true]) as $item)
    <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
@endforeach
{{ $cms->tag('pagination') }}

@foreach($cms->items('config', ['name' => 'partners', 'limit' => 12]) as $partner)
    <a href="{{ $partner['url'] }}">{{ $partner['name'] }}</a>
@endforeach
```

同一页面只允许一个分页主列表，分页列表不能使用非零 offset。不存在的分页继续返回404。自定义标签与直接 items 调用共享分页状态，调用自定义片段不会清空分页。

## 后台自定义标签

后台“模板标签”列表展示可复制的 Blade 调用示例，例如：

```blade
{{ $cms->tag('company_news') }}

{{-- 自己编写展示时，可覆盖允许的参数并循环其数据。 --}}
@foreach($cms->items('company_news', ['limit' => 3]) as $item)
    <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
@endforeach
```

后台编辑器的**展示片段继续使用受限 CMS 语法**，例如 `<a href="{$item.url}">{$item.title}</a>`，也可以嵌套原有 `{cms:...}` 标签。片段中的 `@php`、`{{ ... }}` 均是文本，不会作为 Blade 编译，PHP 开始标记继续被拒绝。

片段可用 `{cms:include file="partials/card" /}` 引用主题内可信的 Blade 文件，数据会按 Blade 的转义规则输出。模板文件由开发者部署，后台片段不能定义服务器代码。调用仍受到200次标签调用、16层受限片段嵌套、100项列表及8MB输出限制。

## Vue 和旧主题

需要在主题文件里保留 Vue 插值时使用 `@{{ query }}`，或者用 `@verbatim ... @endverbatim` 包住字面模板。内容和配置字符串中的花括号不会再次进行 Blade 编译。

原 `.html` 自定义主题和数据库片段仍使用旧解释器，`{$content.title}`、`{cms:content ...}` 等语法继续有效；`.blade.php` 文件应使用本页的原生 Blade 写法，不能混写旧 CMS 指令。旧 HTML 页面可包含已迁移的可信 Blade partial。

## 部署和验证

使用插件更新流程发布源包；本工作区同步了 `admin/app/cms` 运行镜像。主题文件本身不需要 npm 构建，后台标签使用示例更新需正常构建管理端。部署 PHP 类后按 Webman 流程重载 worker。内容、栏目、站点数据不需要迁移，也不会因切换引擎自动改写。

隔离测试：`cd admin && vendor/bin/phpunit tests/Cms/`。额外测试覆盖原生 Blade、HTML兼容、后台片段、跨主题隔离、文件越界及分页错误码；MySQL专用测试需要显式配置隔离测试库。
