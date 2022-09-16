define(['table','form'], function (Table,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests:{
            index_url: 'backend.Module/index',
            add_url: 'backend.Module/add',
            edit_url: 'backend.Module/edit',
            delete_url: 'backend.Module/delete',
            modify_url: 'backend.Module/modify',
            destroy_url: 'backend.Module/destroy',
            recycle_url: 'backend.Module/recycle',
            restore_url: 'backend.Module/restore',
        }
    }
    let Controller = {
        index: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','destroy','recycle'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width:90, fixed: true,sort:true},
                    {field: 'modulename', title:__('ModuleName'), width: 150},
                    {field: 'tablename', title: __('TableName'), minwidth: 150, sort: true},
                    {field: 'intro', title: __('intro'), minwidth: 180},
                    {field: 'status', title: __('status'), filter: 'status',minwidth: 150, sort: true,templet: Table.templet.switch},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat,
                        operat: [
                            'edit',
                            {
                                type: 'open',
                                url:'backend.Field/index/moduleid/{ids}',
                                class: 'layui-btn-xs layui-btn-green',
                                icon: 'layui-icon layui-icon-slider',
                                title: __('FieldList'),
                                text: __('FieldList'),
                                full: 1,
                            },'destroy','delete'],
                    }
                ]],
                done: function(res){

                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        recycle: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width:90, fixed: true,sort:true},
                    {field: 'modulename', title:__('ModuleName'), width: 150},
                    {field: 'tablename', title: __('TableName'), minwidth: 150, sort: true},
                    {field: 'intro', title: __('intro'), minwidth: 180},
                    {field: 'status', title: __('status'), filter: 'status',minwidth: 150, sort: true,templet: Table.templet.switch},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat,
                        operat: ['delete','restore'],
                    }
                ]],
                done: function(res){
                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: 50,
                page: true
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
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