define(['jquery', 'table', 'form'], function ($, Table, Form) {
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    modify_url: 'auth.authGroup/modify',
                    index_url: 'auth.authGroup/index',
                    delete_url: 'auth.authGroup/delete',
                    add_url: 'auth.authGroup/add',
                    edit_url: 'auth.authGroup/edit',
                    access: {
                        type: 'open',
                        class: 'layui-btn-xs layui-btn-warm',
                        icon: 'layui-icon-set-sm',
                        url: 'auth.authGroup/access',
                        text: __('Access Group'),
                        title: __('Access Group'),
                        full: 1,
                    },
                },
            };
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh', 'add', 'delete'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: __('ID'), width: 80, sort: true},
                    // {field: 'pid', title: __('Pid'), width: 150,},
                    {field: 'ltitle', title: __('GroupName'), minwidth: 120,},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 120,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                        filter: 'status',
                        templet: Table.templet.switch
                    },
                    {field: 'create_time', title: __('CreateTime'),search:false,width: 180, templet: Table.templet.time},
                    {field: 'update_time', title: __('UpdateTime'), search: false,width: 180, templet: Table.templet.time},
                    {
                        width: 300,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: function (d){
                            if(d.id==1){
                                return '';
                            }else{
                                return Table.templet.operat.call(this,d);
                            }
                        },
                        operat: ['access', 'edit', 'delete']
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
        upme: function () {
            Controller.api.bindevent()

        },
        access: function () {
            var $ = layui.jquery,
                util = layui.util,
                form = layui.form,
                tree = layui.tree;
            var idList = {};
            Fun.ajax({
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
                    // ,edit: ['add','update']
                    , showLine: false
                    ,onlyIconControl:true//是否仅允许节点左侧图标控制展开收缩
                    , accordion: false//是否开启手风琴模式，默认 false
                    , isJump: false //是否允许点击节点时弹出新窗口跳转

                });
                // var stype = "<style>" +
                //     ".layui-tree-entry{padding: 10px 15px} " +
                //     ".layui-tree-spread .layui-tree-showLine .layui-tree-showLine{display: inline-flex}" +
                //     ".layui-tree-spread .layui-tree-lineExtend .layui-tree-checkedFirst{display: inline-flex!important;}" +
                //     ".layui-tree-spread .layui-tree-lineExtend .layui-tree-checkedFirst.layui-tree-set{width: auto!important;}" +
                //     ".layui-tree-pack .layui-tree-spread .layui-tree-pack.layui-tree-lineExtend{display: inline-flex!important;}" +
                //     ".layui-tree-line .layui-tree-set.layui-tree-setLineShort:before{height: 100%!important;}" +
                //     ".layui-tree-line .layui-tree-set .layui-tree-set:after{top: 20px!important;}" +
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
                Fun.ajax({
                    url: window.location.href,
                    data: {rules: JSON.stringify(dataRule)},
                }, function (res) {
                    Fun.toastr.success(res.msg, function () {
                        Form.api.closeCurrentOpen({refreshTable:true});
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