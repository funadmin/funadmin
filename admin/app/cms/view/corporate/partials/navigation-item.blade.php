@if(!empty($nav['has_children']))
<el-sub-menu index="{{ $nav['id'] ?? '' }}" popper-class="cms-el-menu-popup" show-timeout="80" hide-timeout="120">
  <span data-cms-slot="title">{{ $nav['title'] ?? '' }}</span>
  <el-menu-item index="overview-{{ $nav['id'] ?? '' }}"><a class="cms-menu-link" href="{{ $nav['url'] ?? '' }}" @if(!empty($nav['open_in_new_tab']))target="_blank" rel="noopener noreferrer"@endif>{{ $nav['overview_title'] ?? '' }}</a></el-menu-item>
  {{ $nav['children'] ?? '' }}
</el-sub-menu>
@else
<el-menu-item index="{{ $nav['id'] ?? '' }}"><a class="cms-menu-link" href="{{ $nav['url'] ?? '' }}" @if(!empty($nav['open_in_new_tab']))target="_blank" rel="noopener noreferrer"@endif>{{ $nav['title'] ?? '' }}</a></el-menu-item>
@endif
