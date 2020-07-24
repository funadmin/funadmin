define(['jquery', 'treeGrid','table','form','icon'], function ($,treeGrid,Table, Form,Icon) {
    var treeGrid = layui.treeGrid,form = layui.form;
    var Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests: {
                    index_url: 'auth.auth/index',
                    add_url: 'auth.auth/add',
                    edit_url: 'auth.auth/edit',
                    del_url: 'auth.auth/delete',
                    modify_url: 'auth.auth/modify',
                    child:  {
                        type: 'open',
                        class: 'layui-btn-xs layui-btn-warm',
                        icon: 'layui-icon-add-circle-fine',
                        url: 'auth.auth/child',
                        text: __('Add Child'),
                        title:  __('Add Child'),
                        full:0,
                    },
                },
            };
            treeGrid.render({
                id: Table.init.tablId,
                elem: '#' + Table.init.table_elem,
                url: Speed.url(Table.init.requests.index_url),
                init: Table.init,
                method:'get',
                idField:'id'
                ,cellMinWidth: 100
                ,treeId:'id'//树形id字段名称
                ,treeUpId:'pid'//树形父id字段名称
                ,treeShowName:'title'//以树形式显示的字段
                ,height:'full-140'
                ,isFilter:false
                ,iconOpen:true//是否显示图标【默认显示】
                ,isOpenDefault:true//节点默认是展开还是折叠【默认展开】
                ,cols: [[
                    // {checkbox: true, fixed: true},
                    {field: 'id', title: __('ID'), width: 80, fixed: true, sort: true},
                    {field: 'icon',title: __("icon"), width: 60,templet: Table.templet.icon},
                    {field: 'title', title: __('Auth Name'), minwidth: 120,},
                    {field: 'href', title: __('Controller/Action'), minwidth: 200},
                    {
                        field: 'auth_open',
                        align: 'center',
                        title: __('Auth Verfiy'),
                        width: 150,
                        templet: Table.templet.switch,
                    },
                    {
                        field: 'auth_open',
                        title: __('Menu Status'),
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
            var url = Speed.url(Table.init.requests.modify_url);
            form.on('switch(auth_open)', function(obj){
                var field = obj.elem.name;
                value = obj.elem.checked?1:0;
                data = {id:obj.value,value:value,field:field};
                Speed.ajax({url:url,data:data})
            });
            form.on('switch(status)', function(obj){
                var field = obj.elem.name;
                value = obj.elem.checked?1:0;
                data = {id:obj.value,value:value,field:field};
                Speed.ajax({url:url,data:data})
            });
            $(document).on('click','[lay-event]',function (e) {
                var event = $(this).attr('lay-event');
                if(event=='openAll'){
                    openAll();
                }else if(event=='add'){
                    options = {
                        title:__('add'),
                        url:Speed.url(Table.init.requests.add_url),
                    }
                   Speed.api.open(options)
                }
            })

            /**
             * 方法失效
             */
            function openAll() {
                var treedata=treeGrid.getDataTreeList(Table.init.tablId);
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
                Speed.ajax({
                    url: Table.init.requests.modify_url,
                    prefix: true,
                    data: _data,
                }, function (res) {
                    Speed.msg.success(res.msg, function () {
                        window.location.reload();
                    });
                }, function (res) {
                    Speed.msg.error(res.msg, function () {
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
            Icon.api.bindEvent();
        },
        edit: function () {
            Controller.api.bindevent()
            Icon.api.bindEvent();
        },
        child:function(){
            Controller.api.bindevent()
            Icon.api.bindEvent();

        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});