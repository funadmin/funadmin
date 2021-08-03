define(['jquery','table','form'], function ($,Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    modify_url: 'member.memberGroup/modify',
                    index_url: 'member.memberGroup/index',
                    delete_url: 'member.memberGroup/delete',
                    destroy_url: 'member.memberGroup/destroy',
                    add_url: 'member.memberGroup/add',
                    edit_url: 'member.memberGroup/edit',
                    recycle_url: 'member.member/recycle',
                    export_url: 'member.member/export',
                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add_full','destroy','export','recycle'],
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
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add:function () {
            Controller.api.bindevent()
        },
        edit:function () {
            Controller.api.bindevent()
        },
        recycle: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    recycle_url: 'member.member/recycle',
                    restore_url: 'member.member/restore',
                    delete_url: 'member.member/delete',
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
                    {field: 'id', title: 'ID', width: 80, sort: true},
                    {field: 'username', title: __('memberName'), width: 120},
                    {field: 'email', title: __('Email'), width: 120,},
                    {field: 'mobile', title: __('mobile'), width: 120,edit: 'text'},
                    {
                        field: 'sex',
                        title: __('Sex'),
                        filter: 'sex',
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Female'), 1: __('Male'), 2: __('Secret')},
                        templet: Table.templet.select,
                        tips: __('Female')+'|'+  __('Male')
                    },
                    {
                        field: 'memberLevel.name',
                        title: __('MemberLevel'),
                        width: 120,
                        templet: Table.templet.text
                    },
                    {field: 'avatar', title: __('Avatar'), width: 120, templet: Table.templet.image},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('Registertime'), width: 180,search:'range'},
                    {field: 'last_login', title: __('Lastlogintime'), width: 180,search:'timerange', templet: Table.templet.time},
                    {
                        minwidth: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['restore', 'delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
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