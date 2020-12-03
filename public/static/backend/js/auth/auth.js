define(['jquery','treeGrid','table','form'], function ($,treeGrid,Table, Form) {
    var treeGrid = layui.treeGrid,
        form = layui.form;
    var Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'auth.auth/index',
                    add_url: 'auth.auth/add',
                    edit_url: 'auth.auth/edit',
                    delete_url: 'auth.auth/delete',
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
                },
            };
            treeGrid.render({
                id: Table.init.tableId,
                elem: '#' + Table.init.table_elem,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                idField:'id'
                ,cellMinWidth: 100
                ,treeId:'id'//树形id字段名称
                ,treeUpId:'pid'//树形父id字段名称
                ,treeShowName:'title'//以树形式显示的字段
                ,heightRemove:[".dHead",10]//不计算的高度,表格设定的是固定高度，此项不生效
                // ,height:'full-140'
                ,height:'100%'
                ,isFilter:false
                ,iconOpen:true//是否显示图标【默认显示】
                ,isOpenDefault:true//节点默认是展开还是折叠【默认展开】
                ,loading:true
                ,method:'get'
                ,isPage:false
                ,cols: [[
                    // {checkbox: true, },
                    // {field: 'id', title: __('ID'), width: 80, , sort: true},
                    {field: 'icon',title: __("icon"), width: 60,templet: Table.templet.icon},
                    {field: 'title', title: __('Auth Name'), minwidth: 120,},
                    {field: 'href', title: __('Controller/Action'), minwidth: 200},
                    {

                        field: 'auth_verify',
                        align: 'center',
                        title: __('Auth Verify'),
                        width: 150,
                        tips:__('YES')+'|'+__('NO'),
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        templet: Table.templet.switch,
                    },
                    {
                        field: 'type',
                        title: __('IsMenu'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('No'), 1: __('Yes')},
                        filter: 'status',
                        templet: Table.templet.switch,
                    },
                    {
                        field: 'menu_status',
                        title: __('MenuStatus'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch,
                    },
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch,
                    },
                    {field: 'sort',align: 'center', title: __("order"), width: 80, edit: 'text'},
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
            form.on('switch(auth_verify)', function(obj){
                var field = obj.elem.name;
                value = obj.elem.checked?1:0;
                data = {id:obj.value,value:value,field:field};
                Fun.ajax({url:url,data:data})
            });
            form.on('switch(status)', function(obj){
                var field = obj.elem.name;
                value = obj.elem.checked?1:0;
                data = {id:obj.value,value:value,field:field};
                Fun.ajax({url:url,data:data})
            });
            $(document).on('click','[lay-event]',function (e) {
                var event = $(this).attr('lay-event');
                if(event=='openAll'){
                    openAll();
                }else if(event=='add'){
                    options = {
                        title:__('add'),
                        url:Fun.url(Table.init.requests.add_url),
                    }
                   Fun.api.open(options)
                }
            })

            /**
             * 方法失效
             */
            function openAll() {
                var treedata=treeGrid.getDataTreeList(Table.init.tableId);
                // if(treedata.length>0){
                    treeGrid.treeOpenAll(Table.init.table_elem,!treedata[0][treeGrid.config.cols.isOpen]);
                // }
            }
            treeGrid.on('edit(' + Table.init.table_elem + ')', function (obj) {
                var value = obj.value,
                    data = obj.data,
                    id = data.id,
                    field = obj.field;
                var _data = {
                    id: id,
                    field: field,
                    value: value,
                };
                Fun.ajax({
                    url: url,
                    prefix: true,
                    data: _data,
                }, function (res) {
                    Fun.toastr.success(res.msg, function () {
                        window.location.reload();
                    });
                }, function (res) {
                    Fun.toastr.error(res.msg, function () {
                        window.location.reload();
                    });
                }, function () {
                    treeGrid.render();
                });
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
        child:function(){
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