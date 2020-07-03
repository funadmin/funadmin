define(['jquery',"form"], function ($, Form) {

    let Controller = {

        index: function () {
            Controller.api.bindevent();

        },
        password:function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'),function (data) {
                    Speed.msg.success(data.msg,function () {
                        location.href = data.url;
                    });
                }, function (data) {
                    $("#captchaPic").trigger("click");
                    Speed.msg.error(data.msg);
                })
            }
        },

    };
    return Controller;
});