layui.define(["jquery",'layer'], function (exports) {
    var $ = layui.$,
        element = layui.element,
        layer = layui.layer,
        device = layui.device();
    if (!/http(s*):\/\//.test(location.href)) {
        var tips = "请先将项目部署至web容器（Apache/Tomcat/Nginx/IIS/等），否则部分数据将无法显示";
        return layer.alert(tips);
    }
    var $win = $(window),$body = $('body'), $container = $('#speed-app');
    var  THIS = 'layui-this', ICON_SPREAD = 'layui-icon-spread-left', SIDE_SHRINK = 'layui-side-shrink';
     //主题配置
    var THEME = [
        {
            headerRight: '#1aa094',
            headerRightThis: '#197971',
            headerLogo: '#0c0c0c',
            menuLeft: '#23262e',
            menuLeftThis: '#1aa094',
            menuLeftHover: '#1aa094',
        },
        {
            headerRight: '#1aa094',
            headerRightThis: '#197971',
            headerLogo: '#1aa094',
            menuLeft: '#2f4056',
            menuLeftThis: '#1aa094',
            menuLeftHover: '#3b3f4b',
        },
        {
            headerRight: '#722ed1',
            headerRightThis: '#197971',
            headerLogo: '#722ed1',
            menuLeft: '#2f4056',
            menuLeftThis: '#722ed1',
            menuLeftHover: '#722ed1',
        },
        {
            headerRight: '#00a65a',
            headerRightThis: '#197971',
            headerLogo: '#00a65a',
            menuLeft: '#222d32',
            menuLeftThis: '#00a65a',
            menuLeftHover: '#00a65a',
        },
        {
            headerRight: '#23262e',
            headerRightThis: '#0c0c0c',
            headerLogo: '#0c0c0c',
            menuLeft: '#23262e',
            menuLeftThis: '#1aa094',
            menuLeftHover: '#3b3f4b',
        },
        {
            headerRight: '#ffa4d1',
            headerRightThis: '#bf7b9d',
            headerLogo: '#e694bd',
            menuLeft: '#1f1f1f',
            menuLeftThis: '#ffa4d1',
            menuLeftHover: '#ffa4d1',
        },
        {
            headerRight: '#1e9fff',
            headerRightThis: '#0069b7',
            headerLogo: '#0c0c0c',
            menuLeft: '#1f1f1f',
            menuLeftThis: '#1e9fff',
            menuLeftHover: '#1e9fff',
        },

        {
            headerRight: '#ffb800',
            headerRightThis: '#d09600',
            headerLogo: '#243346',
            menuLeft: '#2f4056',
            menuLeftThis: '#ffb800',
            menuLeftHover: '#ffb800',
        },
        {
            headerRight: '#e82121',
            headerRightThis: '#ae1919',
            headerLogo: '#0c0c0c',
            menuLeft: '#1f1f1f',
            menuLeftThis: '#e82121',
            menuLeftHover: '#e82121',
        },
        {
            headerRight: '#963885',
            headerRightThis: '#772c6a',
            headerLogo: '#243346',
            menuLeft: '#2f4056',
            menuLeftThis: '#963885',
            menuLeftHover: '#963885',
        },

        {
            headerRight: '#1e9fff',
            headerRightThis: '#0069b7',
            headerLogo: '#0069b7',
            menuLeft: '#1f1f1f',
            menuLeftThis: '#1e9fff',
            menuLeftHover: '#1e9fff',
        },
        {
            headerRight: '#ffb800',
            headerRightThis: '#d09600',
            headerLogo: '#d09600',
            menuLeft: '#2f4056',
            menuLeftThis: '#ffb800',
            menuLeftHover: '#ffb800',
        },
        {
            headerRight: '#e82121',
            headerRightThis: '#ae1919',
            headerLogo: '#d91f1f',
            menuLeft: '#1f1f1f',
            menuLeftThis: '#e82121',
            menuLeftHover: '#e82121',
        },
        {
            headerRight: '#e82121',
            headerRightThis: '#ae1919',
            headerLogo: '#772c6a',
            menuLeft: '#1f1f1f',
            menuLeftThis: '#e82121',
            menuLeftHover: '#e82121',
        },
        {
            headerRight: '#963885',
            headerRightThis: '#772c6a',
            headerLogo: '#772c6a',
            menuLeft: '#2f4056',
            menuLeftThis: '#963885',
            menuLeftHover: '#963885',
        }
    ];
    Backend = {
        /**
         * 版本
         */
        v: '1.2',

        /**
         * @param options
         */
        render: function (options) {
            options.initUrl = options.initUrl || null;
            options.refreshUrl = options.refreshUrl || null;
            options.themeid = options.themeid || 0;
            options.maxTabs = options.maxTabs || 15;
            $.getJSON(options.initUrl, function (res) {
                if (res == null) {
                    Speed.msg.error('no menus info')
                } else {
                    Backend.initLogo(res.logoInfo);
                    Backend.initMenu(res.menuInfo);
                    Backend.initBgColor();
                    Backend.initTabs({
                        filter: 'layui-layout-tabs',
                        maxTabs: options.maxTabs,
                        listenSwichCallback: function () {
                            Backend.initDevice();
                        }
                    });
                }
            }).fail(function () {
                Speed.msg.error('api is error');
            });
            Backend.hideLoading(options.loadingTime);
            Backend.listen();
        },
        //加载层,锁屏
        hideLoading:function(time){
            time = time || 600;
            setTimeout(function () {
                $(document).find('.speed-loading').fadeOut();
                //判断是否锁定了界面
                var colorId =  sessionStorage.getItem('speedColorId')
                if(colorId==null){
                    colorId  = 0;
                }
                var bg = THEME[colorId]['headerRight'];
                if(layui.data('BackendLock').lock){
                    layer.prompt({
                        btn: [__('Unlock Now')],
                        title: [__('Input Password'),'background:'+bg+';color:#fff'],
                        closeBtn: 0,
                        formType: 1
                    }, function (value, index, elem) {
                        if (value.length < 1) {
                            Speed.msg.error(__('Input Password'));
                            return false;
                        } else {
                            if(value == layui.data('BackendLock').lock){
                                Speed.msg.close(index);
                                //清除密码
                                layui.data('BackendLock', {
                                    key: 'lock'
                                    ,remove: true
                                });
                                Speed.msg.success(__('Unlock Success'))
                            }else{
                                Speed.msg.error(__('Password Error'))
                                return false;
                            }
                        }
                    });
                }


            }, time)
        },
        /**
         * 菜单
         * @param menuList 菜单数据
         */
        initMenu: function (menuList) {
            menuList = menuList || [];
            var MenuHtml = '' ;
            $.each(menuList, function (key,val) {
                MenuHtml += '<li class="layui-nav-item">\n';
                if (val.child!=[] && val.child!=undefined && val.child.length>0) {
                    child = val.child;
                    MenuHtml += '<a href="javascript:;" lay-id="'+val.id+'" title="'+ __(val.title) +'"  lay-tips="'+ __(val.title) +'" ><i class="' + val.icon + '"></i><cite> ' + __(val.title) + '</cite> </a>';
                    var addChildHtml = function (html, child) {
                        html += '<dl class="layui-nav-child">\n'
                        $.each(child, function (k,v) {
                            html += '<dd>';
                            if (v.child!=[] && v.child!=undefined && v.child.length>0) {
                                html += '<a href="javascript:;" lay-id="'+ v.id +'" title="'+ __(v.title) +'"  lay-tips="'+__(v.title)+'"><i class="' + v.icon + '"></i><cite> ' + __(v.title) + '</cite></a>';
                                html = addChildHtml(html, v.child);
                            } else {
                                v.target = v.target?v.target:'_self';
                                html += '<a href="javascript:;" lay-id="'+ v.id +'" title="'+ __(v.title) +'" lay-tips="'+ __(v.title) +'" lay-href="' + v.href + '" target="' + v.target + '"><i class="' + v.icon + '"></i><cite> ' + __(v.title) + '</cite></a>\n';
                            }
                            html += '</dd>\n';
                        });
                        html += '</dl>\n';
                        return html;
                    };
                    MenuHtml = addChildHtml(MenuHtml, val.child);
                } else {
                    val.target = val.target?val.target:'_self';
                    MenuHtml += '<a href="javascript:;" lay-id="'+ val.id +'" title="'+ __(v.title) +'" lay-tips="'+ __(val.title) +'" lay-href="' + val.href + '" target="' + val.target + '"><i class="' + val.icon + '"></i><cite> ' + __(val.title) + '</cite></a>\n';
                }
                MenuHtml += '</li>\n';
            });
            $('#layui-side-left-menu').html(MenuHtml);

            element.init();
        },
        /**
         * tab
         * @param menuList 菜单数据
         */

        initTabs:function(options){
            options.filter = options.filter || null;
            options.maxTabs = options.maxTabs || 15;
            options.listenSwichCallback = options.listenSwichCallback || function () {
            };
            Backend.listenScroll();
            Backend.listenSwitch(options);
            Backend.listenTabs();
            Backend.listenDeltab(options);
        },
        /**
         * 初始化logo
         * @param data
         */
        initLogo: function (data) {
            var html = '<img src="' + data.image + '" alt="logo" width="60"><cite>' + data.title + '</cite>';
            $('.layui-logo').find('a').attr('href',data.href).html(html);
        },

        //全屏
        fullScreen: function () {
            var ele = document.documentElement
                , reqFullScreen = ele.requestFullScreen || ele.webkitRequestFullScreen
                || ele.mozRequestFullScreen || ele.msRequestFullscreen;
            if (typeof reqFullScreen !== 'undefined' && reqFullScreen) {
                reqFullScreen.call(ele);
            } ;
        }
        //退出全屏
        , exitScreen: function () {
            var ele = document.documentElement
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitCancelFullScreen) {
                document.webkitCancelFullScreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        },

        /**
         * 初始化设备端
         */
        initDevice: function () {
            if (!Speed.api.checkScreen()) {
                // $('#speed-app').addClass('layui-side-shrink').removeClass('speed-app')
            // }else{
                $('#speed-app').removeClass('layui-side-shrink').addClass('speed-app')

            }
        },

        //侧边
        sideFlexible: function () {
            //侧边伸缩
            var app = $container
            app.toggleClass(SIDE_SHRINK)
            app.toggleClass('speed-app')
            $('speed-tool').toggleClass(ICON_SPREAD)
        },

        listenTabs:function(){
            var speedTabInfo =layui.sessionData("speedTabInfo");
            var tabLayId = layui.sessionData('tabLayId');
            var layId = tabLayId.id;
            if (layId === null || layId === undefined) return false;
            if(speedTabInfo){
                $("#layui-side-left-menu a[lay-href]").each(function () {
                    if ($(this).attr("lay-id") === layId) {
                        var text = $(this).attr('lay-tips') || $(this).attr('title')
                        var href = $(this).attr('lay-href')
                        var icon = $(this).find('i').attr('class')
                        Backend.addTab({
                            layId: layId,
                            href: href,
                            text: text,
                            icon: icon,
                            maxnum: options.maxnum,
                        });
                        element.tabChange('layui-layout-tabs', layId);
                    }
                });
            }

        },
        /**
         * 删除tab窗口
         * @param layId
         * @param isParent
         */
        delTab: function (layId, isParent) {

            var speedTabInfo = layui.sessionData("speedTabInfo");
            if (speedTabInfo != null) {
                layui.sessionData("speedTabInfo",{key:layId,remove:true})
            }
            // $(".layui-tab .layui-tab-title .layui-unselect.layui-tab-bar").remove();
            if (isParent === true) {
                parent.layui.element.tabDelete('layui-tabs', layId);
            } else {
                element.tabDelete('layui-layout-tabs', layId);
            }
            layId =  $('.layui-tab .layui-tab-title').find('.layui-this').attr('lay-id')
            Backend.changeSessioinTabId(layId)

        },
        /**
         * 增加tab
         * @param layId
         * @param href
         * @param text
         * @param icon
         */
        addTab: function (options) {
            options.layId = options.layId || null;
            options.href = options.href || null;
            options.text = options.text || null;
            options.icon = options.icon || null;
            if ($(".layui-tab .layui-tab-title li").length >= options.maxTabs) {
                Speed.msg.error('window is create by maxnum');
                return false;
            }
            var speedTabInfo =layui.sessionData("speedTabInfo");
            if (speedTabInfo == null) speedTabInfo = {};
            Backend.changeSessioinTabId(options.layId)
            layui.sessionData('speedTabInfo',
                {
                    key:options.layId,
                    value:options.layId,
                });
            var ele = element;
            ele = ele?ele:parent.layui.element;
            var checkLayId = Backend.checkLayId(options.layId);
            if (!checkLayId) {
                var loadindex = layer.load();
                ele.tabAdd('layui-layout-tabs', {
                    title: ' <i class="' + options.icon + '"></i><cite>' + options.text + '</cite>' //用于演示
                    ,
                    content: '<iframe width="100%" height="100%" frameborder="no" border="0" marginwidth="0" marginheight="0"   src="' + options.href + '"></iframe>'
                    ,
                    id: options.layId
                });

            }else{
                var loadindex = layer.load();

                $(".layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload()

            }
            layer.close(loadindex);

            element.tabChange('layui-layout-tabs', options.layId);

        },

        //切换id
        changeSessioinTabId:function(layId){
            if(layId){
                layui.sessionData('tabLayId',{key:'id',value:layId})
            }else{
                layui.sessionData('tabLayId',null);
            }
        },
        /**
         * 判断tab窗口
         */
        checkLayId: function (layId) {
            // 判断选项卡上是否有
            var checkId = false;
            $(".layui-tab .layui-tab-title li").each(function () {
                var checklayId = $(this).attr('lay-id');
                if (checklayId != null && checklayId === layId) {
                    checkId = true;
                }
            });
            if (checkId === false) {
                return false;
            }
            return true;
        },



        /**
         * 构建背景颜色选择
         * @returns {string}
         */
        buildBgColorHtml :function () {
            var html = '';
            var colorId = sessionStorage.getItem('speedColorId');
            if (colorId == null || colorId == undefined || colorId == '') {
                colorId = 0;
            }
            var theme = THEME;
            $.each(theme, function (key, val) {
                if (key == colorId) {
                    html += '<li class="layui-this" lay-event="setTheme" data-color="' + key + '">\n';
                } else {
                    html += '<li  lay-event="setTheme" data-color="' + key + '">\n';
                }
                html += '<a href="javascript:;" data-skin="skin-blue" style="" class="clearfix full-opacity-hover">\n' +
                    '<div><span style="display:block; width: 20%; float: left; height: 12px; background: ' + val.headerLogo + ';"></span><span style="display:block; width: 80%; float: left; height: 12px; background: ' + val.headerRight + ';"></span></div>\n' +
                    '<div><span style="display:block; width: 20%; float: left; height: 40px; background: ' + val.menuLeft + ';"></span><span style="display:block; width: 80%; float: left; height: 40px; background: #f4f5f7;"></span></div>\n' +
                    '</a>\n' +
                    '</li>';
            });
            return html;
        },
        /**
         * 初始化背景色
         */
       initBgColor :function () {
            var colorId = sessionStorage.getItem('speedColorId');
            if (colorId == null || colorId == undefined || colorId == '') {
                colorId = 0;
            }
            var themeData = THEME[colorId];
            var styleHtml = '.layui-layout-admin .layui-header{background-color:' + themeData.headerRight + '!important;}\n' +
                '.layui-header>ul>.layui-nav-item.layui-this:hover{background-color:' + themeData.headerRightThis + '!important;}\n' +
                '.layui-layout-admin .layui-logo {background-color:' + themeData.headerLogo + '!important;}\n' +
                '.layui-side.layui-bg-black,.layui-side.layui-bg-black>.layui-side-scroll>ul {background-color:' + themeData.menuLeft + '!important;}\n' +
                '.layui-side-scroll .layui-nav .layui-nav-child a:hover:not(.layui-this){background-color:' + themeData.menuLeftHover + '!important;}\n' +
                '.layui-layout-admin .layui-nav-tree .layui-this, .layui-layout-admin .layui-nav-tree .layui-this>a, .layui-layout-admin .layui-nav-tree .layui-nav-child dd.layui-this, .layui-layout-admin .layui-nav-tree .layui-nav-child dd.layui-this a {\n' +
                '    background-color: ' + themeData.menuLeftThis + ' !important;\n}'+ '.layui-pagetabs .layui-tab-title li:hover, .layui-pagetabs .layui-tab-title li.layui-this{ color:'+themeData.menuLeftThis+'!important;}.layui-nav-tree .layui-nav-bar{background:'+themeData.menuLeftThis+'}';
            $('#speed-bg-color').html(styleHtml);
        },
        /**
         //  * 监听tab切换
         //  * @param options
         //  */
        listenSwitch: function (options) {
            options.filter = options.filter || null;

            element.on('tab(' + options.filter + ')', function (data) {
                var layId = $(this).attr('lay-id');
                if (typeof options.listenSwichCallback === 'function') {
                    options.listenSwichCallback();
                }
                Backend.changeSessioinTabId(layId)
                Backend.listenSwitchIframe(layId);
                Backend.scrollPostion();
            });
        },
        /**
         //  * 监听tab删除
         //  * @param options
         //  */
        listenDeltab: function (options) {
            options.filter = options.filter || null;
            element.on('tabDelete(' + options.filter + ')', function (data) {
                var layId = $(this).parent().attr('lay-id');
                Backend.delTab(layId);
            });
        },
        /**
         * 监听滚动
         */
        listenScroll: function (type) {
            var tabNav = $('.layui-tab  .layui-tab-title');
            var left = tabNav.scrollLeft();
            if (type === 'left') {
                tabNav.animate({
                    scrollLeft: left - 450
                }, 100);
            } else {
                tabNav.animate({
                    scrollLeft: left + 450
                }, 100);
            }
        },
        /**
         * 监听切换
         * @param layId
         */
        listenSwitchIframe: function (layId) {
            /**
             * 左侧菜单的样式和多级菜单的展开
             */
            $("#layui-side-left-menu").find("li,dd").removeClass("layui-this").removeClass("layui-nav-itemed");//关闭所有展开的菜单
            $("#layui-side-left-menu > li dl.layui-nav-child").removeAttr('style');
            $("#layui-side-left-menu a[lay-href]").each(function () {
                if ($(this).attr('lay-id') == layId) {
                    $(this).parents("dd").addClass("layui-nav-itemed");
                    $(this).parents("li").addClass("layui-nav-itemed");
                    $(this).parent().removeClass("layui-nav-itemed").addClass("layui-this");
                }
            })
        },
        /**
         * 自动定位
         */
        scrollPostion: function () {
            var tabNav = $('.layui-tab .layui-tab-title');
            var autoLeft = 0;
            tabNav.children("li").each(function () {
                if ($(this).hasClass('layui-this')) {
                    return false;
                } else {
                    autoLeft += $(this).outerWidth();
                }
            });
            tabNav.animate({
                scrollLeft: autoLeft - tabNav.width() / 4
            }, 200);
        },

        /**
         * 监听事件
         */
        listen: function () {
            var events = layui.events = {
                //弹出主题面板
                opentheme: function(){
                    var loading = layer.load(0, {shade: false, time: 2 * 1000});
                    var clientHeight = (document.documentElement.clientHeight) - 60;
                    var bgColorHtml = Backend.buildBgColorHtml();
                    var html = '<div class="layui-speed-color">\n' +
                        '<div class="color-title">\n' +
                        '<span>配色方案</span>\n' +
                        '</div>\n' +
                        '<div class="color-content">\n' +
                        '<ul>\n' + bgColorHtml + '</ul>\n' +
                        '</div>\n' +
                        '</div>';
                    layer.open({
                        type: 1,
                        title: false,
                        closeBtn: 0,
                        shade: 0.2,
                        anim: 2,
                        shadeClose: true,
                        id: 'layui-speed-color',
                        area: ['340px', clientHeight + 'px'],
                        offset: 'rb',
                        content: html,
                        end:function () {
                            $('.layuimini-select-bgcolor').removeClass('layui-this');
                        }
                    });
                    layer.close(loading);
                },

                /**
                 * 设置颜色配置
                 */
                setTheme:function(othis){
                    var colorId = othis.attr('data-color');
                    $('.layui-speed-color .color-content ul .layui-this').attr('class', '');
                    $(this).attr('class', 'layui-this');
                    sessionStorage.setItem('speedColorId', colorId);
                    Backend.initBgColor();
                },
                /**
                 * 锁定屏幕
                 */
                lockScreen:function(){
                    var colorId =  sessionStorage.getItem('speedColorId');
                    if(colorId==null){
                        colorId=0;
                    }
                    var bg = THEME[colorId]['headerRight'];
                    layer.prompt({
                        btn: [__('Lock Now')],
                        title: [__('Set Password To Lock Screen'),'background:'+bg+';color:#fff'],
                        formType: 1
                    }, function (value, index, elem) {
                        if (value.length < 1) {
                            Speed.msg.error(__('Input Password'));
                            return false;
                        } else {
                            layui.data('BackendLock', {
                                key: 'lock'
                                ,value: value
                            });
                            layer.close(index);
                            layer.prompt({
                                btn: [__('Unlock')],
                                title: [__('Input Password'),'background:'+bg+';color:#fff'],
                                closeBtn: 0,
                                formType: 1
                            }, function (value, index, elem) {
                                if (value.length < 1) {
                                    Speed.msg.error(___('Input Password'));
                                    return false;
                                } else {
                                    if(value == layui.data('BackendLock').lock){
                                        layer.close(index);
                                        $(".yy").hide();
                                        //清除密码
                                        layui.data('BackendLock', {
                                            key: 'lock'
                                            ,remove: true
                                        });
                                        Speed.msg.success(__('Unlock Success'));
                                    }else{
                                        Speed.msg.error(__('Password Error'));
                                        return false;
                                    }
                                }
                            });
                        }
                    });

                },
                //伸缩
                flexible: function (othis) {
                    Backend.sideFlexible();
                },
                rightPage:function(){
                    Backend.listenScroll('right')
                },
                leftPage :function(){
                    Backend.listenScroll('left')
                },
                showtips: function (othis, type) {
                    if($container.hasClass(SIDE_SHRINK)){
                        if (type == 1) {
                            var tip = othis.attr('lay-tips');
                            openTips = layer.tips(tip, $(this), {tip: [2, '#2f4056'], time: 30000});
                        } else {
                            closeTips = layer.close(openTips);
                        }
                    }
                }
                //全屏
                , fullscreen: function (othis) {
                    var SCREEN_FULL = 'layui-icon-screen-full'
                        , SCREEN_REST = 'layui-icon-screen-restore'
                        , iconElem = othis.children("i");

                    if (iconElem.hasClass(SCREEN_FULL)) {
                        Backend.fullScreen();
                        iconElem.addClass(SCREEN_REST).removeClass(SCREEN_FULL);
                    } else {
                        Backend.exitScreen();
                        iconElem.addClass(SCREEN_FULL).removeClass(SCREEN_REST);
                    }
                }
                //遮罩
                , shade: function () {
                    Backend.sideFlexible();
                },
                clear: function (othis) {  //清除缓存
                    layui.sessionData('speedTabInfo',
                        {
                            key:'speed-safe',
                            value:'',
                        });
                    layui.sessionData('speedTabInfo',
                        {
                            key:'speed-info',
                            value:'',
                        });
                    var url = othis.attr('lay-ajax') ?othis.attr('lay-ajax'):Backend.refreshUrl;
                    Speed.ajax({url:url},function (res) {
                        Speed.msg.success(res.msg);
                        $(".layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload()

                    },function (res) {
                        Speed.msg.error(res.msg);
                        $(".layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload()

                    })

                },
                refresh: function (othis) {  //刷新
                    Speed.msg.success(__('Refresh Success'));
                    Speed.msg.loading('',setTimeout(function () {
                        $(".layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload()
                            Speed.msg.close();
                        },1200)
                    );
                }
                //关闭当前标签页
                , closeThisTabs: function (othis) {
                    var toplayui = parent === self ? layui : parent.layui.layui;
                    var layId =  $(".layui-tab .layui-tab-title li.layui-this").attr('lay-id');
                    if(layId){
                        Backend.delTab(layId)
                    }
                }
                //关闭其它标签页
                , closeOtherTabs: function (type) {
                    $(".layui-tab .layui-tab-title li").each(function (key,val) {
                        var layId = $(val).attr('lay-id');
                        if(type=='all' && layId){
                            Backend.delTab(layId);
                        }else{
                            if (layId && !$(this).hasClass(THIS)) {
                                Backend.delTab(layId);
                            }
                        }

                    });
                }
                //关闭全部标签页
                , closeAllTabs: function () {
                    events.closeOtherTabs('all');
                },
                /**
                 * 退出登陆
                 * @param othis
                 */
                logout:function (othis) {
                    var url = othis.attr('lay-ajax')
                    Speed.msg.confirm(__('Are you sure todo this'),function () {
                        $.post(url,function (res) {
                            if(res.code>0){
                                Speed.msg.success(res.msg,setTimeout(function () {
                                    window.location = res.url;
                                },2500))

                            }else{
                                Speed.msg.error(res.msg)
                            }
                        })
                    })

                },
                /**
                 * 语言设置
                 * @param othis
                 */
                langset:function (othis) {
                   var url =  othis.attr('lay-ajax')
                    $.post(url,function (res) {
                        if(res.code>0){
                            Speed.msg.success(res.msg,setTimeout(function () {
                                window.location = res.url;
                            },2500))

                        }else{
                            Speed.msg.error(res.msg)
                        }
                    })
                }

            }
            //页面添加tab
            $body.on('click', '*[lay-id]', function () {
                var othis = $(this)
                    , href = othis.attr('lay-href')
                    , layId = othis.attr('lay-id')
                    , text = othis.attr('lay-tips') || $(this).attr('title')
                    , icon = othis.find('i').attr('class')
                    , router = layui.router();
                layId = layId?layId:new Date();
                target = othis.attr('target');
                if (target === '_blank') {
                    window.open(href, "_blank");
                    return false;
                }
                if (href!=undefined) {
                    options = {layId:layId,text:text,href:href,icon:icon}
                    Backend.addTab(options);
                }
                /**
                 * 左侧菜单展开动画
                 */
                othis.parent("li").siblings().removeClass("layui-nav-itemed");
                if (!href) {
                    var superEle = othis.parent();
                    var ele = othis.next('.layui-nav-child');
                    var height = ele.height();
                    ele.css({"display": "block"});
                    // 是否是展开状态
                    if (superEle.is(".layui-nav-itemed")) {
                        ele.height(0);
                        ele.animate({height: height + "px"}, function () {
                            ele.css({height: "auto"});
                        });
                    } else {
                        ele.animate({height: 0}, function () {
                            ele.removeAttr("style");
                        });
                    }
                }else{
                    $('#speed-app').removeClass(SIDE_SHRINK).addClass('speed-app')
                }
            });

            //点击事件
            $body.on('click', '*[lay-event]', function () {
                var othis = $(this)
                    , attrEvent = othis.attr('lay-event');
                events[attrEvent] && events[attrEvent].call(this, othis);
            });

            //鼠标提示
            $body.on("mouseenter", "*[lay-tips]", function () {
                var othis = $(this)
                events['showtips'] && events['showtips'].call(this, othis, 1);

            }).on("mouseleave", "*[lay-tips]", function () {
                var othis = $(this)
                events['showtips'] && events['showtips'].call(this, othis, 2);
            });

        }
    };
    exports("Backend", Backend);
});