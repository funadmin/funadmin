<?php
namespace app\document\view;
use app\document\service\ReaderPage as Page;
$sourceUrl = 'https://gitee.com/funadmin/funadmin';
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=fun-balanced-1">
  <title><?= Page::escape($title) ?> · FunAdmin 文档</title>
  <meta name="description" content="<?= Page::escape($document['summary'] ?? 'FunAdmin 框架指南与插件开发文档') ?>">
  <?php if ($error): ?><meta name="robots" content="noindex"><?php endif ?>
  <link rel="stylesheet" href="/docs/assets/reader.css?v=<?= $assetsVersion ?>">
  <script src="/docs/assets/reader.js?v=<?= $assetsVersion ?>" defer></script>
</head>
<body id="top">
<a class="skip-link" href="#main-content">跳转到正文</a>
<header class="site-header">
  <a class="brand" href="/docs" aria-label="FunAdmin 文档首页"><span class="brand-mark" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 64 64" fill="none"><rect width="64" height="64" rx="16" fill="currentColor" /><path d="M11.5 17H32.125L28.75 23.75H16.75V29H27.625L24.25 35.75H16.75V44Q16.75 45.5 15.25 45.5H10V18.5Q10 17 11.5 17Z" fill="#FFFFFF" /><path d="M34.5625 17H41.3125L37.9375 23.75H31.1875Z" fill="#FFFFFF" opacity=".78" /><path d="M32.748 45.560Q31.331 45.560 30.422 45.085Q29.513 44.610 28.993 43.872Q28.472 43.135 28.236 42.321Q28.001 41.507 27.940 40.807Q27.880 40.108 27.880 39.732L27.880 32.780L30.870 32.780L30.870 38.704Q30.870 39.205 30.935 39.892Q30.999 40.580 31.265 41.244Q31.530 41.909 32.094 42.346Q32.657 42.783 33.655 42.783Q34.119 42.783 34.607 42.634Q35.095 42.485 35.505 42.096Q35.916 41.706 36.172 40.983Q36.428 40.260 36.428 39.122L38.148 39.900Q38.148 41.427 37.541 42.717Q36.934 44.006 35.736 44.783Q34.538 45.560 32.748 45.560ZM36.793 45.200L36.793 41.215L36.428 41.215L36.428 32.780L39.397 32.780L39.397 45.200Z" fill="#FFFFFF" /><path d="M50.795 45.200L50.795 39.276Q50.795 38.774 50.730 38.087Q50.665 37.400 50.400 36.736Q50.134 36.071 49.571 35.634Q49.007 35.197 48.010 35.197Q47.546 35.197 47.058 35.346Q46.569 35.495 46.159 35.884Q45.749 36.274 45.493 36.992Q45.236 37.710 45.236 38.858L43.517 38.080Q43.517 36.553 44.124 35.263Q44.730 33.974 45.928 33.197Q47.126 32.420 48.916 32.420Q50.334 32.420 51.243 32.895Q52.152 33.370 52.672 34.108Q53.192 34.845 53.428 35.659Q53.664 36.473 53.724 37.173Q53.785 37.872 53.785 38.248L53.785 45.200ZM42.246 45.200L42.246 32.780L44.872 32.780L44.872 36.765L45.236 36.765L45.236 45.200Z" fill="#FFFFFF" /></svg></span><span>FunAdmin</span></a>
  <a class="site-title" href="/docs">官方文档</a>
  <nav class="header-nav" aria-label="站点导航"><a href="/">首页</a><a href="/docs?scope=framework">框架指南</a><a href="/docs?scope=plugin_dev">插件开发</a><a href="<?= $sourceUrl ?>" target="_blank" rel="noopener noreferrer">源代码 <?= Page::icon('external') ?></a></nav>
  <div class="header-actions"><button class="icon-button mobile-search" type="button" data-open-search aria-label="搜索文档"><?= Page::icon('search') ?></button><button class="icon-button" type="button" data-theme-toggle aria-label="切换主题"><?= Page::icon('moon','theme-moon') ?><?= Page::icon('sun','theme-sun') ?></button></div>
  <?php if ($document): ?><div class="reading-progress" data-reading-progress></div><?php endif ?>
