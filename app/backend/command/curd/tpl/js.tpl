define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    {{$requests}}
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','destroy','export','recycle'],
                cols: [[
                    {{$jsCols}}
                ]],
                done: function(res){
                },
                //
                limits: [10, 15, 20, 25, 50, 100],
                limit: {{$limit}},
                page: {{$page}}
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
        recycle: function () {
            Table.init = {
                    table_elem: 'list',
                    tableId: 'list',
                    requests: {
                        recycle_url: '{{$controller}}/recycle',
                        delete_url: '{{$controller}}/delete',
                        restore_url: '{{$controller}}/restore',
                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {{$jsColsRecycle}}
                ]],
                done: function(res){
                },
                limits: [10, 15, 20, 25, 50, 100],
                limit: {{$limit}},
                page: {{$page}}
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    return Controller;
});