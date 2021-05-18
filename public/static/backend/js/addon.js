define(['jquery', 'table', 'form', 'md5'], function ($, Table, Form, Md5) {
    //时间戳
    function getTimestamp() {
        return Date.parse(new Date()) / 1000
    }
    //随机数
    function getNonce(len) {
        var $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnoprstuvwxyz123456789';
        var maxPos = $chars.length;
        var nonce = '';
        len = len || 8;
        for (i = 0; i < len; i++) {
            nonce += $chars.charAt(Math.floor(Math.random() * maxPos));
        }
        return nonce;
    }
    //获取签名
    function getSign(obj) {
        //先用Object内置类的keys方法获取要排序对象的属性名，再利用Array原型上的sort方法对获取的属性名进行排序，newkey是一个数组
        var newkey = Object.keys(obj).sort();
        var newObj = {}; //创建一个新的对象，用于存放排好序的键值对
        //排序
        for (var i = 0; i < newkey.length; i++) {
            //遍历newkey数组
            newObj[newkey[i]] = obj[newkey[i]];
            //向新创建的对象中按照排好的顺序依次增加键值对
        }
        var str = '';
        //拼接
        for (var key in newObj) {
            str += key + '=' + newObj[key] + '&';
        }
        str = str.substring(0, str.length - 1);
        return Md5(decodeURI(str)).toLowerCase();
    }
    //获取用户信息
    function getUserinfo() {
        var userinfo = localStorage.getItem("FunAdmin_userinfo");
        return userinfo ? JSON.parse(userinfo) : null;
    }
    //设置用户信息
    function setUserinfo(data) {
        if (data) {
            localStorage.setItem("FunAdmin_userinfo", JSON.stringify(data));
        } else {
            localStorage.removeItem("FunAdmin_userinfo");
        }
    }
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
                    // 配置
                    api_url: 'https://www.funadmin.com',   // 接口地址
                    login_url: '/api/v1.token/accessToken',   // 登陆地址获取token地址
                },
                searchinput:false,
                appid: 'funadmin',   // appid
                appsecret: '',   // appserct
            }
            Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.table_render_id,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh'],
                search: false,
                cols: [[
                    {checkbox: true,},
                    {
                        field: 'name',
                        title: __('Name'),
                        width: 100,
                        sort: true,
                        imageHeight: 40,
                        align: "center",
                    },
                    {
                        field: 'thumb',
                        title: __('Logo'),
                        width: 100,
                        sort: true,
                        imageHeight: 40,
                        align: "center",
                        templet: Table.templet.image
                    },
                    {
                        field: 'title',
                        title: __('Title'),
                        width: 120,
                        sort: true,templet: function (d){
                            if(d.url){
                                return '<a target="_blank" href="'+d.url+'">'+d.title+'</a>';
                            }else{
                                return d.title;
                            }
                        }
                    },
                    {field: 'description', title: __('Description'), minWidth: 220, sort: true,},
                    {field: 'version', title: __('Addon version'), width: 160, sort: true, search: false},
                    {field: 'requires', title: __('Addon require'), width: 160, sort: true, search: false},
                    {field: 'author', title: __('Author'), width: 120, sort: true},
                    {field: 'publish_time', title: __('Publishtime'), width: 180, search: false},
                    {
                        width: 250, align: 'center', init: Table.init, templet: function (d) {
                            var html = '';
                            if (d.install && d.install === 1 || d.install==='1') {
                                html += '<a href="javascript:;" class="layui-btn  layui-btn-xs"  lay-event="open"  title="'+__('Config')+'" data-url="' + Table.init.requests.config_url + '?name=' + d.name + '&id=' + d.id + '">config</a>'
                                if (d.status === 1 ||  d.status === '1') {
                                    html += '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="request"  title="'+__('disabled')+'" data-url="' + Table.init.requests.modify_url + '?name=' + d.name + '&id=' + d.id + '">已启用</a>'
                                } else {
                                    html += '<a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="request"   title="'+__('enabled')+'" data-url="' + Table.init.requests.modify_url + '?name=' + d.name + '&id=' + d.id + '">已禁用</a>'
                                }
                                html += '<a href="javascript:;" class="layui-btn layui-btn-danger layui-btn-xs"   title="'+__('uninstall')+'"lay-event="uninstall"  data-url="' + Table.init.requests.uninstall_url + '?name=' + d.name + '&id=' + d.id + '">uninstall</a>'
                            } else {
                                html += '<a href="javascript:;" class="layui-btn layui-btn-danger layui-btn-xs"  title="'+__('install')+'"  lay-event="install" data-url="' + Table.init.requests.install_url + '?name=' + d.name + '&id=' + d.id + '">install</a>'
                            }
                            if (d.install && d.install === 1 || d.install==='1') {
                                if (d.website !== '') {
                                    html += '<a  href="' + d.website + '"  target="_blank" class="layui-btn  layui-btn-xs">demo</a>';
                                }
                                html+="<a class=\"layui-btn  layui-btn-xs layui-btn-normal\" target='_blank' href='"+d.web+"'>前台</a>"
                            }
                            return html;
                        }
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });
            layui.table.on('tool(' + Table.init.table_elem + ')', function (obj) {
                var url = $(this).data('url');
                url = Fun.url(url);
                var event = $(this).attr('lay-event');
                if (event === 'install') {
                    if (getUserinfo() && getUserinfo().hasOwnProperty('client')) {
                    // if (true) {
                        Fun.toastr.confirm('Are you sure you want to install it', function () {
                            let index = layer.load();
                            Fun.ajax({
                                url: url,
                            }, function (res) {
                                if(res.code>0){
                                    Fun.toastr.success(res.msg, function () {
                                        Fun.toastr.close(index)
                                        Fun.refreshmenu();
                                        Fun.toastr.close();
                                        layui.table.reload(Table.init.tableId);
                                    });
                                }else{
                                    Fun.toastr.close(index)
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
                            btn: ['login','register'],
                            yes: function (index, layero) {
                                var url = Table.init.requests.api_url + Table.init.requests.login_url;
                                var data = {
                                    username: $("#inputUsername", layero).val(),
                                    password: $("#inputPassword", layero).val(),
                                };
                                if (!data.username || !data.password) {
                                    Fun.toastr.error(__('Account Or Password Cannot Empty'));
                                    return false;
                                }
                                data.appid = Table.init.appid;
                                data.appsecret = Table.init.appsecret;
                                data.timestamp = getTimestamp();
                                data.nonce = getNonce();
                                data.key = Table.init.appsecret;
                                data.sign = getSign(data);
                                $.ajax({
                                    url: url, type: 'post', data: data, dataType: "json", success: function (res) {
                                        console.log(res)
                                        if (res.code === 200) {
                                            setUserinfo(res.data);
                                            Fun.toastr.success(res.msg, layer.closeAll())
                                        } else {
                                            Fun.toastr.alert(res.msg)
                                        }
                                    }, error: function (res) {
                                        Fun.toastr.error(res.responseJSON.msg)
                                    }
                                })
                            },
                            btn2: function () {
                                Fun.api.closeCurrentOpen();
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
                        let index = layer.load();
                        Fun.ajax({
                            url: url,
                            method: 'post'
                        }, function (res) {
                            Fun.toastr.close(index)
                            Fun.toastr.success(res.msg, function () {
                                Fun.refreshmenu();
                                layui.table.reload(Table.init.tableId);
                                Fun.toastr.close()
                            });
                        },function(res){
                            Fun.toastr.error(res.msg)
                            Fun.toastr.close(index)
                            Fun.toastr.close()
                            layui.table.reload(Table.init.tableId);
                        })
                    });
                }
                if (event === 'status') {
                    Fun.toastr.confirm(__('Are you sure you want to change it'), function () {
                        Fun.ajax({
                            url: url,
                        }, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                layui.table.reload(Table.init.tableId);
                                Fun.toastr.close()
                            });
                        })
                    });
                }
                return false;
            })
            let table = $('#' + Table.init.table_elem);
            Table.api.bindEvent(table);
        },
        config: function () {
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
