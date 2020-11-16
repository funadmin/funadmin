define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmscategory/index',
                    add_url: 'addons/cms/backend/cmscategory/add',
                    edit_url: 'addons/cms/backend/cmscategory/edit',
                    delete_url: 'addons/cms/backend/cmscategory/delete',
                    modify_url: 'addons/cms/backend/cmscategory/modify',
                    flashCache:{
                        type: 'request',
                        url:'addons/cms/backend/cmscategory/flashCache',
                        class: 'layui-btn-sm layui-btn-warm',
                        icon: 'layui-icon layui-icon-fonts-clear',
                        title: __('flashCache'),
                        text: __('flashCache'),
                        full: 1,
                    },
                    add_full: {
                        type: 'open',
                        url:'addons/cms/backend/cmscategory/add',
                        class: 'layui-btn-sm layui-btn-green',
                        icon: 'layui-icon layui-icon-add-circle-fine',
                        title: __('Add'),
                        text: __('Add'),
                        full: 1,
                    },
                    child: {
                        type: 'open',
                        url:'addons/cms/backend/cmscategory/add',
                        class: 'layui-btn-sm layui-btn-green',
                        icon: 'layui-icon layui-icon-add-circle-fine',
                        title: __('child'),
                        text: __('child'),
                        full: 1,
                    },
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','flashCache','add'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width: 80, fixed: true,},
                    {field: 'lcatename', title: __('Catename'),},
                    {field: 'module', title: __('Module'), },
                    {field: 'cateflag', title:  __('cateflag'), width: 120,},
                    {field: 'type', title: __('Type'), width: 110,templet: Table.templet.select,selectList:['List','Page','OutLink']},
                    {field: 'status', title: __('Status'),filter: 'status',width:100,templet: Table.templet.switch},
                    {field: 'is_menu', title: __('Ismenu'), width: 100,filter: 'status', templet: Table.templet.switch,},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['child','edit','delete']
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
                layui.form.on('radio(type)', function (data) {
                    row = {};
                    layui.laytpl($('#url_tpl').html()).render(row,function (){
                        if (data.value == 3) {
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