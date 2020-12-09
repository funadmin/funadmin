define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmstags/index',
                    add_url: 'addons/cms/backend/cmstags/add',
                    edit_url: 'addons/cms/backend/cmstags/edit',
                    delete_url: 'addons/cms/backend/cmstags/delete',
                    modify_url: 'addons/cms/backend/cmstags/modify',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add'],
                cols: [[
                    {checkbox: true},

                    {field: 'name', title: __('Name'), minwidth: 150, sort: true},
                    {field: 'clicks', title: __('clicks'), minwidth: 150, sort: true},
                    {field: 'nums', title: __('Nums'), minwidth: 150, sort: true},
                    {field: 'filing_ids', title: __('filing_ids'), minwidth: 150, sort: true},
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