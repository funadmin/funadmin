define(['jquery','table','form'], function (undefined,Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'sys.config/index',
                    delete_url: 'sys.config/delete',
                    add_url: 'sys.config/add',
                    edit_url: 'sys.config/edit',
                    modify_url: 'sys.config/modify',
                    setValue:{
                        type: 'open',
                        class: 'layui-btn-xs layui-btn-danger',
                        url: 'sys.config/setValue',
                        icon: 'layui-icon layui-icon-set',
                        text: __('Set'),
                        title: __('Set'),
                        full: 1,
                    },
                },
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: __('ID'), width: 80,  sort: true},
                    {field: 'code', title: __('Config Code'), width: 200,sort: true},
                    {field: 'value', title: __('Config Value'), sort: true},
                    {field: 'group', title: __('Config Group'), width: 120,sort: true},
                    {field: 'type', title: __('Type'), width: 80,sort: true},
                    {field: 'remark', title: __('Config Remark'), width: 220,sort:true},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 180,
                        search: 'select',
                        selectList: {0:__('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch},
                    {
                        width: 250,
                        align: 'center',
                        title:__('Oprate'),
                        init: Table.init,
                        templet : function(d){
                            if(d.is_system){
                                $html = '';
                                var elem = '#'+d.LAY_COL.init.tableId;
                                if(Fun.checkAuth(Fun.common.getNode(Table.init.requests.edit_url),elem)) {
                                    $html += '<button class="layui-btn layui-btn-xs layui-btn-xs" data-tableid="' + Table.init.tableId + '" data-width="800"  data-url="' + Table.init.requests.edit_url + '?id=' + d.id + '" title="' + __('Edit') + '" lay-event="open" data-type="open"><i class="layui-icon layui-icon-edit"></i></button>';
                                }
                                if(Fun.checkAuth(Fun.common.getNode(Table.init.requests.setValue.url),elem)){
                                    $html +='<button data-tableid="'+Table.init.tableId+'" class="layui-btn-xs layui-btn-danger layui-btn layui-btn-xs" title="'+__('SetValue')+'" data-url="'+Table.init.requests.setValue.url+'?id='+ d.id +'" lay-event="open" data-type="open" data-full="1"><i class="layui-icon layui-icon layui-icon-set"></i></button>';
                                }
                                return $html;
                            }else{
                                return Table.templet.operat.call(this,d);
                            }
                        },
                        operat: ['edit','setValue','delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });


            Table.api.bindEvent(Table.init.tableId);
        },
        add:function () {
            Controller.api.bindevent()
        },
        edit:function () {
            Controller.api.bindevent()
        },
        set:function(){
            Controller.api.bindevent()
        },
        setvalue:function(){
            Controller.api.bindevent()
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});
