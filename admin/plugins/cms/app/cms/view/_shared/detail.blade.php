@include('partials.header')
<article class="container narrow-section" data-template="detail">@include('partials.breadcrumb')<span class="eyebrow">{{ $site['name'] ?? '' }}</span><h1>{{ $content['title'] ?? '' }}</h1><p class="summary">{{ $content['summary'] ?? '' }}</p><div class="article-body" data-cms-static>{{ $content['body'] ?? '' }}</div>@include('partials.fields')</article>
@include('partials.footer')
