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
                primaryKey:'{{$primaryKey}}',
                toolbar: [{{$toolbar}}],
                cols: [[
                    {{$jsCols}}
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: {{$limit}},
                page: {{$page}},
                done: function (res, curr, count) {
                }
            });
            Table.api.bindEvent(Table.init.tableId);
        },
        add: function () {
            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()
        },
        {{$jsrecycleTpl}}
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }
    };
    return Controller;
});
