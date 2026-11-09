@if(!empty($home['has_contacts']))
<section class="container module-section"><div class="section-heading"><h2>{{ $ui['contacts'] ?? '' }}</h2></div><div class="contacts-grid">
@foreach($cms->items('theme', ['name' => 'contacts']) as $contact)<article class="contact-card"><div><h3>{{ $contact['name'] ?? '' }}</h3>@if(!empty($contact['phone']))<p>{{ $contact['phone'] ?? '' }}</p>@endif
@if(!empty($contact['email']))<a href="mailto:{{ $contact['email'] ?? '' }}">{{ $contact['email'] ?? '' }}</a>@endif</div>@if(!empty($contact['qrcode']))<img src="{{ $contact['qrcode'] ?? '' }}" alt="{{ $contact['name'] ?? '' }}" loading="lazy" width="100" height="100">@endif</article>@endforeach
</div></section>
@endif
