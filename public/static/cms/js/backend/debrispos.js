define(['table','form'], function (Table,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests:{
            index_url: 'backend.DebrisPos/index',
            add_url: 'backend.DebrisPos/add',
            edit_url: 'backend.DebrisPos/edit',
            modify_url: 'backend.DebrisPos/modify',
            delete_url: 'backend.DebrisPos/delete',
            recycle_url: 'backend.DebrisPos/recycle',
            destroy_url: 'backend.DebrisPos/destroy',
            restore_url: 'backend.DebrisPos/restore',
        }
    }
    let Controller = {
        index: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','destroy','recycle'],
                cols: [[
                    {checkbox: true, fixed: true},

                    {field: 'title', title: __('Title'), minwidth: 150, fixed: true, sort: true},
                    {field: 'status', title: __('Status'),filter: 'status', minwidth: 150, sort: true,templet: Table.templet.switch},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['edit','destroy']
                    }
                ]],
                done: function(res){

                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        recycle: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true, fixed: true},

                    {field: 'title', title: __('Title'), minwidth: 150, fixed: true, sort: true},
                    {field: 'status', title: __('Status'),filter: 'status', minwidth: 150, sort: true,templet: Table.templet.switch},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['delete','restore']
                    }
                ]],
                done: function(res){

                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});