@use('app\document\service\ReaderPage', 'Page')
@php($sourceUrl = 'https://gitee.com/funadmin/funadmin')
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=fun-inset-a-1">
  <title>{{ $title }} · FunAdmin 文档</title>
  <meta name="description" content="{{ $document['summary'] ?? 'FunAdmin 框架指南与插件开发文档' }}">
  @if($error)<meta name="robots" content="noindex">@endif
  <link rel="stylesheet" href="/docs/assets/reader.css?v={{ $assetsVersion }}">
  <script src="/docs/assets/reader.js?v={{ $assetsVersion }}" defer></script>
</head>
<body id="top">
<a class="skip-link" href="#main-content">跳转到正文</a>
<header class="site-header">
  <a class="brand" href="/docs" aria-label="FunAdmin 文档首页"><span class="brand-mark" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 64 64" fill="none"><rect width="64" height="64" rx="16" fill="currentColor" /><path d="M15 17H35.625L32.25 23.75H20.25V44Q20.25 45.5 18.75 45.5H13.5V18.5Q13.5 17 15 17Z" fill="#FFFFFF" /><path d="M38.0625 17H44.8125L41.4375 23.75H34.6875Z" fill="#FFFFFF" opacity=".78" /><path d="M24.5 28H28.8V39.7Q28.8 41.2 30.3 41.2H31.9V28H36.2V45.5H28L24.5 38.5Z" fill="#FFFFFF" /><path d="M40 28H48L51.3 34.6V45.5H47V33.8Q47 32.3 45.5 32.3H43.6V45.5H39.3V28.7Q39.3 28 40 28Z" fill="#FFFFFF" /></svg></span><span>FunAdmin</span></a>
  <nav class="header-tabs" aria-label="站点导航"><a class="active" href="/docs" aria-current="page">文档中心</a><a href="/">官网首页</a><a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">源代码 {!! Page::icon('external') !!}</a></nav>
  <form class="header-search" action="/docs" method="get" role="search"><input data-docs-search id="header-docs-search" type="search" name="keyword" value="{{ $keyword }}" placeholder="请输入关键词，如：权限、插件开发、表单 Schema…" aria-label="搜索文档标题与摘要" maxlength="100" autocomplete="off"><kbd>⌘ K</kbd><button class="header-search-icon" type="submit" aria-label="搜索">{!! Page::icon('search') !!}</button></form>
  <div class="header-actions"><button class="icon-button mobile-search" type="button" data-open-search aria-label="搜索文档">{!! Page::icon('search') !!}</button><button class="icon-button" type="button" data-theme-toggle aria-label="切换主题">{!! Page::icon('moon','theme-moon') !!}{!! Page::icon('sun','theme-sun') !!}</button></div>
  @if($document)<div class="reading-progress" data-reading-progress></div>@endif
</header>
<div class="docs-shell">
  <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="收起目录" aria-expanded="true">{!! Page::icon('chevron') !!}</button>
  <aside class="sidebar"><details class="navigation" data-navigation open><summary class="mobile-nav-heading">{!! Page::icon('menu') !!}<span>文档目录</span>{!! Page::icon('chevron') !!}</summary><div class="sidebar-content">
    <form class="sidebar-search" action="/docs" method="get" role="search"><button type="submit" aria-label="提交搜索">{!! Page::icon('search') !!}</button><input data-docs-search id="docs-search" type="search" name="keyword" value="{{ $keyword }}" placeholder="搜索文档…" aria-label="搜索文档标题与摘要" maxlength="100" autocomplete="off"></form>
    <nav class="scope-nav" aria-label="文档分类"><a class="nav-link {{ $scope === '' ? 'active' : '' }}" href="/docs">文档首页</a>@foreach(Page::SCOPES as $key => $name)<a class="nav-link {{ $scope === $key ? 'active' : '' }}" href="{{ Page::url(['scope' => $key]) }}">{{ $name }}</a>@endforeach</nav>
    @if($categories)<div class="sidebar-label">文档目录</div><nav class="category-nav" aria-label="文档目录">@php($openGroups = 0)@foreach($categories as $category)@php($level = $depth($category))@while($openGroups > $level)</details>@php($openGroups--)@endwhile<details class="category-group" data-category-id="{{ $category['id'] }}" style="--depth:{{ $level }}" open><summary class="category-link">{{ $category['name'] }}</summary>@foreach($category['documents'] ?? [] as $item)@php($current = $document && $item['slug'] === $document['slug'])<a class="chapter-link {{ $current ? 'active' : '' }}" href="/docs/{{ rawurlencode($item['slug']) }}" {!! $current ? 'aria-current="page"' : '' !!}>{{ $item['title'] }}</a>@endforeach @php($openGroups = $level + 1)@endforeach @while($openGroups > 0)</details>@php($openGroups--)@endwhile</nav>@endif
  </div></details></aside>

  <main id="main-content" class="main-content" tabindex="-1">
    @if($error)
      <h1>{{ $error }}</h1><p class="muted">这篇文档暂时无法访问，你可以从左侧目录寻找其他内容。</p><a class="text-link" href="/docs">返回文档首页 →</a>
    @elseif($document)
      <nav class="breadcrumbs" aria-label="当前位置"><a href="/docs">文档中心</a><span class="crumb-sep">{!! Page::icon('chevron') !!}</span><a href="{{ Page::url(['scope' => $document['scope']]) }}">{{ Page::SCOPES[$document['scope']] ?? '指南' }}</a><span class="crumb-sep">{!! Page::icon('chevron') !!}</span><span aria-current="page">{{ $document['title'] }}</span></nav>
      <div class="article-heading"><h1>{{ $document['title'] }}</h1>@if($document['summary'] !== '')<p class="article-summary">{{ $document['summary'] }}</p>@endif<div class="article-meta"><span>更新于 <time datetime="{{ $document['publishedAt'] }}">{{ substr($document['publishedAt'], 0, 10) }}</time></span><span>第 {{ (int) $document['revisionNo'] }} 版</span>@if($document['appliesTo'] !== '')<span>适用 {{ $document['appliesTo'] }}</span>@endif<button class="text-button copy-page" type="button" data-copy-page>{!! Page::icon('copy') !!}<span data-copy-label>复制链接</span></button></div></div>
      @if($content['outline'])<details class="mobile-toc"><summary>本页目录</summary><nav aria-label="本页目录">@foreach($content['outline'] as $heading)<a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>@endforeach</nav></details>@endif
      <article class="reader-body" data-reader-content>{!! $content['html'] !!}</article>
      @include('public.feedback', ['placement' => 'article'])
      <p class="article-help">未能解决你的问题？欢迎到 <a href="{{ $sourceUrl }}/issues" target="_blank" rel="noopener noreferrer">源代码仓库提交 Issue</a> 反馈。</p>
      <nav class="article-pagination" aria-label="继续阅读">@if($previous)<a href="/docs/{{ rawurlencode($previous['slug']) }}"><small>上一篇</small><span>{{ $previous['title'] }}</span></a>@else<a href="{{ Page::url(['scope' => $document['scope']]) }}"><small>返回目录</small><span>{{ Page::SCOPES[$document['scope']] ?? '文档目录' }}</span></a>@endif
