// +----------------------------------------------------------------------
// | FunAdmin极速开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code

define(['jquery', 'table','tableSelect', 'upload', 'fu'], function($,Table, tableSelect, Upload, Fu) {
    var Form = {
        init: {},
        //事件
        events: {
            events:function (){
                list = $("*[lay-event]");
                if (list.length > 0) {
                    layui.each(list, function() {
                        $(this).click(function(){
                            if ($(this).attr('lay-event') === 'open') {
                                Fun.events.open($(this));
                                return true;
                            }
                            if ($(this).attr('lay-event') === 'request') {
                                Fun.events.request($(this));
                                return true;
                            }
                            if ($(this).attr('lay-event') === 'iframe') {
                                Fun.events.iframe($(this));
                                return true;
                            }
                            if ($(this).attr('lay-event') === 'dropdown') {
                                Fun.events.dropdown($(this));
                                return true;
                            }
                        })
                    })
                }

            },
            fu: function() {
                Fu.api.bindEvent()
            },
            uploads: function() {
                Upload.api.uploads();
            },
            cropper: function() {
                Upload.api.cropper();
            },
            //选择文件
            chooseFiles: function() {
                Form.api.chooseFiles()
            },
            //选择文件
            selectFiles: function() {
                Form.api.selectFiles()
            },
            //验证
            verifys: function() {
                layui.form.verify({
                    user: function(value) { //value：表单的值、item：表单的DOM对象
                        if (!new RegExp("^[a-zA-Z0-9_\u4e00-\u9fa5\\s·]+$").test(value)) {
                            return '用户名不能有特殊字符';
                        }
                        if (/(^\_)|(\__)|(\_+$)/.test(value)) {
                            return '用户名首尾不能出现下划线\'_\'';
                        }
                        if (/^\d+\d+\d$/.test(value)) {
                            return '用户名不能全为数字';
                        }
                    },
                    pass: [/^[\S]{6,18}$/, '密码必须6到18位，且不能出现空格'],
                    zipcode: [/^\d{6}$/, "请检查邮政编码格式"],
                    chinese: [/^[\u0391-\uFFE5]+$/, "请填写中文字符"] //包含字母
                    ,
                    money: [/(^[1-9]([0-9]+)?(\.[0-9]{1,2})?$)|(^(0){1}$)|(^[0-9]\.[0-9]([0-9])?$)/, "请输入正确的金额,且最多两位小数!"],
                    letters: [/^[a-z]+$/i, "请填写字母"],
                    digits: [/^\d+$/, '请填入数字'],
                    qq: [/^[1-9]\d{4,}$/, "请填写有效的QQ号"]
                });
            },
            //必填项
            required: function() {
                var vfList = $("[lay-verify]");
                if (vfList.length > 0) {
                    layui.each(vfList, function() {
                        var verify = $(this).attr('lay-verify');
                        // todo 必填项处理
                        if (verify === 'required') {
                            var label = $(this).parent().prev();
                            if (label.is('label') && !label.hasClass('required')) {
                                label.addClass('required');
                            }
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
            submit: function(formObj, success, error, submit) {
                var formList = $("[lay-submit]");
                // 表单提交自动处理
                if (formList.length > 0) {
                    layui.each(formList, function(i) {
                        var filter = $(this).attr('lay-filter'),
                            type = $(this).data('type'),
                            refresh = $(this).data('refresh'),
                            url = $(this).data('request') || $(this).data('url') ;
                        // 表格搜索不做自动提交
                        if (type === 'tableSearch') {
                            return false;
                        }
                        // 判断是否需要刷新表格
                        if (refresh === undefined) refresh = true;
                        if (refresh === 'false') refresh = false;
                        if (refresh === '') refresh = false;
                        // 自动添加layui事件过滤器
                        if (filter === undefined || filter === '') {
                            filter = 'form-' + (i + 1);
                            $(this).attr('lay-filter', filter)
                        }
                        if (url === undefined || url === '' || url == null) {
                            url = location.href;
                        }
                        layui.form.on('submit(' + filter + ')', function(data) {
                            if ($('select[multiple]').length > 0) {
                                var $select = $("select[multiple]");
                                layui.each($select, function() {
                                    var field = $(this).attr('name');
                                    var vals = [];
                                    $(this).children('option:selected').each(function() {
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
            upfileDelete: function(othis) {
                var fileurl = othis.data('fileurl'),
                    that;
                var confirm = Fun.toastr.confirm(__('Are you sure？'), function() {
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
            photos: function(otihs) {
                Fun.events.photos(otihs)
            },
            bindevent: function(form) {
                $('body').on('click', '[lay-event]', function() {
                    var _that = $(this),
                        attrEvent = _that.attr('lay-event');
                    if (Table.events.hasOwnProperty(attrEvent)) {
                        Table.events[attrEvent] && Table.events[attrEvent].call(this, _that);
                    }
                });
            },
        },
        api: {
            /**
             * 关闭窗口
             * @param option
             * @returns {boolean}
             */
            closeOpen: function(option) {
                option = option || {};
                option.refreshTable = option.refreshTable || false;
                option.refreshFrame = option.refreshFrame || false;
                if (option.refreshTable === true) {
                    option.refreshTable = option.tableid ||  Table.init.tableId;
                }
                var index = parent.layui.layer.getFrameIndex(window.name);
                if (index) {
                    parent.layui.layer.close(index);
                }
                if (option.refreshTable !== false) {
                    if (self !== top && parent.$('#' + option.refreshTable).length > 0) {
                        if((parent.layui.treeGrid)){
                            parent.layui.treeGrid.reload(option.refreshTable, {}, true)
                        }
                        Table.api.reload(option.refreshTable)
                    } else {
                        setTimeout(function() {
                            location.reload();
                        }, 2000)
                        return false;
                    }
                }
                if (!option.refreshFrame) {
                    return false;
                }
                setTimeout(function() {
                    location.reload();
                }, 2000)
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
            formSubmit: function(url, data, success, error, refresh) {
                success = success ||
                    function(res) {
                        res.msg = res.msg || 'success';
                        Fun.toastr.success(res.msg, function() {
                            // 返回页面
                            Form.api.closeOpen({
                                refreshTable: refresh,
                                refreshFrame: refresh
                            });
                        });
                        return false;
                    };
                error = error ||
                    function(res) {
                        res.msg = res.msg || 'error';
                        Fun.toastr.error(res.msg, function() {});
                        return false;
                    };
                Fun.ajax({
                    url: url,
                    data: data,
                    // tips:__('loading'),
                    complete: function(xhr) {
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
            initForm: function() {
                if (Config.formData) {
                    layui.form.val("form", Config.formData);
                    layui.form.render();
                }
                multiSelect = layui.multiSelect ? layui.multiSelect : parent.layui.multiSelect;
                multiSelect.render();
            },
            /**
             * 选择文件
             */
            chooseFiles: function() {
                var fileSelectList = $("*[lay-filter=\"upload-choose\"]");
                if (fileSelectList.length > 0) {
                    layui.each(fileSelectList, function(i, v) {
                        var data = $(this).data();
                        if(typeof data.value == 'object') data = data.value;
                        var uploadType = data.type,
                            uploadNum = data.num,
                            uploadMime = data.mime,
                            url = data.tableurl,
                            path = data.path;
                        uploadMime = uploadMime || '*';
                        uploadType = uploadType ? uploadType : 'radio';
                        uploadNum = uploadType === 'checkbox' ? uploadNum : 1;
                        var input = $(this).parents('.layui-upload').find('input[type="text"]');
                        var uploadList = $(this).parents('.layui-upload').find('.layui-upload-list');
                        var id = $(this).attr('id');
                        tableSelect = layui.tableSelect ||  parent.layui.tableSelect;
                        url = url?url: Fun.url(Upload.init.requests.attach_url + '?' +
                            '&elem_id='+id+'&num='+uploadNum+'&type='+uploadType+'&mime=' + uploadMime+ '&path='+path+'&type='+uploadType);
                        layui.tableSelect.render({
                            elem: '#' + id,
                            checkedKey: 'id',
                            searchType: 2,
                            searchList: [{
                                searchKey: 'original_name',
                                searchPlaceholder: __('FileName')
                            }, ],
                            table: {
                                url:url,
                                cols: [
                                    [{
                                        type: uploadType
                                    }, {
                                        field: 'id',
                                        title: 'ID'
                                    }, {
                                        field: 'url',
                                        minWidth: 80,
                                        search: false,
                                        title: __('Path'),
                                        imageHeight: 40,
                                        align: "center",
                                        templet: Table.templet.image,
                                    }, {
                                        field: 'original_name',
                                        width: 150,
                                        title: __('OriginalName'),
                                        align: "center"
                                    }, {
                                        field: 'mime',
                                        width: 120,
                                        title: __('MimeType'),
                                        align: "center"
                                    }, {
                                        field: 'create_time',
                                        width: 200,
                                        title: __('CreateTime'),
                                        align: "center",
                                        search: 'range'
                                    }, ]
                                ]
                            },
                            done: function(elem, data) {
                                var fileArr = [];
                                var html = '';
                                layui.each(data.data, function(index, val) {
                                    if (uploadMime === 'images') {
                                        html += '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + val.path + '" alt=""><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + val.path + '"></i></li>\n';
                                    } else if (uploadMime === 'video') {
                                        html += '<li><video controls class="layui-upload-img fl" width="150" src="' + val.path + '"></video><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + val.path + '"></i></li>\n';
                                    } else if (uploadMime === 'audio') {
                                        html += '<li><audio controls class="layui-upload-img fl"  src="' + val.path + '"></audio><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + val.path + '"></i></li>\n';
                                    } else {
                                        html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + val.path + '"></i></li>\n';
                                    }
                                    fileArr.push(val.path)
                                });
                                var fileurl = fileArr.join(',');
                                Fun.toastr.loading();
                                Fun.toastr.success(__('Choose Success'), function() {
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
             * 选择文件
             */
            selectFiles: function() {
                var fileSelectList = $("*[lay-filter=\"upload-select\"]");
                if (fileSelectList.length > 0) {
                    layui.each(fileSelectList, function(i, v) {
                        $(this).click(function(e){
                            var data = $(this).data();
                            if(typeof data.value == 'object') data = data.value;
                            uploadType = data.type,width = data.width||800,height = data.height||600
                                uploadNum = data.num, uploadMime = data.mime,
                                url  = data.selecturl, path = data.path;
                            uploadMime = uploadMime || '';
                            uploadType = uploadType ? uploadType : 'radio';
                            uploadNum = uploadType === 'checkbox' ? uploadNum : 1;
                            var input = $(this).parents('.layui-upload').find('input[type="text"]');
                            var token = $(this).parents('form').find('input[name="__token__"]');
                            var uploadList = $(this).parents('.layui-upload').find('.layui-upload-list');
                            var id = $(this).attr('id');
                            url = url?url: Fun.url(Upload.init.requests.select_url) + '?' +
                                '&elem_id='+id+'&num='+uploadNum+'&type='+uploadType+'&mime=' + uploadMime+
                                '&path='+path+'&type='+uploadType;
                            var parentiframe = Fun.api.checkLayerIframe();
                            options = {
                                title:__('Filelist'),type:2,
                                url: url, width: width, height: height, method: 'get',
                                yes:  function (index, layero) {
                                    try {
                                        $(document).ready(function () {
                                            // 父页面获取子页面的iframe
                                            var body = layer.getChildFrame('body', index);
                                            if (parentiframe) {
                                                body = parent.layer.getChildFrame('body', index);
                                            }
                                            li = body.find('.box-body .file-list-item li.active');
                                            __token__ = body.find('input[name="__token__"]').val();
                                            if(li.length===0){
                                                Fun.toastr.error(__('please choose file'));
                                                return false;
                                            }
                                            var fileArr=[],html='';
                                            layui.each(li, function(index, val) {
                                                var type = $(this).data('type'), url =  $(this).data('url');
                                                if (type.indexOf('image') >=0)  {
                                                    html += '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' +url + '" alt=""><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + url + '"></i></li>\n';
                                                } else if (type.indexOf('video') >= 0) {
                                                    html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/video.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + url + '"></i></li>\n';
                                                } else if (type.indexOf('audio') >= 0) {
                                                    html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/audio.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + url + '"></i></li>\n';
                                                } else if (type.indexOf('zip') >= 0) {
                                                    html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/zip.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + url + '"></i></li>\n';
                                                } else {
                                                    html += '<li><img  alt="" class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" data-fileurl="' + url + '"></i></li>\n';
                                                }
                                                fileArr.push(url)
                                            });
                                            var fileurl = fileArr.join(',');
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
                                            //token失效
                                            token.val(__token__)
                                            layer.close(index) || parent.layer.close(index)
                                        })
                                    } catch (err) {
                                        Fun.toastr.error(err)
                                    }
                                    return false;
                                }
                            }
                            var index = Fun.api.open(options)
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
            bindEvent: function(form, success, error, submit) {
                form = typeof form == 'object' ? form : $(form);
                var events = Form.events;
                events.submit(form, success, error, submit);
                events.required(form)
                events.verifys(form)
                events.uploads() //上传
                events.chooseFiles() //选择文件
                events.selectFiles() //选择文件页面类型
                events.cropper() //上传
                events.fu() //qita
                events.events() //事件
                events.bindevent(form);
                //初始化数据
                this.initForm();
                $('body').on('click', '[lay-event]', function() {
                    var _that = $(this),
                        attrEvent = _that.attr('lay-event');
                    Form.events[attrEvent] && Form.events[attrEvent].call(this, _that)
                });
            },
        },
    };
    return Form;
});