define(['jquery', "form", 'toastr','upload'], function ($, Form, Toastr,Upload) {
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
                var uploadInst = layui.upload.render({
                    elem: '.upload-img' //绑定元素
                    ,url: Fun.url(Upload.init.requests.upload_url) //上传接口
                    ,done: function(res){
                        $('.upload-img').parents('.avatar-add').find('img').attr('src',res.url);
                        $('.upload-img').find('input').val(res.url);
                    }
                    ,error: function(res){
                        Fun.toastr.error(res.msg);
                        //请求异常回调
                    }
                });
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
                layui.form.on('select(province)', function (data) {
                    var pid = data.value;
                    Fun.ajax({url:"/member/getProvinces?pid=" + pid,method:'GET'}, function (res) {
                        data = res.data
                        var html = '<option value="">请选择市</option>';
                        $.each(data, function (i, value) {
                            html += '<option value="' + value.id + '">' + value.name + '</option>';
                        });
                        $('#city').html(html);
                        $('#district').html('<option value="">请选择县/区</option>');
                        layui.form.render()
                    });
                });
                layui.form.on('select(city)', function (data) {
                    var pid = data.value;
                    Fun.ajax({url:"/member/getProvinces?pid=" + pid,method:'GET'}, function (res) {
                        data = res.data
                        var html = '<option value="">请选择县/区</option>';
                        $.each(data, function (i, value) {
                            html += '<option value="' + value.id + '">' + value.name + '</option>';
                        });
                        $('#district').html(html);
                        layui.form.render()
                    });
                });
            },
        },
    };
    return Controller;
});