define(['jquery','table','form'], function ($,Table,Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'addons/wechat/backend/fans/index',
                    add_url: 'addons/wechat/backend/fans/add',
                    aysn: {
                        url:'addons/wechat/backend/fans/aysn',
                        icon:'layui-icon layui-icon-refresh'
                    },
                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','aysn'],
                cols: [[
                    {checkbox: true,},
                    {field: 'id', title: 'ID', width: 80, fixed: true, sort: true},
                    {field: 'memberid', title: __("memberid"), width: 80, fixed: true, sort: true},
                    {field: 'nickname', title:__("nickname"), width: 180,sort: true},
                    {field: 'headimgurl', title:__("avatar"), width: 150,templet:Table.templet.image},
                    {field: 'sex', title: __("sex"), width: 120,sort: true,
                        search: 'select',
                        selectList: {0: __('female'), 1: __('male'),2:__('secret')},
                        filter: 'sex',
                        templet: Table.templet.select},
                    {field: 'openid', title: 'openid', width: 120, sort: true},
                    {field: 'unionid', title: 'unionid', width: 120, sort: true},
                    {field: 'groupid', title: __('groupid'), width: 120,sort: true},
                    {field: 'subscribe', title: __('issubscribe'), width: 120,sort:true,search: 'select',
                        selectList: {0: __('No'), 1: __('Yes')},
                        filter: 'subscribe',
                        templet: Table.templet.switch
                    },
                    {field: 'remark', title: __('remark'), width: 120, sort: true},
                    {field: 'tags', title: __('tags'), width: 120,sort: true},
                    {field: 'subscribe_time',title:__('subscribeTime'), width: 180,search:'range',sort: true},
                    {field: 'unsubscribe_time',title: __('unsubscribeTime'), width: 180,search:'range',sort: true},
                    // {
                    //     minwidth: 250,
                    //     align: 'center',
                    //     title: __('Operat'),
                    //     init: Table.init,
                    //     templet: Table.templet.operat,
                    //     operat: ['edit_full', 'delete']
                    // }
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