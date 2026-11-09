<nav class="breadcrumb" aria-label="{{ $ui['home'] ?? '' }}"><el-breadcrumb separator="/">
<el-breadcrumb-item><a href="{{ $home['url'] ?? '' }}">{{ $ui['home'] ?? '' }}</a></el-breadcrumb-item>
@foreach($cms->items('breadcrumb') as $crumb)<el-breadcrumb-item><a href="{{ $crumb['url'] ?? '' }}">{{ $crumb['name'] ?? '' }}</a></el-breadcrumb-item>@endforeach
<el-breadcrumb-item>{{ $page['title'] ?? '' }}</el-breadcrumb-item>
</el-breadcrumb></nav>
