define(['jquery','tableSelect', 'upload', 'table','dates','editor'], function (undefined,tableSelect,Upload,Table,Dates,Editor) {

    var form = layui.form;
    var tableSelect = layui.tableSelect;
    var Form = {
        init: {

        },
        //事件
        events: {
            dates:function(){
                Dates.api.bindEvent()
            },
            uploads: function () {
                Upload.api.uploads();
            },
            chooseFiles:function(){
                Form.api.chooseFiles()
            },
            editor:function(){
                Editor.api.bindEvent();
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
                                Form.api.formSubmit(url, dataField, success, error,refresh);
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
                        var verify = $(this).attr('lay-verify');
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

            upfileDelete: function (othis) {
                var fileurl = othis.attr('lay-fileurl');
                var confirm = Speed.msg.confirm(__('Are you sure？'), function () {
                    that = othis.parents('.layui-upload-list').parents('.layui-upload');
                    var input = that.find('input[type="text"]');
                    var inputVal = input.val();
                    var input_temp = '';
                    if (othis.parents('li').index() == 0) {
                        input_temp = inputVal.replace(fileurl, '');
                        input.val(input_temp);
                    } else {

                        input_temp = inputVal.replace(',' + fileurl, '');
                        input.val(input_temp);
                    }
                    othis.parents('li').remove();
                    Speed.msg.close(confirm);
                });
                return false;
            },
            photos: function(otihs){
                Speed.events.photos(otihs)
            },
            bindevent: function (form) {

            },
        },
        api: {

            //关闭窗口并回传数据
            close: function (data) {
                var index = parent.Layer.getFrameIndex(window.name);
                var callback = parent.$("#layui-layer" + index).data("callback");
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
                if (option.refreshTable != false) {
                    if(self!=top && parent.$('#'+option.refreshTable).length>0){
                        parent.layui.table.reload(option.refreshTable)
                        return false;
                    }else{
                        location.reload();
                    }

                }
                if (option.refreshFrame) {
                    location.reload();
                    return false;

                }
                return false;
            },

            formSubmit: function (url, data, success, error,refresh) {
                success = success || function (res) {
                    res.msg = res.msg || 'success';
                    Speed.msg.success(res.msg, function () {
                        // 返回页面
                        Form.api.closeCurrentOpen({
                            refreshTable: refresh,
                            refreshFrame: refresh
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
                    url: url,
                    data: data,
                    // tips:__('loading'),
                    complete: function (xhr) {
                        var token = xhr.getResponseHeader('__token__');
                        if (token) {
                            $("input[name='__token__']").val(token);
                        }
                    },
                }, success, error);

                // Speed.ajax({
                //     method: 'post',
                //     url: url,
                //     data: data,
                //     tips:__('loading'),
                //     complete: function (xhr) {
                //         var token = xhr.getResponseHeader('__token__');
                //         if (token) {
                //             $("input[name='__token__']").val(token);
                //         }
                //     }
                // },
                //     function (res) {
                //     Speed.msg.success(res.msg, function () {
                //         //返回页面
                //         if(self!=top) {
                //             setTimeout(function () {
                //                 Form.api.closeCurrentOpen({
                //                     refreshFrame: refresh,
                //                     refreshTable: refresh
                //                 });
                //             }, 2000)
                //         }
                //         return false;
                //     });
                //     return false;
                // }, function (res) {
                //     res.msg = res.msg || __('Error');
                //     Speed.msg.error(res.msg);
                //     return false;
                // }
                // );

                return false;
            },
            // 初始化表格数据
            initForm: function () {
                if (Config.formData) {
                    form.val("form", Config.formData);
                    form.render();
                }
            },
            //选择文件
            chooseFiles: function () {
                var fileSelectList = document.querySelectorAll("[lay-upload-select]");
                if (fileSelectList.length > 0) {
                    $.each(fileSelectList, function (i, v) {
                        var uploadType = $(this).attr('lay-type'),
                            uploadNum = $(this).attr('lay-num'),
                            uploadMine = $(this).attr('lay-mime');
                        uploadMine = uploadMine || '';
                        uploadType = uploadType ? uploadType : 'radio';
                        uploadNum = uploadType=='checkbox'?uploadNum : 1;
                        var input = $(this).parents('.layui-upload').find('input[type="text"]');
                        var uploadList = $(this).parents('.layui-upload').find('.layui-upload-list');
                        var id = $(this).attr('id')
                        tableSelect.render({
                            elem: '#'+id,
                            checkedKey: 'id',
                            searchType: 2,
                            searchList: [
                                {searchKey: 'name', searchPlaceholder: __('FileName or FileMine')},
                            ],
                            table: {
                                url: Speed.url(Upload.init.requests.attach_url+'?mime='+uploadMine),
                                cols: [[
                                    {type: uploadType},
                                    {field: 'id', title: 'ID'},
                                    {
                                        field: 'url',
                                        minWidth: 80,
                                        search: false,
                                        title: __('Path'),
                                        imageHeight: 40,
                                        align: "center",
                                        templet: Table.templet.image,
                                    },
                                    {field: 'original_name', width: 150, title: __('OriginalName'), align: "center"},
                                    {field: 'mime', width: 120, title: __('MimeType'), align: "center"},
                                    {field: 'create_time', width: 200, title: __('CreateTime'), align: "center", search: 'range'},
                                ]]
                            },
                            done: function (elem, data) {
                                var fileArr = [];
                                var html = '';
                                $.each(data.data, function (index, val) {
                                    if (uploadMine == 'image') {
                                        html += '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + val.path + '"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + val.path + '"></i></li>\n';

                                    } else if (uploadMine == 'video') {
                                        html += '<li><video controls class="layui-upload-img fl" width="150" src="' + val.path + '"></video><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + val.path + '"></i></li>\n';

                                    } else if (uploadMine == 'audio') {
                                        html += '<li><audio controls class="layui-upload-img fl"  src="' + val.path + '"></audio><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + val.path + '"></i></li>\n';
                                    } else {
                                        html += '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + val.path + '"></i></li>\n';

                                    }
                                    fileArr.push(val.path)
                                });
                                var fileurl = fileArr.join(',');
                                Speed.msg.loading();
                                Speed.msg.success(__('Choose Success'), function () {
                                    var inptVal = input.val();
                                    if(uploadNum==1){
                                        input.val(fileurl)
                                        uploadList.html(html)
                                    }else{
                                        if(inptVal){
                                            input.val(inptVal+','+fileurl);
                                        }else{
                                            input.val(fileurl)
                                        }
                                        uploadList.append(html)
                                    }
                                    Speed.msg.close()
                                });
                            }
                        })

                    });

                }

            },

            //绑定事件
            bindEvent: function (form, success, error,submit) {
                form = typeof form == 'object' ? form : $(form);
                var events = Form.events;
                events.bindevent(form);
                events.submit(form, success, error,submit);
                events.required(form)
                events.uploads() //上传
                events.editor() //编辑器
                events.chooseFiles() //选择文件
                events.dates() //日期
                //初始化数据
                this.initForm();
                $('body').on('click', '[lay-event]', function () {
                    var _that = $(this), attrEvent = _that.attr('lay-event');
                    Form.events[attrEvent] && Form.events[attrEvent].call(this,_that)

                });
            }
        }
    }

    return Form;

})