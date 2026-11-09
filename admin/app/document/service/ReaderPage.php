<?php

declare(strict_types=1);

namespace app\document\service;

final class ReaderPage
{
    public const SCOPES = ['framework'=>'FunAdmin 主框架','plugin_dev'=>'插件开发','official_plugin'=>'官方插件','third_party'=>'第三方插件'];

    public static function escape(mixed $text): string
    {
        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(array $query = []): string
    {
        $query = array_filter($query, static fn ($v): bool => $v !== '' && $v !== null);
        return '/docs'.($query === [] ? '' : '?'.http_build_query($query));
    }

    public static function icon(string $name, string $class = ''): string
    {
        $paths = [
            'book'=>'<path d="M4 4h6a3 3 0 0 1 3 3v14a4 4 0 0 0-4-3H4zM20 4h-4a3 3 0 0 0-3 3v14a4 4 0 0 1 4-3h3z"/>',
            'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>',
            'arrow'=>'<path d="M5 12h14m-5-5 5 5-5 5"/>',
            'chevron'=>'<path d="m9 5 7 7-7 7"/>',
            'code'=>'<path d="m8 7-5 5 5 5m8-10 5 5-5 5m-3-13-2 16"/>',
            'layers'=>'<path d="m12 3 9 5-9 5-9-5zm-9 9 9 5 9-5M3 16l9 5 9-5"/>',
            'plugin'=>'<path d="M8 3v4m8-4v4M6 7h12v5a6 6 0 0 1-12 0zm6 11v4"/>',
            'box'=>'<path d="m12 3 9 5v9l-9 5-9-5V8zm-9 5 9 5 9-5m-9 5v9M7.5 5.5l9 5"/>',
            'external'=>'<path d="M14 3h7v7m0-7L10 14m0-10H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-5"/>',
            'copy'=>'<rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4"/>',
            'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
            'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
            'moon'=>'<path d="M20.8 13A9 9 0 0 1 11 3.2 9 9 0 1 0 20.8 13Z"/>',
            'sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>',
            'terminal'=>'<path d="m5 6 5 6-5 6m8 0h6"/>',
            'check'=>'<path d="m5 12 4 4L19 6"/>',
            'thumb-up'=>'<path d="M7 10v12M15 5.9 14 10h5.8a2 2 0 0 1 1.9 2.6l-2.3 8a2 2 0 0 1-1.9 1.4H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.8a2 2 0 0 0 1.8-1.1L12 2a3.1 3.1 0 0 1 3 3.9Z"/>',
            'thumb-down'=>'<path d="M17 14V2M9 18.1 10 14H4.2a2 2 0 0 1-1.9-2.6l2.3-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.8a2 2 0 0 0-1.8 1.1L12 22a3.1 3.1 0 0 1-3-3.9Z"/>',
            'home'=>'<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/>',
        ];
        return '<svg class="icon '.self::escape($class).'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['book']).'</svg>';
    }

    public static function assetPath(string $name): string
    {
        if (!in_array($name, ['reader.css', 'reader.js'], true)) throw new \InvalidArgumentException('未知文档资源');
        return dirname(__DIR__).'/view/public/'.$name;
    }

    public function render(array $listing, array $categories, ?array $document, array $filters = [], ?string $error = null): string
    {
        $content = $document ? (new PublicContentRenderer())->render($document['content']) : ['html'=>'','outline'=>[]];
        $title = $error ?? ($document['title'] ?? '文档中心');
        $keyword = is_string($filters['keyword'] ?? null) ? mb_substr($filters['keyword'], 0, 100) : '';
        $scope = is_string($filters['scope'] ?? null) ? $filters['scope'] : ($document['scope'] ?? '');
        $categoryId = is_scalar($filters['categoryId'] ?? null) ? (string)$filters['categoryId'] : '';
        $depth = static function (array $category) use ($categories): int {
            $parents = array_column($categories, 'parentId', 'id');
            $level = 0; $seen = []; $parent = $category['parentId'];
            while ($parent && isset($parents[$parent]) && !isset($seen[$parent])) { $seen[$parent]=true; $level++; $parent=$parents[$parent]; }
            return min($level, 8);
        };
        $categoryName = '';
        foreach ($categories as $category) if ((string)$category['id'] === $categoryId) $categoryName = $category['name'];
        $listingTitle = $keyword !== '' ? '搜索结果' : ($categoryName ?: (self::SCOPES[$scope] ?? '最近更新'));
        $previous = $next = null;
        if ($document) {
            // 上一篇、下一篇按左侧菜单顺序；文档不在任何目录时按列表顺序。
            $sequence = [];
            foreach ($categories as $category) foreach ($category['documents'] ?? [] as $item) $sequence[$item['slug']] ??= $item;
            $sequence = array_values($sequence);
            $index = array_search($document['slug'], array_column($sequence, 'slug'), true);
            if ($index === false) { $sequence = $listing['list']; $index = array_search($document['slug'], array_column($sequence, 'slug'), true); }
            if ($index !== false) { $previous = $sequence[$index-1] ?? null; $next = $sequence[$index+1] ?? null; }
        }
        $assetsVersion = substr(hash('sha256', (string)@file_get_contents(self::assetPath('reader.css')).(string)@file_get_contents(self::assetPath('reader.js'))), 0, 12);
        // 模板为 Blade：app/document/view/public/reader.blade.php（{{ }} 自动转义，图标与已清洗的正文用 {!! !!}）。
        return \app\common\view\Blade::render('public/reader', compact(
            'title', 'document', 'error', 'assetsVersion', 'keyword', 'scope', 'categoryId', 'categories', 'depth',
            'content', 'previous', 'next', 'listing', 'listingTitle', 'filters'
        ), 'document', '');
    }
}
