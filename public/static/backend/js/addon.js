define(['jquery', 'table', 'form', 'md5','upload'], function ($, Table, Form, Md5,Upload) {
    //表格重载失效的问题解决方案
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'addon/index',
                    install_url: 'addon/install',
                    uninstall_url: 'addon/uninstall',
                    config_url: 'addon/config',
                    modify_url: 'addon/modify',
                    logout_url: 'addon/logout',
                    localinstall:{
                        type: 'upload',
                        class: 'layui-btn-sm layui-btn-normal',
                        url: 'addon/localinstall',
                        icon: 'layui-icon layui-icon-upload-drag',
                        text: __('Local Install'),
                        title: __('Local Install'),
                        extend:"id='localinstall' data-callback='importFile'",
                    },
                    plugins:{
                        type: 'href',
                        class: 'layui-btn-sm layui-btn-normal',
                        url: 'https://www.funadmin.com/frontend/plugins',
                        icon: 'layui-icon layui-icon-app',
                        text: __('plugins'),
                        title: __('plugins'),
                        extend:"id='plugins' ",
                    },create:{
                        type: 'open',
                        class: 'layui-btn-sm layui-btn-normal',
                        url: 'addon/add',
                        icon: 'layui-icon layui-icon-addition',
                        text: __('Create'),
                        title: __('Create'),
                        extend:"id='Create' ",
                    },account:{
                        type: 'account',
                        class: 'layui-btn-sm layui-btn-normal',
                        url: 'addon/add',
                        icon: 'layui-icon layui-icon-user',
                        text: __('Account'),
                        title: __('Account'),
                        node:false,
                        extend:"data-callback='AccountClick' id='account'",
                    }
                },
            }
            importFile = function(obj){
                $('#importFile').click();
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.table_render_id,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh','localinstall','plugins','create','account'],
                searchInput:true,
                searchName:'name',
                search: true,
                show:false,
                cols: [[
                    {checkbox: true,},
                    {
                        field: 'name',
                        title: __('ADDONAME'),
                        width: 150,
                        imageHeight: 40,
                        align: "center",
                        hide:true
                    },
                    {
                        field: 'title',
                        title: __('Title'),
                        // width: 150,
                        templet: function (d){
                            if(d.website){
                                return '<a class="layui-btn-xs layui-btn layui-btn-normal" target="_blank" href="'+d.website+'">'+d.title+'</a>';
                            }else{
                                return d.title;
                            }
                        }
                    },
                    {
                        field: 'thumb',
                        title: __('Logo'),
                        width: 100,
                        imageHeight: 40,
                        search: false,
                        align: "center",
                        templet: Table.templet.image
                    },
                    {field: 'description', title: __('Description'), minWidth: 220, },
                    {
                        field: 'version', title: __('Addon version'), width: 160, search: false,
                        templet: function (d) {
                            return d['pluginsVersion'] ? d['pluginsVersion'] ['0']['version'] : d.version;
                        }
                    },
                    // {field: 'requires', title: __('Addon require'), width: 160, sort: true, search: false},
                    {field: 'author', title: __('Author'), width: 120,},
                    {field: 'general_price', title: __('Price'), width: 120,search: false,
                        templet: function (d){
                            if(d.general_price>0){
                                return '<span class="layui-badge">￥'+d.general_price+'</span>';
                            }else{
                                return '<span class="layui-badge layui-bg-blue">免费</span>';
                            }
                        }
                    },
                    {field: 'download', title: __('download'), width: 120,search: false},
                    {field: 'publish_time', title: __('Publishtime'), width: 180, search: false,templet:Table.templet.time},
                    {
                        minwidth: 250, align: 'center', init: Table.init, templet: function (d) {
                            var html = '';
                            if (d.install && d.install == 1 ) {
                                if(d.lastVersion > d.localVersion){
                                    html += "<a data-auth='"+auth+"' href='javascript:;' " +
                                        "class='layui-btn layui-btn-normal layui-btn-xs '"   +
                                        "title='+__('upgrade')+'  data-value='" +JSON.stringify(d.pluginsVersion)+"' lay-event='more' " +
                                        'data-url="' + Table.init.requests.install_url + '?name=' + d.name+'&plugins_id='+d.plugins_id  + '&id=' + d.id + '">' +
                                        __('Upgrade')+"</a>";
                                }
                                html += '<a  data-auth="'+auth+'" href="javascript:;" class="layui-btn  layui-btn-xs"  lay-event="open"  title="'+__('Config')+'" data-url="' + Table.init.requests.config_url + '?name=' + d.name + '&id=' + d.id + '">'+__('Config')+'</a>'
                                if (d.status == 1 ) {
                                    html += '<a lastversion="'+d.lastVersion  +'" localversion="'+ d.localVersion+'" data-auth="'+auth+'" class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status"  title="'+__('enabled')+'" data-text="disable" data-url="' + Table.init.requests.modify_url + '?name=' + d.name + '&id=' + d.id + '">'+__('Enabled')+'</a>'
                                } else {
                                    html += '<a data-auth="'+auth+'" class="layui-btn layui-btn-xs layui-btn-warm" lay-event="status"   title="'+__('disabled')+'" data-text="enable" data-url="' + Table.init.requests.modify_url + '?name=' + d.name + '&id=' + d.id + '">'+__('Disabled')+'</a>'
                                }
                                html += '<a data-auth="'+auth+'" href="javascript:;" class="layui-btn layui-btn-danger layui-btn-xs"  lay-event="uninstall" title="'+__('uninstall')+'"   data-url="' + Table.init.requests.uninstall_url + '?name=' + d.name +'&version_id='+d.version_id +  '&id=' + d.id + '">'+__('uninstall')+'</a>'
                                if (d.website !== '') {
                                    html += '<a  data-auth="'+auth+'" href="' + d.website + '"  target="_blank" class="layui-btn  layui-btn-xs">demo</a>';
                                }
                                if(d.web){
                                    html+="<a data-auth=\"'+auth+'\" class=\"layui-btn  layui-btn-xs layui-btn-normal\" target='_blank' href='"+d.web+"'>前台</a>"
                                }
                            } else {
                                if(d.hasOwnProperty('kinds') && d.kinds==10){
                                    html+="<a class=\"layui-btn  layui-btn-xs layui-btn-normal\" target='_blank' href='"+d.website+"'>点击了解</a>"
                                }else{
                                    html += '<a data-auth="'+auth+'" href="javascript:;" class="layui-btn layui-btn-danger layui-btn-xs"  title="'+__('install')+'" lay-event="install" data-url="' + Table.init.requests.install_url + '?name=' + d.name+'&plugins_id='+d.plugins_id  +'&version_id='+d.version_id + '&id=' + d.id + '">'+__('install')+'</a>'
                                }
                            }
                            return html;
                        }
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: false
            });
            Table.api.bindEvent(Table.init.tableId);
            layui.table.on('tool(' + Table.init.table_elem + ')', function (obj) {
                var url = $(this).data('url'),auth = $(this).data('auth');
                url = Fun.url(url);var event = obj.event;
                if(event ==='open'){ return this.call();}
                if (event === 'install') {
                    if (auth) {
                        Fun.toastr.confirm(__('Are you sure you want to install it'), function () {
                            let index = layer.load();
                            Fun.ajax({
                                url: url,
                            }, function (res) {
                                Fun.toastr.success(res.msg, function () {
                                    Fun.refreshmenu();
                                    Fun.toastr.close(index)
                                    layui.table.reloadData(Table.init.tableId);
                                });
                            },function (res) {
                                Fun.toastr.error(res.msg, function () {
                                    Fun.toastr.close(index)
                                    layui.table.reloadData(Table.init.tableId);
                                });
                                if(res.url){
                                    layui.layer.open({
                                        content:res.url,
                                        title: '立即支付',
                                        shadeClose: true,
                                        shade: 0.8,
                                        resize:true,
                                        maxmin:true,
                                        area:['650px','680px'],
                                        type:2,
                                    })
                                }
                            })
                        });
                    } else {
                        layer.open({
                            type: 1,
                            shadeClose: true,
                            content: $("#login_tpl").html(),
                            zIndex: 9999,
                            area: ['450px', '350px'],
                            title: [__('Login In ') + 'FunAdmin', 'text-align:center'],
                            resize: false,
                            btnAlign: 'c',
                            btn: [__('Login'),__('Register')],
                            yes: function (index, layero) {
                                var url = Fun.url(Table.init.requests.index_url);
                                var data = {
                                    username: $("#inputUsername", layero).val(),
                                    password: $("#inputPassword", layero).val(),
                                };
                                if (!data.username || !data.password) {
                                    Fun.toastr.error(__('Account Or Password Cannot Empty'));
                                    return false;
                                }
                                $.ajax({
                                    url: url, type: 'post', data: data, dataType: "json", success: function (res) {
                                        if (res.code === 1) {
                                            Fun.toastr.success(res.msg, layer.closeAll());
                                            location.reload();
                                        } else {
                                            Fun.toastr.alert(res.msg);
                                        }
                                    }, error: function (res) {
                                        Fun.toastr.error(res.msg)
                                    }
                                })
                            },
                            btn2: function () {
                                Fun.api.close();
                                return false;
                            },
                            success: function (layero, index) {
                                $(".layui-layer-btn1", layero).prop("href", "http://www.funadmin.com/frontend/login/index.html").prop("target", "_blank");
                            },
                            end: function () {
                                $("#login").hide();
                            },
                        });
                    }
                }
                if (event === 'uninstall') {
                    Fun.toastr.confirm(__('Are you sure you want to uninstall it'), function () {
                        Fun.ajax({
                            url: url,
                            method: 'post'
                        }, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                Fun.refreshmenu();
                                layui.table.reloadData(Table.init.tableId);

                            });
                        },function(res){
                            Fun.toastr.error(res.msg)
                            layui.table.reloadData(Table.init.tableId);
                        })
                    });
                }
                if (event === 'status') {
                    Fun.toastr.confirm(__('Are you sure you want to change it'), function () {
                        Fun.ajax({
                            url: url,
                        }, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                Fun.refreshmenu();
                                layui.table.reloadData(Table.init.tableId);
                                Fun.toastr.close()
                            });
                        })
                    });
                }
                if(event === 'more') {
                    if (auth) {
                        //更多下拉菜单
                        jsondata = $(this).data('value');
                        for(i=0;i<jsondata.length;i++){
                            jsondata[i]['title'] = __('Upgrade')+ jsondata[i]['version'];
                        }
                        layui.dropdown.render({
                            elem: this
                            ,show: true //外部事件触发即显示
                            ,data: jsondata
                            ,click: function(data, othis){
                                Fun.toastr.confirm(__('Please backup your data before upgrading!!!'), function () {
                                    Fun.ajax({
                                        url: url+"&version_id="+data.id +'&type=upgrade',
                                    }, function (res) {
                                        Fun.toastr.success(res.msg, function () {
                                            Fun.refreshmenu();
                                            layui.table.reloadData(Table.init.tableId);
                                            Fun.toastr.close()
                                        });
                                    })
                                });
                            }
                            ,align: 'right' //右对齐弹出（v2.6.8 新增）
                            ,style: 'box-shadow: 1px 1px 10px rgb(0 0 0 / 12%);' //设置额外样式
                        });                    } else {
                        layer.open({
                            type: 1,
                            shadeClose: true,
                            content: $("#login_tpl").html(),
                            zIndex: 9999,
                            area: ['450px', '350px'],
                            title: [__('Login In ') + 'FunAdmin', 'text-align:center'],
                            resize: false,
                            btnAlign: 'c',
                            btn: [__('Login'),__('Register')],
                            yes: function (index, layero) {
                                var url = Fun.url(Table.init.requests.index_url);
                                var data = {
                                    username: $("#inputUsername", layero).val(),
                                    password: $("#inputPassword", layero).val(),
                                };
                                if (!data.username || !data.password) {
                                    Fun.toastr.error(__('Account Or Password Cannot Empty'));
                                    return false;
                                }
                                $.ajax({
                                    url: url, type: 'post', data: data, dataType: "json", success: function (res) {
                                        if (res.code === 1) {
                                            Fun.api.setStorage('funadmin_memberinfo',res.data);
                                            Fun.toastr.success(res.msg, layer.closeAll());
                                            location.reload();
                                        } else {
                                            Fun.toastr.alert(res.msg);
                                        }
                                    }, error: function (res) {
                                        Fun.toastr.error(res.msg)
                                    }
                                })
                            },
                            btn2: function () {
                                Fun.api.close();
                                return false;
                            },
                            success: function (layero, index) {
                                $(".layui-layer-btn1", layero).prop("href", "http://www.funadmin.com/frontend/login/index.html").prop("target", "_blank");
                            },
                            end: function () {
                                $("#login").hide();
                            },
                        });
                    }
                }
                return false;
            })
            //指定允许上传的文件类型
            var uploadinit = layui.upload.render({
                elem: '#importFile'
                ,url: Fun.url(Upload.init.requests.upload_url)+'?save=1&path=addon' //改成您自己的上传接口
                ,accept: 'file' //普通文件
                ,exts: 'zip|rar|7z' //只允许上传压缩文件
                ,size:1024*50
                ,before: function(obj){ //obj参数包含的信息，跟 choose回调完全一致，可参见上文。
                    layer.load(); //上传loading
                }
                ,done: function(res){
                    if(res.code<=0){
                        Fun.toastr.error(res.msg);
                        return false;
                    }
                    var load = layer.load();
                    Fun.ajax({
                        url:Table.init.requests.localinstall.url,
                        data:{url:res.url}
                    },function (res){
                        if(res.code==1){
                            Fun.toastr.success(res.msg);
                        }else{
                            Fun.toastr.error(res.msg);
                        }
                        uploadinit.reload({  elem: '#localinstall'});
                        Table.api.reload();//渲染表格点击无效无效
                        layui.layer.close(load)
                        // 重载该实例，支持重载全部基础参数
                    },function (res) {
                        Fun.toastr.error(res.msg);
                        Fun.toastr.close(load)
                    })
                }
            });
            AccountClick = function(e){
                console.log()
                let funadmin_memberinfo =  Fun.api.getStorage('funadmin_memberinfo')
                console.log(funadmin_memberinfo)
                if(typeof funadmin_memberinfo !=='undefined' && funadmin_memberinfo!=''){
                    layer.open({
                        type: 1,
                        shadeClose: true,
                        content: $("#memberinfo_tpl").html(),
                        zIndex: 9999,
                        area: ['450px', '350px'],
                        title: [__('Member Info') + 'FunAdmin', 'text-align:center'],
                        resize: false,
                        btnAlign: 'c',
                        btn: [__('Logout'),__('Register')],
                        yes: function (index, layero) {
                            var url = Fun.url(Table.init.requests.logout_url);
                            $.ajax({
                                url: url, type: 'post', dataType: "json", success: function (res) {
                                    if (res.code === 1) {
                                        Fun.api.setStorage('funadmin_memberinfo','');
                                        Fun.toastr.success(res.msg, layer.closeAll());
                                        location.reload();
                                    } else {
                                        Fun.toastr.alert(res.msg);
                                    }
                                }, error: function (res) {
                                    Fun.toastr.error(res.msg)
                                }
                            })
                        },
                        btn2: function () {
                            Fun.api.close();
                            return false;
                        },
                        success: function (layero, index) {
                            $(".layui-layer-btn1", layero).prop("href", "http://www.funadmin.com/frontend/login/index.html").prop("target", "_blank");
                        },
                        end: function () {
                            $("#login").hide();
                        },
                    });
                }else{
                    layer.open({
                        type: 1,
                        shadeClose: true,
                        content: $("#login_tpl").html(),
                        zIndex: 9999,
                        area: ['450px', '350px'],
                        title: [__('Login In ') + 'FunAdmin', 'text-align:center'],
                        resize: false,
                        btnAlign: 'c',
                        btn: [__('Login'),__('Register')],
                        yes: function (index, layero) {
                            var url = Fun.url(Table.init.requests.index_url);
                            var data = {
                                username: $("#inputUsername", layero).val(),
                                password: $("#inputPassword", layero).val(),
                            };
                            if (!data.username || !data.password) {
                                Fun.toastr.error(__('Account Or Password Cannot Empty'));
                                return false;
                            }
                            $.ajax({
                                url: url, type: 'post', data: data, dataType: "json", success: function (res) {
                                    if (res.code === 1) {
                                        Fun.api.setStorage('funadmin_memberinfo',res.data);
                                        Fun.toastr.success(res.msg, layer.closeAll());
                                        location.reload();
                                    } else {
                                        Fun.toastr.alert(res.msg);
                                    }
                                }, error: function (res) {
                                    Fun.toastr.error(res.msg)
                                }
                            })
                        },
                        btn2: function () {
                            Fun.api.close();
                            return false;
                        },
                        success: function (layero, index) {
                            $(".layui-layer-btn1", layero).prop("href", "http://www.funadmin.com/frontend/login/index.html").prop("target", "_blank");
                        },
                        end: function () {
                            $("#login").hide();
                        },
                    });
                }
            }
        },
        config: function () {
            Controller.api.bindevent()
        },
        add:function (){
            Controller.api.bindevent()
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            },
        },

    };
    return Controller;
});
