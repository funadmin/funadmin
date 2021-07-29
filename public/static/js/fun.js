/**
 @ www.funadmin.com
 @ Name：fun.js
 @ Author：yuege
 */
/*** 后台总控制API*/
define(["jquery", "lang", 'toastr', 'moment'], function ($, Lang, Toastr, Moment) {
    var layer = layui.layer, element = layui.element;
    layer = layer || parent.layer;
    layui.layer.config({
        skin: 'fun-layer-class'
    });
    Toastr = parent.Toastr || Toastr;
    Toastr.options = {
        closeButton: true,//显示关闭按钮
        debug: false,//启用debug
        positionClass: "toast-top-right",//弹出的位置,
        showDuration: "300",//显示的时间
        hideDuration: "1000",//消失的时间
        timeOut: "3000",//停留的时间
        extendedTimeOut: "1000",//控制时间
        showEasing: "swing",//显示时的动画缓冲方式
        hideEasing: "linear",//消失时的动画缓冲方式
        iconClass: 'toast-info', // 自定义图标，有内置，如不需要则传空 支持layui内置图标/自定义iconfont类名
        onclick: null, // 点击关闭回调
        showMethod: "fadeIn",
        hideMethod: "fadeOut",
    };
    var Fun = {
        url: function (url) {
            var domain = window.location.host;
            if (url.indexOf(domain) !== -1) {
                return url;
            }
            url = Fun.common.parseNodeStr(url);
            if (!Config.addonname) {
                if (Config.entrance !== '/' && url.indexOf(Config.entrance) === -1) {
                    url = url.indexOf('/')===0?url.replace('/',''):url;
                    return Config.entrance + url;
                } else  {
                    return url;
                }
            } else {
                url = url.indexOf('/')===0?url.replace('/',''):url
                if (Config.addonname && Config.modulename === 'backend' && url.indexOf('ajax') !== -1) {
                    return Config.entrance + url;
                } else {
                    url = url.indexOf('/')===0?url.replace('/',''):url
                    return '/' + url;
                }
            }
        },
        //替换ids
        replaceurl: function (url, d) {
            d.id = typeof d.id !== 'undefined' ? d.id : 0;
            //替换ids
            if (url) {
                url = url.indexOf('{ids}') !== -1 ? url.replace('{ids}', d.id) : url;
            }
            return url;
        },
        checkAuth: function (node) {
            // 超管，全部权限
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
                var msg = (res.msg === undefined && res.message === undefined) ? __('Return data is not right') : res.msg ? res.msg : res.message;
                Fun.toastr.success(msg);
                Fun.toastr.close();
                return false;
            };
            error = error || function (res) {
                var msg = (res.msg === undefined && res.message === undefined) ? __('Return data is not right') : res.msg ? res.msg : res.message;
                Fun.toastr.error(msg);
                return false;
            };
            ex = ex || function (res) {

            };
            if (option.url === '') {
                Fun.toastr.error(__('Request url can not empty'));
                return false;
            }
            // var index = Fun.toastr.loading(option.tips)
            $.ajax({
                url: option.url,
                type: option.method,
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                dataType: "json",
                data: option.data,
                timeout: 0,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').prop('content')
                },
                beforeSend: function (xhr, request) {
                    request.url = Fun.url(request.url);
                },
                success: function (res) {
                    // if(index){
                    //     Fun.toastr.close(index);
                    // }
                    if (eval('res.' + option.statusName) >= option.statusCode) {
                        return success(res);
                    } else {
                        if (res.hasOwnProperty('data') && res.data['token']) {
                            $("input[name='__token__']").val(res.data.token);
                        }
                        return error(res);
                    }
                },
                error: function (xhr) {
                    console.log(xhr);
                    var message = typeof xhr.responseJSON !== 'undefined' ? __(xhr.responseJSON.message) : __('，Try again later!');
                    Fun.toastr.error('Status:' + xhr.status + '\n' + message, function () {
                        $("input[name='__token__']").val(xhr.responseJson);
                        ex(this);
                    });
                    return false;
                },
                complete: function (xhr) {
                    var token = xhr.getResponseHeader('__token__');
                    if (token) {
                        $("input[name='__token__']").val(token);
                    }
                }
            });
            return true;
        },
        /**
         * @returns {*}
         */
        lang: function () {
            var obj = arguments, str = arguments[0];
            str = str.toString();
            str = str.toLowerCase();
            if (typeof Lang !== 'undefined' && typeof Lang[str] !== 'undefined') {
                str = Lang[str];
            } else {
                str = obj[0];
            }
            if (typeof obj[1] === 'object') {
                for (i = 0; i < obj[1].length; i++) {
                    str= str.replace(/%((%)|s|d)/g, obj[1][i]);
                }
            } else if (typeof obj[1]=== 'string' || typeof obj[1]=== 'number' ) {
                str = str.replace(/%((%)|s|d)/g, obj[1]);
            } else {
                str;
            }
            return str;
        },
        /**
         *inti 初始化
         */
        init: function () {
            // 绑定ESC关闭窗口事件
            $(window).keyup(function (event) {
                if (event.keyCode === 27 || event.keyCode === '27') {
                    var index = 0;
                    $(document).ready(function () {
                        if ($(".layui-layer").length > 0) {
                            $(".layui-layer").each(function () {
                                index = Math.max(index, parseInt($(this).attr("times")));
                            });
                            if (index) {
                                layer.close(index);
                            }
                        } else if (parent.$(".layui-layer").length) {
                            parent.$(".layui-layer").each(function () {
                                index = Math.max(index, parseInt($(this).attr("times")));
                            });
                            if (index) {
                                parent.layer.close(index);
                            }
                        }
                    })
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
        bindEvent: function (formCallback, success, error, ex) {

        },
        common: {
            parseNodeStr: function (node) {
                if (node.indexOf('/') === -1) {
                    node = Config.controllername + '/' + node;
                }
                var arrayNode = node.split('/');
                $.each(arrayNode, function (key, val) {
                    if (key === 0) {
                        val = val.split('.');
                        $.each(val, function (i, v) {
                            v = Fun.common.lower(Fun.common.snake(v));
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
            lower: function (name) {
                return name.toLowerCase();
            },
            arrTostr: function (arr) {
                var str = '';
                for (i = 0; i < arr.length; i++) {
                    str += arr[i]['name'] + ',';
                }
                return str.substring(0, str.length - 1);
            }
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
                return Toastr.warning(msg, callback);
            },
            // 对话框
            confirm: function (msg, success, error) {
                var index = layer.confirm(msg, {
                    title: __('Are you sure'),
                    icon: 3,
                    btn: [__('Confirm'), __('Cancel')]
                }, function () {
                    typeof success === 'function' && success.call(this);
                    Fun.toastr.close(index);
                }, function () {
                    typeof error === 'function' && error.call(this);
                    self.close(index);
                });
                return index;
            },
            // 消息提示
            tips: function (msg, callback) {
                return Toastr.info(msg, callback);
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
        //事件
        events: {
            photos: function (othis) {
                var title = othis.prop('title'),
                    src = othis.prop('src'),
                    alt = othis.prop('alt');
                var photos = {
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
                var data = othis.data();
                var options = {
                    title: othis.prop('title') ? othis.prop('title') : data.title,
                    url: data.url ? data.url : data.href,
                    width: data.width,
                    height: data.height,
                    isResize: data.title,
                    full: data.full,
                    btn: data.btn,
                    btnAlign: data.btnAlign,
                };
                Fun.api.open(options);
            },
            iframe:function(othis){
                var _that = othis
                    , url = _that.data('url') ? _that.data('url') : _that.data('iframe')
                    , layId = _that.attr('data-id')
                    , text =  _that.attr('title') || _that.data('text') || _that.attr('lay-tips')
                    , icon = _that.find('i').attr('class') || 'layui-icon layui-icon-radio'
                    , iframe = !!_that.has('data-iframe'),
                    target = _that.prop('target') || '_self';
                options = {url:url, layId:layId, text:text, icon:icon, iframe:iframe, target:target,}
                Fun.api.iframe(options)
            },
        },
        //接口
        api: {
            /**
             * 关闭当前弹窗
             */
            close:function(index,type=0){
                index =  index === undefined? parent.layer.getFrameIndex(window.name):index;
                type === 1? parent.layer.closeAll(): parent.layer.close(index)
                return true;
            },
            /**
             * 检测屏幕是否手机
             * @returns {boolean}
             */
            checkScreen: function () {
                //屏幕类型 大小
                var ua = navigator.userAgent.toLocaleLowerCase();
                var pl = navigator.platform.toLocaleLowerCase();
                var isAndroid = (/android/i).test(ua) || ((/iPhone|iPod|iPad/i).test(ua) && (/linux/i).test(pl)) || (/ucweb.*linux/i.test(ua));
                var isIOS = (/iPhone|iPod|iPad/i).test(ua) && !isAndroid;
                var isWinPhone = (/Windows Phone|ZuneWP7/i).test(ua);
                var $win = $(window);
                var width = $win.width();
                return !(!isAndroid && !isIOS && !isWinPhone && width > 768);
            },
            //检测上级是否有窗口
            checkLayerIframe: function () {
                return !!parent.$(".layui-layer").length;
            },
            //打开新窗口
            open: function (options) {
                var title = options.title,
                    url = options.url, width = options.width,
                    height = options.height,
                    success = options.success,
                    yes = options.yes,
                    btn2 = options.btn2,
                    type = options.type;
                type = type === undefined || type===2  ? 2 : 1;
                isResize = options.isResize === undefined;
                isFull = !!options.full;
                url = type===2?Fun.url(url):url;
                isResize = isResize === false ? true : isResize;
                width = width || '800';
                height = height || '600';
                width = width + 'px';
                height = height + 'px';
                if (isFull) {width = '100%';height = '100%';}
                var btns = [];
                if (options.btn === undefined) {
                    btns = ['submit', 'close'];
                    options.btn_lang = [__('submit'), __('close')];
                } else if (options.btn === 'false' || options.btn === false || options.btn === '') {
                    options.btn_lang = false;
                } else {
                    btnsdata = options.btn;
                    btnsdata = (btnsdata.split(','));
                    options.btn_lang = [];
                    $.each(btnsdata, function (k, v) {
                        options.btn_lang[k] = __(v);
                        btns.push(v);
                    })
                }
                if (options.btnAlign === undefined) {
                    options.btnAlign = 'c';
                }
                if (options.btn_lang === []) options.btn_lang = false;
                var parentiframe = Fun.api.checkLayerIframe()
                options = {
                    title: title,
                    type: type,
                    area: [width, height],
                    content: url,
                    shadeClose: true,
                    anim: 0,
                    shade: 0.1,
                    isOutAnim: true,
                    // zIndex: layer.zIndex, //
                    maxmin: true,
                    moveOut: true,
                    resize: isResize,
                    scrollbar: true,
                    btnAlign: options.btnAlign,
                    btn: options.btn_lang,
                    success: success === undefined ? function (layero) {
                        try {
                            // 置顶当前窗口
                            layer.setTop(layero);
                            // 将保存按钮改变成提交按钮
                            layero.addClass('layui-form');
                            layero.find('.layui-layer-btn.layui-layer-btn-c').css('background', '#f3f6f6');
                        } catch (err) {
                            //在此处理错误
                        }
                    } : success,
                    yes: yes === undefined ? function (index, layero) {
                        try {
                            $(document).ready(function () {
                                // 父页面获取子页面的iframe
                                var body = layer.getChildFrame('body', index);
                                if (parentiframe) {
                                    body = parent.layer.getChildFrame('body', index);
                                }
                                body.find('button[type="' + btns[0] + '"]').trigger('click');
                                body.find('.layui-hide').hide();
                            })
                        } catch (err) {
                            layer.close(index);
                        }
                        return false;
                    } : yes,
                    btn2: btn2 === undefined ? function (index) {
                        layer.close(layer.index);
                    } : btn2,
                    cancel: function (index, layero) {
                        layer.close(layer.index);
                    }
                }
                var index =  parentiframe? parent.layer.open(options): layer.open(options);
                if (Fun.api.checkScreen() || width === undefined || height === undefined) {
                    layer.full(index);
                }
                if (isFull) {
                    layer.full(index);
                }
                if (isResize) {
                    $(window).on("resize", function () {
                        layer.full(index);
                    })
                }
            },
            //打开iframe
            iframe:function(options){
                var url = options.url
                    , layId = options.layId || options.url
                    , text = options.tips || options.title || options.text
                    , icon = options.icon || 'layui-icon layui-icon-radio'
                    , iframe =  options.iframe || false,
                    target = options.target || '_self';
                url = Fun.url(url);
                if (!layId) {
                    return false;
                } else {
                    if (target === '_blank') {
                        window.open(url, "_blank");
                        return false;
                    }
                    let options = {layId: layId, text: text, url: url, icon: icon, iframe: iframe};
                    layui.Backend.addTab(options);
                    if (layui.Backend.checkScreen()) {
                        $container.removeClass(SIDE_SHRINK).addClass('fun-app')
                    }
                }
            },
            refreshIframe: function () {
                parent.location.reload();
                return false;
            },
            refreshTable: function (tableName) {
                tableName = tableName | 'list';
                layui.table.reload(tableName,{},true);
            },
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
