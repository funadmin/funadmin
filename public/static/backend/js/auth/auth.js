define(['jquery','table','form'], function ($,Table, Form) {
    var form = layui.form;
    var Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'auth.auth/index',
                    add_url: 'auth.auth/add',
                    edit_url: 'auth.auth/edit',
                    delete: {
                        type: 'request',
                        class: 'layui-btn-xs layui-btn-warm',
                        icon: 'layui-icon-add-circle-fine',
                        url: 'auth.auth/delete',
                        text: __('Are you sure you want to delete menu and children menu!!!'),
                        title:  __('Delete'),
                        full:0,
                        width:'',
                        height:'',
                    },
                    modify_url: 'auth.auth/modify',
                    child:  {
                        type: 'open',
                        class: 'layui-btn-xs layui-btn-warm',
                        icon: 'layui-icon-add-circle-fine',
                        url: 'auth.auth/child',
                        text: __('Add Child'),
                        title:  __('Add Child'),
                        full:0,
                        width:'',
                        height:'',
                    },
                    expand:{
                        type: 'expand',
                        class: 'layui-btn-xs layui-btn-normal',
                        url: '',
                        text: __('展开'),
                        title:  __('展开'),
                        width:'',
                        height:'',
                        node:false,
                        callback:function (data){
                            //暂时不支持
                            layui.treeTable.expandAll( Table.init.tableId, true); // 关闭全部节点
                        }
                    },
                    close:{
                        type: 'close',
                        class: 'layui-btn-xs layui-btn-warm',
                        url: '',
                        text: __('关闭'),
                        title:  __('关闭'),
                        width:'',
                        height:'',
                        node:false,
                        callback:function (data){
                            layui.treeTable.expandAll( Table.init.tableId, false); // 关闭全部节点
                        }
                    }
                },
            };
            Table.render({
                id: Table.init.tableId,
                elem: '#' + Table.init.table_elem,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar:['refresh','add','expand','close'],
                // maxHeight: '501px',
                tree: {
                    customName: {
                        'name':'title',
                    },
                    // data: {isSimpleData:false},
                    }
                ,cols: [[
                    {checkbox: true, },
                    {field: 'id', title: __('ID'), width: 80,  sort: true},
                    {field: 'icons',title: __("icon"), width: 60,templet: Table.templet.icon},
                    {field: 'title', title: __('Auth Name'), minwidth: 120,align: 'left'},
                    {field: 'href', title: __('Module/Controller/Action'),align: 'left', minwidth: 200,templet: function (d){
                            return d.module +'@'+ d.href;
                        }},
                    {
                        field: 'auth_verify',
                        title: __('Auth Verify'),
                        width: 100,
                        tips:__('YES')+'|'+__('NO'),
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        templet: Table.templet.switch,
                    },
                    {
                        field: 'type',
                        title: __('IsMenu'),
                        width: 100,
                        search: 'select',
                        selectList: {0: __('No'), 1: __('Yes')},
                        filter: 'status',
                        templet: Table.templet.switch,
                    },
                    {
                        field: 'menu_status',
                        title: __('MenuStatus'),
                        width: 100,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch,
                    },
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 100,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch,
                    },
                    {field: 'sort',align: 'center', title: __("sort"), width: 60, edit: 'text'},
                    {
                        width: 300,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['child','edit', 'delete',]
                    },
                ]]
                ,page:false
            });
            var url = Fun.url(Table.init.requests.modify_url);
            Table.api.bindEvent(Table.init.tableId);

            form.on('switch(auth_verify)', function (obj) {
                Fun.refreshmenu();
                return false;
            });
            form.on('switch(status)', function (obj) {
                Fun.refreshmenu();
                return false;
            });
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        child:function(){
            Controller.api.bindevent()

        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'), function (res) {
                    Fun.toastr.success(res.msg, setTimeout(function () {
                        Fun.refreshmenu();
                        Fun.toastr.close();
                    }, 0));
                    }, function (res) {
                        Fun.toastr.error(res.msg);
                    }
                );
            }
        }

    };
    return Controller;
});
