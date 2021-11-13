define(['jquery','table','form'], function ($,Table,Form) {

    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests: {
            modify_url: 'member.member/modify',
            index_url: 'member.member/index',
            recycle_url: 'member.member/recycle',
            delete_url: 'member.member/delete',
            destroy_url: 'member.member/destroy',
            export_url: 'member.member/export',
            restore_url: 'member.member/restore',
            // add_url: 'member.member/add',
            // edit_url: 'member.member/edit',
            add_full:{
                type: 'open',
                class: 'layui-btn-sm layui-btn-green',
                url: 'member.member/add',
                icon: 'layui-icon layui-icon-add',
                text: __('Add'),
                title: __('Add'),
                node:false,//不使用节点权限
                // full: 1,
                width:'800',
                height:'600',
            },
            edit_full:{
                type: 'open',
                class: 'layui-btn-xs layui-btn-green',
                url: 'member.member/edit',
                icon: 'layui-icon layui-icon-edit',
                text: __('Edit'),
                title: __('Edit'),
                // full: 1,
                width:'800',
                height:'600',
            },
        },
    };
    let Controller = {
        index: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add_full','destroy','export','recycle'],
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
                        selectList: {0: __('Secret'), 1: __('Male'), 2: __('Female')},
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
                        operat: ['edit_full', 'destroy']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: 15,
                page: true
                ,done: function (res, curr, count) {
                    this.limits.push(count) ;
                }
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
                        selectList: {0: __('Secret'), 1: __('Male'), 2: __('Female')},
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
                limits: [10, 15, 20, 25, 50, 100,500,1000,5000],
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
