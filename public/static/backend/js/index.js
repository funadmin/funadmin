define(["jquery",'speedBackend','speed'], function ($,speedBackend,Speed) {
    var Controller = {
        index: function () {
           var  speedBackend = layui.speedBackend;
            options = {
                initUrl :Speed.url('ajax/initInfo'),
            };
            speedBackend.render(options)
            $('.login-out').on("click", function () {
                speed.request.get({
                    url: 'login/logout',
                    prefix: true,
                }, function (res) {
                    speed.msg.success(res.msg, function () {
                        window.location = speed.url('login/index');
                    })
                });
            });
        },
        console:function () {

        }

    }
    return Controller;
});
