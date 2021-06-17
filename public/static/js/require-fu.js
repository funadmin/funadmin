define(['jquery', 'xmSelect', 'iconPicker', 'cityPicker', 'inputTags', 'timePicker', 'regionCheckBox', 'multiSelect', 'upload'], function($, xmSelect, iconPicker, cityPicker, inputTags, timePicker, regionCheckBox, multiSelect, Upload) {
    var Fu = {
        init: {},
        events: {
            xmSelect: function() {
                var list = document.querySelectorAll("*[lay-filter='xmSelect']");
                if (list.length > 0) {
                    $.each(list, function() {
                        var id = $(this).prop('id'),
                            lang = $(this).data('lang'),
                            paging = $(this).data('paging');
                        var pageSize = $(this).data('pageSize'),
                            radio = $(this).data('radio');
                        var disabled = $(this).data('disabled'),
                            clickClose = $(this).data('clickClose');
                        var create = $(this).data('create'),
                            value = $(this).data('value'),
                            theme = $(this).data('theme');
                        theme = theme ? theme : '#333';
                        value = value ? value : [];
                        lang = lang ? lang : 'zh';
                        paging = paging === undefined || paging !== 'false';
                        pageSize = pageSize ? pageSize : 10;
                        radio = !! radio;
                        disabled = !! disabled;
                        clickClose = clickClose ? clickClose : false;
                        create = !create ?
                            function(val) {
                                return {
                                    name: val,
                                    value: val
                                }
                            } : false;
                        xmSelect = window.xmSelect ? window.xmSelect : parent.window.xmSelect;
                        xmselect = xmSelect.render({
                            el: '#' + id,
                            toolbar: {
                                show: true,
                                showIcon: false,
                            },
                            theme: {
                                color: theme,
                            },
                            language: lang,
                            radio: radio,
                            paging: paging,
                            pageSize: pageSize,
                            filterable: true,
                            autoRow: true,
                            disabled: disabled,
                            clickClose: clickClose,
                            data: value,
                            on: function(data) {
                                $('#' + id).find('input[name="' + id + '"]').val(Fun.common.arrTostr(data.arr))
                            },
                            create: create,
                        })
                    })
                }
            },
            editor: function() {
                var list = document.querySelectorAll("*[lay-filter='editor']");
                if (list.length > 0) {
                    $.each(list, function() {
                        if ($(this).data('editor') === 2 || $(this).data('editor') === '2') {
                            var id = $(this).prop('id');
                            var name = $(this).prop('name');
                            $(this).html(Config.formData[name]);
                            window['editor' + id] = layui.layedit.build(id, {
                                height: 350,
                                uploadImage: {
                                    url: Fun.url(Upload.init.requests.upload_url) + '?editor=layedit',
                                    type: 'post'
                                }
                            })
                        }
                    })
                }
            },
            tags: function() {
                var list = document.querySelectorAll("*[lay-filter='tags']");
                if (list.length > 0) {
                    $.each(list, function() {
                        var _that = $(this),
                            content = [];
                        var tag = _that.parents('.tags').find('input[type="hidden"]').val();
                        if (tag) content = tag.substring(0, tag.length - 1).split(',');
                        var id = _that.prop('id');
                        var inputTags = layui.inputTags ? layui.inputTags : parent.layui.inputTags;
                        inputTags.render({
                            elem: '#' + id,
                            content: content,
                            done: function(value) {}
                        })
                    })
                }
            },
            icon: function() {
                var list = document.querySelectorAll("*[lay-filter='iconPickers']");
                if (list.length > 0) {
                    $.each(list, function() {
                        var _that = $(this);
                        var id = _that.prop('id');
                        layui.iconPicker.render({
                            elem: '#' + id,
                            type: 'fontClass',
                            search: true,
                            page: true,
                            limit: 12,
                            click: function(data) {
                                _that.prev("input[type='hidden']").val(data.icon)
                            },
                            success: function(d) {}
                        })
                    })
                }
            },
            color: function() {
                var list = document.querySelectorAll("*[lay-filter='colorPicker']");
                if (list.length > 0) {
                    $.each(list, function() {
                        var _that = $(this);
                        var id = _that.prop('id');
                        var color = _that.prev('input').val();
                        layui.colorpicker.render({
                            elem: '#' + id,
                            color: color,
                            predefine: true,
                            colors: ['#F00', '#0F0', '#00F', 'rgb(255, 69, 0)', 'rgba(255, 69, 0, 0.5)'],
                            size: 'lg',
                            change: function(color) {},
                            done: function(color) {
                                _that.prev('input[type="hidden"]').val(color)
                            }
                        })
                    })
                }
            },
            regionCheck: function() {
                var list = document.querySelectorAll("*[lay-filter='regionCheck']");
                if (list.length > 0) {
                    $.each(list, function() {
                        var _that = $(this);
                        var id = _that.prop('id'),
                            name = _that.prop('name');
                        layui.regionCheckBox.render({
                            elem: '#' + id,
                            name: name,
                            value: ['北京', '内蒙古', '江西-九江'],
                            width: '550px',
                            border: true,
                            ready: function() {
                                _that.prev('input[type="hidden"]').val(getAllChecked())
                            },
                            change: function(result) {
                                _that.prev('input[type="hidden"]').val(getAllChecked())
                            }
                        });

                        function getAllChecked() {
                            var all = '';
                            $("input:checkbox[name='" + name + "']:checked").each(function() {
                                all += $(this).val() + ','
                            });
                            return all.substring(0, all.length - 1)
                        }
                    })
                }
            },
            city: function() {
                var list = document.querySelectorAll("*[lay-filter='cityPicker']");
                if (list.length > 0) {
                    cityPicker = layui.cityPicker;
                    $.each(list, function() {
                        var id = $(this).prop('id'),
                            name = $(this).prop('name');
                        var provinceId = $(this).data('provinceid'),
                            cityId = $(this).data('cityid');
                        var province, city, district;
                        if (Config.formData[name]) {
                            var cityValue = Config.formData[name], province = cityValue.split('/')[0], city = cityValue.split('/')[1],district = cityValue.split('/')[2];
                        }
                        var districtId = $(this).data('districtid');
                        currentPicker = new cityPicker("#" + id, {
                            provincename: provinceId,
                            cityname: cityId,
                            districtname: districtId,
                            level: 'districtId',
                            province: province,
                            city: city,
                            district: district
                        });
                        var str = '';
                        if (Config.formData.hasOwnProperty(provinceId)) {
                            str += ChineseDistricts[886][Config.formData[provinceId]]
                        }
                        if (Config.formData.hasOwnProperty(cityId) && Config.formData[[cityId]] && Config.formData.hasOwnProperty(provinceId)) {
                            str += '/' + ChineseDistricts[Config.formData[provinceId]][Config.formData[cityId]]
                        }
                        if (Config.formData.hasOwnProperty(cityId) && Config.formData[districtId] && Config.formData.hasOwnProperty(districtId)) {
                            str += '/' + ChineseDistricts[Config.formData[cityId]][Config.formData[districtId]]
                        }
                        if (!str) {
                            str = Config.formData.hasOwnProperty(name) ? Config.formData['name'] : ''
                        }
                        currentPicker.setValue(Config.formData[name] ? Config.formData[name] : str)
                    })
                }
            },
            timepicker: function() {
                var list = document.querySelectorAll("*[lay-filter='timePicker']");
                if (list.length > 0) {
                    $.each(list, function() {
                        var id = $(this).prop('id');
                        layui.timePicker.render({
                            elem: '#' + id,
                            trigger: 'click',
                            options: {
                                timeStamp: false,
                                format: 'YYYY-MM-DD HH:ss:mm',
                            },
                        })
                    })
                }
            },
            date: function() {
                var list = document.querySelectorAll("*[lay-filter='date']");
                if (list.length > 0) {
                    $.each(list, function() {
                        var format = $(this).data('format'),
                            type = $(this).data('type'),
                            range = $(this).data('range');
                        if (type === undefined || type === '' || type == null) {
                            type = 'datetime'
                        }
                        var options = {
                            elem: this,
                            type: type,
                            trigger: 'click',
                            calendar: true,
                            theme: '#393D49'
                        };
                        if (format !== undefined && format !== '' && format != null) {
                            options['format'] = format
                        }
                        if (range !== undefined) {
                            if (range != null || range === '') {
                                range = '-'
                            }
                            options['range'] = range
                        }
                        laydate = layui.laydate ? layui.laydate : parent.layui.laydate;
                        laydate.render(options)
                    })
                }
            },
            addInput: function() {
                $(document).on('click', ".addInput", function() {
                    name = $(this).data('name');
                    verify = $(this).data('verify');
                    num = $(this).parents('.layui-form-item').siblings('.layui-form-item').length + 1;
                    var str = '<div class="layui-form-item">' + '<label class="layui-form-label"></label>' + '<div class="layui-input-inline">' + '<input type="text" name="' + name + '[key][' + num + ']" placeholder="key" class="layui-input input-double-width">' + '</div>' + '<div class="layui-input-inline">\n' + '<input type="text" id="" name="' + name + '[value][' + num + ']" lay-verify="required" placeholder="value" autocomplete="off" class="layui-input input-double-width">\n' + '</div>' + '<div class="layui-input-inline">' + '<button data-name="' + name + '" type="button" class="layui-btn layui-btn-danger layui-btn-sm removeInupt"><i class="layui-icon">&#xe67e;</i></button>' + '</div>' + '</div>';
                    $(this).parents('.layui-form-item').after(str)
                })
            },
            removeInupt: function() {
                $(document).on('click', ".removeInupt", function() {
                    var parentEle = $(this).parent().parent();
                    parentEle.remove()
                })
            },
            bindevent: function() {}
        },
        api: {
            bindEvent: function() {
                var events = Fu.events;
                events.icon();
                events.xmSelect();
                events.color();
                events.tags();
                events.city();
                events.date();
                events.timepicker();
                events.editor();
                events.regionCheck();
                events.addInput();
                events.removeInupt();
                events.bindevent()
            }
        }
    };
    return Fu
})