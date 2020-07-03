define(['jquery'], function (undefined) {

    let laydate = layui.laydate;
    let Date = {
        init: {},
        //事件
        events: {
            date: function () {
                var dateList = document.querySelectorAll("[data-date]");
                if (dateList.length > 0) {
                    $.each(dateList, function (i, v) {
                        var format = $(this).attr('data-date'),
                            type = $(this).attr('data-date-type'),
                            range = $(this).attr('data-date-range');
                        if (type === undefined || type === '' || type === null) {
                            type = 'datetime';
                        }
                        var options = {
                            elem: this,
                            type: type,
                        };
                        if (format !== undefined && format !== '' && format !== null) {
                            options['format'] = format;
                        }
                        if (range !== undefined) {
                            if (range === null || range === '') {
                                range = '-';
                            }
                            options['range'] = range;
                        }
                        laydate.render(options);
                    });
                }

            },
            bindevent:function(){

            }
        },
        api: {

            //绑定事件
            bindEvent: function () {
                let events = Date.events;
                events.bindevent(form);

            }
        }
    }

    return Date;

})