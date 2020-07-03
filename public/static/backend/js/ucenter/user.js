define(['jquery','table','form'], function ($,Table,Form) {

    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests: {
                    modify_url: 'ucenter.user/modify',
                    index_url: 'ucenter.user/index',
                    del_url: 'ucenter.user/delete',
                    add_url: 'ucenter.user/add',
                    edit_url: 'ucenter.user/edit',

                },
            },
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tablId,
                url: Speed.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width: 80, sort: true},
                    {field: 'username', title: __('Username'), width: 120,},
                    {field: 'email', title: __('Email'), width: 120,},
                    {field: 'mobile', title: __('mobile'), width: 120,},
                    {
                        field: 'sex',
                        title: __('Sex'),
                        filter: 'sex',
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Female'), 1: __('Man'), 2: __('Secret')},
                        templet: Table.templet.switch,
                        tips: __('Female')+'|'+  __('Man')
                    },
                    {
                        field: 'userLevel.level_name',
                        title: __('Userlevel'),
                        width: 120,
                        templet: Table.templet.resolution
                    },
                    {field: 'avatar', title: __('Avatar'), width: 120, templet: Table.templet.image},
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
                    {field: 'create_time', title: __('Registertime'), width: 180,},
                    {field: 'last_login', title: __('Lastlogintime'), width: 180, templet: Table.templet.time},
                    {
                        width: 250,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit', 'delete',]
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
        // delete:function () {
        //     Controller.api.bindevent()
        // },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});