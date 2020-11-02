define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmslink/index',
                    add_url: 'addons/cms/backend/cmslink/add',
                    edit_url: 'addons/cms/backend/cmslink/edit',
                    delete_url: 'addons/cms/backend/cmslink/delete',
                    modify_url: 'addons/cms/backend/cmslink/modify',
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
                    {field: 'id', title: 'ID', width: 80, fixed: true, sort: true},
                    {field: 'name', title: __('Name'), minwidth: 120, fixed: true, sort: true},
                    {field: 'email', title: __('email'), width: 180},
                    {field: 'url', title: __('Url'), minwidth: 110, sort: true},
                    {field: 'thumb', title: __('Thumb'), minwidth: 110, sort: true},
                    {field: 'status', title: __('status'),filter: 'status', minwidth: 150, sort: true,templet: Table.templet.switch},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
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