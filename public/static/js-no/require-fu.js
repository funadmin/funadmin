define(['jquery', 'iconPicker', 'cityPicker', 'inputTags', 'timePicker', 'regionCheckBox', 'multiSelect', 'upload','layui' ],
    function ($, iconPicker, cityPicker, inputTags, timePicker, regionCheckBox, multiSelect, Upload,undefined) {
        var Fu = {
            init: {},
            //事件
            events: {
                editor: function () {
                    var list = document.querySelectorAll("*[lay-filter='editor']");
                    if (list.length > 0) {
                        $.each(list, function () {
                            if ($(this).data('editor') === 2) {
                                var id = $(this).prop('id');
                                window['editor' + id] = layedit.build(id,
                                    {
                                        height: 350,
                                        uploadImage: {
                                            url: Fun.url(Upload.init.requests.upload_url) + '?editor=layedit',
                                            type: 'post'
                                        }
                                    }); //建立编辑器
                            }
                        })
                    }
                },
                tags: function () {
                    var list = document.querySelectorAll("*[lay-filter='tags']");
                    if (list.length > 0) {
                        $.each(list, function () {
                            var _that = $(this),
                                content = [];
                            var tag = _that.parents('.tags').find('input[type="hidden"]').val();
                            if (tag) content = tag.substring(0, tag.length - 1).split(',');
                            var id = _that.prop('id');
                            layui.inputTags.render({
                                elem: '#' + id,//定义输入框input对象
                                content: content,//默认标签
                                done: function (value) { //空格后的回调
                                }
                            })
                        })
                    }
                },
                icon: function () {
                    var list = document.querySelectorAll("*[lay-filter='iconPickers']");
                    if (list.length > 0) {
                        $.each(list, function () {
                            var _that = $(this);
                            var id = _that.prop('id');
                            layui.iconPicker.render({
                                // 选择器，推荐使用input
                                elem: '#' + id,
                                // 数据类型：fontClass/unicode，推荐使用fontClass
                                type: 'fontClass',
                                // 是否开启搜索：true/false
                                search: true,
                                // 是否开启分页
                                page: true,
                                // 每页显示数量，默认12
                                limit: 12,
                                // 点击回调
                                click: function (data) {
                                    _that.prev("input[type='hidden']").val(data.icon)
                                },
                                // 渲染成功后的回调
                                success: function (d) {
                                }
                            });
                        })
                    }
                },
                color: function () {
                    var list = document.querySelectorAll("*[lay-filter='colorPicker']");
                    if (list.length > 0) {
                        $.each(list, function () {
                            var _that = $(this);
                            var id = _that.prop('id');
                            var color = _that.prev('input').val();
                            layui.colorPicker.render({
                                // 选择器，推荐使用input
                                elem: '#' + id,
                                // 数据类型：fontClass/unicode，推荐使用fontClass
                                color: color,//默认颜色，不管你是使用 hex、rgb 还是 rgba 的格式输入，最终会以指定的格式显示。
                                // 是否开启搜索：true/false
                                predefine: true,//预定义颜色是否开启
                                colors: ['#F00', '#0F0', '#00F', 'rgb(255, 69, 0)', 'rgba(255, 69, 0, 0.5)'],//预定义颜色，此参数需配合 predefine: true 使用。
                                size: 'lg',//下拉框大小，可以选择：lg、sm、xs。
                                // 点击回调
                                change: function (color) {
                                },
                                done: function (color) {//颜色选择后的回调
                                    _that.prev('input[type="hidden"]').val(color)
                                }
                            });

                        })
                    }
                },
                regionCheck: function () {
                    var list = document.querySelectorAll("*[lay-filter='regionCheck']");
                    if (list.length > 0) {
                        $.each(list, function () {
                            var _that = $(this);
                            var id = _that.prop('id');
                            var name = _that.prop('name');
                            //执行实例
                            layui.regionCheckBox.render({
                                elem: '#' + id,
                                name: name, //input name
                                value: ['北京', '内蒙古', '江西-九江'], //赋初始值
                                width: '550px', //默认550px
                                border: true, //默认true
                                ready: function () { //初始化完成时执行
                                    _that.prev('input[type="hidden"]').val(getAllChecked())
                                },
                                change: function (result) { //点击复选框时执行
                                    _that.prev('input[type="hidden"]').val(getAllChecked())
                                }
                            });
                            function getAllChecked() {
                                var all = '';
                                $("input:checkbox[name='" + name + "']:checked").each(function () {
                                    all += $(this).val() + ',';
                                });
                                return all.substring(0, all.length - 1);
                            }
                        })
                    }
                },
                city: function () {
                    var list = document.querySelectorAll("*[lay-filter='cityPicker']");
                    if (list.length > 0) {
                        cityPicker = layui.cityPicker;
                        $.each(list, function () {
                            var id = $(this).prop('id');
                            new cityPicker("#" + id, {
                                provincename: "provinceId",
                                cityname: "cityId",
                                districtname: "districtId",
                                level: 'districtId',// 级别
                            });
                            // currentPicker.setValue("");
                        })
                    }
                },
                timepicker: function () {
                    var list = document.querySelectorAll("*[lay-filter='timePicker']");
                    if (list.length > 0) {
                        $.each(list, function () {
                            var id = $(this).prop('id');
                            layui.timePicker.render({
                                elem: '#' + id, //定义输入框input对象
                                trigger: 'click', //添加这一行来处理闪退
                                options: {      //可选参数timeStamp，format
                                    timeStamp: false,//true开启时间戳 开启后format就不需要配置，false关闭时间戳 //默认false
                                    format: 'YYYY-MM-DD HH:ss:mm',//格式化时间具体可以参考moment.js官网 默认是YYYY-MM-DD HH:ss:mm
                                },
                            });
                        })
                    }
                },
                date: function () {
                    var list = document.querySelectorAll("*[lay-filter='date']");
                    if (list.length > 0) {
                        $.each(list, function () {
                            var format = $(this).data('format'),
                                type = $(this).data('type'),
                                range = $(this).data('range');
                            if (type === undefined || type === '' || type == null) {
                                type = 'datetime';
                            }
                            var options = {
                                elem: this,
                                type: type,
                                trigger: 'click',
                                calendar: true,
                                theme: '#393D49'

                            };
                            if (format !== undefined && format !== '' && format != null) {
                                options['format'] = format;
                            }
                            if (range !== undefined) {
                                if (range != null || range === '') {
                                    range = '-';
                                }
                                options['range'] = range;
                            }
                            layui.laydate.render(options);
                        });
                    }
                },
                //数组添加input
                addInput: function () {
                    $(document).on('click', ".addInput", function () {
                        name = $(this).data('name');
                        verify = $(this).data('verify');
                        var str = '<div class="layui-form-item">' +
                            '<label class="layui-form-label"></label>' +
                            '<div class="layui-input-inline">' +
                            '<input type="text" name="' + name + '[][key]" placeholder="key" class="layui-input input-double-width">' +
                            '</div>' +
                            '<div class="layui-input-inline">\n' +
                            '<input type="text" id="" name="' + name + '[][key]" lay-verify="required" placeholder="value" autocomplete="off" class="layui-input input-double-width">\n' +
                            '</div>' +
                            '<div class="layui-input-inline">' +
                            '<button data-name="' + name + '" type="button" class="layui-btn layui-btn-danger layui-btn-sm removeInupt"><i class="layui-icon">&#xe67e;</i></button>' +
                            '</div>' +
                            '</div>';
                        $(this).parents('.layui-form-item').after(str);
                    });
                },
                removeInupt: function () {
                    //删除动态添加的input输入框
                    $(document).on('click', ".removeInupt", function () {
                        //元素移除前校验是否被引用
                        var parentEle = $(this).parent().parent();
                        //移除父元素
                        parentEle.remove();
                    });
                },
                bindevent: function () {
                }
            },
            api: {
                //绑定事件
                bindEvent: function () {
                    var events = Fu.events;
                    events.icon();
                    events.color();
                    events.tags();
                    events.city();
                    events.date();
                    events.timepicker();
                    events.editor();
                    events.regionCheck();
                    events.addInput();
                    events.removeInupt();
                    events.bindevent();

                }
            }
        };

        return Fu;

    })