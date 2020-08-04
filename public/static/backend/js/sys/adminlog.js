define(['jquery','table'], function (undefined,Table) {
    var Table = Table;
    var Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests:{
                    index_url: 'sys.adminlog/index',
                    del_url: 'sys.adminlog/delete',
                    delall_url:{
                            type: 'request',
                            class: 'layui-btn-sm layui-btn-danger',
                            icon:'layui-icon layui-icon-delete',
                            url: 'sys.adminlog/delete?id=all',
                            text: __('DeleteAll'),
                            title: __('DeleteAll'),
                            // full: 1,
                    },
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tablId,
                url: Speed.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','delete','delall_url'],
                cols: [[
                    {type: "checkbox"},
                    {field: 'id', title: 'ID', sort: true,width: 80,search: false},
                    {field: 'admin_id', title:__('Admin ID'), width: 80, sort: true},
                    {field: 'username', title:__('Admin Username'), width: 150, sort: true},
                    {field: 'method', title:__('Method'), width: 150, sort: true},
                    {field: 'url', title:__('Log Addr'), sort: true,},
                    {field: 'content', title:__('Log Content'), width: 150, sort: true,},
                    {field: 'title', title: __('Log Title'), width: 150, sort: true,},
                    {field: 'agent', title: __('Log Agent'), width: 120, sort: true,},
                    {field: 'ip', title: 'Ip', width: 80},
                    {field: 'create_time', title: __('CreateTime'), width: 180,search: 'range'},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init, templet : Table.templet.operat, operat: ['delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });

            var table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
    };
    return Controller;
});