define(['jquery',"speed",'table','form'], function ($, speed,Table,Form) {
    let Controller = {
        /**
         * 会员等级
         *
         */
        index:function () {
            console.log(Table)
            // 初始化表格参数配置
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests:{
                    modify_url:'ucenter.userLevel/modify',
                    index_url: 'ucenter.userLevel/index',
                    add_url: 'ucenter.userLevel/add',
                    edit_url: 'ucenter.userLevel/edit',
                    del_url: 'ucenter.userLevel/delete',
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tablId,
                url: speed.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','add','delete',],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: __('Id'), width: 80, sort: true},
                    {field: 'level_name', title: __('Levelname'), width: 120, sort: true},
                    {field: 'amount', title: __('Levelmoney'), width: 150, sort: true},
                    {field: 'discount', title: __('Leveldiscount'), width: 180, sort: true},
                    {field: 'description', title: __('Leveldesc'), width: 150, sort: true},
                    {
                        field: 'status', title: __('Status'), width: 180, search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('Createtime'), width: 180,},
                    {field: 'update_time', title: __('Updatetime'), width: 180,},
                    {
                        width: 250,
                        align: 'center',
                        title: '操作',
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['edit', 'delete',]
                    }

                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });
            var table = $("#"+ Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add:function () {
            Controller.api.bindevent();
        },
        edit:function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent()
            }
        }
    };
    return Controller;
});