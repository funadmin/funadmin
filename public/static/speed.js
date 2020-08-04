/**
 * 后台总控制js
 */
define(["jquery","lang",'toastr'], function ($,Lang,Toastr) {
    var layer = layui.layer,
        table = layui.table;
        layer.config({
            skin: 'speed-layer-class'
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
    var Speed = {
        url: function (url) {
            if(!Config.addonname){
                if(url.indexOf(Config.entrance) ===-1){
                    return Config.entrance  + $.trim(url,'/');
                }else{
                    return url;
                }
            }else{
                return '/'+$.trim(url,'/');
            }

        },
        checkAuth: function (node) {
            // todo 有问题，先全部返回true
            if (Config.superAdmin == true) {
                return true;
            }
            node = Speed.common.parseNodeStr(node);
            var check = Config.authNode[node] == undefined ? false : true;
            return check;
        },
        parame: function (param, defaultParam) {
            return param != undefined ? param : defaultParam;
        },
        ajax: function (option, success, error, ex) {
            option.method = option.method || 'post';
            option.tips = option.tips || '';
            option.url = option.url || '';
            option.data = option.data || {};
            option.statusName = option.statusName || 'code';
            option.statusCode = option.statusCode || 1;
            success = success || function (res) {
                var msg = (res.msg == undefined && res.message==undefined) ? __('Return data is not right') : res.msg?res.msg:res.message;
                Speed.msg.success(msg);
                Speed.msg.close()
                return false;
            };
            error = error || function (res) {
                var msg = (res.msg == undefined && res.message==undefined) ? __('Return data is not right') : res.msg?res.msg:res.message;
                Speed.msg.error(msg);
                return false;
            };
            ex = ex || function (res) {

            };
            if (option.url == '') {
                Speed.msg.error(__('Request url can not empty'));
                return false;
            }
            option.url = Speed.url(option.url);
            var index = Speed.msg.loading(option.tips);
            $.ajax({
                url: option.url,
                type:option.method,
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                dataType: "json",
                data: option.data,
                timeout: 6000,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                beforeSend:function(xhr){

                },
                success: function (res) {
                    Speed.msg.close(index);
                    if (eval('res.' + option.statusName) >= option.statusCode) {
                        return success(res);
                    } else {
                        $("input[name='__token__']").val(res.data.token);
                        return error(res);
                    }
                },
                error: function (xhr, textstatus, thrown) {
                    var message = xhr.responseJSON.message?__(xhr.responseJSON.message): __('，Try again later!')
                    Speed.msg.error('Status:' + xhr.status +'\n'+ message , function () {
                        // $("input[name='__token__']").val(xhr.responseJson);
                        ex(this);
                    });
                    return false;
                },
                complete: function (xhr,textstatus, thrown) {
                    var token = xhr.getResponseHeader('__token__');
                    if (token) {
                        $("input[name='__token__']").val(token);
                    }
                }
            });
            return true;
        },
        common: {
            parseNodeStr: function (node) {
                if(node.indexOf('/')===-1){
                    node = Config.controllername+'/'+node;
                }
                var arrayNode = node.split('/');
                $.each(arrayNode, function (key, val) {
                    if (key == 0) {
                        val = val.split('.');
                        $.each(val, function (i, v) {
                            v = Speed.common.camel(v);
                            val[i] = v.slice(0,1).toLowerCase() + v.slice(1);
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
                return name.replace(/\_(\w)/g, function (all, letter) {
                    return letter.toUpperCase();
                });
            },
            //大写变下划线
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
                var index = Toastr.success(msg,callback);
                return index;
            },
            // 失败消息
            error: function (msg, callback) {
                if (callback == undefined) {
                    callback = function () {

                    }
                }
                var index = Toastr.error(msg,callback);
                return index;
            },
            // 警告消息框
            alert: function (msg, callback) {
                var index =  Toastr.warning(msg,callback)
                return index;
            },
            // 对话框
            confirm: function (msg, success, error) {
                var index = layer.confirm(msg, {title: __('Are you sure'), btn: [__('Confirm'), __('Cancel')]}, function () {
                    typeof success === 'function' && success.call(this);
                }, function () {
                    typeof error === 'function' && error.call(this);
                    self.close(index);
                });
                return index;
            },
            // 消息提示
            tips: function (msg, callback) {
                var index =  Toastr.info(msg,callback)
                return index;
            },
            // 加载中提示
            loading: function (msg, callback) {
                var index = msg ? layer.msg(msg, {icon:16, scrollbar: false, shade: this.shade, time: 0, end: callback}) : layer.load(0, {time: 0, scrollbar: false, shade: this.shade, end: callback});
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

        events:{
            photos: function (othis) {
                var title = othis.attr('lay-photos'),
                    src = othis.attr('src'),
                    alt = othis.attr('alt');
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
            open:function (othis) {
                var options = {
                    title:othis.attr('lay-title'),
                    url:Speed.url(othis.attr('lay-url')),
                    width:othis.attr('lay-width'),
                    height:othis.attr('lay-height'),
                    isResize:othis.attr('lay-isResize'),
                    full:othis.attr('lay-full'),

                }
                Speed.api.open(options)
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
                var ua = navigator.userAgent.toLocaleLowerCase();
                var pl = navigator.platform.toLocaleLowerCase();
                var isAndroid = (/android/i).test(ua) || ((/iPhone|iPod|iPad/i).test(ua) && (/linux/i).test(pl))
                    || (/ucweb.*linux/i.test(ua));
                var isIOS = (/iPhone|iPod|iPad/i).test(ua) && !isAndroid;
                var isWinPhone = (/Windows Phone|ZuneWP7/i).test(ua);
                var $win = $(window),$body = $('body'), container = $('#speed-app');
                var width = $win.width();
                if (!isAndroid && !isIOS && !isWinPhone && width > 768) {
                    return false;
                } else {
                    return true;
                }
            },
            open: function (options) {
                var title= options.title,
                    url = options.url, width=options.width,
                    height = options.height, isResize = options.isResize,
                    isFull = options.full;
                if (isResize == undefined) isResize = true;
                if (isFull == undefined) isFull = false;
                isResize = isResize == undefined ? true : isResize;
                width= width || '600';
                height= height || '600';
                width = width+'px';
                height = height+'px';
                options  = {
                    title: title,
                    type: 2,
                    area: [width, height],
                    content: url,
                    shadeClose : true,
                    anim : 0,
                    isOutAnim : true,
                    maxmin : true,
                    resize  : isResize,
                    scrollbar : true,

                }
                var index = layer.open(options);
                if (Speed.api.checkScreen() || width == undefined || height == undefined) {
                    layer.full(index);
                }
                if(isFull){
                    layer.full(index);
                    return false;
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
            var args = arguments,
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
                var current = Lang[arr[0]];
                for (var i = 1; i < arr.length; i++) {
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

                var val = null;
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

        bineEvent: function (formCallback, success, error, ex) {


        },

    };
    window.__ = Speed.lang;
    window.Toastr = Toastr;
    window.Speed = Speed;
    return Speed;
});