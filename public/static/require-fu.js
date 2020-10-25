define(['jquery','iconPicker','cityPicker','timePicker','multiSelect','upload'],
function ($,iconPicker,cityPicker,timePicker,multiSelect,Upload) {
    var iconPicker = layui.iconPicker,
        layedit = layui.layedit,
        timePicker = layui.timePicker,
        colorPicker = layui.colorPicker,
        cityPicker = layui.cityPicker,
        laydate = layui.laydate;
    let Fu = {
        init: {},
        //事件
        events: {

            editor:function (){
                let list = document.querySelectorAll("*[lay-editor]");
                if (list.length > 0) {
                    $.each(list, function () {
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
                let list = document.querySelectorAll("*[lay-filters='iconPickers']");
                if (list.length > 0) {
                    $.each(list, function () {
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
                let list = document.querySelectorAll("*[lay-filter='colorPicker']");
                if (list.length > 0) {
                    $.each(list, function () {
                        var _that = $(this);
                        let id = $(this).attr('id');
                        colorPicker.render({
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
            city: function (){
                let list = document.querySelectorAll("*[lay-filter='cityPicker']");
                if (list.length > 0) {
                    $.each(list, function () {
                        let id = $(this).attr('id');
                        console.log(cityPicker)
                        var currentPicker = new cityPicker("#cityPicker", {
                            provincename:"provinceId",
                            cityname:"cityId",
                            districtname: "districtId",
                            level: 'districtId',// 级别
                        });
                        // currentPicker.setValue("");

                    })
                }
            },
            time:function (){
                let list = document.querySelectorAll("*[lay-filter='timePicker']");
                if (list.length > 0) {
                    $.each(list, function () {
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
                let list = document.querySelectorAll("[lay-date]");
                if (list.length > 0) {
                    $.each(list, function () {
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
                let events = Fu.events;
                events.icon();
                events.color();
                events.city();
                events.date();
                events.editor();
                events.time();
                events.bindevent();

            }
        }
    };

    return Fu;

})