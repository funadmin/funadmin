recycle: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    {{$requestsRecycle}}
                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                primaryKey:'{{$primaryKey}}',
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {{$jsColsRecycle}}
                ]],
                limits: [10, 15, 20, 25, 50, 100,500],
                limit: {{$limit}},
                page: {{$page}},
                done: function (res, curr, count) {
                }
            });
            Table.api.bindEvent(Table.init.tableId);
        },
