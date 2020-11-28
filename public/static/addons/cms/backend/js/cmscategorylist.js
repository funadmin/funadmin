define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            var $ = layui.jquery,tree = layui.tree;
            var parent_frame_height = parent.layui.$("#homePage").height()-80;
            $(window).on('resize', function () {
                setTimeout(function () {
                    parent_frame_height = parent.layui.$("#homePage").height();
                    frameheight();
                }, 100);
            });

            function frameheight() {
                $("#categorys_list").height(parent_frame_height);
            }
            (function () {
                frameheight();
            })();
            //点击节点新窗口跳转
            tree.render({
                elem: '#tree'
                ,data: data
                ,isJump: false  //link 为参数匹配
                ,showLine: false  //是否开启连接线
                ,onlyIconControl: true  //是否仅允许节点左侧图标控制展开收缩
                ,showCheckbox: true
                ,click: function(obj){
                    var data = obj.data;  //获取当前点击的节点数据
                    // if(!data.children){
                    if(data.href){
                        $('#categorys_list').attr('src',data.href);
                    }
                    // }
                }
            });

            $('#clear').click(function (){
                loading =layer.load(1, {shade: [0.1,'#fff']});
                $.post("{:url('flashCache')}",{},function(res){
                    layer.close(loading);
                    if(res.code>0){
                        layer.msg(res.msg,{time:1000,icon:1});
                    }else{
                        layer.msg(res.msg,{time:1000,icon:2});
                    }
                });
            });
            Controller.api.bindevent()

        },
        list:function (){
            cateid = cate?cate.id:'';
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmscategorylist/list?cateid='+ cateid,
                    add_url: 'addons/cms/backend/cmscategorylist/add?cateid='+   cateid,
                    edit_url: 'addons/cms/backend/cmscategorylist/add?cateid='+   cateid,
                    delete_url: 'addons/cms/backend/cmscategorylist/delete?cateid='+  cateid,
                    modify_url: 'addons/cms/backend/cmscategorylist/modify?cateid='+  cateid,
                    flashCache:{
                        type: 'request',
                        url:'addons/cms/backend/cmscategorylist/flashCache?cateid='+   cateid,
                        class: 'layui-btn-sm layui-btn-warm',
                        icon: 'layui-icon layui-icon-fonts-clear',
                        title: __('flashCache'),
                        text: __('flashCache'),
                        full: 1,
                    },
                    add_full: {
                        type: 'open',
                        url:'addons/cms/backend/cmscategorylist/add?cateid='+   cateid,
                        class: 'layui-btn-sm layui-btn-green',
                        icon: 'layui-icon layui-icon-add-circle-fine',
                        title: __('Add'),
                        text: __('Add'),
                        full: 1,
                    },
                }
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','flashCache','add_full'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width: 80, fixed: true,},
                    {field: 'title', title: __('Title'), width: 150,},
                    {field: 'sort', title:  __('sort'), width: 80,edit:true},
                    {field: 'hits', title:  __('Hits'), width: 80},
                    {field: 'status', title: __('Status'),filter: 'status',width:100,templet: Table.templet.switch},
                    {field: 'is_menu', title: __('Ismenu'), width: 100,filter: 'status', templet: Table.templet.switch,},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
                    {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
                    {
                        align: 'center', title: __('Operat'), init: Table.init,
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
        page:function (){
            Controller.api.bindevent()
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