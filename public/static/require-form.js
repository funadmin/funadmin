define(['jquery','tableSelect', 'upload', 'table','fu'], function (undefined,tableSelect,Upload,Table,Fu) {

    let form = layui.form;
    tableSelect = layui.tableSelect;
    let Form = {
        init: {},
        //事件
        events: {
            fu: function () {
                Fu.api.bindEvent()
            },
            uploads: function () {
                console.log(1111)
                Upload.api.uploads();
            },
            cropper: function () {
                Upload.api.cropper();
            },
            chooseFiles: function () {
                console.log(22222)
                Form.api.chooseFiles()
            },

            submit: function (formObj, success, error, submit) {
                let formList = document.querySelectorAll("[lay-submit]");
                // 表单提交自动处理
                if (formList.length > 0) {
                    $.each(formList, function (i) {
                        let filter = $(this).attr('lay-filter'),
                            type = $(this).attr('lay-type'),
                            refresh = $(this).attr('lay-refresh'),
                            url = $(this).attr('lay-request');
                        // 表格搜索不做自动提交
                        if (type === 'tableSearch') {
                            return false;
                        }
                        // 判断是否需要刷新表格
                        refresh = !(refresh === 'false' || refresh === undefined || false || refresh === '');
                        // 自动添加layui事件过滤器
                        if (filter === undefined || filter === '') {
                            filter = 'form-' + (i + 1);
                            $(this).attr('lay-filter', filter)
                        }
                        if (url === undefined || url === '' || url == null) {
                            url = location.href;
                        } else {
                            url = Fun.url(url);
                        }
                        form.on('submit(' + filter + ')', function (data) {
                            if($('select[multiple]').length>0){
                                let $select = document.querySelectorAll("select[multiple]");
                                $.each($select, function () {
                                    let field = $(this).attr('name');
                                    let vals = [];
                                    $('select[multiple] option:selected').each(function() {
                                        vals.push($(this).val());
                                    })
                                    data.field[field] = vals.join(',');

                                })

                            }
                            let dataField = data.field;
                            if (typeof formObj == 'function') {
                                formObj(url, dataField);
                            } else if (typeof submit == 'function') {
                                submit(url, dataField);
                            } else {
                                Form.api.formSubmit(url, dataField, success, error, refresh);
                            }
                            return false;
                        });
                    })

                }

            },

            //必填项
            required: function () {
                let vfList = document.querySelectorAll("[lay-verify]");
                if (vfList.length > 0) {
                    $.each(vfList, function () {
                        let verify = $(this).attr('lay-verify');
                        // todo 必填项处理
                        if (verify === 'required') {
                            let label = $(this).parent().prev();
                            if (label.is('label') && !label.hasClass('required')) {
                                label.addClass('required');
                            }
                            if ($(this).attr('lay-reqtext') === undefined && $(this).attr('placeholder') !== undefined) {
                                $(this).attr('lay-reqtext', $(this).attr('placeholder'));
                            }
                            if ($(this).attr('placeholder') === undefined && $(this).attr('lay-reqtext') !== undefined) {
                                $(this).attr('placeholder', $(this).attr('lay-reqtext'));
                            }
                        }

                    });
                }
            },

            upfileDelete: function (othis) {
                let fileurl = othis.attr('lay-fileurl'), that;
                let confirm = Fun.toastr.confirm(__('Are you sure？'), function () {
                    that = othis.parents('.layui-upload-list').parents('.layui-upload');
                    let input = that.find('input[type="text"]');
                    let inputVal = input.val();
                    let input_temp;
                    if (othis.parents('li').index() === 0) {
                        input_temp = inputVal.replace(fileurl, '');
                        input.val(input_temp);
                    } else {

                        input_temp = inputVal.replace(',' + fileurl, '');
                        input.val(input_temp);
                    }
                    othis.parents('li').remove();
                    Fun.toastr.close(confirm);
                });
                return false;
            },
            photos: function (otihs) {
                Fun.events.photos(otihs)
            },
            bindevent: function (form) {

            },
        },
        api: {
            /**
             * 关闭窗口并回传数据
             * @param data
             */
            close: function (data) {
                let index = parent.layer.getFrameIndex(window.name);
                let callback = parent.$("#layui-layer" + index).data("callback");
                //再执行关闭
                parent.layer.close(index);
                //再调用回传函数
                if (typeof callback === 'function') {
                    callback.call(undefined, data);
                }
            },
            /**
             * 关闭窗口
             * @param option
             * @returns {boolean}
             */
            closeCurrentOpen: function (option) {
                option = option || {};
                option.refreshTable = option.refreshTable || false;
                option.refreshFrame = option.refreshFrame || false;
                if (option.refreshTable === true) {
                    option.refreshTable = Table.init.tableId;
                }
                let index = parent.layer.getFrameIndex(window.name);
                parent.layer.close(index);
                if (option.refreshTable !== false) {
                    if (self !== top && parent.$('#' + option.refreshTable).length > 0) {
                        parent.layui.table.reload(option.refreshTable)
                    } else {
                        location.reload();
                    }
                }
                if (!option.refreshFrame) {
                    return false;
                }
                location.reload();
                return false;
            },
            /**
             * 提交
             * @param url
             * @param data
             * @param success
             * @param error
             * @param refresh
             * @returns {boolean}
             */
            formSubmit: function (url, data, success, error, refresh) {
                success = success || function (res) {
                    res.msg = res.msg || 'success';
                    Fun.toastr.success(res.msg, function (){
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
                    Fun.toastr.error(res.msg, function () {

                    });
                    return false;
                };
                Fun.ajax({
                    url: url,
                    data: data,
                    // tips:__('loading'),
                    complete: function (xhr) {
                        let token = xhr.getResponseHeader('__token__');
                        if (token) {
                            $("input[name='__token__']").val(token);
                        }
                    },
                }, success, error);

                return false;
            },
            /**
             * 初始化表格数据
             */
            initForm: function () {
                if (Config.formData) {
                    form.val("form", Config.formData);
                    form.render();
                    layui.multiSelect.render();
                }
            },
            /**
             * 选择文件
             */
            chooseFiles: function () {
                let fileSelectList = document.querySelectorAll("*[lay-upload-select]");
                if (fileSelectList.length > 0) {
                    $.each(fileSelectList, function () {
                        let uploadType = $(this).attr('lay-type'),
                            uploadNum = $(this).attr('lay-num'),
                            uploadMine = $(this).attr('lay-mime');
                        uploadMine = uploadMine || '';
                        uploadType = uploadType ? uploadType : 'radio';
                        uploadNum = uploadType === 'checkbox' ? uploadNum : 1;
                        let input = $(this).parents('.layui-upload').find('input[type="text"]');
                        let uploadList = $(this).parents('.layui-upload').find('.layui-upload-list');
                        let id = $(this).attr('id');
                        tableSelect.render({
                            elem: '#' + id,
                            checkedKey: 'id',
                            searchType: 2,
                            searchList: [
                                {searchKey: 'name', searchPlaceholder: __('FileName or FileMine')},
                            ],
                            table: {
                                url: Fun.url(Upload.init.requests.attach_url + '?mime=' + uploadMine),
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
                                    {
                                        field: 'create_time',
                                        width: 200,
                                        title: __('CreateTime'),
                                        align: "center",
                                        search: 'range'
                                    },
                                ]]
                            },
                            done: function (elem, data) {
                                let fileArr = [];
                                let html = '';
                                $.each(data.data, function (index, val) {
                                    if (uploadMine === 'image') {
                                        html += '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + val.path + '" alt=""><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + val.path + '"></i></li>\n';

                                    } else if (uploadMine === 'video') {
                                        html += '<li><video controls class="layui-upload-img fl" width="150" src="' + val.path + '"></video><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + val.path + '"></i></li>\n';

                                    } else if (uploadMine === 'audio') {
                                        html += '<li><audio controls class="layui-upload-img fl"  src="' + val.path + '"></audio><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + val.path + '"></i></li>\n';
                                    } else {
                                        html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + val.path + '"></i></li>\n';

                                    }
                                    fileArr.push(val.path)
                                });
                                let fileurl = fileArr.join(',');
                                Fun.toastr.loading();
                                Fun.toastr.success(__('Choose Success'), function () {
                                    let inptVal = input.val();
                                    if (uploadNum === 1) {
                                        input.val(fileurl)
                                        uploadList.html(html)
                                    } else {
                                        if (inptVal) {
                                            input.val(inptVal + ',' + fileurl);
                                        } else {
                                            input.val(fileurl)
                                        }
                                        uploadList.append(html)
                                    }
                                    Fun.toastr.close()
                                });
                            }
                        })

                    });

                }

            },
            /**
             * 绑定事件
             * @param form
             * @param success
             * @param error
             * @param submit
             */
            bindEvent: function (form, success, error, submit) {
                form = typeof form == 'object' ? form : $(form);
                let events = Form.events;
                events.submit(form, success, error, submit);
                events.required(form)
                events.uploads() //上传
                events.chooseFiles() //选择文件
                events.cropper() //上传

                events.fu() //qita
                events.bindevent(form);
                //初始化数据
                this.initForm();
                $('body').on('click', '[lay-event]', function () {
                    let _that = $(this), attrEvent = _that.attr('lay-event');
                    Form.events[attrEvent] && Form.events[attrEvent].call(this, _that)

                });
            }
        }
    };

    return Form;

})