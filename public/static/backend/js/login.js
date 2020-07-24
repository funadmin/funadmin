define(['jquery',"form",'toastr'], function ($, Form,toastr) {

    Toastr.options = {
        positionClass:"toast-top-center",//弹出的位置,

    }
    let Controller = {

        index: function () {
            Controller.api.bindevent();

        },
        password:function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'),function (res) {
                    Speed.msg.success(res.msg,setTimeout(function () {
                        window.location = res.url;
                    },2000))
                }, function (res) {
                    $("#captchaPic").trigger("click");
                    $('input[name="__token__"]').val(res.data.token)
                    Speed.msg.error(res.msg);
                })
            }
        },

    };
    return Controller;
});