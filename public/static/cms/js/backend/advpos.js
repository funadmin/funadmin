define(['table', 'form'], function (Table, Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests: {
            index_url: 'backend.AdvPos/index',
            add_url: 'backend.AdvPos/add',
            edit_url: 'backend.AdvPos/edit',
            delete_url: 'backend.AdvPos/delete',
            modify_url: 'backend.AdvPos/modify',
            recycle_url: 'backend.AdvPos/recycle',
            destroy_url: 'backend.AdvPos/destroy',
            restore_url: 'backend.AdvPos/restore',
        }
    }
    let Controller = {
        index: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh', 'add','destroy','recycle'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width: 80, fixed: true, sort: true},
                    {field: 'name', title: __('Name'), minwidth: 150, fixed: true, sort: true},
                    {field: 'intro', title: __('Intro'), width: 120, fixed: true,},
                    {field: 'width', title: __('width'), width: 120, fixed: true,},
                    {field: 'Style', title: __('Style'), width: 110, sort: true},
                    {field: 'status', title: __('status'),filter: 'status', minwidth: 120, templet: Table.templet.switch,sort: true},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet: Table.templet.operat, operat: ['edit', 'destroy']
                    }
                ]],
                done: function (res) {

                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            let table = $('#' + Table.init.table_elem);
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
                    {field: 'id', title: 'ID', width: 80, fixed: true, sort: true},
                    {field: 'name', title: __('Name'), minwidth: 150, fixed: true, sort: true},
                    {field: 'intro', title: __('Intro'), width: 120, fixed: true,},
                    {field: 'width', title: __('width'), width: 120, fixed: true,},
                    {field: 'Style', title: __('Style'), width: 110, sort: true},
                    {field: 'status', title: __('status'),filter: 'status', minwidth: 120, templet: Table.templet.switch,sort: true},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet: Table.templet.operat, operat: ['delete','restore']
                    }
                ]],
                done: function (res) {

                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            let table = $('#' + Table.init.table_elem);
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