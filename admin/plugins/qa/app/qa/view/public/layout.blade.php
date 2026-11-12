{{-- 问答前台页面外框：所有文本 {{ }} 转义；JSON-LD 已用 JSON_HEX_* 编码（PublicPages::structured）。 --}}
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="index,follow">
<link rel="canonical" href="{{ $canonical }}">
@if ($prev)
<link rel="prev" href="{{ $prev }}">
@endif
@if ($next)
<link rel="next" href="{{ $next }}">
@endif
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta name="twitter:card" content="summary">
@if ($jsonLd !== null)
<script type="application/ld+json">{!! $jsonLd !!}</script>
@endif
<style>
:root{color-scheme:light dark;--bg:#f6f7f8;--card:#fff;--text:#1f2a24;--muted:#6b7a72;--line:#e1e6e3;--accent:#1f6b47}
@media (prefers-color-scheme:dark){:root{--bg:#141816;--card:#1c211f;--text:#e5ebe7;--muted:#9aa8a0;--line:#2c3430;--accent:#6cc497}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.7 system-ui,-apple-system,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}
a{color:var(--accent)}.qa-wrap{max-width:960px;margin:0 auto;padding:32px 16px 64px}.qa-site{font-size:14px;color:var(--muted);margin:0 0 8px}
h1{font-size:28px;line-height:1.4;margin:0 0 20px;overflow-wrap:anywhere}.qa-card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:20px;margin:16px 0;min-width:0}
.qa-card h2{font-size:19px;margin:6px 0;overflow-wrap:anywhere}.qa-meta{display:flex;flex-wrap:wrap;gap:12px;font-size:13px;color:var(--muted)}
.qa-tag{border:1px solid var(--line);border-radius:999px;padding:0 10px}.qa-tag.ok{border-color:var(--accent);color:var(--accent)}
.qa-body{overflow-wrap:anywhere}.qa-body img{max-width:100%;height:auto}.qa-files{font-size:14px;color:var(--muted)}.qa-files ul{margin:4px 0;padding-left:20px}
.qa-answer.accepted{border-color:var(--accent)}.qa-pager{display:flex;justify-content:space-between;gap:12px;margin-top:24px}.qa-empty{color:var(--muted)}
</style>
</head>
<body>
<main class="qa-wrap">
<p class="qa-site">{{ $siteName }}</p>
@yield('content')
</main>
</body>
</html>
