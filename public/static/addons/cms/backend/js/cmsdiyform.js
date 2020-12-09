define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmsdiyform/index',
                    add_url: 'addons/cms/backend/cmsdiyform/add',
                    edit_url: 'addons/cms/backend/cmsdiyform/edit',
                    delete_url: 'addons/cms/backend/cmsdiyform/delete',
                    modify_url: 'addons/cms/backend/cmsdiyform/modify',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: 'ID', width: 80,  sort: true},
                    {field: 'tablename', title: __('tablename'), width: 180, },
                    {field: 'name', title: __('Name'), minwidth: 150, },
                    {field: 'status',title: __("status"), width: 120,sort:true,templet:Table.templet.switch,},
                    {field: 'create_time', title: __('Createtime'), width: 180},
                    {field: 'fieldlist', title: __("Field"), width: 200,sort:true,
                        templet: function (d){
                            return  '<a class="layui-btn layui-btn-xs layui-bg-green" href="/addons/cms/backend/cmsdiyform/field"><i class="layui-icon layui-icon-list"></i>'+__('Filelist')+'</a>'+
                           '<a class="layui-btn layui-btn-xs layui-bg-blue" href="/addons/cms/backend/cmsdiyform/data"><i class="layui-icon  layui-icon-template-1"></i>'+__('datalist')+'</a>'
                        }
                    },
                    {field: 'status',title: __("status"), width: 120,sort:true,templet:Table.templet.switch},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['edit','delete']
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