@include('partials.header')
@if(!empty($home['is_home']))
<div data-template="editorial-home">
@if(!empty($home['has_slides']))
@include('partials.slides')
@else
<section class="editorial-hero container">
  <div class="editorial-masthead"><span>THE JOURNAL / {{ $ui['year'] }}</span><a href="#insights">{{ $ui['insights'] }} <span aria-hidden="true">↗</span></a></div>
  <div class="editorial-lead"><div class="editorial-lead-copy"><span class="hero-kicker">PERSPECTIVES THAT MATTER</span><h1>{{ $ui['hero_line_one'] }}<span>{{ $ui['hero_line_two'] }}</span></h1><p>{{ $home['intro'] }}</p><a class="cms-button-link" href="#insights"><el-button tag="span" tabindex="-1" class="button" type="primary">{{ $ui['read_insights'] }} <span aria-hidden="true">→</span></el-button></a></div>
  <figure class="editorial-art"><img src="{{ $theme['hero'] }}" alt="" width="900" height="760" fetchpriority="high"><figcaption><span>OBSERVE. THINK. SHARE.</span><span>01 — {{ $ui['year'] }}</span></figcaption></figure></div>
  <nav class="editorial-index" aria-label="{{ $ui['menu'] }}"><a href="#insights"><span>01</span>{{ $ui['insights'] }}<i aria-hidden="true">↗</i></a><a href="#products"><span>02</span>{{ $ui['products'] }}<i aria-hidden="true">↗</i></a><a href="#cases"><span>03</span>{{ $ui['cases'] }}<i aria-hidden="true">↗</i></a></nav>
</section>
@endif
<section id="insights" class="enterprise-section editorial-stories"><div class="container">
  <div class="enterprise-section-heading"><div><span class="eyebrow">01 / NEWS & PERSPECTIVES</span><h2>{{ $ui['insights'] ?? '' }}</h2></div><p>{{ $ui['insights_intro'] ?? '' }}</p></div>
  @if(!empty($home['has_articles']))
    <div class="content-grid enterprise-grid articles-grid cms-el-grid-host"><el-row gutter="24" class="cms-el-grid">
      @foreach($cms->items('theme', ['name' => 'articles']) as $item)
        <el-col xs="24" sm="12" md="12" lg="8">@include('partials.card')</el-col>
      @endforeach
    </el-row></div>
  @else
    @include('partials.empty')
  @endif
</div></section>
<section id="products" class="enterprise-section editorial-products"><div class="container">
  <div class="enterprise-section-heading"><div><span class="eyebrow">02 / PRODUCTS & SOLUTIONS</span><h2>{{ $ui['products'] ?? '' }}</h2></div><p>{{ $ui['products_intro'] ?? '' }}</p></div>
  @if(!empty($home['has_products']))
    <div class="content-grid enterprise-grid products-grid cms-el-grid-host"><el-row gutter="24" class="cms-el-grid">
      @foreach($cms->items('theme', ['name' => 'products']) as $item)
        <el-col xs="24" sm="12" md="12" lg="8">@include('partials.card')</el-col>
      @endforeach
    </el-row></div>
  @else
    @include('partials.empty')
  @endif
</div></section>
<section id="cases" class="enterprise-section editorial-cases"><div class="container">
  <div class="enterprise-section-heading"><div><span class="eyebrow">03 / SELECTED WORK</span><h2>{{ $ui['cases'] ?? '' }}</h2></div><p>{{ $ui['cases_intro'] ?? '' }}</p></div>
  @if(!empty($home['has_cases']))
    <div class="content-grid enterprise-grid cases-grid cms-el-grid-host"><el-row gutter="24" class="cms-el-grid">
      @foreach($cms->items('theme', ['name' => 'cases']) as $item)
        <el-col xs="24" sm="12" md="12" lg="12">@include('partials.card')</el-col>
      @endforeach
    </el-row></div>
  @else
    @include('partials.empty')
  @endif
</div></section>
<section id="about" class="enterprise-section enterprise-about "><div class="container enterprise-overview">
  <div><span class="eyebrow">ABOUT {{ $site['name'] ?? '' }}</span><h2 class="multiline">{{ $ui['about_title'] ?? '' }}</h2></div>
  <div><p class="overview-intro">{{ $ui['about_intro'] ?? '' }}</p><div class="enterprise-principles">
    <article><span>01</span><h3>{{ $ui['principle_one'] ?? '' }}</h3><p>{{ $ui['principle_one_body'] ?? '' }}</p></article>
    <article><span>02</span><h3>{{ $ui['principle_two'] ?? '' }}</h3><p>{{ $ui['principle_two_body'] ?? '' }}</p></article>
    <article><span>03</span><h3>{{ $ui['principle_three'] ?? '' }}</h3><p>{{ $ui['principle_three_body'] ?? '' }}</p></article>
  </div></div>
</div></section>
@include('partials.partners')
@include('partials.ads')
@include('partials.contacts')
<section class="enterprise-invitation"><div class="container"><div><span class="eyebrow">A NEW CHAPTER STARTS HERE</span><h2 class="multiline">{{ $ui['newsletter_title'] ?? '' }}</h2><p>{{ $ui['newsletter_intro'] ?? '' }}</p></div><a class="cms-button-link" href="{{ $page['search_url'] ?? '' }}"><el-button tag="span" tabindex="-1" class="button" type="primary">{{ $ui['discover'] ?? '' }} <span aria-hidden="true">↗</span></el-button></a></div></section>
</div>
@else
<section class="container page-intro"><span class="eyebrow">THE COLLECTION</span><h1>{{ $ui['all_content'] }}</h1><p>{{ $ui['category_intro'] }}</p></section>
@include('partials.listing')
@endif
@include('partials.footer')
