define(['jquery','iconPicker'], function (undefined,iconPicker) {
    var iconPicker = layui.iconPicker;
    var Icon = {
        init: {

        },
        //事件
        events: {
            layuiIcon: function () {
                var iconList = document.querySelectorAll("[lay-filter='iconPicker']");
                if (iconList.length > 0) {
                    $.each(iconList, function (i, v) {
                    console.log(i)
                        var id = $(this).attr('id');
                        iconPicker.render({
                            // 选择器，推荐使用input
                            elem: '#'+id,
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
                                console.log(data);
                            },
                            // 渲染成功后的回调
                            success: function(d) {
                                console.log(d);
                            }
                        });

                    })

                }

            },
            bindevent: function () {

            }
        },
        api: {

            //绑定事件
            bindEvent: function () {
                var events = Icon.events;
                events.layuiIcon();
                events.bindevent();

            }
        }
    }

    return Icon;

})