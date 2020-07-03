define(['jquery','table','form'], function (undefined,Table,Form) {

    let Controller = {
        index:function(){
            Controller.api.bindevent();
        },
        configlist: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests: {
                    index_url: 'sys.system/index',
                    configlist_url: 'sys.system/configlist',
                    del_url: 'sys.system/configDel',
                    add_url: 'sys.system/configAdd',
                    edit_url: 'sys.system/configEdit',
                    configGroupAdd: {
                        type: 'open',
                        class: 'layui-btn-nomarl',
                        icon: 'layui-icon-add-circle-fine',
                        url: 'sys.system/configGroupAdd',
                        text: '添加配置组',
                        title: '添加配置组',
                    },
                    configGroup: {
                        type: 'list',
                        class: 'layui-btn-nomarl',
                        icon: 'layui-icon-add-circle-fine',
                        url: 'sys.system/configGroup',
                        text: '配置分组列表',
                        title: '配置分组列表',
                    },
                },
            }
            speed.table.render({
                elem: '#' + init.table_elem,
                id: init.tablId,
                url: speed.url(init.requests.configlist_url),
                init: init,
                toolbar: ['refresh','add','delete','configGroupAdd','configGroup'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: lang('Id'), width: 80,  sort: true},
                    {field: 'code', title: '配置键', width: 120,sort: true},
                    {field: 'value', title: '配置值', width: 250,sort: true},
                    {field: 'type', title: '分组', width: 250,sort: true},
                    {field: 'remark', title: '备注', width: 220,sort:true},
                    {field: 'status', title: '状态', width: 180, search: 'select', selectList: {0: '禁用', 1: '启用'}, filter: 'status', templet: speed.table.switch},
                    {
                        width: 250, align: 'center', title: '操作', init: init, templet : speed.table.tool, operat: ['edit','delete',]
                    }

                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });

            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        config_add:function () {
            Controller.api.bindevent()
        },
        config_edit:function () {
            Controller.api.bindevent()
        },
        config_group:function () {
            Controller.api.bindevent()
        },
        config_group_add:function () {
            Controller.api.bindevent()
        },
        // config_del:function () {
        //    Controller.api.bindevent()
        // },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});