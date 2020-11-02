define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmsdebris/index',
                    add_url: 'addons/cms/backend/cmsdebris/add',
                    edit_url: 'addons/cms/backend/cmsdebris/edit',
                    modify_url: 'addons/cms/backend/cmsdebris/modify',
                    delete_url: 'addons/cms/backend/cmsdebris/delete',
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

                    {field: 'title', title: __('Name'), minwidth: 150, fixed: true, sort: true},
                    {field: 'cmsDebrisPos.title', title: __('Type'), width: 150,templet: Table.templet.resolution, sort: true},
                    {field: 'image', title: __('Image'), width: 110, templet: Table.templet.image,sort: true},
                    {field: 'url', title: __('Url'), width: 110, sort: true},
                    {field: 'sort', title: __('Sort'), width: 110, sort: true,edit:true},
                    {field: 'status', title: __('status'), filter: 'status',minwidth: 150,templet: Table.templet.switch, sort: true},
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