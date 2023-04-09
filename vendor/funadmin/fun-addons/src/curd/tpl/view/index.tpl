<table class="layui-table" id="list" lay-filter="list"
       data-node-add="{:auth({{$nodeType}}('add'))}"
       data-node-edit="{:auth({{$nodeType}}('edit'))}"
       data-node-delete="{:auth({{$nodeType}}('delete'))}"
       data-node-destroy="{:auth({{$nodeType}}('destroy'))}"
       data-node-modify="{:auth({{$nodeType}}('modify'))}"
       data-node-recycle="{:auth({{$nodeType}}('recycle'))}"
       data-node-restore="{:auth({{$nodeType}}('restore'))}"
       data-node-import="{:auth({{$nodeType}}('import'))}"
       data-node-export="{:auth({{$nodeType}}('export'))}"
></table>
{{$script}}
