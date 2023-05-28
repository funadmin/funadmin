define(['jquery','table','form'], function (undefined,Table,Form) {

    return Controller =   {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'sys.attachGroup/index',
                    delete_url: 'sys.attachGroup/delete',
                    add_url: 'sys.attachGroup/add',
                    edit_url: 'sys.attachGroup/edit',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add'],
                tree: {
                    customName: {
                        'name':'title',
                    },
                    // data: {isSimpleData:false},
                },
                cols: [[
                    {type: "checkbox"},
                    {field: 'id', title: 'ID', sort: true, width: 80, search: false},
                    {field: 'title', title: __('Title'), width: 150, sort: true},
                    {field: 'thumb', title: __('thumb'), sort: true,templet:Table.templet.image},
                    {field: 'create_time', title: __('CreateTime'), width: 180, search: 'range'},
                    {
                        width: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit','delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });

            Table.api.bindEvent(Table.init.tableId);

        },
        add:function (){
            Controller.api.bindevent();
        },
        edit:function (){
            Controller.api.bindevent();
        },
        api:{
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
});
