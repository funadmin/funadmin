// +----------------------------------------------------------------------
// | FunAdmin全栈开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code
// |  后台总控制API

define(["jquery", "lang",'toastr','dayjs'], function ($, Lang,Toastr,Dayjs) {
    var layer = layui.layer, element = layui.element;layer = layer || parent.layer;
    layui.layer.config({skin: 'fun-layer-class'});Toastr = parent.Toastr || Toastr;
    var Fun = {
        url: function (url) {
            url = url==undefined?location.href:url;
            var domain = window.location.host;
            if (url &&　url.indexOf(domain) !== -1) return url;
            var ajax_url = Config.publicAjaxUrl;tempurl = url.split('?') ;//公共url
            var suffix = tempurl[1]?"?"+tempurl[1]:'',prefix = tempurl[0];
            if($.inArray(prefix,ajax_url)!== -1) return Config.module+url
            prefix = prefix.indexOf('/')==0 ? prefix.replace('/',''):prefix;
            prefix = Fun.common.parseNodeStr(prefix);
            var n=(prefix.split('/')).length-1;
            if(n==0){
                url =  '/'+Config.appname+'/'+ Config.controllername+'/'+prefix+suffix;
            }else if(n==1){
                url =  '/'+Config.appname+'/'+prefix+suffix;
            }else {
                url =  '/' +prefix+suffix;
            }
            return url;
        },
        //替换ids
        replaceurl: function (url, d) {
            id = typeof d.primaryKeyValue !== 'undefined' ? d.primaryKeyValue : 0;
            //替换ids
            if (url) {
                url = url.indexOf('{ids}') !== -1 ? url.replace('{ids}', id) : url;
            }
            return url;
        },
        checkAuth: function (node,ele) {
            // 超管，全部权限
            if (Config.superAdmin === true) {
                return true;
            }
            if(node.indexOf('?')>=0) node = node.replace(/([?#])[^'"]*/, '');           //去除参数
            return $(ele).data('node-' + node.toLowerCase()) === 1;
        },
        param: function (param, defaultParam) {
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
            ex = ex || function (res) {};
            if (option.url === '') {
                Fun.toastr.error(__('Request url can not empty'));
                return false;
            }
            //false 同步 true 异步
            option.async = option.async===false ?option.async : true;
            // var index = Fun.toastr.loading(option.tips)
            $.ajax({
                url: option.url, type: option.method,
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                dataType: "json", data: option.data,
                async:option.async, timeout: 0,
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').prop('content')},
                beforeSend: function (xhr, request) {request.url = Fun.url(request.url);},
                success: function (res) {
                    if (eval('res.' + option.statusName) >= option.statusCode) {
                        return success(res);
                    } else {
                        return error(res);
                    }
                },
                error: function (xhr) {
                    var message = typeof xhr.responseJSON !== 'undefined' ? __(xhr.responseJSON.message) : __('，Try again later!');
                    Fun.toastr.error('Status:' + xhr.status + '\n' + message, function () {
                        var token = xhr.getResponseHeader('__token__');
                        if (token) {
                            $("input[name='__token__']").val(token);
                        }
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
            if(str !==null && str!==''){
                str = str.toString();
                str = str.toLowerCase();
            }
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
                if (event.keyCode == 27) {
                    var index = 0;
                    $(document).ready(function () {
                        if ($(".layui-layer").length > 0) {
                            $(".layui-layer").each(function () {
                                index = Math.max(index, parseInt($(this).attr("times")));
                            });
                        } else if (parent.$(".layui-layer").length) {
                            parent.$(".layui-layer").each(function () {
                                index = Math.max(index, parseInt($(this).attr("times")));
                            });
                        }
                        if (index) {
                            var confirm = top.layui.layer.confirm(__('are you sure you want to close this window'), {
                                btn: [__('confirm'), __('cancel')] //按钮
                            }, function () {
                                layui.layer.close(index);
                                top.layui.layer.close(confirm);
                                parent.layui.layer.close(index);
                            }, function () {
                            });
                        }
                    })
                    //锁屏
                    if($('#lock-screen').length>0 &&  !Fun.api.getStorage('BackendLock')){
                        $('#lock-screen').remove();
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
        bindEvent: function (formCallback, success, error, ex) {

        },
        common: {
            parseNodeStr: function (node) {
                if (node && node.indexOf('/') === -1) {
                    node = Config.controllername + '/' + node;
                }
                if(node!==undefined){
                    var arrayNode = node.split('/');
                    layui.each(arrayNode, function (key, val) {
                        if (key === 0) {
                            val = val.split('.');
                            layui.each(val, function (i, v) {
                                v = Fun.common.lower(Fun.common.snake(v));
                                val[i] = v.slice(0, 1).toLowerCase() + v.slice(1);
                            });
                            val = val.join(".");
                            arrayNode[key] = Fun.common.camel(val);
                        }
                    });
                    node = arrayNode.join("/");
                    return node;
                }
                return '';
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
            },
            //获取节点
            getNode:function (url){
                return url!==undefined && url!==''?url.substring(url.lastIndexOf('\/')+1,url.length):'';
            }
        },
        toastr: {
            //callback 函数
            // position: 'topCenter', // 弹出位置bottomRight, bottomLeft, topRight, topLeft, topCenter, bottomCenter, center
            // duration: 2000, //默认2秒关闭
            // showClose: true //显示关闭按钮
            // 成功消息
            success: function (msg, callback,duration,position,showClose) {
                return Toastr.success(msg,callback,duration,position,showClose);
            },
            // 失败消息
            error: function (msg, callback,duration,position,showClose) {
                return Toastr.error(msg,callback,duration,position,showClose);
            },
            info:function(msg, callback,duration,position,showClose) {
                return Toastr.info(msg,callback,duration,position,showClose);
            },
            warning:function(msg, callback,duration,position,showClose) {
                return Toastr.warning(msg,callback,duration,position,showClose);
            },
            // 警告消息框
            alert: function (msg, callback,duration,position,showClose) {
                return Toastr.warning(msg,callback,duration,position,showClose);
            },
            // 消息提示
            tips: function (msg, callback,duration,position,showClose) {
                return Toastr.info(msg,callback,duration,position,showClose);
            },
            // 加载中提示
            loading: function (msg, callback,duration,position,showClose) {
                return Toastr.loading(msg,callback,duration,position,showClose);
            },
            // 对话框
            confirm: function (msg, success, error) {
                var index = layui.layer.confirm(msg, {
                    title: __('Are you sure'),
                    icon: 3,
                    btn: [__('Confirm'), __('Cancel')]
                }, function () {
                    typeof success === 'function' && success.call(this);
                    Fun.toastr.close(index);
                }, function () {
                    typeof error === 'function' && error.call(this);
                });
                return index;
            },
            // 关闭消息框
            close: function (index) {
                if (index) {layui.layer.close(index);} else {layui.layer.closeAll();}
                return Toastr.destroyAll(); //全部关闭

            }
        },
        /**
         * 事件
         */
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
                layui.layer.photos({
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
                    isResize: data.resize,
                    full: data.full,
                    btn: data.btn,
                    anim:data.anim,
                    offset: data.offset,
                    btnAlign: data.btnAlign,
                    autoheight: data.autoheight,
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
            request: function (othis, options,Table) {
                var data = othis.data(),value;
                if (options) {
                    title = options.title;
                    url = options.url;
                    tableId = options.tableId || Table.init.tableId
                } else {
                    var title = data.confirm ||  othis.prop('confirm') ||  othis.prop('text') || data.text || othis.prop('title') || data.title  , url = data.url ? data.url : data.href,
                        tableId = data.tableid;
                    title = title || 'Are you sure to do this';
                    url = url !== undefined ? url : window.location.href;
                    tableId = tableId || Table.init.tableId, value = data.value;
                }
                ids = '';
                if(Table){
                    arr = Table.getIds(url, tableId);
                    ids = arr[0];
                    length = arr[1];
                }
                postdata = {ids:ids};if(value){postdata.value = value}
                Fun.toastr.confirm(__(title), function () {
                    Fun.ajax({url: url, data: postdata}, function (res) {
                        Fun.toastr.success(res.msg, function () {
                            Table && Table.api.reload(tableId)
                        })
                    }, function (res) {
                        Fun.toastr.error(res.msg, function () {
                            Table && Table.api.reload(tableId)
                        })
                    })
                    Fun.toastr.close()
                }, function (res) {
                    if (res === undefined) {
                        Fun.toastr.close();
                        return false
                    }
                    Fun.toastr.success(res.msg, function () {
                        Table && Table.api.reload(tableId)
                    })
                });
                return false
            },
            dropdown: function (othis,rowData,tableOption) {
                var data = $(othis).data(); extend = data.extend;
                var dropdowndata = [];
                if (typeof extend === 'object') {
                    ele = '';d= '';
                    if(rowData){ele = rowData.config;d = rowData.data;}
                    layui.each(extend, function (k, v) {
                        v.class = v['class'] || '';
                        v.title = v.title || v.text;
                        v.event = v.event || v.type;
                        url = v.url ? v.url : $(othis).attr('data-url');
                        node = Fun.common.getNode(url);
                        if (ele) {
                            if(url.indexOf('?')>=0){
                                url = url+"&"+ele.primaryKey+'='+d[ele.primaryKey];
                            } else {
                                url = url+"?"+ele.primaryKey+'='+d[ele.primaryKey];
                            }
                        }
                        if(Fun.checkAuth(node,ele.elem || tableOption.elem)){
                            dropdowndata[k] = extend[k];
                            dropdowndata[k].rowindex = k;
                            dropdowndata[k].buttonsindex = data.buttonsindex;
                            dropdowndata[k].url = url;
                            dropdowndata[k].class =v.class || '';
                            dropdowndata[k].id = v.id || v.event ;
                            dropdowndata[k].callback = v.callback || '';
                            dropdowndata[k].extend = v.extend || '';
                            dropdowndata[k].type = v.type || 'normal';
                            dropdowndata[k].target = v.target || '_self';
                            dropdowndata[k].child = v.child || [];
                            dropdowndata[k].textTitle = v.title
                            dropdowndata[k].icon = v.icon || '';
                            dropdowndata[k].field = v.field || '';
                            dropdowndata[k].value = v.value || '';
                            icon = extend[k].icon ? '<i class="{{d.icon}}"></i>':'';
                            dropdowndata[k].templet = v.templet ||  "<a data-value='{{d.value}}' data-field='{{d.field}}' data-id='{{d.id}}' lay-event='{{d.event}}' data-url='{{d.url}}' class='{{d.class}}' title='{{d.title}}'>" +icon+' {{d.title}}  </a>';
                            dropdowndata[k].title =v.title ;
                        }
                    })
                    var inst = layui.dropdown.render({
                        elem: othis, show: true, data: dropdowndata, click: function (row, _that) {
                            attrEvent = row.event;
                            data.title = row.textTitle;
                            data.rowindex = row.rowindex;
                            buttons = rowData?rowData['data']['buttons']:tableOption['buttons'];
                            buttons = Fun.api.getButtons(buttons ,data.buttonsindex,data.rowindex);
                            console.log(buttons)
                            callback = buttons.extend[data.rowindex].callback;
                            require(['table'], function (Table) {
                                if (Table.events.hasOwnProperty(attrEvent)) {
                                    Table.events[attrEvent].call(this, _that.find('button'),data,rowData,tableOption)
                                }else if(data.callback){
                                    eval(data.callback)(_that,data,rowData,tableOption)
                                }else if(typeof callback ==='function'){
                                    callback(_that,data,rowData,tableOption)
                                }else if(callback && typeof callback ==='string'){
                                    eval(callback)(_that,data,rowData,tableOption)
                                }else{
                                    eval(data.event)(_that,data,rowData,tableOption)
                                }
                            })

                        }, style: 'box-shadow: 1px 1px 10px rgb(0 0 0 / 12%);'
                    })
                    inst.reload();//点击后需要重载才不会隐藏
                    return false;
                }
            },
        },
        /*
        接口
         */
        api: {
            setStorage: function(key,value) {
                if (value != null && value !== "undefined") {
                    layui.data(key,{
                        key: key,
                        value:value
                    })
                }else {
                    layui.data(key,{
                        key: key,
                        remove:true
                    })
                }
            },
            getStorage: function(key) {
                var array = layui.data(key);
                if (array) {
                    return array[key]
                } else {
                    return false
                }
            },
            //设置子页面主题
            setFrameTheme:function(body){
                var colorId = Fun.api.getStorage('funColorId');
                colorId = colorId?colorId:0;theme = 'theme'+colorId;
                if(Fun.api.getStorage('setFrameTheme')){
                    var iframe = $("#layui-tab .layui-tab-item").find("iframe");
                    for (var i = 0; i < iframe.length; i++) {
                        $(iframe[i]).on('load',function(){
                            //此处加载时必须要
                            $(this).contents().find('body').attr('id', theme);
                        })
                        $(iframe[i]).contents().find('body').attr('id', theme);
                    }
                    //打开弹窗
                    if(body){
                        body.attr('id', theme);
                    }
                    layui.$('iframe').contents().find('body').attr('id',theme);
                }
                //测试
                themeData = Fun.api.getStorage('setFrameTheme');
                if(themeData){
                    $('body').addClass('active');
                }else{
                    $('body').removeClass('active');
                }
                top.layui.$('body').attr('id',theme);
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
                var title = options.title, url = options.url, width = options.width,
                    height = options.height, success = options.success, cancel = options.cancel,end=options.end,
                yes = options.yes, type = options.type, autoheight = options.autoheight;
                type = type === undefined || type===2  ? 2 : 1;
                var isResize = (options.isResize === undefined);
                var isFull = !!options.full;url = type===2?Fun.url(url):url;
                isResize = isResize === false ? true : isResize;
                width = width || '800';height = height || '100%';
                width =  /%|px/.test(width)?width:$(window).width()+20 >= width ? width + 'px' :'95%';
                height = /%|px/.test(height)?height:($(window).height()+110)>=height?height + 'px' :'100%';
                autoheight = autoheight ? true:false;
                offset= options.offset!==undefined? options.offset :'r'; anim = options.anim!==undefined?options.anim : 'slideLeft';
                if (isFull) {width = '100%';height = '100%';}
                var btns = [];
                if (options.btn == undefined) {
                    btns = ['submit', 'close'];
                    options.btn_lang = [__('submit'), __('close')];
                } else if (options.btn === 'false' || options.btn === false || options.btn === '') {
                    options.btn_lang = false;
                } else {
                    btnsdata = options.btn;
                    btnsdata = layui.isArray(btnsdata)?btnsdata:btnsdata.split(',');
                    options.btn_lang = [];
                    layui.each(btnsdata, function (k, v) {
                        options.btn_lang[k] = __(v);
                        btns.push(v);
                    })
                }
                if (options.btnAlign === undefined) {
                    options.btnAlign = 'c';
                }
                if (options.btn_lang === []) options.btn_lang = false;
                var parentiframe = Fun.api.checkLayerIframe();
                opt = $.extend(options ? options : {},{
                    title: title, type: type, area: [width, height], content: url,
                    shadeClose: true,offset: offset, anim: anim, shade: 0.1, isOutAnim: true,
                    zIndex: parent.layui.layer.zIndex, //
                    maxmin: true, moveOut: true, resize: isResize, scrollbar: true,
                    btnAlign: options.btnAlign, btn: options.btn_lang,
                    success: success === undefined ? function (layero,index) {
                        var that = this;
                        try {
                            $(layero).data("callback", that.callback?that.callback:'');
                            // 置顶当前窗口
                            parent.layui.layer.setTop(layero);
                            if(autoheight) parent.layui.layer.iframeAuto(index) //- 指定iframe层自适应
                            // 将保存按钮改变成提交按钮
                            layero.addClass('layui-form');
                            layero.find('.layui-layer-btn.layui-layer-btn-c').css('background', '#f3f6f6');
                            body = layero.find('iframe').contents().find('body')
                            Fun.api.setFrameTheme(body);
                        } catch (err) {
                            console.log(err)
                            //在此处理错误
                        }
                    } : success,
                    yes: yes === undefined ? function (index, layero) {
                        try {
                            //此处必须是close才直接关闭
                            if(btns.length==1 && btns[0]=='close'){layui.ayer.close(index);return false;}
                            $(document).ready(function () {// 父页面获取子页面的iframe
                                var body = layui.layer.getChildFrame('body', index);
                                if (parentiframe) {body = parent.layui.layer.getChildFrame('body', index);}
                                body.find('button[type="' + btns[0] + '"]').trigger('click');
                                body.find('.layui-hide').hide();
                            })
                        } catch (err) {
                            layui.layer.close(index);
                        }
                        return false;
                    } : yes,
                    cancel: cancel === undefined? function (index, layero) {
                        layui.layer.close(layer.index);
                    }:cancel,
                    end:end === undefined?function(index, layero){
                        layui.layer.close(layer.index);
                    }:end,
                })
                //增加多个按钮
                if(btns.length>1){
                    for (i=1;i<btns.length;i++) {
                        if(i==btns.length-1){
                            opt['btn'+(i+1)] =  function (index, layero) {
                                layui.layer.close(layer.index);
                            };
                        }else{
                            try {
                                var func = btns[i];
                                opt['btn'+(i+1)] = opt['btn'+(i+1)] || eval(func);
                                } catch(e) {
                                    console.log('function '+ func + ' not exists');
                                }
                        }
                    }
                }
                var index =  parentiframe? parent.layui.layer.open(opt): layui.layer.open(opt);
                if (Fun.api.checkScreen() || width === undefined || height === undefined) {
                    layui.layer.full(index);
                }
                if (isFull) {
                    layui.layer.full(index);
                }
                if (isResize) {
                    $(window).on("resize", function () {
                        layui.layer.full(index);
                    })
                }
            },
            /**
             * 关闭当前弹窗
             */
            close:function(index,type){
                index =  index === undefined? parent.layui.layer.getFrameIndex(window.name):index;
                type === 1? parent.layui.layer.closeAll(): parent.layui.layer.close(index)
                return true;
            },
            /**
             * 关闭窗口并回传数据
             * @param data
             */
            closecallback: function(data) {
                var index = parent.layui.layer.getFrameIndex(window.name);
                var callback = parent.$("#layui-layer" + index).data("callback");
                //再执行关闭
                parent.layui.layer.close(index);
                //再调用回传函数
                if (typeof callback === 'function') {
                    callback.call(undefined, data);
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
                    options = {layId: layId, text: text, url: url, icon: icon, iframe: iframe};
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
            refreshTable: function (tableId) {
                tableId = tableId || 'list';
                table = layui.table || layui.treeTable;
                table.reload(tableId,{},true);
            },
            //获取同步数据
            getData: function (url,data,method,async) {
                method = method?method:"GET";
                async = typeof async!=='undefined'?async:true;
                if(!url) return false;
                var returnData;
                Fun.ajax({url:Fun.url(url),data:data,method:method,async:async},function(res){
                    returnData = res.data;
                },function(res){
                    return false;
                })
                return returnData;
            },
            getButtons:function(buttons,buttonsindex){
                if(buttons && buttons[buttonsindex]){
                    return buttons[buttonsindex];
                }
                return '';
            },
            callback:function (othis,rowData,tableOption){
                var data = othis.data(),callback = data.callback;
                if (callback && typeof callback === 'string') {
                    eval(callback)(othis,rowData,tableOption);
                }else if(!callback && ( rowData || tableOption)){
                    var buttons = rowData?rowData['data']['buttons']:tableOption['buttons'];
                    var button = Fun.api.getButtons(buttons ,data.buttonsindex,data.rowindex);
                    if(!button) return true;
                    callback = button.callback;
                    if(!callback) return true;
                    if(typeof callback === 'string'){
                        eval(callback)(othis,rowData,tableOption);
                    }else if(typeof callback === 'function'){
                       callback(othis,rowData,tableOption);
                    }
                }
                return true;
            },
        },
    };
    //初始化
    window.__ = Fun.lang;
    window.Toastr = Toastr;
    window.Dayjs = Dayjs;
    window.Fun = Fun;
    window.Fun.init();
    return Fun;
});
