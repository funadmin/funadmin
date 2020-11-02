define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmsadv/index',
                    edit_url: 'addons/cms/backend/cmsadv/edit',
                    delete_url: 'addons/cms/backend/cmsadv/delete',
                    modify_url: 'addons/cms/backend/cmsadv/modify',
                    add_full: {
                        type: 'open',
                        url:'addons/cms/backend/cmsadv/add',
                        class: 'layui-btn-sm layui-btn-green',
                        icon: 'layui-icon layui-icon-add-circle-fine',
                        title: __('Add'),
                        text: __('Add'),
                        full: 1,
                    },
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add_full'],
                cols: [[
                    {checkbox: true, fixed: true},

                    {field: 'name', title: __('Name'), minwidth: 150, fixed: true, sort: true},
                    {field: 'cmsPos.name', title: __('PositionName'), width: 150,templet: Table.templet.resolution, sort: true},
                    {field: 'image', title: __('Image'), width: 110, templet: Table.templet.image,sort: true},
                    {field: 'type', title: __('Type'), width: 110, sort: true},
                    {field: 'url', title: __('Url'), width: 110, sort: true},
                    {field: 'status', title: __('status'),filter: 'status', minwidth: 120,templet: Table.templet.switch, sort: true},
                    {field: 'start_time', title: __('Starttime'), width: 180,templet: Table.templet.time, sort: true,},
                    {field: 'end_time', title: __('Endtime'), width: 180,templet: Table.templet.time ,sort: true,},
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