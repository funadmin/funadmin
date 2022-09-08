define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url:'testCate/index',
                    add_url:'testCate/add',
                    edit_url:'testCate/edit',
                    destroy_url:'testCate/destroy',
                    delete_url:'testCate/delete',
                    recycle_url:'testCate/recycle',
                    import_url:'testCate/import',
                    export_url:'testCate/export',
                    modify_url:'testCate/modify',

                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete','export','recycle'],
                cols: [[
                    {checkbox: true,},
                     {field: 'id', title: __('ID'), sort:true,},
                    {field:'name', title: __('Name'),align: 'center'},
                    {field:'thumb',title: __('Thumb'),templet: Table.templet.image},
                    {field:'create_time',title: __('CreateTime'),align: 'center',timeType:'datetime',dateformat:'yyyy-MM-dd HH:mm:ss',searchdateformat:'yyyy-MM-dd HH:mm:ss',search:'time',templet: Table.templet.time,sort:true},
                    {
                        minWidth: 250,
                        align: "center",
                        title: __("Operat"),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ["edit", "destroy","delete"]
                    },
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: 15,
                page: true,
                done: function (res, curr, count) {
                }
            });
            Table.api.bindEvent(Tabe.init);
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        recycle: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    delete_url:'testCate/delete',
                    recycle_url:'testCate/recycle',
                    restore_url:'testCate/restore',
                    
                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true,},
                     {field: 'id', title: __('ID'), sort:true,},
                    {field:'name', title: __('Name'),align: 'center'},
                    {field:'thumb',title: __('Thumb'),templet: Table.templet.image},
                    {field:'create_time',title: __('CreateTime'),align: 'center',timeType:'datetime',dateformat:'yyyy-MM-dd HH:mm:ss',searchdateformat:'yyyy-MM-dd HH:mm:ss',search:'time',templet: Table.templet.time,sort:true},
                    {
                        minWidth: 250,
                        align: "center",
                        title: __("Operat"),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ["restore","delete"]
                    },
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: 15,
                page: true,
                done: function (res, curr, count) {
                }
            });
            Table.api.bindEvent(Tabe.init);
        },

        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    return Controller;
});
