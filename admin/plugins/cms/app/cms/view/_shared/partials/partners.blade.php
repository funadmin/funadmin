@if(!empty($home['has_partners']))
<section class="container module-section"><div class="section-heading"><h2>{{ $ui['partners'] ?? '' }}</h2></div><div class="partners-grid">
@foreach($cms->items('theme', ['name' => 'partners']) as $partner)
<article class="partner-card">@if(!empty($partner['url']))<a href="{{ $partner['url'] ?? '' }}">@if(!empty($partner['logo']))<img src="{{ $partner['logo'] ?? '' }}" alt="{{ $partner['name'] ?? '' }}" loading="lazy">@endif<span>{{ $partner['name'] ?? '' }}</span></a>@else
@if(!empty($partner['logo']))<img src="{{ $partner['logo'] ?? '' }}" alt="{{ $partner['name'] ?? '' }}" loading="lazy">@endif<span>{{ $partner['name'] ?? '' }}</span>@endif</article>
@endforeach
</div></section>
@endif
