{{-- 附件只列出名称与大小；文件私有存储，登录会员通过问答接口下载。 --}}
@if (!empty($files))
<div class="qa-files">{{ $labels['attachments'] }}<ul>
@foreach ($files as $file)
<li>{{ $file['name'] }} ({{ number_format($file['size'] / 1024, 1) }} KB)</li>
@endforeach
</ul>{{ $labels['attachmentHint'] }}</div>
@endif
