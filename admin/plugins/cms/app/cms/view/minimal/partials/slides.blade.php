<section class="container home-slides cms-carousel-host" data-cms-slider aria-label="{{ $ui['slides'] ?? '' }}">
<el-carousel class="cms-el-carousel slides-track" data-slides-track tabindex="0" aria-label="{{ $ui['slides'] ?? '' }}" autoplay="false" loop="false" height="clamp(240px, 36vw, 520px)" arrow="{{ !empty($home['has_multiple_slides']) ? 'always' : 'never' }}" indicator-position="{{ !empty($home['has_multiple_slides']) ? '' : 'none' }}">
@foreach($cms->items('theme', ['name' => 'slides']) as $slide)<el-carousel-item>@include('partials.slide')</el-carousel-item>@endforeach
</el-carousel>
<div data-cms-fallback class="slider-controls" data-slider-controls hidden><button type="button" data-slide-direction="-1" aria-label="{{ $ui['previous_slide'] ?? '' }}">←</button><span data-slider-status aria-live="polite"></span><button type="button" data-slide-direction="1" aria-label="{{ $ui['next_slide'] ?? '' }}">→</button></div>
</section>
