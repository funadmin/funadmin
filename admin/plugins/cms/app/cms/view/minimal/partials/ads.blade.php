@if(!empty($home['has_ads']))
<section class="container module-section"><div class="section-heading"><h2>{{ $ui['ads'] ?? '' }}</h2></div><div class="offers-grid">
@foreach($cms->items('theme', ['name' => 'ads']) as $ad)<article class="offer-card">@if(!empty($ad['image']))<img src="{{ $ad['image'] ?? '' }}" alt="{{ $ad['name'] ?? '' }}" loading="lazy">@endif<div><h3>{{ $ad['name'] ?? '' }}</h3>@if(!empty($ad['url']))<a class="text-link" href="{{ $ad['url'] ?? '' }}">{{ $ui['discover'] ?? '' }} ↗</a>@endif</div></article>@endforeach
</div></section>
@endif
