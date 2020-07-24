define(['jquery', 'table', 'form'], function ($, Table, Form) {

    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tablId: 'list',
                requests: {
                    modify_url: 'auth.authgroup/modify',
                    index_url: 'auth.authgroup/index',
                    del_url: 'auth.authgroup/delete',
                    add_url: 'auth.authgroup/add',
                    edit_url: 'auth.authgroup/edit',
                    access: {
                        type: 'open',
                        class: 'layui-btn-xs layui-btn-warm',
                        icon: 'layui-icon-add-circle-fine',
                        url: 'auth.authgroup/access',
                        text: __('Access Group'),
                        title: __('Access Group'),
                        full: 1,
                    },

                },
            };
            Table.render({
                    elem: '#' + Table.init.table_elem,
                    id: Table.init.tablId,
                    url: Speed.url(Table.init.requests.index_url),
                    init: Table.init,
                    toolbar: ['refresh', 'add', 'delete'],
                    cols: [[
                        {checkbox: true, fixed: true},
                        {field: 'id', title: __('ID'), width: 80, fixed: true, sort: true},
                        {field: 'pid', title: __('Pid'), width: 150,},
                        {field: 'title', title: __('GroupName'), minwidth: 120,},
                        {
                            field: 'status',
                            title: __('Status'),
                            width: 120,
                            search: 'select',
                            selectList: {0: __('Disabled'), 1: __('Enabled')},
                            filter: 'status',
                            templet: Table.templet.switch
                        },
                        {field: 'create_time', title: __('CreateTime'), width: 180, templet: Table.templet.time},
                        {field: 'update_time', title: __('UpdateTime'), width: 180, templet: Table.templet.time},
                        {
                            width: 300,
                            align: 'center',
                            title: __('Operat'),
                            init: Table.init,
                            templet: Table.templet.operat,
                            operat: ['access', 'edit', 'delete',]
                        }

                    ]],
                    limits: [10, 15, 20, 25, 50, 100],
                    limit: 15,
                    page: true
                });
            let table = $('#' + Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        add: function () {

            Controller.api.bindevent()
        },
        edit: function () {
            Controller.api.bindevent()

        },
        access: function () {
            var $ = layui.jquery,
                util = layui.util,
                form = layui.form,
                tree = layui.tree;
            var idList = {};
            Speed.ajax({
                url: window.location.href,
                method: 'get'
            }, function (res) {
                idList = res.data.idList;
                list = res.data.list;
                tree.render({
                    elem: '#tree'
                    , data: list
                    , showCheckbox: true  //是否显示复选框
                    , id: 'treebox'
                    // ,edit: ['add', 'update', 'del']
                    , showLine: true
                    , accordion: true//是否开启手风琴模式，默认 false
                    , isJump: false //是否允许点击节点时弹出新窗口跳转
                });
                //修改权限样式
                // var stype = "<style>.layui-tree-line .layui-tree-set::before{height: 0}" +
                // ".layui-form-item .layui-tree-main{padding:10px;}"+
                // ".layui-form-item .layui-tree-entry{padding:10px;}"+
                // ".layui-tree-set{margin-right:10px;}"+
                // ".layui-tree-pack.layui-tree-lineExtend .layui-tree-set.layui-tree-spread{display:inline-block;width:auto}"+
                // ".layui-tree-checkedFirst{display:inline-block;width:auto}"+
                // ".layui-tree-spread{width:100%}"+
                //     "<\/style>"
                // $('body').append(stype)
            })
            // 按钮事件
            util.event('lay-event', {
                getChecked: function (othis) {
                    var checkedData = tree.getChecked('treebox'); //获取选中节点的数据
                }
                , setChecked: function () {
                    tree.setChecked('treebox', idList); //勾选指定节点
                }
                , reload: function () {
                    //重载实例
                    tree.reload('treebox', {});

                }
            })
            Form.api.bindEvent($('form'), function () {
            }, function () {
            }, function (e) {
                var dataRule = tree.getChecked('treebox');
                Speed.ajax({
                    url: window.location.href,
                    data: {rules: dataRule},
                }, function (res) {
                    Speed.msg.success(res.msg, function () {
                        Form.api.closeCurrentOpen()
                    })
                })
            })


        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});