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
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests:{
                    index_url: 'addons/cms/backend/cmscategorylist/index',
                    add_url: 'addons/cms/backend/cmscategorylist/add',
                    edit_url: 'addons/cms/backend/cmscategorylist/add',
                    delete_url: 'addons/cms/backend/cmscategorylist/delete',
                    modify_url: 'addons/cms/backend/cmscategorylist/modify',
                    flashCache:{
                        type: 'request',
                        url:'addons/cms/backend/cmscategorylist/flashCache',
                        class: 'layui-btn-sm layui-btn-warm',
                        icon: 'layui-icon layui-icon-fonts-clear',
                        title: __('flashCache'),
                        text: __('flashCache'),
                        full: 1,
                    },
                    add_full: {
                        type: 'open',
                        url:'addons/cms/backend/cmscategorylist/add',
                        class: 'layui-btn-sm layui-btn-green',
                        icon: 'layui-icon layui-icon-add-circle-fine',
                        title: __('Add'),
                        text: __('Add'),
                        full: 1,
                    },
                }
            }
            var tableObj = Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','flashCache','add_full'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {field: 'id', title: 'ID', width: 80, fixed: true,},
                    {field: 'title', title: __('Title'), width: 150,},
                    {field: 'hits', title:  __('Hits'), width: 80},
                    {field: 'is_read', title:  __('Read'), width: 80},
                    {field: 'status', title: __('Status'),filter: 'status',width:100,templet: Table.templet.switch},
                    {field: 'sort', title:  __('sort'), width: 80,edit:true},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true},
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
            tree.render({
                elem: '#tree'
                ,data: data
                ,isJump: false  //link 为参数匹配
                ,showLine: true  //是否开启连接线
                ,onlyIconControl: true  //是否仅允许节点左侧图标控制展开收缩
                // ,showChe ckbox: true
                ,click: function(obj){
                    var data = obj.data;  //获取当前点击的节点数据
                    console.log(data);
                    if(data.type===1 ||  data.type===3 || data.type===4){
                        $('.table').show();
                        //1 列表2 单页，3 外连接，4 封面
                        $('.layui-col-md10 .layui-card-body').addClass('layui-hide');
                        tableObj.reload({ page: {page: 1},where: {'cateid': data.id}});
                    }else if(data.type===2 ){
                        $('.table').hide();
                        //1 列表2 单页，3 外连接，4 封面
                        $('#categorys_list').attr('src',data.href).show();
                        $('.layui-col-md10 .layui-card-body').removeClass('layui-hide');

                    }
                }
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