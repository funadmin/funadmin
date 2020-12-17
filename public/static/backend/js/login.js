define(['jquery', "form", 'toastr'], function ($,Form, Toastr) {

    Toastr.options = {
        positionClass: "toast-top-center",//弹出的位置,
    };
    var Controller = {

        index: function () {
            Controller.api.bindevent();

        },
        password: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'), function (res) {
                    console.log(111)
                    Fun.toastr.success(res.msg, setTimeout(function () {
                        debugger;
                        window.location = res.url;
                    }, 0));
                }, function (res) {
                    $("#captchaPic").trigger("click");
                    $('input[name="__token__"]').val(res.data.token);
                    Fun.toastr.error(res.msg);
                }
                );
            },
        },

    };
    return Controller;
});