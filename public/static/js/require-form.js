define(['jquery','tableSelect', 'upload', 'table','fu'], function (undefined,tableSelect,Upload,Table,Fu) {

    var form = layui.form;
    var element = layui.element;
    tableSelect = layui.tableSelect;
    var Form = {
        init: {},
        //事件
        events: {
            fu: function () {
                Fu.api.bindEvent()
            },
            uploads: function () {
                Upload.api.uploads();
            },
            cropper: function () {
                Upload.api.cropper();
            },
            //选择文件
            chooseFiles: function () {
                Form.api.chooseFiles()
            },
            //验证
            verifys:function (){
                form.verify({
                    user: function(value){ //value：表单的值、item：表单的DOM对象
                        if(!new RegExp("^[a-zA-Z0-9_\u4e00-\u9fa5\\s·]+$").test(value)){
                            return '用户名不能有特殊字符';
                        }
                        if(/(^\_)|(\__)|(\_+$)/.test(value)){
                            return '用户名首尾不能出现下划线\'_\'';
                        }
                        if(/^\d+\d+\d$/.test(value)){
                            return '用户名不能全为数字';
                        }
                    }
                    //layedit 编辑器同步
                    ,layedit:function(value){
                        var list = document.querySelectorAll("*[lay-filter='editor']");
                        if (list.length > 0) {
                            $.each(list, function () {
                                if($(this).data('editor')==2){
                                    var id = $(this).prop('id');
                                    return  layui.layedit.sync(window['editor'+id])
                                }
                            })
                        }
                    }
                    ,pass: [/^[\S]{6,18}$/, '密码必须6到18位，且不能出现空格']
                    ,zipcode: [/^\d{6}$/, "请检查邮政编码格式"]
                    ,chinese: [/^[\u0391-\uFFE5]+$/, "请填写中文字符"] //包含字母
                    ,money:[/(^[1-9]([0-9]+)?(\.[0-9]{1,2})?$)|(^(0){1}$)|(^[0-9]\.[0-9]([0-9])?$)/, "请输入正确的金额,且最多两位小数!"]
                    ,letters: [/^[a-z]+$/i, "请填写字母"]
                    ,digits: [/^\d+$/, '请填入数字']
                    ,qq: [/^[1-9]\d{4,}$/, "请填写有效的QQ号"]

                });
            },
            //必填项
            required: function () {
                var vfList = document.querySelectorAll("[lay-verify]");
                if (vfList.length > 0) {
                    $.each(vfList, function () {
                        var verify = $(this).attr('lay-verify');
                        // todo 必填项处理
                        if (verify === 'required') {
                            var label = $(this).parent().prev();
                            if (label.is('label') && !label.hasClass('required')) {label.addClass('required');}
                            if (typeof $(this).attr('lay-reqtext') === 'undefined' && typeof $(this).attr('placeholder') !== 'undefined') {
                                $(this).attr('lay-reqtext', $(this).attr('placeholder'));
                            }
                            if (typeof $(this).attr('placeholder') === 'undefined' && typeof $(this).attr('lay-reqtext') !== 'undefined') {
                                $(this).attr('placeholder', $(this).attr('layreqtext'));
                            }
                        }
                    });
                }
            },
            submit: function (formObj, success, error, submit) {
                var formList = document.querySelectorAll("[lay-submit]");
                // 表单提交自动处理
                if (formList.length > 0) {
                    $.each(formList, function (i) {
                        var filter = $(this).attr('lay-filter'),
                            type = $(this).data('type'),
                            refresh = $(this).data('refresh'),
                            url = $(this).data('data-request');
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
                        }
                        form.on('submit(' + filter + ')', function (data) {
                            if($('select[multiple]').length>0){
                                var $select = document.querySelectorAll("select[multiple]");
                                $.each($select, function () {
                                    var field = $(this).attr('name');
                                    var vals = [];
                                    $('select[multiple] option:selected').each(function() {
                                        vals.push($(this).val());
                                    })
                                    data.field[field] = vals.join(',');
                                })
                            }
                            var dataField = data.field;
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
            //删除
            upfileDelete: function (othis) {
                var fileurl = othis.data('fileurl'), that;
                var confirm = Fun.toastr.confirm(__('Are you sure？'), function () {
                    that = othis.parents('.layui-upload-list').parents('.layui-upload');
                    var input = that.find('input[type="text"]');
                    var inputVal = input.val();
                    var input_temp;
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
            //图片
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
                var index = parent.layer.getFrameIndex(window.name);
                var callback = parent.$("#layui-layer" + index).data("callback");
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
                var index = parent.layer.getFrameIndex(window.name);
                parent.layer.close(index);
                console.log(parent.$('#' + option.refreshTable).length);
                if (option.refreshTable !== false) {
                    if (self !== top && parent.$('#' + option.refreshTable).length > 0) {
                        parent.layui.table.reload(option.refreshTable)
                    } else {
                        location.reload();
                        return false;
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
                        var token = xhr.getResponseHeader('__token__');
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
                }
                layui.multiSelect.render();

            },
            /**
             * 选择文件
             */
            chooseFiles: function () {
                var fileSelectList = document.querySelectorAll("*[lay-filter=\"upload-select\"]");
                if (fileSelectList.length > 0) {
                    $.each(fileSelectList, function () {
                        var data = $(this).data();
                        var uploadType = data.value.type,
                            uploadNum = data.value.num,
                            uploadMine = data.value.mine;
                        uploadMine = uploadMine || '*';
                        uploadType = uploadType ? uploadType : 'radio';
                        uploadNum = uploadType === 'checkbox' ? uploadNum : 1;
                        var input = $(this).parents('.layui-upload').find('input[type="text"]');
                        var uploadList = $(this).parents('.layui-upload').find('.layui-upload-list');
                        var id = $(this).attr('id');
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
                                var fileArr = [];
                                var html = '';
                                $.each(data.data, function (index, val) {
                                    console.log(uploadMine)
                                    if (uploadMine === 'image') {
                                        html += '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + val.path + '" alt=""><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + val.path + '"></i></li>\n';

                                    } else if (uploadMine === 'video') {
                                        html += '<li><video controls class="layui-upload-img fl" width="150" src="' + val.path + '"></video><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + val.path + '"></i></li>\n';

                                    } else if (uploadMine === 'audio') {
                                        html += '<li><audio controls class="layui-upload-img fl"  src="' + val.path + '"></audio><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + val.path + '"></i></li>\n';
                                    } else {
                                        html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + val.path + '"></i></li>\n';

                                    }
                                    fileArr.push(val.path)
                                });
                                var fileurl = fileArr.join(',');
                                Fun.toastr.loading();
                                Fun.toastr.success(__('Choose Success'), function () {
                                    var inptVal = input.val();
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
                var events = Form.events;
                events.submit(form, success, error, submit);
                events.required(form)
                events.verifys(form)
                events.uploads() //上传
                events.chooseFiles() //选择文件
                events.cropper() //上传
                events.fu() //qita
                events.bindevent(form);
                //初始化数据
                this.initForm();
                $('body').on('click', '[lay-event]', function () {
                    var _that = $(this), attrEvent = _that.attr('lay-event');
                    Form.events[attrEvent] && Form.events[attrEvent].call(this, _that)
                });

            }
        }
    };

    return Form;

})