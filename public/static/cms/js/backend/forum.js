define(['table','form'], function (Table,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests:{
            index_url: 'backend.Forum/index',
            add_url: 'backend.Forum/add',
            edit_url: 'backend.Forum/edit',
            delete_url: 'backend.Forum/delete',
            modify_url: 'backend.Forum/modify',
            destroy_url: 'backend.Forum/destroy',
            recycle_url: 'backend.Forum/recycle',
            restore_url: 'backend.Forum/restore',
            flashCache:{
                type: 'request',
                url:'backend.Forum/flashCache',
                class: 'layui-btn-sm layui-btn-warm',
                icon: 'layui-icon layui-icon-fonts-clear',
                title: __('flashCache'),
                text: __('flashCache'),
                full: 1,
            },
            add_full: {
                type: 'open',
                url:'backend.Forum/add',
                class: 'layui-btn-sm layui-btn-green',
                icon: 'layui-icon layui-icon-add-circle-fine',
                title: __('Add'),
                text: __('Add'),
                full: 1,
                extend:'id="cateadd"'

            },
        }
    }
    let Controller = {
        index: function () {
            var $ = layui.jquery,tree = layui.tree?layui.tree:parent.layui.tree;
            var parent_frame_height = parent.layui.$("#homePage").height()-80;
            $(window).on('resize', function () {
                setTimeout(function () {
                    parent_frame_height = parent.layui.$(".layui-card-body").height();
                    frameheight();
                }, 100);
            });
            function frameheight() {
                $("#categorys_list").height(parent_frame_height);
            }
            (function () {
                frameheight();
            })();
            var tableObj = Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','flashCache','add_full','destory','recycle'],
                cols: [[
                    {checkbox: true},
                    {field: 'id', title: 'ID', width: 80, },
                    {field: 'category.catename', title: __('Cate'), width: 80,templet:Table.templet.resolution},
                    {field: 'title', title: __('Title'), width: 150,},
                    {field: 'thumb', title: __('thumb'), width: 150,templet:Table.templet.image},
                    {field: 'clicks', title:  __('clicks'), width: 80},
                    {field: 'is_read', title:  __('Read'), width: 80},
                    {field: 'status', title: __('Status'),filter: 'status',width:100,templet: Table.templet.switch},
                    {field: 'sort', title:  __('sort'), width: 80,edit:true},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true,},
                    {
                        align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['edit','destory','delete']
                    }
                ]],
                done: function(res){
                },
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
                // ,showChe checkbox: true
                ,click: function(obj){
                    var data = obj.data;  //获取当前点击的节点数据
                    var type = parseInt(data.type)
                    if(type===1 ||  type===3 || type===4){
                        $('.table').removeClass('layui-hide');
                        //1 列表2 单页，3 外连接，4 封面
                        $('.layui-col-md10 .layui-card-body').addClass('layui-hide');
                        tableObj.reload({ page: {page: 1},where: {'cateid': data.id}});
                        var url = $('#cateadd').attr('data-url');
                        $('#cateadd').attr('data-url',url+'?cateid='+data.id);
                    }else if(type===2 ){
                        $('.table').hide();
                        //1 列表2 单页，3 外连接，4 封面
                        $('#categorys_list').prop('src',data.href).show();
                        $('.layui-col-md10 .layui-card-body').removeClass('layui-hide');
                    }
                }
            });
            let table = $('#'+Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        recycle: function () {
            var $ = layui.jquery,tree = layui.tree?layui.tree:parent.layui.tree;
            var parent_frame_height = parent.layui.$(".fun-main").height()-80;
            $(window).on('resize', function () {
                setTimeout(function () {
                    parent_frame_height = parent.layui.$(".fun-main").height();
                    frameheight();
                }, 100);
            });
            function frameheight() {
                $("#categorys_list").height(parent_frame_height);
            }
            (function () {
                frameheight();
            })();
            var tableObj = Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.recycle_url),
                init: Table.init,
                toolbar: ['refresh','delete','restore'],
                cols: [[
                    {checkbox: true},
                    {field: 'id', title: 'ID', width: 80, },
                    {field: 'category.catename', title: __('Cate'), width: 80,templet:Table.templet.resolution},
                    {field: 'title', title: __('Title'), width: 150,},
                    {field: 'thumb', title: __('thumb'), width: 150,templet:Table.templet.image},
                    {field: 'clicks', title:  __('clicks'), width: 80},
                    {field: 'is_read', title:  __('Read'), width: 80},
                    {field: 'status', title: __('Status'),filter: 'status',width:100,templet: Table.templet.switch},
                    {field: 'sort', title:  __('sort'), width: 80,edit:true},
                    {field: 'create_time', title: __('Createtime'), width: 180, sort: true,},
                    {
                        align: 'center', title: __('Operat'), init: Table.init,
                        templet : Table.templet.operat, operat: ['delete','restore']
                    }
                ]],
                done: function(res){
                },
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
                // ,showChe checkbox: true
                ,click: function(obj){
                    var data = obj.data;  //获取当前点击的节点数据
                    var type = parseInt(data.type)
                    if(type===1 ||  type===3 || type===4){
                        $('.table').removeClass('layui-hide');
                        //1 列表2 单页，3 外连接，4 封面
                        $('.layui-col-md10 .layui-card-body').addClass('layui-hide');
                        tableObj.reload({ page: {page: 1},where: {'cateid': data.id}});
                        var url = $('a[lay-event="open"]').attr('data-url');
                        $('a[lay-event="open"]').attr('data-url',url+'?cateid='+data.id);
                    }else if(type===2 ){
                        $('.table').addClass('layui-hide');
                        //1 列表2 单页，3 外连接，4 封面
                        $('#categorys_list').prop('src',data.href).show();
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
            var form = layui.form?layui.form:parent.layui.form;
            form.on('select(category)',function(data){
                var id = data.value;
                $.get('getfield?id='+id,function(res){
                    $('.field').html(res);
                    Form.api.bindEvent($('form'))
                });
            })
            Controller.api.bindevent()
        },
        edit: function () {
            var form = layui.form?layui.form:parent.layui.form;
            form.on('select(category)',function(data){
                con
                var id = data.value;
                console.log(111);
                $.get('getfield?id='+id,function(res){
                    $('.field').html(res);
                    Form.api.bindEvent($('form'))
                });
            })
            Controller.api.bindevent()
        },
        board:function(){

        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});