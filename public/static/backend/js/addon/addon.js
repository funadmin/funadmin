define(['jquery', 'table','form'], function ($, Table,Form) {

    /*
     时间戳
   */
    function getTimestamp() {
        return Date.parse(new Date()) / 1000
    };

    /*
    随机数
     */
    function getNonce(len) {
        var len = len || 8;
        var $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnoprstuvwxyz123456789';
        /****默认去掉了容易混淆的字符oOLl,9gq,Vv,Uu,I1****/
        var maxPos = $chars.length;
        var nonce = '';
        for (i = 0; i < len; i++) {
            nonce += $chars.charAt(Math.floor(Math.random() * maxPos));
        }
        return nonce;
    };

    //获取签名
    function getSign(obj) {
        //先用Object内置类的keys方法获取要排序对象的属性名，再利用Array原型上的sort方法对获取的属性名进行排序，newkey是一个数组
        var newkey = Object.keys(obj).sort();
        //console.log('newkey='+newkey);
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
        return md5(decodeURI(str)).toLowerCase();
    };

    //获取用户信息
    function getUserinfo() {
        var userinfo = localStorage.getItem("FunAdmin_userinfo");
        return userinfo ? JSON.parse(userinfo) : null;

    };

    //设置用户信息
    function setUserinfo(data) {
        if (data) {
            localStorage.setItem("FunAdmin_userinfo", JSON.stringify(data));
        } else {
            localStorage.removeItem("FunAdmin_userinfo");
        }

    };
    let Controller = {
        index: function () {
            Table.init = {
                table_elem: 'list',
                tableId: 'list',
                requests: {
                    index_url: 'addon.addon/index',
                    install_url: 'addon.addon/install',
                    uninstall_url: 'addon.addon/uninstall',
                    config_url: 'addon.addon/config',
                    modify_url: 'addon.addon/modify',
                    // 配置
                    api_url: 'https://www.FunAdmin.com',   // 接口地址
                    login_url: '/api/v1.token/accessToken',   // 登陆地址获取token地址
                },
                appid: 'FunAdmin',   // appid
                appsecret: 'L9EwqM1jQQFOvniYnpe6K0SavguQOgoS',   // appserct
            }

            let tableIn = Table.render({
                elem: '#' + Table.init.table_elem,
                id: Table.init.table_render_id,
                url: Fun.url(Table.init.requests.index_url),
                init: Table.init,
                toolbar: ['refresh'],
                cols: [[
                    {checkbox: true, fixed: true},
                    {
                        field: 'title',
                        title: __('Title'),
                        width: 120,
                        sort: true,
                    },
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
                    {field: 'description', title: __('Description'), minWidth: 220, sort: true,},
                    {field: 'version', title: __('Addon version'), width: 160, sort: true, search: false},
                    {field: 'require', title: __('Addon require'), width: 160, sort: true, search: false},
                    {field: 'author', title: __('Author'), width: 120, sort: true},
                    {field: 'publish_time', title: __('Publishtime'), width: 180, search: false},
                    {width: 250, align: 'center', init: Table.init, templet: function (d) {
                            var html = '';
                            if (d.install == 1) {
                                html += '<a href="javascript:;" class="layui-btn  layui-btn-xs"  lay-event="config"  lay-url="' + Table.init.requests.config_url + '?name=' + d.name + '&id=' + d.id + '">config</a>'
                                if (d.status == 1) {
                                    html += '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="status"  lay-url="' + Table.init.requests.modify_url + '?name=' + d.name + '&id=' + d.id + '">已启用</a>'
                                } else {
                                    html += '<a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="status"   lay-url="' + Table.init.requests.modify_url + '?name=' + d.name + '&id=' + d.id + '">已禁用</a>'
                                }
                                html += '<a href="javascript:;" class="layui-btn layui-btn-danger layui-btn-xs"  lay-event="uninstall"  lay-url="' + Table.init.requests.uninstall_url + '?name=' + d.name + '&id=' + d.id + '">uninstall</a>'
                            } else {
                                html += '<a href="javascript:;" class="layui-btn layui-btn-danger layui-btn-xs"  lay-event="install" lay-url="' + Table.init.requests.install_url + '?name=' + d.name + '&id=' + d.id + '">install</a>'
                            }
                            if (d.install == 1) {
                                if (d.website != '') {
                                    html += '<a  href="' + d.website + '"  target="_blank" class="layui-btn  layui-btn-xs">demo</a>';
                                }
                            }
                            return html;
                        }
                    }
                ]],
                limits: [10, 15, 20, 25, 50, 100],
                limit: 15,
                page: true
            });
            layui.table.on('tool('+Table.init.table_elem+')', function(obj) {
                var url = $(this).attr('lay-url');
                url = Fun.url(url);
                var event = $(this).attr('lay-event');
                if (event === 'install') {
                    // if (getUserinfo() && getUserinfo().hasOwnProperty('client')) {
                    Fun.toastr.confirm('Are you sure you want to install it', function () {
                        Fun.ajax({
                            url: url,
                        }, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                Fun.refreshmenu();
                                Fun.toastr.close()
                                layui.table.reload(Table.init.tableId);

                            });
                        })
                    });
                    // } else {
                    //     var index = layer.open({
                    //         type: 1,
                    //         content: $("#login"),
                    //         zIndex: 9999,
                    //         area: ['450px', '350px'],
                    //         title: [__('Login') + 'FunAdmin', 'text-align:center'],
                    //         resize: false,
                    //         btn: [__('Login'), __('Register')],
                    //         yes: function (index, layero) {
                    //             var url = Table.init.requests.api_url + Table.init.requests.login_url;
                    //             var nonce = getNonce();
                    //             var timestamp = getTimestamp();
                    //             var data = {
                    //                 appid: Table.init.requests.appid,
                    //                 appsecret: Table.init.requests.appsecret,
                    //                 username: $("#inputUsername", layero).val(),
                    //                 password: $("#inputPassword", layero).val(),
                    //                 nonce: nonce,
                    //                 key: Table.init.requests.appsecret,
                    //                 timestamp: timestamp,
                    //             };
                    //             var sign = getSign(data);
                    //             data.sign = sign;
                    //             $.post(url, data, function (res) {
                    //                 res = JSON.parse(res)
                    //                 if (res.code == 200) {
                    //                     setUserinfo(res.data);
                    //                     Fun.toastr.success(res.message, Fun.api.closeCurrentOpen())
                    //                 } else {
                    //                     Fun.toastr.alert(res.message)
                    //                 }
                    //             })
                    //         },
                    //         btn2: function () {
                    //             Fun.api.closeCurrentOpen();
                    //             return false;
                    //         },
                    //         success: function (layero, index) {
                    //             $(".layui-layer-btn1", layero).prop("href", "https://www.FunAdmin.cn/bbs/login/reg.html").prop("target", "_blank");
                    //         },
                    //         end: function () {
                    //             $("#login").hide();
                    //         },
                    //     });
                    // }
                }
                if (event === 'uninstall') {
                    Fun.toastr.confirm(__('Are you sure you want to uninstall it'), function () {
                        Fun.ajax({
                            url: url,
                            method:'post'
                        }, function (res) {
                            Fun.toastr.success(res.msg, function () {
                                Fun.refreshmenu();
                                layui.table.reload(Table.init.tableId);
                                Fun.toastr.close()
                            });
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

                if (event === 'config') {
                    var index = layer.open({
                        type: 2,
                        content: url,
                        area: ['600px', '800px'],
                        maxmin: true
                    });
                    layer.full(index)

                }
                return false;
            })

            // let table = $('#' + Table.init.table_elem);
            // Table.api.bindEvent(table);
        },
        config: function () {
            Controller.api.bindevent()
        },
        api:{

            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    };
    return Controller;
});
