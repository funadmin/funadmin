define(["form", 'toastr'], function (Form, Toastr) {
    Toastr.options = {
        positionClass: "toast-top-center",//弹出的位置,
    };
    var Controller = {
        index: function () {
            Controller.api.bindevent();
            if(top.location!=self.location){
                top.location.href = location.href;
            }

        },
        password: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'), function (res) {
                    Fun.toastr.success(res.msg, setTimeout(function () {
                        window.location = res.url;
                    }, 2500));
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
