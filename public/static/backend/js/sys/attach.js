define(['jquery','table','upload','form'], function (undefined,Table,Upload,Form) {
    Table.init = {
        table_elem: 'list',
        tableId: 'list',
        requests: {
            index_url: 'sys.attach/index',
            // add_url: 'sys.attach/add',
            add_full:{
                type: 'open',
                class: 'layui-btn-sm layui-btn-green',
                url: 'sys.attach/add',
                icon: 'layui-icon layui-icon-add',
                text: __('Add'),
                title: __('Add'),
                full: 0,
                extend:"data-btn='close'",
            },
            edit_url: 'sys.attach/edit',
            delete_url: 'sys.attach/delete',
            move_url: 'sys.attach/move',
            modify_url: 'sys.attach/modify',
            group_index_url:{
                type: 'open',
                class: 'layui-btn-sm layui-btn-green',
                url: 'sys.attachGroup/index',
                icon: 'layui-icon layui-icon-list1',
                text: __('Group List'),
                title: __('Group List'),
                node:false,
                extend:"data-btn='false'",
            },
            group_add_url: 'sys.attachGroup/add',
            group_edit_url: 'sys.attachGroup/edit',
            group_delete_url: 'sys.attachGroup/delete',
        }
    }

    let Controller = {
        index: function () {
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.tableId,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh', 'add_full', 'delete','group_index_url'],
                cols: [[
                    {checkbox: true, },
                    {field: 'id', title: 'ID', width: 80 , sort: true},
                    {field: 'name', title: __('Name'), minWidth: 120, sort: true},
                    {field: 'original_name', title: __('OriginalName'), minWidth: 180, sort: true},
                    {
                        field: 'mime', title: __('FileTye'), width: 120, sort: true, templet: function (d) {
                            let html = '';
                            if (d.mime.indexOf('image') >=0) {
                                html += '<img src="' + STATIC + '/backend/images/filetype/image.jpg" alt="' + __('Image') + '" width="50">'
                            } else if (d.mime === 'application/pdf') {
                                html += '<img src="' + STATIC + '/backend/images/filetype/pdf.jpg" alt="' + __('Pdf') + '" width="50">'
                            } else if (d.mime === 'application/zip') {
                                html += '<img src="' + STATIC + '/backend/images/filetype/zip.jpg" alt="' + __('Zip') + '" width="50">'
                            //} else if (d.mime === 'application/msexcel' || d.mime === 'application/mspowerpoint' || d.mime === 'application/msword') {
                            } else if ( d.mime.indexOf("application/vnd.ms") != -1 || d.mime.indexOf("application/vnd.openxmlformats-officedocument") != -1) {
                                html += '<img src="' + STATIC + '/backend/images/filetype/office.jpg" alt="' + __('Office') + '" width="50">'
                            } else {
                                html += '<img src="' + STATIC + '/backend/images/filetype/file.jpg" alt="' + __('File') + '" width="50">'
                            }
                            return html;

                        }
                    },
                    {field: 'path', title: __('Path'), width: 80, sort: true,},
                    {field: 'ext', title: __('Ext'), width: 120, sort: true},
                    {field: 'size', title: __('Size(K)'), width: 80, sort: true},
                    {field: 'driver', title: __('Driver'), width: 80, sort: true},
                    {
                        field: 'status',
                        title: __('Status'),
                        width: 180,
                        filter: 'status',
                        templet: Table.templet.switch,
                        sort: true,
                        search: 'select',
                        selectList: {0: __('Disabled'), 1: __('Enabled')},
                    },
                    {
                        field: 'create_time',
                        title: __('CreateTime'),
                        width: 180,
                        templet: Table.templet.time,
                        search: 'range'
                    },
                    {
                        width: 100,
                        align: 'center',
                        title: __('Operat'),
                        init: Table.init,
                        templet: Table.templet.operat,
                        operat: ['delete']
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });

            Table.api.bindEvent(Table.init.tableId);
        },
        add: function () {
            Controller.api.bindevent()
        },
        selectfiles:function(){
            var  index = 0,group_id = param.group_id?param.group_id:0;
            showCheckbox = $('#tree').data('checkbox')
            showRadio = $('#tree').data('radio')
            //选择文件
            function fileSelect(othis,type=1) {
                if(type==1 && othis.parent('li').length==1){
                    var id = othis.parent('li').data('id')
                    $ids = [id] ;
                    return $ids;
                }
                var li = $('.box-body .file-list-item li.active');
                var ids = [];
                if(li.length ===0 && type===1){
                    return ids;
                }
                li.each(function () {
                    ids.push($(this).attr('data-id'));
                });
                return ids;
            }
            var tree = layui.tree.render({
                elem: '#tree',
                data: treeData,//静态数据,
                showCheckbox: false,
                showLine: true , // 是否开启连接线
                accordion:true,//是否开启手风琴模式
                // isJump:true,
                edit: ['add', 'update', 'del'] ,// 开启节点的右侧操作图标
                click: function(obj){//节点被点击的回调函数。返回的参数如下：
                    var url = window.location.href;
                    url  = url.indexOf('?')!==-1?url+"&group_id="+obj.data.id :url+'?group_id='+obj.data.id
                    location.href = url;
                },
                operate: function(obj){
                    var type = obj.type; // 得到操作类型：add、edit、del
                    var elem = obj.elem; // 得到当前节点元素
                    var data =  obj.data; // 得到当前节点元素
                    // Ajax 操作
                    var id = data.id; // 得到节点索引
                    if(type == 'add'){ // 增加节点
                        var postdata = {
                            pid:obj.data.id,
                            title:elem.find('.layui-tree-txt').html(),
                            '__token__': $("input[name='__token__']").val()
                        }
                        Fun.ajax({url: Table.init.requests.group_add_url, data:postdata }
                            , function (res) {
                                if (res.code > 0) {
                                    Fun.toastr.success(res.msg);
                                    window.location.reload()
                                }
                        })
                    } else if(type == 'update'){ // 修改节点
                        console.log(obj)
                        var postdata = {
                            id:obj.data.id,
                            title:elem.find('.layui-tree-txt').html(),
                            '__token__': $("input[name='__token__']").val()
                        }
                        Fun.ajax({url: Table.init.requests.group_edit_url, data: postdata}, function (res) {
                            if (res.code > 0) {
                                Fun.toastr.success(res.msg);
                            }
                        },function (res){
                        })
                        console.log(elem.find('.layui-tree-txt').html()); // 得到修改后的内容
                    } else if(type == 'del'){ // 删除节点
                        Fun.ajax({url: Table.init.requests.group_delete_url, data: {ids: obj.data.id}}
                        , function (res) {
                            if (res.code > 0) {
                                Fun.toastr.success(res.msg);
                            }
                        })
                    };
                }

            })
            var selectTree = layui.tree.render({
                elem: '#selectTree',
                data: treeData,//静态数据,
                showCheckbox: false,
                showLine: true , // 是否开启连接线
                accordion:true,//是否开启手风琴模式
                click: function(obj){//节点被点击的回调函数。返回的参数如下：
                    console.log(obj.data); // 得到当前点击的节点数据
                    console.log(obj.state); // 得到当前节点的展开状态：open、close、normal
                    console.log(obj.elem); // 得到当前节点元素
                    var _this = $(obj.elem);
                    var ids = fileSelect(_this,2);
                    $('.group-select').removeClass('active');
                    if (ids.length === 0) {
                        Fun.toastr.error(__('please choose data'))
                        return false;
                    }
                    if(obj.data.id===0){
                        Fun.toastr.error(__('please choose group'))
                        return false;
                    }
                    var Url =Fun.url(Table.init.requests.move_url);
                    var data = {
                        ids: ids, '__token__': $("input[name='__token__']").val(),
                        group_id:obj.data.id?obj.data.id:1,
                    };
                    Fun.ajax({url:Url, data:data}, function (res) {
                        if (res.code >0 ) {
                            $('.box-body .file-list-item li.active').remove();
                            Fun.toastr.success(res.msg);
                        } else {
                            Fun.toastr.error(res.msg);
                        }
                    });
                },
            })
            //分页
            layui.laypage.render({
                elem: 'page' //注意，这里的 test1 是 ID，不用加 # 号
                ,count: count //数据总数，从服务端得到,
                ,curr:param.page?param.page:1
                ,limit:param.limit?param.limit:12
                ,limits: [12,24,72,108,1000]
                // ,layout: ['prev', 'page', 'next', 'limit', 'refresh','count' ,'skip']
                ,layout: ['prev', 'page', 'next', 'limit','refresh']
                //跳转页码时调用
                , jump: function (data, first) { //obj为当前页的属性和方法，第一次加载first为true
                    //         do something
                    if (!first) {
                        var url = window.location.href;
                        url  = url.indexOf('?')!==-1?url+"&limit="+data.limit+"&page="+data.curr+'&group_id='+group_id :url+"?limit="+data.limit+'&page='+data.curr+'&group_id='+group_id
                        location.href = url;
                    }
                }
            });
            //多图片上传
            var upvalue = $('#uploadfile').data(),multiple = true,load;
            if(upvalue.value.num ===1){
                multiple= false;
            }
            layui.upload.render({
                elem: '#uploadfile'
                , url: Fun.url(Upload.init.requests.upload_url)
                , multiple: multiple
                , data: {group_id:group_id,path:upvalue.value.path}
                , before: function (obj) {
                    load = parent.layer.msg(__('uploading...'), {
                        icon: 1
                        , time: 0
                        , shade: [0.5, '#000', true]
                    });
                }
                , error: function (index) {
                    //console.info(index);
                    parent.layer.close(load);
                }
                , allDone: function (obj) { //当文件全部被提交后，才触发
                    parent.layer.close(load);
                }
                , done: function (res) { //console.info(res);
                    if (res.code >0) {
                        window.location.reload();
                    } else {
                        Fun.toastr.error(res.msg);
                        return false;
                    }
                }
            });
            layui.form.on('select(mime)', function(obj){
                var mime = obj.value,val = $.trim($('input.search').val()), url = location.href.split('?')[0];
                url = url+"?group_id="+group_id+'&original_name='+val+'&mime='+mime ;
                location.href = url;
                return false;
            })
            layui.form.on('submit(submit)', function(obj){
                var _this = $(this),val = $.trim($('input.search').val()),mime = obj.data.mime, url = location.href.split('?')[0];
                url = url+"?group_id="+group_id+'&original_name='+val+'&mime='+mime ;
                location.href = url;
                return false;
            })
            $(document).on('click','.file-delete',function () {
                var _this = $(this);
                var ids = fileSelect(_this);
                if (ids.length ==0) {
                    Fun.toastr.error(__('please choose data'))
                    return false;
                }
                var Url =Fun.url(Table.init.requests.delete_url);
                var data = {
                    ids: ids, '__token__': $("input[name='__token__']").val()
                };
                Fun.ajax({url:Url, data:data}, function (res) {
                    if (res.code >0 ) {
                        $('.box-body .file-list-item li.active').remove();
                        _this.parents('li').remove();
                        Fun.toastr.success(res.msg);
                    } else {
                        Fun.toastr.error(res.msg);
                    }
                });
            });
            //显示移动至
            $(document).on('click','.group-select .layui-btn',function () {
                var _this = $(this).parent();
                _this.toggleClass('active');
            });
            //图片编辑
            $('.box-body').delegate('.file-edit,.file-name', 'click', function () {
                var _this, id, name;_this = $(this).parent();id = _this.attr('data-id');name = _this.attr('title');
                layui.layer.prompt({title: '修改图片名称', value: name, formType: 3}, function (value, index) {
                    layui.layer.close(index);
                    var Url = Fun.url(Table.init.requests.edit_url+ '?id=' + id) ;
                    var data = {
                        'original_name': value,
                        'id': id,
                        '__token__': $("input[name='__token__']").val()
                    };
                    Fun.ajax({url:Url, data:data}, function (res) {
                        if (res.code >0) {
                            _this.attr('title', value)
                            _this.find('.file-name').html(value);
                            Fun.toastr.success(res.msg);
                        } else {
                            Fun.toastr.error(res.msg);
                        }
                    });
                });
            });
            //点击图片选中
            $('.box-body').delegate('.file-list-item li .img-cover,.file-list-item li .select-mask', 'click', function () {
                var _this = $(this).parent();
                var length =  _this.parents('ul').find('active').length;
                if(param.num!=='*' && param.num>1 &&　length>param.num){
                    Fun.toastr.error(__('num limit %s'),param.num);
                    return false;
                }
                _this.toggleClass('active');
                if (param.type==='radio') {
                    _this.siblings().removeClass('active');
                }
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))

            }
        },
    };
    return Controller;
});
