define(['jquery','iconPicker','multiSelect','upload'], function ($,iconPicker,multiSelect,Upload) {
    var iconPicker = layui.iconPicker,
        layedit = layui.layedit,
        timePicker = layui.timePicker,
        colorpicker = layui.colorpicker,
        laydate = layui.laydate;
    let Hk = {
        init: {},
        //事件
        events: {

            editor:function (){
                let layeditorlist = document.querySelectorAll("*[lay-editor]");
                if (layeditorlist.length > 0) {
                    $.each(layeditorlist, function () {
                        console.log($(this).attr('lay-editor'))
                        if($(this).attr('lay-editor')==2){
                            let id = $(this).attr('id');
                            let editor = layedit.build(id,
                                {height: 350,
                                    uploadImage:{url: Fun.url(Upload.init.requests.upload_url), type: 'post'}
                                }); //建立编辑器
                            layedit.sync(editor)
                        }


                    })

                }

            },
            icon: function () {
                let iconList = document.querySelectorAll("*[lay-filters='iconPickers']");
                if (iconList.length > 0) {
                    $.each(iconList, function () {
                        var _that = $(this);
                        let id = _that.attr('id');
                        iconPicker.render({
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
                let colorList = document.querySelectorAll("*[lay-filter='colorPicker']");
                if (colorList.length > 0) {
                    $.each(colorList, function () {
                        var _that = $(this);
                        let id = $(this).attr('id');
                        colorpicker.render({
                            // 选择器，推荐使用input
                            elem: '#' + id,
                            // 数据类型：fontClass/unicode，推荐使用fontClass
                            color: 'rgb',//默认颜色，不管你是使用 hex、rgb 还是 rgba 的格式输入，最终会以指定的格式显示。
                            // 是否开启搜索：true/false
                            predefine: true,//预定义颜色是否开启
                            colors: ['#F00','#0F0','#00F','rgb(255, 69, 0)','rgba(255, 69, 0, 0.5)'],//预定义颜色，此参数需配合 predefine: true 使用。

                            size:'lg',//下拉框大小，可以选择：lg、sm、xs。
                            // 点击回调
                            change: function(color){

                            },
                            done: function(color){//颜色选择后的回调
                                console.log(color)
                                _that.prev('input[type="hidden"]').val(color)
                            }
                        });

                    })
                }
            },
            time:function (){
                let timeList = document.querySelectorAll("*[lay-filter='timePicker']");
                if (timeList.length > 0) {
                    $.each(timeList, function () {
                        let id = $(this).attr('id');
                        timePicker.render({
                            elem: '#' + id, //定义输入框input对象
                            options:{      //可选参数timeStamp，format
                                timeStamp:false,//true开启时间戳 开启后format就不需要配置，false关闭时间戳 //默认false
                                format:'YYYY-MM-DD HH:ss:mm',//格式化时间具体可以参考moment.js官网 默认是YYYY-MM-DD HH:ss:mm
                            },
                        });

                    })
                }
            },
            date: function () {
                let dateList = document.querySelectorAll("[lay-date]");
                if (dateList.length > 0) {
                    $.each(dateList, function () {
                        let format = $(this).attr('lay-format'),
                            type = $(this).attr('lay-type'),
                            range = $(this).attr('lay-range');
                        if (type === undefined || type === '' || type == null) {
                            type = 'datetime';
                        }
                        let options = {
                            elem: this,
                            type: type,
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
                        console.log(options)
                        laydate.render(options);
                    });
                }

            },

            bindevent: function () {

            }
        },
        api: {
            //绑定事件
            bindEvent: function () {
                let events = Hk.events;
                events.icon();
                events.color();
                events.date();
                events.editor();
                // events.time();
                // events.multiSelect();
                events.bindevent();

            }
        }
    };

    return Hk;

})