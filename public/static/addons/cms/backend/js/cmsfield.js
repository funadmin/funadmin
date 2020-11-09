define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmsfield/index',
                    add_url: 'addons/cms/backend/cmsfield/add',
                    edit_url: 'addons/cms/backend/cmsfield/edit',
                    delete_url: 'addons/cms/backend/cmsfield/delete',
                    destroy_url: 'addons/cms/backend/cmsfield/destroy',
                    modify_url: 'addons/cms/backend/cmsfield/modify',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'name', title: __("fieldname"), width: 120,sort:true},
                    {field: 'field', title: __("field"), width: 120,sort:true},
                    {field: 'maxlength', title: __("maxlength"), width: 50,sort:true},
                    {field: 'minlength', title: __("maxlength"), width: 50,sort:true},
                    {field: 'radix', title: __("maxlength"), width: 50,sort:true},
                    {field: 'msg', title: __("msg"), width: 180,sort:true},
                    {field: 'rule', title: __("rule"), width: 180},
                    {field: 'type', title: __("type"), width: 180,sort:true},
                    {field: 'value', title: __("value"), width: 180,sort:true},
                    {field: 'required', title: __("required"), width: 100,sort:true,},
                    {field: 'sort', title: __('Sort'), width: 80,edit:true,sort:true},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        width: 250, align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['edit','destroy','delete']
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