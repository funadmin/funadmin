define(['table','form'], function (Table, Form) {
    var form = layui.form;
    var Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index: 'auth.auth/index',
                    add: 'auth.auth/add',
                    edit: 'auth.auth/edit',
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
                    modify: 'auth.auth/modify',
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
                url: Fun.url(Table.init.requests.index),
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
                    {field: 'title', title: __('Auth Name'), minWidth: 120,align: 'left'},
                    {field: 'href', title: __('Permission Code'), align: 'left', minWidth: 220},
                    {
                        field: 'is_public',
                        title: __('Public'),
                        width: 100,
                        selectList: {0: __('No'), 1: __('Yes')},
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
            var url = Fun.url(Table.init.requests.modify);
            Table.api.bindEvent(Table.init.tableId);

            form.on('switch(is_public)', function (obj) {
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
                        parent.layui.layer.closeAll()
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
