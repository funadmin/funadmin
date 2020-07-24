define(['jquery', 'table', 'form'], function ($, Table, Form) {

    var Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests: {
                    modify_url: 'auth.admin/modify',
                    index_url: 'auth.admin/index',
                    del_url: 'auth.admin/delete',
                    add_url: 'auth.admin/add',
                    edit_url: 'auth.admin/edit',
                    add_full:{
                        type: 'open',
                        class: 'layui-btn-sm layui-btn-green',
                        url: 'auth.admin/add',
                        text: __('Add'),
                        title: __('Add'),
                        full: 1,
                    },
                    edit_full: {
                        type: 'open',
                        class: 'layui-btn-xs layui-btn-green',
                        url: 'auth.admin/edit',
                        text: __('Edit'),
                        title: __('Edit'),
                        full: 1,
                    },

                },
            },
            Table.render({
                    elem: '#' + Table.init.table_elem,
                    id: Table.init.tablId,
                    url: Speed.url(Table.init.requests.index_url),
                    init: Table.init,
                    toolbar: ['refresh', 'add_full', 'delete'],
                    cols: [[
                        {checkbox: true, fixed: true},
                        {field:'id', title: 'ID', width:60,fixed: true}
                        ,{field:'username', title: __('username'), width:180}
                        ,{field:'authGroup.title', title: __("AuthGroup"),templet:Table.templet.resolve}
                        ,{field:'email', title: __("email"), width:200}
                        ,{field:'mobile', title: __("mobile"), width:150}
                        ,{field:'ip', title: __("Ip"),width:150,hide:true},
                        {
                            field: 'status',
                            title: __('Status'),
                            width: 120,
                            search: 'select',
                            selectList: {0: __('Disabled'), 1: __('Enabled')},
                            filter: 'status',
                            templet: Table.templet.switch
                        },
                        {field: 'create_time', title: __('CreateTime'), width: 180,templet:Table.templet.time},
                        {field: 'update_time', title: __('UpdateTime'), width: 180, templet: Table.templet.time},
                        {
                            width: 250,
                            align: 'center',
                            title: __('Operat'),
                            init: Table.init,
                            templet: Table.templet.operat,
                            operat: ['edit_full', 'delete']
                        }

                    ]],
                    limits: [10, 15, 20, 25, 50, 100],
                    limit: 15,
                    page: true
                });
            var table = $('#' + Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add: function () {

            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()

        },
        password:function(){
            Controller.api.bindevent()
        },
        rule:function(){

        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});