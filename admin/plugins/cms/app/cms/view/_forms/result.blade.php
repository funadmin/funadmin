{{-- 无 JS 提交的结果页：成功提示，或错误列表 + 返回链接。所有文字均为转义输出。 --}}
<!doctype html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $ok ? $ui['success_title'] : $ui['error_title'] }}</title>
<style>body{margin:0;font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;color:#1f2933;background:#f5f7fa}main{max-width:560px;margin:12vh auto;padding:32px;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08)}h1{margin:0 0 12px;font-size:22px}ul{padding-left:20px}a{color:#2563eb}</style>
</head>
<body>
<main class="cms-form-result">
<h1>{{ $ok ? $ui['success_title'] : $ui['error_title'] }}</h1>
@if($ok)
<p>{{ $message }}</p>
@else
@if($problems)
<p>{{ $ui['error_summary'] }}</p>
<ul>
@foreach($problems as $problem)
<li>@if($problem['label'] !== '')<strong>{{ $problem['label'] }}</strong>：@endif{{ $problem['text'] }}</li>
@endforeach
</ul>
@else
<p>{{ $message }}</p>
@endif
@endif
<p><a href="{{ $back }}">{{ $ui['back'] }}</a></p>
</main>
</body>
</html>
