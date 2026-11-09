@include('partials.header')
<section class="container page-intro" data-template="category">@include('partials.breadcrumb')<span class="eyebrow">EXPLORE OUR WORLD</span><h1>{{ $category['name'] ?? '' }}</h1><p>@if(!empty($category['description'])){{ $category['description'] ?? '' }}@else{{ $ui['category_intro'] ?? '' }}@endif</p></section>
@include('partials.listing')@include('partials.footer')
