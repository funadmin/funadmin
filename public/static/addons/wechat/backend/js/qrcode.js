define(['jquery','table','form'], function ($,Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'addons/wechat/backend/index/index',
                    add_url: 'addons/wechat/backend/index/add',
                    edit_url: 'addons/wechat/backend/index/edit',
                    modify_url: 'addons/wechat/backend/index/modify',
                    delete_url: 'addons/wechat/backend/index/delete',
                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete'],
                cols: [[
                    {checkbox: true,},
                    {field: 'id', title: 'ID', width: 80, fixed: true, sort: true},
                    {field: 'wxname', title: __('wxname'), width: 120,},
                    {field: 'origin_id', title: __('originid'), width: 120,},
                    {field: 'app_id', title: __('originid'), width: 120, },
                    {field: 'app_secret', title: __('originid'), width: 120, },
                    {field: 'w_token', title: __('originid'), width: 120,},
                    {field: 'qr', title: __('qrcode'), width: 120,templet: Table.templet.image},
                    {field: 'logo', title: __('logo'), width: 120,templet: Table.templet.image},
                    {field: 'type', title: __('type'), width: 120,
                        search: 'select',
                        selectList: {0: __('订阅号'), 1: __('服务号'), 2: __('企业号')},
                    },
                    // {field: 'store_id', title: '店铺id', width: 120,sort: true},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('reateTime'), width: 180,search:'range'},
                    {
                        minwidth: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit_full', 'delete']
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
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});