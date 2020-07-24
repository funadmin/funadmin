define(['jquery','table','form'], function ($,Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests: {
                    modify_url: 'member.memberGroup/modify',
                    index_url: 'member.memberGroup/index',
                    del_url: 'member.memberGroup/delete',
                    add_url: 'member.memberGroup/add',
                    edit_url: 'member.memberGroup/edit',

                },
            },
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tablId,
                url: Speed.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete'],
                cols: [[
                    {checkbox: true, fixed: true},
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
                    {field: 'create_time', title: __('CreateTime'), width: 180,},
                    {
                        width: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit', 'delete',]
                    }

                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true

            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add:function () {
            Controller.api.bindevent()
        },
        edit:function () {
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