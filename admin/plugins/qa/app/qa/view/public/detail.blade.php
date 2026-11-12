{{-- 问题详情：正文经 HtmlSanitizer 清洗后原样输出；回答只含审核通过、未隐藏、未删除的。每条回答都带 id="answer-{id}" 锚点（JSON-LD 的 url 指向它）。
     已采纳回答排在前 100 条之后时（$pinnedAccepted）置顶显示在回答列表前。 --}}
@extends('public.layout')
@section('content')
<p><a href="{{ $listUrl }}">{{ $labels['back'] }}</a></p>
<article class="qa-card">
<h1>{{ $heading }}</h1>
<div class="qa-meta"><span>{{ $question['author_name'] }}</span><time>{{ $question['created_at'] }}</time><span class="qa-tag">{{ $state }}</span></div>
<div class="qa-body">{!! $question['body_safe'] !!}</div>
@include('public.files', ['files' => $question['attachments']])
</article>
<h2>{{ sprintf($labels['answers'], $question['answers_total']) }}</h2>
@if ($pinnedAccepted)
<article class="qa-card qa-answer accepted" id="{{ $pinnedAccepted['anchor'] }}">
<div class="qa-meta"><span>{{ $pinnedAccepted['author_name'] }}</span><time>{{ $pinnedAccepted['created_at'] }}</time><span class="qa-tag ok">{{ $labels['accepted'] }}</span></div>
<div class="qa-body">{!! $pinnedAccepted['body_safe'] !!}</div>
@include('public.files', ['files' => $pinnedAccepted['attachments']])
</article>
@endif
@forelse ($answers as $answer)
<article class="qa-card qa-answer{{ $answer['accepted'] ? ' accepted' : '' }}" id="{{ $answer['anchor'] }}">
<div class="qa-meta"><span>{{ $answer['author_name'] }}</span><time>{{ $answer['created_at'] }}</time>@if ($answer['accepted'])<span class="qa-tag ok">{{ $labels['accepted'] }}</span>@endif</div>
<div class="qa-body">{!! $answer['body_safe'] !!}</div>
@include('public.files', ['files' => $answer['attachments']])
</article>
@empty
<p class="qa-empty">{{ $labels['noAnswers'] }}</p>
@endforelse
@endsection
