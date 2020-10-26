/**
 * 后台总控制js
 */
define(["jquery","lang",'toastr','moment'], function ($,Lang,Toastr,Moment) {
    let layer = layui.layer,
        table = layui.table;
    layer.config({
            skin: 'fun-layer-class'
        });
    Toastr= parent.Toastr || Toastr;
    Toastr.options = {
        closeButton:true,//显示关闭按钮
        debug:false,//启用debug
        positionClass:"toast-top-right",//弹出的位置,
        showDuration:"300",//显示的时间
        hideDuration:"1000",//消失的时间
        timeOut:"3000",//停留的时间
        extendedTimeOut:"1000",//控制时间
        showEasing:"swing",//显示时的动画缓冲方式
        hideEasing:"linear",//消失时的动画缓冲方式
        iconClass: 'toast-info', // 自定义图标，有内置，如不需要则传空 支持layui内置图标/自定义iconfont类名
        onclick: null, // 点击关闭回调
        showMethod: "fadeIn",
        hideMethod: "fadeOut"
    };
    let Fun = {
        url: function (url) {
            url = Fun.common.parseNodeStr(url)
            if (!Config.addonname) {
                if (url.indexOf(Config.entrance) === -1) {
                    return Config.entrance + $.trim(url, '/');
                } else {
                    return url;
                }
            } else if(Config.addonname && Config.modulename =='backend' &&　url.indexOf('ajax') !=-1){
                return Config.entrance + $.trim(url, '/');

            }else{
                return '/' + $.trim(url, '/');
            }

        },
        checkAuth: function (node) {
            // todo 有问题，先全部返回true
            if (Config.superAdmin === true) {
                return true;
            }
            node = Fun.common.parseNodeStr(node);
            return Config.authNode[node] !== undefined;

        },
        parame: function (param, defaultParam) {
            return param !== undefined ? param : defaultParam;
        },
        refreshmenu: function () {
            top.window.$("#layui-side-left-menu").trigger("refresh");
        },
        ajax: function (option, success, error, ex) {
            option.method = option.method || 'post';
            option.tips = option.tips || '';
            option.url = option.url || '';
            option.data = option.data || {};
            option.statusName = option.statusName || 'code';
            option.statusCode = option.statusCode || 1;
            success = success || function (res) {
                let msg = (res.msg === undefined && res.message === undefined) ? __('Return data is not right') : res.msg ? res.msg : res.message;
                Fun.toastr.success(msg);
                Fun.toastr.close()
                return false;
            };
            error = error || function (res) {
                let msg = (res.msg === undefined && res.message === undefined) ? __('Return data is not right') : res.msg ? res.msg : res.message;
                Fun.toastr.error(msg);
                return false;
            };
            ex = ex || function (res) {

            };
            if (option.url === '') {
                Fun.toastr.error(__('Request url can not empty'));
                return false;
            }
            // option.url = Fun.url(option.url);
            let index = Fun.toastr.loading(option.tips)
            $.ajax({
                url: option.url,
                type: option.method,
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                dataType: "json",
                data: option.data,
                timeout: 0,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                beforeSend: function (xhr,request) {
                    request.url = Fun.url(request.url);
                },
                success: function (res) {
                    Fun.toastr.close(index);
                    if (eval('res.' + option.statusName) >= option.statusCode) {
                        return success(res);
                    } else {
                        $("input[name='__token__']").val(res.data.token);
                        return error(res);
                    }
                },
                error: function (xhr) {
                    console.log(xhr);
                    let message = typeof xhr.responseJSON !== 'undefined' ? __(xhr.responseJSON.message) : __('，Try again later!');
                    Fun.toastr.error('Status:' + xhr.status + '\n' + message, function () {
                        $("input[name='__token__']").val(xhr.responseJson);
                        ex(this);
                    });
                    return false;
                },
                complete: function (xhr) {
                    let token = xhr.getResponseHeader('__token__');
                    if (token) {
                        $("input[name='__token__']").val(token);
                    }
                }
            });
            return true;
        },
        common: {
            parseNodeStr: function (node) {
                if (node.indexOf('/') === -1) {
                    node = Config.controllername + '/' + node;
                }
                let arrayNode = node.split('/');
                $.each(arrayNode, function (key, val) {
                    if (key === 0) {
                        val = val.split('.');
                        $.each(val, function (i, v) {
                            v = Fun.common.lower(Fun.common.camel(v));
                            val[i] = v.slice(0, 1).toLowerCase() + v.slice(1);
                        });
                        val = val.join(".");
                        arrayNode[key] = val;
                    }
                });
                node = arrayNode.join("/");
                return node;
            },
            //下划线变驼峰
            camel: function (name) {
                return name.replace(/_(\w)/g, function (all, letter) {
                    return letter.toUpperCase();
                });
            },
            //大写变下划线
            snake: function (name) {
                return name.replace(/([A-Z])/g, "_$1").toLowerCase();
            },
            //大写变小写
            lower:function (name){
                return name.toLowerCase();
            },
        },
        toastr: {
            // 成功消息
            success: function (msg, callback) {
                if (callback === undefined) {
                    callback = function () {

                    }
                }
                return Toastr.success(msg, callback);

            },
            // 失败消息
            error: function (msg, callback) {
                if (callback === undefined) {
                    callback = function () {

                    }
                }
                return Toastr.error(msg, callback);
            },
            // 警告消息框
            alert: function (msg, callback) {
                return Toastr.warning(msg, callback)
            },
            // 对话框
            confirm: function (msg, success, error) {
                let index = layer.confirm(msg, {
                    title: __('Are you sure'),
                    btn: [__('Confirm'), __('Cancel')]
                }, function () {
                    typeof success === 'function' && success.call(this);
                    Fun.toastr.close();
                }, function () {
                    typeof error === 'function' && error.call(this);
                    self.close(index);
                });
                return index;
            },
            // 消息提示
            tips: function (msg, callback) {
                return Toastr.info(msg, callback)

            },
            // 加载中提示
            loading: function (msg, callback) {
                return msg ? layer.msg(msg, {
                    icon: 16,
                    scrollbar: false,
                    shade: this.shade,
                    time: 0,
                    end: callback
                }) : layer.load(0, {time: 0, scrollbar: false, shade: this.shade, end: callback});
            },
            // 关闭消息框
            close: function (index) {
                if (index) {
                    return layer.close(index);

                } else {
                    return layer.closeAll();
                }
            }
        },

        events: {
            photos: function (othis) {
                let title = othis.attr('lay-photos'),
                    src = othis.attr('src'),
                    alt = othis.attr('alt');
                let photos = {
                    "title": title,
                    "id": Math.random(),
                    "data": [
                        {
                            "alt": alt,
                            "pid": Math.random(),
                            "src": src,
                            "thumb": src
                        }
                    ]
                };
                layer.photos({
                    photos: photos,
                    anim: 5
                });
                return false;
            },
            open: function (othis) {
                let options = {
                    title: othis.attr('lay-title'),
                    url: othis.attr('lay-url'),
                    width: othis.attr('lay-width'),
                    height: othis.attr('lay-height'),
                    isResize: othis.attr('lay-resize'),
                    full: othis.attr('lay-full'),

                };
                Fun.api.open(options)
            },
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
                let $win = $(window);
                let width = $win.width();
                return !(!isAndroid && !isIOS && !isWinPhone && width > 768);
            },
            open: function (options) {
                let title = options.title,
                    url = options.url, width = options.width,
                    height = options.height,
                    isResize = options.isResize === undefined;
                    isFull = options.full !== undefined;
                url = Fun.url(url)
                isResize = isResize === false ? true : isResize;
                width = width || '800';
                height = height || '600';
                width = width+'px';
                height = height+'px';
                if(isFull){
                    width = '100%';
                    height = '100%';
                }
                options = {
                    title: title,
                    type: 2,
                    area: [width,height],
                    content: url,
                    shadeClose: true,
                    anim: 0,
                    isOutAnim: true,
                    maxmin: true,
                    moveOut: true,
                    resize: isResize,
                    scrollbar: true,

                }
                let index = layer.open(options);
                if (Fun.api.checkScreen() || width === undefined || height === undefined) {
                    layer.full(index);
                }
                if (isFull) {
                    layer.full(index);
                }
                console.log(isResize)
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
        /**
         *
         * @returns {void|undefined|string|*}
         */
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
                var arr = string.split('.');
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

                let val;
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

        /**
         * 初始化
         */
        init: function () {
            // 绑定ESC关闭窗口事件
            $(window).keyup(function (event) {
                if (event.keyCode == 27) {
                    if ($(".layui-layer").length > 0) {
                        var index = 0;
                        $(".layui-layer").each(function () {
                            index = Math.max(index, parseInt($(this).attr("times")));
                        });
                        if (index) {
                           Fun.toastr.close(index)
                        }
                    }
                }
            });

        },
        /**
         *
         * @param formCallback
         * @param success
         * @param error
         * @param ex
         */
        bineEvent: function (formCallback, success, error, ex) {


        },


    };
    //初始化
    window.__ = Fun.lang;
    window.Toastr = Toastr;
    window.Moment = Moment;
    window.Fun = Fun;
    window.Fun.init();

    return Fun;
});