</header>
<div class="docs-shell">
  <aside class="sidebar"><details class="navigation" data-navigation open><summary class="mobile-nav-heading"><?= Page::icon('menu') ?><span>文档目录</span><?= Page::icon('chevron') ?></summary><div class="sidebar-content">
    <form class="sidebar-search" action="/docs" method="get" role="search"><button type="submit" aria-label="提交搜索"><?= Page::icon('search') ?></button><input id="docs-search" type="search" name="keyword" value="<?= Page::escape($keyword) ?>" placeholder="搜索文档…" aria-label="搜索文档标题与摘要" maxlength="100" autocomplete="off"></form>
    <nav class="scope-nav" aria-label="文档分类"><a class="nav-link <?= $scope === '' ? 'active' : '' ?>" href="/docs">文档首页</a><?php foreach (Page::SCOPES as $key=>$name): ?><a class="nav-link <?= $scope === $key ? 'active' : '' ?>" href="<?= Page::escape(Page::url(['scope'=>$key])) ?>"><?= Page::escape($name) ?></a><?php endforeach ?></nav>
    <?php if ($categories): ?><div class="sidebar-label">目录</div><nav class="category-nav" aria-label="文档目录"><?php foreach ($categories as $category): ?><details class="category-group" data-category-id="<?= Page::escape($category['id']) ?>" style="--depth:<?= $depth($category) ?>" open><summary class="category-link"><?= Page::escape($category['name']) ?></summary><?php foreach ($category['documents'] ?? [] as $item): $current = $document && $item['slug'] === $document['slug']; ?><a class="chapter-link <?= $current ? 'active' : '' ?>" href="/docs/<?= rawurlencode($item['slug']) ?>" <?= $current ? 'aria-current="page"' : '' ?>><?= Page::escape($item['title']) ?></a><?php endforeach ?></details><?php endforeach ?></nav><?php endif ?>
  </div></details></aside>

  <main id="main-content" class="main-content" tabindex="-1">
    <?php if ($error): ?>
      <h1><?= Page::escape($error) ?></h1><p class="muted">这篇文档暂时无法访问，你可以从左侧目录寻找其他内容。</p><a class="text-link" href="/docs">返回文档首页 →</a>
    <?php elseif ($document): ?>
      <nav class="breadcrumbs" aria-label="当前位置"><a href="/docs">文档</a><span>/</span><a href="<?= Page::escape(Page::url(['scope'=>$document['scope']])) ?>"><?= Page::escape(Page::SCOPES[$document['scope']] ?? '指南') ?></a><span>/</span><span><?= Page::escape($document['title']) ?></span></nav>
      <div class="article-heading"><h1><?= Page::escape($document['title']) ?></h1><?php if ($document['summary'] !== ''): ?><p class="article-summary"><?= Page::escape($document['summary']) ?></p><?php endif ?><div class="article-meta"><span>更新于 <time datetime="<?= Page::escape($document['publishedAt']) ?>"><?= Page::escape(substr($document['publishedAt'],0,10)) ?></time></span><span>第 <?= (int)$document['revisionNo'] ?> 版</span><?php if ($document['appliesTo'] !== ''): ?><span>适用 <?= Page::escape($document['appliesTo']) ?></span><?php endif ?><button class="text-button copy-page" type="button" data-copy-page><?= Page::icon('copy') ?><span data-copy-label>复制链接</span></button></div></div>
      <?php if ($content['outline']): ?><details class="mobile-toc"><summary>本页目录</summary><nav aria-label="本页目录"><?php foreach ($content['outline'] as $heading): ?><a href="#<?= Page::escape($heading['id']) ?>"><?= Page::escape($heading['title']) ?></a><?php endforeach ?></nav></details><?php endif ?>
      <article class="reader-body" data-reader-content><?= $content['html'] ?></article>
      <nav class="article-pagination" aria-label="继续阅读"><?php if ($previous): ?><a href="/docs/<?= rawurlencode($previous['slug']) ?>"><small>上一篇</small><span>← <?= Page::escape($previous['title']) ?></span></a><?php else: ?><a href="<?= Page::escape(Page::url(['scope'=>$document['scope']])) ?>"><small>返回目录</small><span>← <?= Page::escape(Page::SCOPES[$document['scope']] ?? '文档目录') ?></span></a><?php endif ?><?php if ($next): ?><a class="next-article" href="/docs/<?= rawurlencode($next['slug']) ?>"><small>下一篇</small><span><?= Page::escape($next['title']) ?> →</span></a><?php endif ?></nav>
    <?php else: ?>
      <h1><?= Page::escape($scope !== '' || $categoryId !== '' || $keyword !== '' ? $listingTitle : '文档中心') ?></h1><p class="page-description"><?= $keyword !== '' ? '与「'.Page::escape($keyword).'」相关的文档' : 'FunAdmin 框架指南、插件开发与使用参考。' ?></p>
      <div class="list-heading"><span>共 <?= (int)$listing['total'] ?> 篇文档</span><?php if ($keyword !== '' || $scope !== '' || $categoryId !== ''): ?><a href="/docs">浏览全部文档</a><?php endif ?></div>
      <?php if (!$listing['list']): ?><div class="empty-state"><h2><?= $keyword !== '' ? '没有找到相关文档' : '暂无公开文档' ?></h2><p><?= $keyword !== '' ? '试试其他关键词，或从左侧选择一个分类。' : '文档内容正在整理，欢迎稍后再来查看。' ?></p></div><?php endif ?>
      <div class="document-list"><?php foreach ($listing['list'] as $item): ?><a class="document-item" href="/docs/<?= rawurlencode($item['slug']) ?>"><h2><?= Page::escape($item['title']) ?></h2><?php if ($item['summary'] !== ''): ?><p><?= Page::escape($item['summary']) ?></p><?php endif ?><small><?= Page::escape(Page::SCOPES[$item['scope']] ?? '') ?> · <?= Page::escape(substr($item['publishedAt'],0,10)) ?></small></a><?php endforeach ?></div>
      <?php $pages=max(1,(int)ceil($listing['total']/$listing['pageSize'])); if ($pages>1): ?><nav class="pager" aria-label="文档分页"><?php if ($listing['page']>1): ?><a href="<?= Page::escape(Page::url(array_replace($filters,['page'=>$listing['page']-1]))) ?>">← 上一页</a><?php else: ?><span></span><?php endif ?><span><?= (int)$listing['page'] ?> / <?= $pages ?></span><?php if ($listing['page']<$pages): ?><a href="<?= Page::escape(Page::url(array_replace($filters,['page'=>$listing['page']+1]))) ?>">下一页 →</a><?php endif ?></nav><?php endif ?>
    <?php endif ?>
  </main>
  <aside class="context-rail"><?php if ($document && $content['outline']): ?><nav class="toc-nav" aria-label="本页导航"><?php foreach ($content['outline'] as $heading): ?><a data-toc-link style="--level:<?= max(0,$heading['level']-2) ?>" href="#<?= Page::escape($heading['id']) ?>"><?= Page::escape($heading['title']) ?></a><?php endforeach ?></nav><a class="back-top" href="#top">返回顶部 ↑</a><?php endif ?></aside>
</div>
<footer class="site-footer"><span>Powered by <a href="<?= $sourceUrl ?>" target="_blank" rel="noopener noreferrer">FunAdmin</a></span><span>框架指南与插件开发文档</span></footer>
<div class="copy-feedback" data-copy-feedback role="status" aria-live="polite"></div>
</body>
</html>
