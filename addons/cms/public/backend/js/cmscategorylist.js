define(['table','form'], function (Table,Form) {
    let Controller = {
        index: function () {
            alert(1111111111111)
            var $ = layui.jquery,tree = layui.tree;
            var P_frame_height = parent.layui.$("#homePage").height() - 100;
            $(window).on('resize', function () {
                setTimeout(function () {
                    P_frame_height = parent.layui.$("#homePage").height() -300;
                    frameheight();
                }, 100);
            });

            function frameheight() {
                $("#categorys").height(P_frame_height);
                $("#categorys_list").height(P_frame_height);
            }

            (function () {
                frameheight();
            })();

            //点击节点新窗口跳转
            tree.render({
                elem: '#tree'
                ,data: data
                ,isJump: false  //link 为参数匹配
                ,click: function(obj){
                    var data = obj.data;  //获取当前点击的节点数据
                    // if(!data.children){
                    $('#categorys_list').attr('src',data.href);
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
            // Table.init = {
            //     table_elem: 'list',
            //     tableId: 'list',
            //     requests:{
            //         index_url: 'addons/cms/backend/cmscategorylist/index',
            //         add_url: 'addons/cms/backend/cmscategorylist/add',
            //         edit_url: 'addons/cms/backend/cmscategorylist/edit',
            //         delete_url: 'addons/cms/backend/cmscategorylist/delete',
            //         modify_url: 'addons/cms/backend/cmscategorylist/modify',
            //         flashCache:{
            //             type: 'request',
            //             url:'addons/cms/backend/cmscategorylist/flashCache',
            //             class: 'layui-btn-sm layui-btn-warm',
            //             icon: 'layui-icon layui-icon-fonts-clear',
            //             title: __('flashCache'),
            //             text: __('flashCache'),
            //             full: 1,
            //         },
            //         add_full: {
            //             type: 'open',
            //             url:'addons/cms/backend/cmscategorylist/add',
            //             class: 'layui-btn-sm layui-btn-green',
            //             icon: 'layui-icon layui-icon-add-circle-fine',
            //             title: __('Add'),
            //             text: __('Add'),
            //             full: 1,
            //         },
            //         child: {
            //             type: 'open',
            //             url:'addons/cms/backend/cmscategorylist/add',
            //             class: 'layui-btn-sm layui-btn-green',
            //             icon: 'layui-icon layui-icon-add-circle-fine',
            //             title: __('child'),
            //             text: __('child'),
            //             full: 1,
            //         },
            //     }
            // }
            // Table.render({
            //     elem: '#' + Table.init.table_elem,
            //     id: Table.init.tableId,
            //     url: Fun.url(Table.init.requests.index_url),
            //     init: Table.init,
            //     toolbar: ['refresh','flashCache','add'],
            //     cols: [[
            //         {checkbox: true, fixed: true},
            //         {field: 'id', title: 'ID', width: 80, fixed: true,},
            //         {field: 'lcatename', title: __('Catename'),},
            //         {field: 'module', title: __('Module'), },
            //         {field: 'cateflag', title:  __('cateflag'), width: 120,},
            //         {field: 'type', title: __('Type'), width: 110,templet: Table.templet.select,selectList:['List','Page','OutLink']},
            //         {field: 'status', title: __('Status'),filter: 'status',width:100,templet: Table.templet.switch},
            //         {field: 'is_menu', title: __('Ismenu'), width: 100,filter: 'status', templet: Table.templet.switch,},
            //         {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
            //         {field: 'update_time', title: __('Updatetime'), width: 180, sort: true},
            //         {
            //             align: 'center', title: __('Operat'), init: Table.init,
            //             templet : Table.templet.operat, operat: ['child','edit','delete']
            //         }
            //     ]],
            //     done: function(res){
            //
            //     },
            //     //
            //     limits: [10, 15, 20, 25, 50, 100],
            //     limit: 50,
            //     page: true
            // });
            // let table = $('#'+Table.init.table_elem);
            // Table.api.bindEvent(table);
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