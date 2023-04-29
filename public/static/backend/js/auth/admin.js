define(['jquery', 'table', 'form'], function ($, Table, Form) {
    var Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    modify_url: 'auth.admin/modify',
                    index_url: 'auth.admin/index',
                    delete_url: 'auth.admin/delete',
                    add_url: 'auth.admin/add',
                    edit_url: 'auth.admin/edit',
                    add_full:{
                        type: 'open',
                        class: 'layui-btn-sm',
                        url: 'auth.admin/add',
                        icon: 'layui-icon layui-icon-add-circle',
                        text: __('Add'),
                        title: __('Add'),
                        full: 0,
                        width: 800,
                    },
                    edit_full: {
                        type: 'open',
                        class: 'layui-btn-xs layui-btn-primary layui-border-green',
                        url: 'auth.admin/edit',
                        icon: 'layui-icon layui-icon-edit',
                        text: __('Edit'),
                        title: __('Edit'),
                        full: 1,
                    },
                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh', 'add_full', 'delete'],
                cols: [[
                    {checkbox: true, },
                    {field:'id', title: 'ID', width:60,}
                    ,{field:'username', title: __('username'), width:180}
                    ,{field:'authGroup.title', title: __("AuthGroup"),templet:Table.templet.resolution,search: false}
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
                    {field: 'create_time', title: __('CreateTime'),search: false, width: 180,templet:Table.templet.time},
                    {field: 'update_time', title: __('UpdateTime'),search: false, width: 180, templet: Table.templet.time},
                    {
                        width: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: function (d){
                            if(d.id==1){
                                return '';
                            }
                            return Table.templet.operat.call(this,d)
                        },
                        operat: ['password','edit_full', 'delete']
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
        upme: function () {

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
