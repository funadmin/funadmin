define(['jquery','table','form'], function ($,Table,Form) {
    let Controller = {
        /**
         * 会员等级
         *
         */
        index:function () {
            // 初始化表格参数配置
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    modify_url:'member.memberLevel/modify',
                    index_url: 'member.memberLevel/index',
                    add_url: 'member.memberLevel/add',
                    edit_url: 'member.memberLevel/edit',
                    destroy_url: 'member.memberLevel/destroy',
                    delete_url: 'member.memberLevel/delete',
                    recycle_url: 'member.member/recycle',
                    export_url: 'member.member/export',
                }
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add_full','destroy','export','recycle'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: __('Id'), width: 80, sort: true},
                    {field: 'name', title: __('Levelname'), width: 120, sort: true},
                    {field: 'amount', title: __('Levelmoney'), width: 150, sort: true},
                    {field: 'discount', title: __('Leveldiscount'), width: 180, sort: true},
                    {field: 'description', title: __('Leveldesc'), width: 150, sort: true},
                    {
                        field: 'status', title: __('Status'), width: 180, search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('Createtime'), width: 180,search: false},
                    {field: 'update_time', title: __('Updatetime'), width: 180,search: false},
                    {
                        minwidth: 250,
                        align: 'center',
                        title: '操作',
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit', 'destroy',]
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });
            let table = $("#"+ Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add:function () {
            Controller.api.bindevent();
        },
        edit:function () {
            Controller.api.bindevent();
        },
        recycle:function () {
            // 初始化表格参数配置
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
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: __('Id'), width: 80, sort: true},
                    {field: 'name', title: __('Levelname'), width: 120, sort: true},
                    {field: 'amount', title: __('Levelmoney'), width: 150, sort: true},
                    {field: 'discount', title: __('Leveldiscount'), width: 180, sort: true},
                    {field: 'description', title: __('Leveldesc'), width: 150, sort: true},
                    {
                        field: 'status', title: __('Status'), width: 180, search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('Createtime'), width: 180,search: false},
                    {field: 'update_time', title: __('Updatetime'), width: 180,search: false},
                    {
                        minwidth: 250,
                        align: 'center',
                        title: '操作',
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['restore', 'delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });
            let table = $("#"+ Table.init.table_elem);
            Table.api.bindEvent(table);
        },

        api: {
            bindevent: function () {
                Form.api.bindEvent()
            }
        }
    };
    return Controller;
});