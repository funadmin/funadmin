layui.define(["jquery", 'layer'], function (exports) {
    var $ = layui.$,
        element = layui.element,
        layer = layui.layer;
    element.render()
    if (!/http(s*):\/\//.test(location.href)) {
        let tips = "请先将项目部署至web容器（Apache/Tomcat/Nginx/IIS/等），否则部分数据将无法显示";
        return Fun.toastr.alert(tips);
    }
    var $document = $(document), $container = $('#fun-app');
    var THIS = 'layui-this', ICON_SPREAD = 'layui-icon-spread-left', SIDE_SHRINK = 'layui-side-shrink';
    //主题配置
    var THEME = [
        {
            headerBg: '#fff',
            headerfontColor: '#595959',
            headerBgThis: '#772c6a',
            headerBgLogo: '#0c0c0c',
            headerLogofontColor: '#0c0c0c',
            menuLeftBg: '#23262e',
            menuLeftBgThis: '#772c6a',
            menuLeftBgHover: '#772c6a',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',
        },
        {
            headerBg: '#fff',
            headerfontColor: '#595959',
            headerBgThis: '#197971',
            headerBgLogo: '#0c0c0c',
            headerLogofontColor: '#0c0c0c',
            menuLeftBg: '#23262e',
            menuLeftBgThis: '#20b598',
            menuLeftBgHover: '#20b598',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',

        },
        {
            headerBg: '#fff',
            headerfontColor: '#595959',
            headerBgThis: '#722ed1',
            headerBgLogo: '#0c0c0c',
            headerLogofontColor: '#0c0c0c',
            menuLeftBg: '#23262e',
            menuLeftBgThis: '#722ed1',
            menuLeftBgHover: '#722ed1',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',

        },
        {
            headerBg: '#20b598',
            headerfontColor: '#fff',
            headerBgThis: '#197971',
            headerBgLogo: '#0c0c0c',
            headerLogofontColor: '#fff',
            menuLeftBg: '#23262e',
            menuLeftBgThis: '#20b598',
            menuLeftBgHover: '#20b598',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },
        {
            headerBg: '#20b598',
            headerfontColor: '#fff',
            headerBgThis: '#197971',
            headerBgLogo: '#20b598',
            headerLogofontColor: '#fff',
            menuLeftBg: '#2f4056',
            menuLeftBgThis: '#20b598',
            menuLeftBgHover: '#3b3f4b',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },
        {
            headerBg: '#722ed1',
            headerfontColor: '#fff',
            headerBgThis: '#197971',
            headerBgLogo: '#722ed1',
            headerLogofontColor: '#fff',
            menuLeftBg: '#2f4056',
            menuLeftBgThis: '#722ed1',
            menuLeftBgHover: '#722ed1',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },
        {
            headerBg: '#00a65a',
            headerBgThis: '#197971',
            headerBgLogo: '#00a65a',
            headerLogofontColor: '#fff',
            menuLeftBg: '#222d32',
            menuLeftBgThis: '#00a65a',
            menuLeftBgHover: '#00a65a',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },
        {
            headerBg: '#23262e',
            headerfontColor: '#fff',
            headerBgThis: '#0c0c0c',
            headerBgLogo: '#0c0c0c',
            headerLogofontColor: '#fff',
            menuLeftBg: '#23262e',
            menuLeftBgThis: '#20b598',
            menuLeftBgHover: '#3b3f4b',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },
        {
            headerBg: '#ffa4d1',
            headerfontColor: '#fff',
            headerBgThis: '#bf7b9d',
            headerBgLogo: '#e694bd',
            headerLogofontColor: '#fff',
            menuLeftBg: '#1f1f1f',
            menuLeftBgThis: '#ffa4d1',
            menuLeftBgHover: '#ffa4d1',
            menuLeftfontColor: 'rgba(255,255,255,.7)',

        },
        {
            headerBg: '#1e9fff',
            headerfontColor: '#fff',
            headerBgThis: '#0069b7',
            headerBgLogo: '#0c0c0c',
            headerLogofontColor: '#fff',
            menuLeftBg: '#1f1f1f',
            menuLeftBgThis: '#1e9fff',
            menuLeftBgHover: '#1e9fff',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',

        },

        {
            headerBg: '#ffb800',
            headerfontColor: '#fff',
            headerBgThis: '#d09600',
            headerBgLogo: '#243346',
            headerLogofontColor: '#fff',
            menuLeftBg: '#2f4056',
            menuLeftBgThis: '#ffb800',
            menuLeftBgHover: '#ffb800',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },
        {
            headerBg: '#e82121',
            headerfontColor: '#fff',
            headerBgThis: '#ae1919',
            headerBgLogo: '#0c0c0c',
            headerLogofontColor: '#fff',
            menuLeftBg: '#1f1f1f',
            menuLeftBgThis: '#e82121',
            menuLeftBgHover: '#e82121',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',

        },
        {
            headerBg: '#963885',
            headerfontColor: '#fff',
            headerBgThis: '#772c6a',
            headerBgLogo: '#243346',
            headerLogofontColor: '#fff',
            menuLeftBg: '#2f4056',
            menuLeftBgThis: '#963885',
            menuLeftBgHover: '#963885',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },

        {
            headerBg: '#1e9fff',
            headerfontColor: '#fff',
            headerBgThis: '#0069b7',
            headerBgLogo: '#0069b7',
            headerLogofontColor: '#fff',
            menuLeftBg: '#1f1f1f',
            menuLeftBgThis: '#1e9fff',
            menuLeftBgHover: '#1e9fff',
            menuLeftfontColorHover: '#fff',

        },
        {
            headerBg: '#ffb800',
            headerfontColor: '#fff',
            headerBgThis: '#d09600',
            headerBgLogo: '#d09600',
            headerLogofontColor: '#fff',
            menuLeftBg: '#2f4056',
            menuLeftBgThis: '#ffb800',
            menuLeftBgHover: '#ffb800',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },
        {
            headerBg: '#e82121',
            headerfontColor: '#fff',
            headerBgThis: '#ae1919',
            headerBgLogo: '#d91f1f',
            headerLogofontColor: '#fff',
            menuLeftBg: '#1f1f1f',
            menuLeftBgThis: '#e82121',
            menuLeftBgHover: '#e82121',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',


        },
        {
            headerBg: '#e82121',
            headerfontColor: '#fff',
            headerBgThis: '#ae1919',
            headerBgLogo: '#772c6a',
            headerLogofontColor: '#fff',
            menuLeftBg: '#1f1f1f',
            menuLeftBgThis: '#e82121',
            menuLeftBgHover: '#e82121',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',

        },
        {
            headerBg: '#963885',
            headerfontColor: '#fff',
            headerBgThis: '#772c6a',
            headerBgLogo: '#772c6a',
            headerLogofontColor: '#fff',
            menuLeftBg: '#2f4056',
            menuLeftBgThis: '#963885',
            menuLeftBgHover: '#963885',
            menuLeftfontColor: 'rgba(255,255,255,.7)',
            menuLeftfontColorHover: '#fff',

        }
    ];
    var Backend = {
        /**
         * 版本
         */
        v: '1.2',
        /**
         * @param options
         */
        render: function (options = {
            refreshUrl: '',
            themeid: '',
            maxTabs: '',
            loadingTime: '',
        }) {
            options.refreshUrl = options.refreshUrl || Fun.url('ajax/clearcache');
            options.themeid = options.themeid || 0;
            options.maxTabs = options.maxTabs || 15;
            Backend.initTabs({
                filter: 'layui-layout-tabs',
                maxTabs: options.maxTabs,
                listenSwichCallback: function () {
                    Backend.initDevice();
                }
            });
            Backend.initBgColor();
            Backend.hideLoading(options.loadingTime);
            Backend.api.bindEvent();
        },
        //加载层,锁屏
        hideLoading: function (time) {
            time = time || 300;
            setTimeout(function () {
                $(document).find('.fun-loading').fadeOut();
                //判断是否锁定了界面
                var colorId = sessionStorage.getItem('funColorId')
                if (colorId == null) {
                    colorId = 0;
                }
                var bg = THEME[colorId]['headerBg'];
                if (layui.data('BackendLock').lock) {
                    layer.prompt({
                        btn: [__('Unlock Now')],
                        title: [__('Input Password'), 'background:' + bg + ';color:#fff'],
                        closeBtn: 0,
                        formType: 1
                    }, function (value, index) {
                        if (value.length < 1) {
                            Fun.toastr.error(__('Input Password'));
                            return false;
                        } else {
                            if (value === layui.data('BackendLock').lock) {
                                Fun.toastr.close(index);
                                //清除密码
                                layui.data('BackendLock', {
                                    key: 'lock'
                                    , remove: true
                                });
                                Fun.toastr.success(__('Unlock Success'))
                            } else {
                                Fun.toastr.error(__('Password Error'))
                                return false;
                            }
                        }
                    });
                }


            }, time)
        },

        /**
         * tab
         */

        initTabs: function (options) {
            options.filter = options.filter || null;
            options.maxTabs = options.maxTabs || 15;
            options.listenSwichCallback = options.listenSwichCallback || function () {
            };
            Backend.listenScroll();
            Backend.listenSwitch(options);
            Backend.listenTabs(options);
            Backend.listenDeltab(options);
        },


        //全屏
        fullScreen: function () {
            var ele = document.documentElement
                , reqFullScreen = ele.requestFullScreen || ele.webkitRequestFullScreen
                || ele.mozRequestFullScreen || ele.msRequestFullscreen;
            if (typeof reqFullScreen !== 'undefined' && reqFullScreen) {
                reqFullScreen.call(ele);
            }
        }
        //退出全屏
        , exitScreen: function () {
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
            if (!Fun.api.checkScreen()) {
                $('#fun-app').removeClass('layui-side-shrink').addClass('fun-app')
            }
        },

        //侧边
        sideFlexible: function () {
            //侧边伸缩
            var app = $container
            app.toggleClass(SIDE_SHRINK)
            app.toggleClass('fun-app')
            $('fun-tool').toggleClass(ICON_SPREAD)
        },

        listenTabs: function (options) {
            var funTabInfo = layui.sessionData("funTabInfo");
            var tabLayId = layui.sessionData('tabLayId');
            var layId = tabLayId.id;
            if (layId === null || layId === undefined) return false;
            if (funTabInfo) {
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
                            maxTabs: options.maxTabs,
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

            var funTabInfo = layui.sessionData("funTabInfo");
            if (funTabInfo != null) {
                layui.sessionData("funTabInfo", {key: layId, remove: true})
            }
            if (isParent === true) {
                parent.layui.element.tabDelete('layui-tabs', layId);
            } else {
                element.tabDelete('layui-layout-tabs', layId);
            }
            layId = $('.layui-tab .layui-tab-title').find('.layui-this').attr('lay-id')
            Backend.changeSessioinTabId(layId)
            $('#layui-sp-righmenu').remove();

        },
        /**
         * 增加tab
         */
        addTab: function (options) {
            options.layId = options.layId || null;
            options.href = options.href || null;
            options.text = options.text || null;
            options.icon = options.icon || null;
            options.iframe = options.iframe || null;
            if ($(".layui-tab .layui-tab-title li").length >= options.maxTabs) {
                Fun.toastr.error('window is create by maxnum');
                return false;
            }
            Backend.changeSessioinTabId(options.layId)
            layui.sessionData('funTabInfo',
                {
                    key: options.layId,
                    value: options.layId,
                });
            var ele = element;if(options.iframe) ele = parent.layui.element;
            var checkLayId = Backend.checkLayId(options.layId);
            var loadindex;
            if (!checkLayId) {
                loadindex = layer.load();
                ele.tabAdd('layui-layout-tabs', {
                    title: ' <i class="' + options.icon + '"></i><cite>' + options.text + '</cite>' //用于演示
                    ,
                    content: '<iframe width="100%" height="100%" frameborder="no"   src="' + options.href + '"></iframe>'
                    ,
                    id: options.layId
                });
            } else {
                loadindex = layer.load();
                $(".layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload()

            }
            $('#layui-sp-righmenu').remove();

            layer.close(loadindex);

            ele.tabChange('layui-layout-tabs', options.layId);

        },

        //切换id
        changeSessioinTabId: function (layId) {
            if (layId) {
                layui.sessionData('tabLayId', {key: 'id', value: layId})
            } else {
                layui.sessionData('tabLayId', null);
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
            return checkId !== false;

        },

        /**
         * 构建背景颜色选择
         * @returns {string}
         */
        buildBgColorHtml: function () {
            var html = '';
            var colorId = sessionStorage.getItem('funColorId');
            if (colorId == null || colorId === '') {
                colorId = 0;
            }
            $.each(THEME, function (key, val) {
                if (key === colorId) {
                    html += '<li class="layui-this" lay-event="setTheme" data-color="' + key + '">\n';
                } else {
                    html += '<li  lay-event="setTheme" data-color="' + key + '">\n';
                }
                html += '<a href="javascript:;" data-skin="skin-blue" style="" class="clearfix full-opacity-hover">\n' +
                    '<div><span style="display:block; width: 20%; float: left; height: 12px; background: ' + val.headerBgLogo + ';"></span><span style="display:block; width: 80%; float: left; height: 12px; background: ' + val.headerBg + ';"></span></div>\n' +
                    '<div><span style="display:block; width: 20%; float: left; height: 40px; background: ' + val.menuLeftBg + ';"></span><span style="display:block; width: 80%; float: left; height: 40px; background: #f4f5f7;"></span></div>\n' +
                    '</a>\n' +
                    '</li>';
            });
            return html;
        },
        /**
         * 初始化背景色
         */
        initBgColor: function () {
            var colorId = sessionStorage.getItem('funColorId');
            if (colorId == null || colorId === '') {
                colorId = 0;
            }
            var themeData = THEME[colorId];
            var styleHtml = '.layui-layout-admin .layui-header ul li a{color:' + themeData.headerfontColor + '!important;}' +
                '.layui-layout-admin .layui-header{background-color:' + themeData.headerBg + '!important;}\n' +
                '.layui-header>ul>.layui-nav-item.layui-this:hover{color:' + themeData.headerBgThis + '!important;}\n' +
                '.layui-layout-admin .layui-logo {background-color:' + themeData.headerBgLogo + '!important;}\n' +
                '.layui-layout-admin .layui-logo{color:' + themeData.headerfontColor + '!important;}\n' +
                '.layui-layout-admin .layui-side-scroll .layui-nav-tree .layui-nav-item a{color:' + themeData.menuLeftfontColor + '}\n' +
                '.layui-layout-admin .layui-side-scroll .layui-nav-tree .layui-nav-item>a:hover{color:' + themeData.menuLeftfontColorHover + '!important;}\n' +
                '.layui-layout-admin .layui-side-scroll .layui-nav-tree>.layui-nav-item>a:before{background-color:' + themeData.menuLeftBgHover + '!important;}\n' +
                '.layui-side.layui-bg-black,.layui-side.layui-bg-black>.layui-side-scroll>ul {background-color:' + themeData.menuLeftBg + '!important;}\n' +
                '.layui-side-scroll .layui-nav .layui-nav-child a:hover:not(.layui-this){background-color:' + themeData.menuLeftBgHover + '!important;}\n' +
                '.layui-layout-admin .layui-nav-tree .layui-this,' +
                '.layui-layout-admin .layui-nav-tree .layui-this>a,' +
                '.layui-layout-admin .layui-nav-tree .layui-nav-child dd.layui-this,' +
                '.layui-layout-admin .layui-nav-tree .layui-nav-child dd.layui-this a {background-color: ' + themeData.menuLeftBgThis + ' !important;}\n' +
                '.layui-pagetabs .layui-tab-title li:hover, ' +
                '.layui-pagetabs .layui-tab-title li.layui-this{ color:' + themeData.menuLeftBgThis + '!important;}\n' +
                '.layui-layout-admin .layui-nav-tree .layui-nav-bar{background:' + themeData.menuLeftBgThis + '!important;}\n';
            $('#fun-bg-color').html(styleHtml);
        },
        /**
         * 监听tab切换
         * @param options
         */
        listenSwitch: function (options) {
            options.filter = options.filter || null;

            element.on('tab(' + options.filter + ')', function () {
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
            element.on('tabDelete(' + options.filter + ')', function () {
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
                if ($(this).attr('lay-id') === layId) {
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
        events: {
            //弹出主题面板
            opentheme: function () {
                var loading = layer.load(0, {shade: false, time: 2 * 1000});
                var clientHeight = (document.documentElement.clientHeight) - 60;
                var bgColorHtml = Backend.buildBgColorHtml();
                var anims = [0, 1, 2, 3, 4, 5, 6];
                var anim = anims[Math.floor(Math.random() * anims.length + 1) - 1];
                var html = '<div class="layui-fun-color">\n' +
                    '<div class="color-title">\n' +
                    '<span>' + __('Theme Color') + '</span>\n' +
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
                    anim: anim,
                    shadeClose: true,
                    isOutAnim: true,
                    id: 'layui-fun-color',
                    area: ['340px', clientHeight + 'px'],
                    offset: 'rb',
                    content: html,

                });
                layer.close(loading);
            },

            /**
             * 设置颜色配置
             */
            setTheme: function (othis) {
                var colorId = othis.attr('data-color');
                $('.layui-fun-color .color-content ul .layui-this').attr('class', '');
                $(this).attr('class', 'layui-this');
                sessionStorage.setItem('funColorId', colorId);
                Backend.initBgColor();
            },
            /**
             * 锁定屏幕
             */
            lockScreen: function () {
                var colorId = sessionStorage.getItem('funColorId');
                if (colorId == null) {
                    colorId = 0;
                }
                layer.prompt({
                    btn: [__('Lock Now')],
                    title: [__('Set Password To Lock Screen'), 'background:' + THEME[colorId]['headerBg'] + ';color:' + THEME[colorId]['headerfontColor']],
                    formType: 1
                }, function (value, index) {
                    if (value.length < 1) {
                        Fun.toastr.error(__('Input Password'));
                        return false;
                    } else {
                        layui.data('BackendLock', {
                            key: 'lock'
                            , value: value
                        });
                        layer.close(index);
                        layer.prompt({
                            btn: [__('Unlock')],
                            title: [__('Input Password'), 'background:' + THEME[colorId]['headerBg'] + ';color:' + THEME[colorId]['headerfontColor']],
                            closeBtn: 0,
                            formType: 1
                        }, function (value, index) {
                            if (value.length < 1) {
                                Fun.toastr.error(__('Input Password'));
                                return false;
                            } else {
                                if (value === layui.data('BackendLock').lock) {
                                    layer.close(index);
                                    $(".yy").hide();
                                    //清除密码
                                    layui.data('BackendLock', {
                                        key: 'lock'
                                        , remove: true
                                    });
                                    Fun.toastr.success(__('Unlock Success'));
                                } else {
                                    Fun.toastr.error(__('Password Error'));
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
            rightPage: function () {
                Backend.listenScroll('right')
            },
            leftPage: function () {
                Backend.listenScroll('left')
            },
            showtips: function (othis, type) {

                if ($container.hasClass(SIDE_SHRINK)) {
                    if (type === 1) {
                        let tip = othis.attr('lay-tips');
                        layer.tips(tip,othis);
                    } else {
                        layer.close();
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
                layui.sessionData('funTabInfo',
                    {
                        key: 'fun-safe',
                        value: '',
                    });
                layui.sessionData('funTabInfo',
                    {
                        key: 'fun-info',
                        value: '',
                    });
                var url = othis.attr('lay-ajax') ? othis.attr('lay-ajax') : Backend.refreshUrl;
                Fun.ajax({url: url}, function (res) {
                    Fun.toastr.success(res.msg);
                    $(".layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload()

                }, function (res) {
                    Fun.toastr.error(res.msg);
                    $(".layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload()

                })

            },
            refresh: function () {  //刷新
                Fun.toastr.success(__('Refresh Success'));
                Fun.toastr.loading('', setTimeout(function () {
                        $(".layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload()
                        Fun.toastr.close();
                    }, 1200)
                );
            }
            //关闭当前标签页
            , closeThisTabs: function () {
                var layId = $(".layui-tab .layui-tab-title li.layui-this").attr('lay-id');
                if (layId) {
                    Backend.delTab(layId)
                }
            }
            //关闭其它标签页
            , closeOtherTabs: function (type) {
                $(".layui-tab .layui-tab-title li").each(function (key, val) {
                    var layId = $(val).attr('lay-id');
                    if (type === 'all' && layId) {
                        Backend.delTab(layId);
                    } else {
                        if (layId && !$(this).hasClass(THIS)) {
                            Backend.delTab(layId);
                        }
                    }

                });
            }
            //关闭全部标签页
            , closeAllTabs: function () {
                Backend.events.closeOtherTabs('all');
            },
            /**
             * 退出登陆
             * @param othis
             */
            logout: function (othis) {
                var url = othis.attr('lay-ajax')
                Fun.toastr.confirm(__('Are you sure todo this'), function () {
                    $.post(url, function (res) {
                        if (res.code > 0) {
                            Fun.toastr.success(res.msg, setTimeout(function () {
                                window.location = res.url;
                            }, 2500))

                        } else {
                            Fun.toastr.error(res.msg)
                        }
                    })
                })

            },
            /**
             * 语言设置
             * @param othis
             */
            langset: function (othis) {
                var url = othis.attr('lay-ajax')
                Fun.ajax({url: url}, function (res) {
                    Fun.toastr.success(res.msg, setTimeout(function () {
                        window.location.reload();
                    }, 1500))

                }, function (res) {
                    Fun.toastr.error(res.msg)
                })
            }

        },
        /**
         * 监听事件
         */
        api: {
            bindEvent: function () {
                /*菜单点击*/
                $document.on('click', '*[lay-id]', function () {
                    var _that = $(this)
                        , href = _that.attr('lay-href')?_that.attr('lay-href'):_that.attr('lay-iframe')
                        , layId = _that.attr('lay-id')
                        , text = _that.attr('lay-tips') || $(this).attr('title')
                        , icon = _that.find('i').attr('class')
                        , iframe= _that.has('layi-iframe')?true: false,
                        target = _that.attr('target');
                    layId = layId ? layId : href;
                    if (!$(this).attr("lay-href")) {
                        var parent = _that.parent();
                        var child = _that.next('.layui-nav-child');
                        var height = child.height();
                        // 是否是展开状态
                        child.css({"display": "block"});
                        if (parent.hasClass("layui-nav-itemed")) {
                            child.height(0);
                            child.animate({height: height + "px"}, function () {
                                child.css({height: "auto"});
                            });
                            parent.siblings('li').children('.layui-nav-child').removeAttr("style");

                        } else {

                            child.animate({height: 0}, function () {
                                child.removeAttr("style");
                            });
                        }
                    } else {
                        if (target === '_blank') {
                            window.open(href, "_blank");
                            return false;
                        }
                        let options = {layId: layId, text: text, href: href, icon: icon,iframe:iframe}
                        Backend.addTab(options);
                        $('#fun-app').removeClass(SIDE_SHRINK).addClass('fun-app')
                    }

                });
                //点击事件
                $document.on('click', '*[lay-event]', function () {
                    var _that = $(this)
                        , attrEvent = _that.attr('lay-event');
                    Backend.events[attrEvent] && Backend.events[attrEvent].call(this, _that);
                });

                //鼠标提示
                $document.on("mouseenter", "*[lay-tips]", function () {
                    var _that = $(this)
                   Backend.events['showtips'] && Backend.events['showtips'].call(this, _that, 1);

                }).on("mouseleave", "*[lay-tips]", function () {
                    var _that = $(this)
                   Backend.events['showtips'] && Backend.events['showtips'].call(this, _that, 2);
                });
                /*** 鼠标事件*/
                $document.unbind("mousedown", ".layui-tab .layui-tab-title li").bind("contextmenu", function (e) {
                    e.preventDefault();
                    return false;
                });
                $document.on("mousedown", ".layui-tab ul li", function (event) {
                    event = event || window.event;  //兼容写法
                    if (event.which === 3) {
                        var _that = $(this);
                        var leftwith = _that.offset().left + (_that.outerWidth()) / 2;
                        if ($('body').find('#layui-sp-righmenu').length === 0) {
                            var menuContent = '<div id="layui-sp-righmenu"><div class="rightMenu" style="left: ' + leftwith + 'px">\n' +
                                '<div lay-event="refresh"><a href="javascript:;"><i class="layui-icon layui-icon-refresh"></i>刷新当前页</a></div>\n' +
                                '<div lay-event="closeThisTabs"><a href="javascript:;"><i class="layui-icon layui-icon-close-fill"></i>关闭当前页</a></div>\n' +
                                '<div lay-event="closeOtherTabs"><a href="javascript:;"><i class="layui-icon layui-icon-unlink"></i>关闭其它页</a></div>\n' +
                                '<div lay-event="closeAllTabs"><a href="javascript:;"><i class="layui-icon layui-icon-close"></i>关闭全部页</a></div>\n' +
                                '</div><div class="layui-rightmenu-shade"></div></div>';
                            _that.parents('.layui-tab-title').after(menuContent);
                        } else {
                            $('.rightMenu').css('left', leftwith + 'px')
                        }

                    }
                })
                //关闭右键菜单
                $document.on('click', '.layui-body,.layui-header,.layui-side-menu,.layui-tab,.layui-right-shade', function () {
                    $('#layui-sp-righmenu').remove();
                });

            },

        }
    };
    exports("Backend", Backend);
});