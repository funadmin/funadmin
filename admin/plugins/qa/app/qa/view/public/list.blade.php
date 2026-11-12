{{-- 问题列表：审核通过、未删除的问题，置顶在前，每页 20 条。 --}}
@extends('public.layout')
@section('content')
<h1>{{ $heading }}</h1>
@forelse ($items as $item)
<article class="qa-card">
<h2><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h2>
@if ($item['excerpt'] !== '')
<p class="qa-body">{{ $item['excerpt'] }}</p>
@endif
<div class="qa-meta"><span>{{ $item['author'] }}</span><time>{{ $item['created_at'] }}</time><span class="qa-tag">{{ $item['state'] }}</span></div>
</article>
@empty
<p class="qa-empty">{{ $labels['empty'] }}</p>
@endforelse
@if ($prev || $next)
<nav class="qa-pager" aria-label="{{ $labels['pagination'] }}">
<span>@if ($prev)<a href="{{ $prev }}" rel="prev">{{ $labels['previous'] }}</a>@endif</span>
<span>{{ sprintf($labels['page'], $page) }} / {{ $last }}</span>
<span>@if ($next)<a href="{{ $next }}" rel="next">{{ $labels['next'] }}</a>@endif</span>
</nav>
@endif
@endsection
