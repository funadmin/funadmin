define(['jquery','table','form'], function ($,Table,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests: {
            modify_url: 'member.memberGroup/modify',
            index_url: 'member.memberGroup/index',
            add_url: 'member.memberGroup/add',
            delete_url: 'member.memberGroup/delete',
            destroy_url: 'member.memberGroup/destroy',
            edit_url: 'member.memberGroup/edit',
            recycle_url: 'member.memberGroup/recycle',
            export_url: 'member.memberGroup/export',
        },
    };
    let Controller = {
        index: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','destroy','export','recycle'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: 'ID', width: 80, sort: true},
                    {field: 'name', title: __('GroupName'), minwidth: 120,},
                    {field: 'rules', title: __('Rules'), minwidth: 120,},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('CreateTime'),search: 'range', width: 180,},
                    {
                        minwidth: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit', 'destroy',]
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
        recycle: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: 'ID', width: 80, sort: true},
                    {field: 'name', title: __('GroupName'), minwidth: 120,},
                    {field: 'rules', title: __('Rules'), minwidth: 120,},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('CreateTime'),search: 'range', width: 180,},
                    {
                        minwidth: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit', 'destroy',]
                    }

                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });
            Table.api.bindEvent(Table.init.tableId);
        },

        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});