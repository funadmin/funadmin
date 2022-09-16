define(['table','form'], function (Table,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests:{
            index_url: 'backend.Category/index',
            add_url: 'backend.Category/add',
            edit_url: 'backend.Category/edit',
            delete_url: 'backend.Category/delete',
            modify_url: 'backend.Category/modify',
            recycle_url: 'backend.Category/recycle',
            destroy_url: 'backend.Category/destroy',
            restore_url: 'backend.Category/restore',
            flashCache:{
                type: 'request',
                url:'backend.Category/flashCache',
                class: 'layui-btn-warm',
                icon: 'layui-icon layui-icon-fonts-clear',
                title: __('flashCache'),
                text: __('flashCache'),
                full: 1,
            },
            add_full: {
                type: 'open',
                url:'backend.Category/add',
                class: ' layui-btn-green',
                icon: 'layui-icon layui-icon-add-circle-fine',
                title: __('Add'),
                text: __('Add'),
                full: 1,
            },
            child: {
                type: 'open',
                url:'backend.Category/add',
                class: 'layui-btn-green',
                icon: 'layui-icon layui-icon-add-circle-fine',
                title: __('child'),
                text: __('child'),
                full: 1,
            },
        }
    }
    let Controller = {
        index: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','flashCache','add','destroy','recycle'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width: 80, fixed: true,},
                    {field: 'lcatename', title: __('Catename'),},
                    {field: 'module.modulename', title: __('Module'),templet: Table.templet.resolution, },
                    {field: 'cateflag', title:  __('cateflag'), width: 120,},
                    {field: 'sort', title:  __('Sort'), width: 120,edit: true},
                    {field: 'type', title: __('Type'), width: 110,templet: Table.templet.select,
                        selectList: {1:'List',2:'Page',3:'OutLink',4:'Column'}
                    },
                    {field: 'status', title: __('Status'),filter: 'status',width:100,templet: Table.templet.switch},
                    {field: 'is_menu', title: __('Ismenu'), width: 100,filter: 'is_menu', templet: Table.templet.switch,},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['child','edit','destroy']
                    }
                ]],
                done: function(res){},
                // limits: [10, 15, 20, 25, 50, 100],
                // limit: 50,
                page: false
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
        recycle: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width: 80, fixed: true,},
                    {field: 'lcatename', title: __('Catename'),},
                    {field: 'module.modulename', title: __('Module'),templet: Table.templet.resolution, },
                    {field: 'cateflag', title:  __('cateflag'), width: 120,},
                    {field: 'sort', title:  __('Sort'), width: 120,edit: true},
                    {field: 'type', title: __('Type'), width: 110,templet: Table.templet.select,
                        selectList: {1:'List',2:'Page',3:'OutLink',4:'Column'}
                    },
                    {field: 'status', title: __('Status'),filter: 'status',width:100,templet: Table.templet.switch},
                    {field: 'is_menu', title: __('Ismenu'), width: 100,filter: 'is_menu', templet: Table.templet.switch,},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['delete','restore']
                    }
                ]],
                done: function(res){},
                // limits: [10, 15, 20, 25, 50, 100],
                // limit: 50,
                page: false
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        api: {
            bindevent: function () {
                layui.form.on('radio(type)', function (data) {
                    let row = {};
                    layui.laytpl($('#url_tpl').html()).render(row,function (){
                        if (data.value === 3 || data.value==='3') {
                            console.log($('#url_tpl').html())
                            $('#url').html($('#url_tpl').html()) ;
                        } else {
                            $('#url').html('') ;
                        }
                    })
                });
                Form.api.bindEvent($('form'))
            },

        }

    };
    return Controller;
});