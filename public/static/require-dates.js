define(['jquery'], function (undefined) {
    var laydate = layui.laydate;
    var Dates = {
        init: {},
        //事件
        events: {
            date: function () {
                var dateList = document.querySelectorAll("[lay-date]");
                if (dateList.length > 0) {
                    $.each(dateList, function (i, v) {
                        var format = $(this).attr('lay-date'),
                            type = $(this).attr('lay-type'),
                            range = $(this).attr('lay-range');
                        if (type == undefined || type == '' || type == null) {
                            type = 'datetime';
                        }
                        var options = {
                            elem: this,
                            type: type,
                        };
                        if (format != undefined && format != '' && format != null) {
                            options['format'] = format;
                        }
                        if (range != undefined) {
                            if (range != null || range == '') {
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
                var events = Dates.events;
                events.date();
                events.bindevent();

            }
        }
    }

    return Dates;

})