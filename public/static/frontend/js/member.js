define(['jquery', "form", 'toastr'], function ($, Form, Toastr) {
    Toastr.options = {
        positionClass: "toast-top-center",//弹出的位置,
    };
    var Controller = {
        index: function () {
            Controller.api.bindevent();

        },
        home:function (){
            Controller.api.bindevent();
        },
        set: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindEvent($('form'));
                $(document).on('click','#logout',function(){
                    Fun.ajax({
                        url: $(this).data('url'),
                        data: {},
                    }, function (res) {
                        Fun.toastr.success(res.msg, function () {
                            setTimeout(function(){
                                window.location.href = res.url;
                            },2000)
                        })
                    })
                })
            },
        },
    };
    return Controller;
});