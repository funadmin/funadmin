define(["jquery",'backend'], function ($,Backend) {

    var Controller = {
        index: function () {
            var  Backend = layui.Backend;
            options = {
                initUrl :Speed.url('ajax/initMenuConfig'),
            };
            Backend.render(options)
        },
        console:function () {

        }

    }
    return Controller;
});