@if($next)<a class="next-article" href="/docs/{{ rawurlencode($next['slug']) }}"><small>下一篇</small><span>{{ $next['title'] }}</span></a>@endif</nav>
    @else
      <h1>{{ $scope !== '' || $categoryId !== '' || $keyword !== '' ? $listingTitle : '文档中心' }}</h1><p class="page-description">{!! $keyword !== '' ? '与「'.e($keyword).'」相关的文档' : 'FunAdmin 框架指南、插件开发与使用参考。' !!}</p>
      <div class="list-heading"><span>共 {{ (int) $listing['total'] }} 篇文档</span>@if($keyword !== '' || $scope !== '' || $categoryId !== '')<a href="/docs">浏览全部文档</a>@endif</div>
      @if(!$listing['list'])<div class="empty-state"><h2>{{ $keyword !== '' ? '没有找到相关文档' : '暂无公开文档' }}</h2><p>{{ $keyword !== '' ? '试试其他关键词，或从左侧选择一个分类。' : '文档内容正在整理，欢迎稍后再来查看。' }}</p></div>@endif
      <div class="document-list">@foreach($listing['list'] as $item)<a class="document-item" href="/docs/{{ rawurlencode($item['slug']) }}"><h2>{{ $item['title'] }}</h2>@if($item['summary'] !== '')<p>{{ $item['summary'] }}</p>@endif<small>{{ Page::SCOPES[$item['scope']] ?? '' }} · {{ substr($item['publishedAt'], 0, 10) }}</small></a>@endforeach</div>
      @php($pages = max(1, (int) ceil($listing['total'] / $listing['pageSize'])))@if($pages > 1)<nav class="pager" aria-label="文档分页">@if($listing['page'] > 1)<a href="{{ Page::url(array_replace($filters, ['page' => $listing['page'] - 1])) }}">← 上一页</a>@else<span></span>@endif<span>{{ (int) $listing['page'] }} / {{ $pages }}</span>@if($listing['page'] < $pages)<a href="{{ Page::url(array_replace($filters, ['page' => $listing['page'] + 1])) }}">下一页 →</a>@endif</nav>@endif
    @endif
  </main>
  <aside class="context-rail">@if($document)@include('public.feedback', ['placement' => 'rail'])@if($content['outline'])<div class="toc-title">本页目录</div><nav class="toc-nav" aria-label="本页导航">@foreach($content['outline'] as $heading)<a data-toc-link style="--level:{{ max(0, $heading['level'] - 2) }}" href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>@endforeach</nav>@endif<a class="back-top" href="#top">返回顶部 ↑</a>@endif</aside>
</div>
<footer class="site-footer"><span>Powered by <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer">FunAdmin</a></span><span>框架指南与插件开发文档</span></footer>
<div class="copy-feedback" data-copy-feedback role="status" aria-live="polite"></div>
</body>
</html>
