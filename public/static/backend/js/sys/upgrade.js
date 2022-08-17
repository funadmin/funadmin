define(['jquery','table','form'], function (undefined,Table,Form) {

    return Controller =   {
        index: function () {
            $(document).on('click','#upgrade',function(){
                auth = $(this).data('auth');
                login = $(this).data('login');
                check = $(this).data('check');
                if(!auth){
                    layer.open({
                        type: 1,
                        shadeClose: true,
                        content: $("#login_tpl").html(),
                        zIndex: 9999,
                        area: ['450px', '350px'],
                        title: [__('Login In') + 'FunAdmin', 'text-align:center'],
                        resize: false,
                        btnAlign: 'c',
                        btn: [__('Login'),__('Register')],
                        yes: function (index, layero) {
                            var url = login;
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
                                        Fun.toastr.success(res.msg, layer.closeAll())
                                        window.location.reload()
                                    } else {
                                        Fun.toastr.alert(res.msg)
                                    }
                                }, error: function (res) {
                                    Fun.toastr.error(res.responseJSON.msg)
                                }
                            })
                        },
                        btn2: function () {
                            Fun.api.close();
                            return false;
                        },
                        success: function (layero, index) {
                            $(".layui-layer-btn1", layero).prop("href", "https://www.funadmin.com/frontend/login/index.html").prop("target", "_blank");
                        },
                        end: function () {
                            $("#login").hide();
                        },
                    });
                }else{
                    Fun.ajax({
                        url: check,
                        data: {},
                    }, function (res) {
                        Fun.toastr.success(res.msg, function () {
                            //第三步：渲染模版
                            var getTpl = upgrade_tpl.innerHTML
                            layui.laytpl(getTpl).render(res.data.data, function(html){
                                $('#upgrade_tpl').html(html) ;
                            });
                            Fun.api.open({
                                type:1,
                                btn:'close',
                                url:$('#upgrade_tpl').html(),
                                yes:function (){
                                    layer.closeAll()
                                },
                            })
                        })
                    })
                }
            })
            //备份
            $(document).on('click','#backup',function(){
                var _that = $(this);
                if(_that.attr('disabled')){
                    return false;
                }
                _that.html('备份中...')
                Fun.ajax({
                    url: _that.data('url'),
                    data: {},
                }, function (res) {
                    Fun.toastr.success(res.msg, function () {
                        _that.html('已经备份').attr('disabled','disabled');
                    })
                })
            })
            //安装
            $(document).on('click','#install',function(){
                var _that = $(this);
                if(_that.attr('disabled')){
                    return false;
                }
                _that.html('升级中...请不要关闭窗口');
                _that.attr('disabled','disabled');
                Fun.ajax({
                    url: $(this).data('url'),
                    data: {},
                }, function (res) {
                    _that.html('升级成功');
                    Fun.toastr.success(res.msg,
                        setTimeout(function (){
                            window.location.reload();
                        },2000)
                    )
                },function (res) {
                    Fun.toastr.error(res.msg, function () {
                        _that.removeAttr('disabled');
                        _that.html('点击升级');
                    })
                })
            })
            Controller.api.bindevent()
        },
        add:function () {
            Controller.api.bindevent()
        },
        edit:function () {
            Controller.api.bindevent()
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'))
            }
        }

    }
});