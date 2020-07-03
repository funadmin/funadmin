/**
 * 后台总控制js
 */
define(["jquery","lang"], function ($,Lang) {
    let form = layui.form,
        layer = layui.layer,
        table = layui.table,
        laydate = layui.laydate,
        upload = layui.upload,
        element = layui.element;

    let Speed = {
        config: {
            shade: [0.02, '#000'],
        },
        url: function (url) {

            return Config.moduleurl + '/' + url;
        },
        checkAuth: function (node) {
            // todo 有问题，先全部返回true
            if (Config.superAdmin == true) {
                return true;
            }
            node = Speed.common.parseNodeStr(node);
            let check = authNode[node] == undefined ? false : true;
            return check;
        },
        parame: function (param, defaultParam) {
            return param != undefined ? param : defaultParam;
        },
        ajax: function (option, success, error, ex) {
            option.method = option.method || 'post';
            option.url = option.url || '';
            option.data = option.data || {};
            option.prefix = option.prefix || false;
            option.statusName = option.statusName || 'code';
            option.statusCode = option.statusCode || 1;
            success = success || function (res) {

            };
            error = error || function (res) {
                let msg = (res.msg == undefined && res.message==undefined) ? __('Return data is not right') : res.msg?res.msg:res.message;
                Speed.msg.error(msg);
                return false;
            };
            ex = ex || function (res) {

            };
            if (option.url == '') {
                Speed.msg.error(__('Request url can not empty'));
                return false;
            }
            if (option.prefix == true) {
                option.url = Speed.url(option.url);
            }
            let index = Speed.msg.loading(__('loading'));
            $.ajax({
                url: option.url,
                type:option.method,
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                dataType: "json",
                data: option.data,
                timeout: 60000,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                beforeSend:function(res){
                    //请求前的处理
                },
                success: function (res) {
                    Speed.msg.close(index);
                    if (eval('res.' + option.statusName) == option.statusCode) {
                        return success(res);
                    } else {
                        return error(res);
                    }

                },
                error: function (xhr, textstatus, thrown) {
                    Speed.msg.error('Status:' + xhr.status + '，' + xhr.statusText + __('，Try again later!'), function () {
                        ex(this);
                    });
                    return false;
                },
                complete: function (xhr,textstatus, thrown) {
                    var responseText = xhr.responseText;
                    responseText = JSON.parse(responseText);
                    if (responseText.data['__token__']) {
                        let token = responseText.data['__token'];
                        $("input[name='__token__']").val(token);
                    }
                }
            });
            return true;
        },
        common: {
            parseNodeStr: function (node) {
                let array = node.split('/');
                $.each(array, function (key, val) {
                    if (key == 0) {
                        val = val.split('.');
                        $.each(val, function (i, v) {
                            val[i] = Speed.common.snake(v.replace(v[0], v[0].toLowerCase()));
                        });
                        val = val.join(".");
                        array[key] = val;
                    }
                });
                node = array.join("/");
                return node;
            },
            //驼峰
            camel: function (name) {
                return name.replace(/\_(\w)/g, function (all, letter) {
                    return letter.toUpperCase();
                });
            },
            //下划线
            snake: function (name) {
                return name.replace(/([A-Z])/g, "_$1").toLowerCase();
            },
        },
        msg: {
            // 成功消息
            success: function (msg, callback) {
                if (callback == undefined) {
                    callback = function () {

                    }
                }
                let index = layer.msg(msg, {icon: 1, shade: Speed.config.shade, scrollbar: false, time: 2000, shadeClose: true}, callback);
                return index;
            },
            // 失败消息
            error: function (msg, callback) {
                if (callback == undefined) {
                    callback = function () {

                    }
                }
                let index = layer.msg(msg, {icon: 2, shade: Speed.config.shade, scrollbar: false, time: 3000, shadeClose: true}, callback);
                return index;
            },
            // 警告消息框
            alert: function (msg, callback) {
                let index = layer.alert(msg, {end: callback, scrollbar: false});
                return index;
            },
            // 对话框
                confirm: function (msg, success, error) {
                let index = layer.confirm(msg, {title: __('Are you sure'), btn: [__('Confirm'), __('Cancel')]}, function () {
                    typeof success === 'function' && success.call(this);
                }, function () {
                    typeof error === 'function' && error.call(this);
                    self.close(index);
                });
                return index;
            },
            // 消息提示
            tips: function (msg, time, callback) {
                let index = layer.msg(msg, {time: (time || 3) * 1000, shade: this.shade, end: callback, shadeClose: true});
                return index;
            },
            // 加载中提示
            loading: function (msg, callback) {
                let index = msg ? layer.msg(msg, {icon:16, scrollbar: false, shade: this.shade, time: 0, end: callback}) : layer.load(0, {time: 0, scrollbar: false, shade: this.shade, end: callback});
                return index;
            },
            // 关闭消息框
            close: function (index) {
                if(index){
                    return layer.close(index);

                }else{
                    return layer.closeAll();
                }
            }
        },

        listen: function (formCallback, success, error, ex) {


        },

        //接口
        api: {
            /**
             * 检测屏幕
             * @returns {boolean}
             */
            checkScreen: function () {
                //屏幕类型 大小
                let ua = navigator.userAgent.toLocaleLowerCase();
                let pl = navigator.platform.toLocaleLowerCase();
                let isAndroid = (/android/i).test(ua) || ((/iPhone|iPod|iPad/i).test(ua) && (/linux/i).test(pl))
                    || (/ucweb.*linux/i.test(ua));
                let isIOS = (/iPhone|iPod|iPad/i).test(ua) && !isAndroid;
                let isWinPhone = (/Windows Phone|ZuneWP7/i).test(ua);
                let $win = $(window),$body = $('body'), container = $('#speed-app');
                let width = $win.width();
                if (!isAndroid && !isIOS && !isWinPhone && width > 768) {
                    return false;
                } else {
                    return true;
                }
            },
            open: function (title, url, width, height, isResize) {
                if (isResize == undefined) isResize = true;
                isResize = isResize == undefined ? true : isResize;
                width= width || '800px';
                height= height || '600px';
                options  = {
                    title: title,
                    type: 2,
                    area: [width, height],
                    content: url,
                    shadeClose : true,
                    anim : 5,
                    isOutAnim  : true,
                    maxmin   : true,
                    resize    : isResize,
                    scrollbar     : true,
                }
                let index = layer.open(options);
                if (Speed.api.checkScreen() || width == undefined || height == undefined) {
                    layer.full(index);
                }
                if (isResize) {
                    $(window).on("resize", function () {
                        layer.full(index);
                    })
                }
            },

            refreshiFrame: function () {
                parent.location.reload();
                return false;
            },
            refreshTable: function (tableName) {
                tableName = tableName | 'list';
                table.reload(tableName);
            },

        },
        //语言
        lang: function () {
            let args = arguments,
                string = args[0],
                i = 1;
            string = string.toLowerCase();
            //string = typeof Lang[string] != 'undefined' ? Lang[string] : string;
            if (typeof Lang !== 'undefined' && typeof Lang[string] !== 'undefined') {
                if (typeof Lang[string] == 'object')
                    return Lang[string];
                string = Lang[string];
            } else if (string.indexOf('.') !== -1 && false) {
                let arr = string.split('.');
                let current = Lang[arr[0]];
                for (let i = 1; i < arr.length; i++) {
                    current = typeof current[arr[i]] != 'undefined' ? current[arr[i]] : '';
                    if (typeof current != 'object')
                        break;
                }
                if (typeof current == 'object')
                    return current;
                string = current;
            } else {
                string = args[0];
            }
            return string.replace(/%((%)|s|d)/g, function (m) {

                let val = null;
                if (m[2]) {
                    val = m[2];
                } else {
                    val = args[i];
                    // A switch statement so that the formatter can be extended. Default is %s
                    switch (m) {
                        case '%d':
                            val = parseFloat(val);
                            if (isNaN(val)) {
                                val = 0;
                            }
                            break;
                    }
                    i++;
                }
                return val;
            });
        },

    };
    window.__ = Speed.lang;
    window.Speed = Speed;
    return Speed;
});