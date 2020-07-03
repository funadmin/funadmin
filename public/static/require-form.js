define(['jquery', 'upload', 'table'], function (undefined, Upload, Table) {

    let form = layui.form,
        laydate = layui.laydate,
        element = layui.element;
    let Form = {
        init: {

        },
        //事件
        events: {
            uploads: function (form) {
                Upload.api.upload();
            },
            submit: function (formObj, success, error,submit) {
                var formList = document.querySelectorAll("[lay-submit]");
                // 表单提交自动处理
                if (formList.length > 0) {
                    $.each(formList, function (i, v) {
                        var filter = $(this).attr('lay-filter'),
                            type = $(this).attr('lay-type'),
                            refresh = $(this).attr('lay-refresh'),
                            url = $(this).attr('lay-request');
                        // 表格搜索不做自动提交
                        if (type == 'tableSearch') {
                            return false;
                        }
                        // 判断是否需要刷新表格
                        if (refresh == 'false' || refresh == undefined || refresh == null || refresh == '') {
                            refresh = true;
                        } else {
                            refresh = false;
                        }
                        // 自动添加layui事件过滤器
                        if (filter == undefined || filter == '') {
                            filter = 'form-' + (i + 1);
                            $(this).attr('lay-filter', filter)
                        }
                        if (url == undefined || url == '' || url == null) {
                            url = location.href;

                        } else {
                            url = Speed.url(url);

                        }
                        form.on('submit(' + filter + ')', function (data) {
                            var dataField = data.field;
                            if (typeof formobj == 'function') {
                                formobj(url, dataField);
                            }else if (typeof submit == 'function') {
                                submit(url, dataField);
                            } else {
                                Form.api.formSubmit(url, dataField, success, error, submit, refresh);
                            }
                            return false;
                        });
                    })

                }

            },

            //必填项
            required: function (form) {
                var verifyList = document.querySelectorAll("[lay-verify]");
                if (verifyList.length > 0) {
                    $.each(verifyList, function (i, v) {
                        let verify = $(this).attr('lay-verify');
                        // todo 必填项处理
                        if (verify == 'required') {
                            var label = $(this).parent().prev();
                            if (label.is('label') && !label.hasClass('required')) {
                                label.addClass('required');
                            }
                            if ($(this).attr('lay-reqtext') == undefined && $(this).attr('placeholder') !== undefined) {
                                $(this).attr('lay-reqtext', $(this).attr('placeholder'));
                            }
                            if ($(this).attr('placeholder') == undefined && $(this).attr('lay-reqtext') !== undefined) {
                                $(this).attr('placeholder', $(this).attr('lay-reqtext'));
                            }
                        }

                    });
                }
            },
            chooseattach:function(){
              Upload.api.chooseattach()
            },
            bindevent: function (form) {

            },
        },
        api: {
            //关闭窗口并回传数据
            close: function (data) {
                let index = parent.Layer.getFrameIndex(window.name);
                let callback = parent.$("#layui-layer" + index).data("callback");
                //再执行关闭
                parent.layer.close(index);
                //再调用回传函数
                if (typeof callback === 'function') {
                    callback.call(undefined, data);
                }
            },
            closeCurrentOpen: function (option) {
                option = option || {};
                option.refreshTable = option.refreshTable || false;
                option.refreshFrame = option.refreshFrame || false;
                if (option.refreshTable === true) {
                    option.refreshTable = Table.init.tablId;
                }
                var index = parent.layer.getFrameIndex(window.name);
                parent.layer.close(index);
                if (option.refreshTable !== false) {
                    parent.layui.table.reload(option.refreshTable);
                }
                if (option.refreshFrame) {
                    parent.location.reload();
                }
                return false;
            },

            formSubmit: function (url, data, success, error, submit, refresh) {
                success = success || function (res) {
                    res.msg = res.msg || 'ok';
                    Speed.msg.success(res.msg, function () {
                        //返回页面
                        Form.api.closeCurrentOpen({
                            refreshTable: refresh
                        });
                    });
                    return false;
                };
                error = error || function (res) {
                    res.msg = res.msg || 'error';
                    Speed.msg.error(res.msg, function () {

                    });
                    return false;
                };
                Speed.ajax({
                    method: 'post',
                    url: url,
                    data: data,
                }, success, error, submit);

                return false;
            },
            // 初始化表格数据
            initForm: function () {
                if (Config.formData) {
                    form.val("form", Config.formData);
                    form.render();
                }
            },

            //绑定事件
            bindEvent: function (form, success, error,submit) {
                form = typeof form == 'object' ? form : $(form);
                let events = Form.events;
                events.bindevent(form);
                events.submit(form, success, error,submit);
                events.required(form)
                events.uploads(form)
                events.chooseattach()
                //初始化数据
                this.initForm();

            }
        }
    }

    return Form;

})