define(['jquery','table','form'], function (undefined,Table,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests: {
            index_url: 'sys.blacklist/index',
            delete_url: 'sys.blacklist/delete',
            add_url: 'sys.blacklist/add',
            edit_url: 'sys.blacklist/edit',
            recycle_url: 'sys.blacklist/recycle',
            destroy_url: 'sys.blacklist/destroy',
            restore_url: 'sys.blacklist/restore',
            modify_url: 'sys.blacklist/modify',
        },
    };
    let Controller = {
        index: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete','recycle'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: __('ID'), width: 80,  sort: true},
                    {field: 'ip', title: __('Ip'), width: 120,sort: true},
                    {field: 'remark', title: __('Remark'), width: 250,sort: true},
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
                        templet : Table.templet.operat,
                        operat: ['edit','destroy','delete']
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
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','restore','destroy'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: __('ID'), width: 80,  sort: true},
                    {field: 'ip', title: __('Ip'), width: 120,sort: true},
                    {field: 'remark', title: __('Remark'), width: 250,sort: true},
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
                        templet : Table.templet.operat,
                        operat: ['edit','restore','destroy']
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