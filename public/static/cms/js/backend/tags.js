define(['table','form'], function (Table,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests:{
            index_url: 'backend.Tags/index',
            add_url: 'backend.Tags/add',
            edit_url: 'backend.Tags/edit',
            delete_url: 'backend.Tags/delete',
            modify_url: 'backend.Tags/modify',
            recycle_url: 'backend.Tags/recycle',
            destroy_url: 'backend.Tags/destroy',
            restore_url: 'backend.Tags/restore',
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
                    {checkbox: true},
                    {field: 'name', title: __('Name'), minwidth: 150, sort: true},
                    {field: 'clicks', title: __('clicks'), minwidth: 150, sort: true},
                    {field: 'nums', title: __('Nums'), minwidth: 150, sort: true},
                    {field: 'forum_ids', title: __('forum_ids'), minwidth: 150, sort: true},
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
                    {checkbox: true},
                    {field: 'name', title: __('Name'), minwidth: 150, sort: true},
                    {field: 'clicks', title: __('clicks'), minwidth: 150, sort: true},
                    {field: 'nums', title: __('Nums'), minwidth: 150, sort: true},
                    {field: 'forum_ids', title: __('forum_ids'), minwidth: 150, sort: true},
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