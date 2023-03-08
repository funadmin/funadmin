define(['jquery','table','form'], function (undefined,Table,Form) {

    let Controller = {

        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'sys.configGroup/index',
                    add_url: 'sys.configGroup/add',
                    edit_url: 'sys.configGroup/edit',
                    delete_url: 'sys.configGroup/delete',
                    modify_url: 'sys.configGroup/modify',
                },
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh', 'add', 'delete'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: __('ID'), width: 80, sort: true},
                    {field: 'title', title: __('Group Title'), minwidth: 250, sort: true},
                    {field: 'name', title: __('Group Name'), minwidth: 120, sort: true},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 180,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {
                        width: 250,
                        align: 'center',
                        title: __('Oprate'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit', 'delete',]
                    }

                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });


            Table.api.bindEvent(Table.init.tableId);
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
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