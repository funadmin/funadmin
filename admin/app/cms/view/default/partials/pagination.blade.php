@if(!empty($listing['has_pagination']))
<nav class="pagination cms-pagination-host" aria-label="{{ $ui['pagination'] ?? '' }}">
<el-pagination data-cms-pagination data-cms-change="pagination" total="{{ $listing['total'] ?? '' }}" current-page="{{ $page['page'] ?? '' }}" page-size="12" layout="prev, slot, next" background="true" size="small" data-total="{{ $listing['total'] ?? '' }}" data-current="{{ $page['page'] ?? '' }}" data-page-size="12" data-base-path="{{ $page['base_path'] ?? '' }}" data-search="{{ $page['target_type'] ?? '' }}" data-query="{{ $page['query'] ?? '' }}">
@foreach($cms->items('theme', ['name' => 'pagination']) as $number)<a href="{{ $number['url'] ?? '' }}" @if(!empty($number['current']))aria-current="page"@endif>{{ $number['number'] ?? '' }}</a>@endforeach
</el-pagination>
</nav>
@endif
