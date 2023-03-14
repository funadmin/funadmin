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
                toolbar: ['refresh', 'add_full', 'delete'],
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
                            } else if (d.mime === 'application/msexcel' || d.mime === 'application/mspowerpoint' || d.mime === 'application/msword') {
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
            var tree = layui.eleTree({
                el: '#tree',data: treeData,//静态数据,
                emptText:"",//当数据为空时显示的内容
                highlightCurrent:true,//是否高亮当前选中节点
                defaultExpandAll:true,//是否默认展开所有节点
                radioOnClickNode:true,//单选框是否在点击文本的时候选中节点
                checkOnClickNode:true,//单选框是否在点击文本的时候选中节点
                showLine:true,
                indent:21,
                radioType: "level", // all      // 单选范围（是同一级还是整体只能选择一个）
                rightMenuList: [
                    // "copy",
                    // "paste",
                    // "paste_before",
                    // "paste_after",
                    // "cut_paste",
                    "edit", "remove",
                    "add_child",
                    "add_before",
                    "add_after"
                ],
                customText: function(data) {
                    var s = data.title;s+=`<i style="" class="layui-icon layui-icon-ok tree-choose"></i>`;
                    return s;
                },
                imgUrl:PLUGINS+"/lay-module/eletree/images/",
                icon: {
                    fold: "fold.png",
                    leaf: "fold.png",
                    // leaf: "leaf.png",
                },defaultRadioCheckedKeys:[group_id],
                draggable:false,showCheckbox: showCheckbox?true:false,showRadio: showRadio?true:false,
                request: {          // 对于后台数据重新定义名字
                    name: "title",
                    key: "id",
                    children: "children",
                    disabled: "disabled",       // 被禁用的节点不会影响父子节点的选中状态
                    checked: "checked",
                    isOpen: "isOpen",
                    isLeaf: "isLeaf",
                    pid: "pid",
                    radioChecked: "radioChecked",
                    radioDisabled: "radioDisabled"
                },
            })
            tree.setRadioChecked([group_id])
            var selectTree = layui.eleTree({
                el: '#selectTree',data: treeData,//静态数据,
                emptText:"",//当数据为空时显示的内容
                highlightCurrent:true,//是否高亮当前选中节点
                defaultExpandAll:true,//是否默认展开所有节点
                radioOnClickNode:true,//单选框是否在点击文本的时候选中节点
                checkOnClickNode:true,//单选框是否在点击文本的时候选中节点
                showLine:true,
                indent:21,
                radioType: "level", // all      // 单选范围（是同一级还是整体只能选择一个）
                imgUrl:PLUGINS+"/lay-module/eleTree/images/",
                customText: function(data) {
                    var s = data.title;s+=`<i style="" class="layui-icon layui-icon-ok tree-choose"></i>`;
                    return s;
                },
                icon: {
                    fold: "fold.png",
                    leaf: "fold.png",
                    // leaf: "leaf.png",
                },defaultRadioCheckedKeys:[group_id],
                draggable:false,showCheckbox: showCheckbox?true:false,showRadio: showRadio?true:false,
                request: {          // 对于后台数据重新定义名字
                    name: "title",
                    key: "id",
                    children: "children",
                    disabled: "disabled",       // 被禁用的节点不会影响父子节点的选中状态
                    checked: "checked",
                    isOpen: "isOpen",
                    isLeaf: "isLeaf",
                    pid: "pid",
                    radioChecked: "radioChecked",
                    radioDisabled: "radioDisabled"
                },
            })
            selectTree.on("click", function(data) {
                if(this.target.classList.contains('tree-choose')){
                    var _this = $(this);
                    var ids = fileSelect(_this,2);
                    $('.group-select').removeClass('active');
                    if (ids.length === 0) {
                        Fun.toastr.error(__('please choose data'))
                        return false;
                    }
                    if(data.data.id===0){
                        Fun.toastr.error(__('please choose group'))
                        return false;
                    }
                    var Url =Fun.url(Table.init.requests.move_url);
                    var data = {
                        ids: ids, '__token__': $("input[name='__token__']").val(),
                        group_id:data.data.id?data.data.id:1,
                    };
                    Fun.ajax({url:Url, data:data}, function (res) {
                        if (res.code >0 ) {
                            $('.box-body .file-list-item li.active').remove();
                            Fun.toastr.success(res.msg);
                        } else {
                            Fun.toastr.error(res.msg);
                        }
                    });
                }

            })
            // 如果不写下面的事件，则默认自动执行此操作
            tree.on("click", function(data) {
                if(this.target.classList.contains('eleTree-title') || this.target.classList.contains('tree-choose')) {
                    var url = window.location.href;
                    url  = url.indexOf('?')!==-1?url+"&group_id="+data.data.id :url+'?group_id='+data.data.id
                    location.href = url;
                }
            }).on("edit", function(data) {
                setTimeout(function() {
                    data.load({
                        checked: true
                    })
                }, 100)
                var postdata = {
                    id:data.data.id,
                    title:data.data.title,
                    '__token__': $("input[name='__token__']").val()
                }
                Fun.ajax({url: Table.init.requests.group_edit_url, data: postdata}, function (res) {
                    if (res.code > 0) {
                        Fun.toastr.success(res.msg);
                    }
                },function (res){
                    $('.eleTree-title-active').find('.eleTree-text').text(data.rightClickData.title);
                })
            }).on("remove", function(data) {
                setTimeout(data.load, 100)
                var confirm = Fun.toastr.confirm(__('Are you sure delete？'), function () {
                    Fun.ajax({url: Table.init.requests.group_delete_url, data: {ids: data.data.id}}
                        , function (res) {
                            if (res.code > 0) {
                                Fun.toastr.success(res.msg);
                                tree.remove(data.data.id)
                            }
                        })
                });
            }).on("add_child", function(data) {
                setTimeout(data.load, 100)
                var postdata = {
                    pid:data.rightClickData.id,
                    title:data.data.title,
                    '__token__': $("input[name='__token__']").val()
                }
                Fun.ajax({url: Table.init.requests.group_add_url, data:postdata }
                    , function (res) {
                        if (res.code > 0) {
                            Fun.toastr.success(res.msg);
                        }
                    })
            }).on("add_before", function(data) {
                setTimeout(data.load, 100)
                var postdata = {
                    pid:0,
                    title:data.data.title,
                    sort:data.rightClickData.sort - 1,
                    '__token__': $("input[name='__token__']").val()
                };Fun.ajax({url: Table.init.requests.group_add_url, data:postdata }
                    , function (res) {
                        if (res.code > 0) {
                            Fun.toastr.success(res.msg);
                        }
                    })
            }).on("add_after", function(data) {
                setTimeout(data.load, 100)
                var postdata = {
                    pid:0,
                    title:data.data.title,
                    sort:data.rightClickData.sort + 1,
                    '__token__': $("input[name='__token__']").val()
                }
                Fun.ajax({url: Table.init.requests.group_add_url, data:postdata }
                    , function (res) {
                        if (res.code > 0) {
                            Fun.toastr.success(res.msg);
                        }
                    })
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