define(['jquery','table','form'], function (undefined,Table,Form) {

    let Controller = {

        index: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests: {
                    index_url: 'sys.config/index',
                    del_url: 'sys.config/delete',
                    add_url: 'sys.config/add',
                    edit_url: 'sys.config/edit',
                    modify_url: 'sys.config/modify',

                },
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tablId,
                url: Speed.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: __('ID'), width: 80,  sort: true},
                    {field: 'code', title: __('Config Code'), width: 120,sort: true},
                    {field: 'value', title: __('Config Value'), width: 250,sort: true},
                    {field: 'group', title: __('Config Group'), width: 250,sort: true},
                    {field: 'type', title: __('Type'), width: 250,sort: true},
                    {field: 'remark', title: __('Config Remark'), width: 220,sort:true},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 180,
                        search: 'select',
                        selectList: {0:__('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch},
                    {
                        width: 250, align: 'center', title:__('Oprate'), init: Table.init, templet : Table.templet.operat, operat: ['edit','delete',]
                    }

                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });

            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add:function () {
            Controller.api.bindevent()
        },
        edit:function () {
            Controller.api.bindevent()
        },
        set:function(){
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