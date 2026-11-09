<article class="home-slide">
      <picture>@if(!empty($slide['mobile_image']))<source media="(max-width: 640px)" srcset="{{ $slide['mobile_image'] ?? '' }}">@endif<img src="{{ $slide['image'] ?? '' }}" alt="{{ $slide['title'] ?? '' }}" width="1440" height="640"></picture>
      <div class="slide-caption">@if(!empty($slide['title']))<h1>{{ $slide['title'] ?? '' }}</h1>@endif
@if(!empty($slide['url']))<a class="cms-button-link" href="{{ $slide['url'] ?? '' }}"><el-button tag="span" tabindex="-1" class="button" type="primary">{{ $ui['discover'] ?? '' }} <span aria-hidden="true">↗</span></el-button></a>@endif</div>
    </article>
