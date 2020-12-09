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
                    {field: 'title', title: __('Title'), width: 180,},
                    {field: 'status',title: __("status"), width: 120,sort:true,templet:Table.templet.switch,},
                    {field: 'create_time', title: __('Createtime'), width: 180},
                    // {field: 'id', title:'ID', width:90, sort:true,},
                    // {field: 'name', title: __('Name'), minwidth: 150,  sort: true},
                    // {field: 'field', title: __("field"), width: 120,sort:true},
                    // // {field: 'maxlength', title: __("maxlength")}', width: 50,sort:true},
                    // {field: 'msg', title: __("msg"), width: 180,sort:true},
                    // // {field: 'rule', title: __("rule"), width: 180},
                    // {field: 'type', title: __("type"), width: 180,sort:true},
                    // {field: 'value', title: __("value"), width: 180,sort:true},
                    // {field: 'required', title: __("required"), width: 100,templet:Table.templet.switch},
                    // {field: 'sort', title: __("sort"), width: 80,},
                    {field: 'status',title: __("status"), width: 120,sort:true,templet:Table.templet.switch,},

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