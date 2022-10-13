define(['jquery','table','form'], function (undefined,Table,Form) {

    return Controller =   {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'sys.languages/index',
                    add_url: 'sys.languages/add',
                    delete_url: 'sys.languages/delete',
                    edit_url: 'sys.languages/edit',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete'],
                cols: [[
                    {type: "checkbox"},
                    {field: 'id', title: 'ID', sort: true, width: 80, search: false},
                    {field: 'name', title: __('Name'),  sort: true,},
                    {field: 'is_default', title: __('Default'), width: 150, sort: true},
                    {field: 'create_time', title: __('CreateTime'), width: 180, search: 'time'},
                    {
                        width: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: function (d){
                            if(d.name=='zh-cn'){
                                return ''
                            }
                            return Table.templet.operat.call(this,d)
                        },
                        operat: ['edit','delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });


            Table.api.bindEvent(Table.init.tableId);
        },
        add:function () {
            Controller.api.bindevent()
        },
        edit:function () {
            Controller.api.bindevent()
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    }
});