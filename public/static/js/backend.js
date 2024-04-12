define([], function () {
    var $ = layui.$, element = layui.element;
    var $document = $(document), $container = $('#fun-app'), FUN_APP = 'fun-app',
        THIS = 'layui-this', SIDE_SHRINK = 'layui-side-shrink',
        //主题配置
        THEME = [
            {
                headerBg: '#fff',
                headerfontColor: '#595959',
                headerBgThis: '#4d70ff',
                headerBgLogo: '#0c0c0c',
                headerLogofontColor: '#fff',
                menuLeftBg: '#23262e',
                menuLeftBgThis: '#4d70ff',
                menuLeftBgHover: '#4d70ff',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#fff',
                headerfontColor: '#595959',
                headerBgThis: '#197971',
                headerBgLogo: '#0c0c0c',
                headerLogofontColor: '#fff',
                menuLeftBg: '#23262e',
                menuLeftBgThis: '#20b598',
                menuLeftBgHover: '#20b598',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',

            }, {
                headerBg: '#fff',
                headerfontColor: '#595959',
                headerBgThis: '#8e10b9',
                headerBgLogo: '#0c0c0c',
                headerLogofontColor: '#fff',
                menuLeftBg: '#23262e',
                menuLeftBgThis: '#8e10b9',
                menuLeftBgHover: '#8e10b9',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',

            }, {
                headerBg: '#fff',
                headerfontColor: '#595959',
                headerBgThis: '#b8279f',
                headerBgLogo: '#fff',
                headerLogofontColor: '#595959',
                menuLeftBg: '#23262e',
                menuLeftBgThis: '#b8279f',
                menuLeftBgHover: '#b8279f',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#fff',
                headerfontColor: '#595959',
                headerBgThis: '#197971',
                headerBgLogo: '#fff',
                headerLogofontColor: '#595959',
                menuLeftBg: '#23262e',
                menuLeftBgThis: '#20b598',
                menuLeftBgHover: '#20b598',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#fff',
                headerfontColor: '#595959',
                headerBgThis: '#8e10b9',
                headerBgLogo: '#fff',
                headerLogofontColor: '#595959',
                menuLeftBg: '#23262e',
                menuLeftBgThis: '#8e10b9',
                menuLeftBgHover: '#8e10b9',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#20b598',
                headerfontColor: '#fff',
                headerBgThis: '#197971',
                headerBgLogo: '#0c0c0c',
                headerLogofontColor: '#fff',
                menuLeftBg: '#23262e',
                menuLeftBgThis: '#20b598',
                menuLeftBgHover: '#20b598',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#20b598',
                headerfontColor: '#fff',
                headerBgThis: '#197971',
                headerBgLogo: '#20b598',
                headerLogofontColor: '#fff',
                menuLeftBg:'#23262e',
                menuLeftBgThis: '#20b598',
                menuLeftBgHover: '#20b598',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#00a65a',
                headerBgThis: '#197971',
                headerBgLogo: '#00a65a',
                headerLogofontColor: '#fff',
                menuLeftBg: '#222d32',
                menuLeftBgThis: '#00a65a',
                menuLeftBgHover: '#00a65a',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#8e10b9',
                headerfontColor: '#fff',
                headerBgThis: '#197971',
                headerBgLogo: '#8e10b9',
                headerLogofontColor: '#fff',
                menuLeftBg:'#23262e',
                menuLeftBgThis: '#8e10b9',
                menuLeftBgHover: '#8e10b9',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#23262e',
                headerfontColor: '#fff',
                headerBgThis: '#0c0c0c',
                headerBgLogo: '#0c0c0c',
                headerLogofontColor: '#fff',
                menuLeftBg: '#23262e',
                menuLeftBgThis: '#20b598',
                menuLeftBgHover: '#3b3f4b',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#ffa4d1',
                headerfontColor: '#fff',
                headerBgThis: '#bf7b9d',
                headerBgLogo: '#e694bd',
                headerLogofontColor: '#fff',
                menuLeftBg: '#1f1f1f',
                menuLeftBgThis: '#ffa4d1',
                menuLeftBgHover: '#ffa4d1',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#4d70ff',
                headerfontColor: '#fff',
                headerBgThis: '#0069b7',
                headerBgLogo: '#0c0c0c',
                headerLogofontColor: '#fff',
                menuLeftBg: '#1f1f1f',
                menuLeftBgThis: '#4d70ff',
                menuLeftBgHover: '#4d70ff',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#963885',
                headerfontColor: '#fff',
                headerBgThis: '#b8279f',
                headerBgLogo: '#000',
                headerLogofontColor: '#fff',
                menuLeftBg: '#191a23',
                menuLeftBgThis: '#963885',
                menuLeftBgHover: '#963885',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#963885',
                headerfontColor: '#fff',
                headerBgThis: '#b8279f',
                headerBgLogo: '#b8279f',
                headerLogofontColor: '#fff',
                menuLeftBg: '#191a23',
                menuLeftBgThis: '#963885',
                menuLeftBgHover: '#963885',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#4d70ff',
                headerfontColor: '#fff',
                headerBgThis: '#4d70ff',
                headerBgLogo: '#4d70ff',
                headerLogofontColor: '#fff',
                menuLeftBg: '#1f1f1f',
                menuLeftBgThis: '#4d70ff',
                menuLeftBgHover: '#4d70ff',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#ffb800',
                headerfontColor: '#fff',
                headerBgThis: '#d09600',
                headerBgLogo: '#000',
                headerLogofontColor: '#fff',
                menuLeftBg:'#23262e',
                menuLeftBgThis: '#ffb800',
                menuLeftBgHover: '#ffb800',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#ffb800',
                headerfontColor: '#fff',
                headerBgThis: '#d09600',
                headerBgLogo: '#d09600',
                headerLogofontColor: '#fff',
                menuLeftBg:'#23262e',
                menuLeftBgThis: '#ffb800',
                menuLeftBgHover: '#ffb800',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#e82121',
                headerfontColor: '#fff',
                headerBgThis: '#ae1919',
                headerBgLogo: '#0c0c0c',
                headerLogofontColor: '#fff',
                menuLeftBg: '#1f1f1f',
                menuLeftBgThis: '#e82121',
                menuLeftBgHover: '#e82121',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            }, {
                headerBg: '#e82121',
                headerfontColor: '#fff',
                headerBgThis: '#ae1919',
                headerBgLogo: '#d91f1f',
                headerLogofontColor: '#fff',
                menuLeftBg: '#1f1f1f',
                menuLeftBgThis: '#e82121',
                menuLeftBgHover: '#e82121',
                menuLeftDlBg: 'rgba(0,0,0,.3)',
                menuLeftfontColor: '#fff',
                menuLeftfontColorHover: '#fff',
            },  {
                headerBg: '#fff',
                headerfontColor: '#000',
                headerBgThis: '#e2ebfb',
                headerBgLogo: '#fff',
                headerLogofontColor: '#000',
                menuLeftBg: '#fff',
                menuLeftBgThis: '#e2ebfb',
                menuLeftBgHover: '#e2ebfb',
                menuLeftDlBg: '#fff',
                menuLeftfontColor: '#000',
                menuLeftfontColorHover: '#e2ebfb',
            }
        ];
    var Backend = {
        /*** 版本*/
        v: '5.0',
        /**
         * @param options
         */
        render: function (options) {
            options.refreshUrl = options.refreshUrl || Fun.url('ajax/clearcache');
            options.themeid = options.themeid || 0;
            options.maxTabs = options.maxTabs || 15;
            THEME = options.theme ?options.theme:THEME;
            Backend.initTabs({
                filter: 'layui-layout-tabs',
                maxTabs: options.maxTabs,
            });
            Backend.hideLoading(options.loadingTime);
            Backend.initBodyTheme();
            Backend.initBgColor();
            Backend.api.bindEvent();
        },
        initBodyTheme:function (name){
            if(Config.site.site_theme ==0){
                return false;
            }
            name = typeof name==='undefined'?'setTab':name;
            $('.layui-side-menu .layui-nav-item').removeClass('layui-nav-hover');
            $('.layui-side-menu .layui-nav-item').find('dl').removeClass('layui-nav-child-drop').removeAttr('style');
            if($('.layui-layout-admin .layui-nav-header').length>0){
                height = $('.layui-nav-header ul').height();//横屏
                $('.layui-layout-admin .layui-pagetabs').attr('style','top:'+(60+height)+'px!important;');
                $('.layui-layout-admin .layui-body').attr('style','padding-bottom:'+(20+height)+'px!important;');
            }
            value = Fun.api.getStorage(name);
            if(value && value == 1) {
                $('.layui-tabs-control.layui-icon-prev,.layui-tabs-control.layui-icon-next,.layui-tabs-control.layui-icon-down,#layui-tab-header').removeClass(
                    'layui-hide');
                $('#layui-app-body').animate({
                    top: '40px'
                }, 100);
            }else if(value && value == 2){
                $('.layui-tabs-control.layui-icon-prev,.layui-tabs-control.layui-icon-next,.layui-tabs-control.layui-icon-down,#layui-tab-header').addClass(
                    'layui-hide');
                $('#layui-app-body').animate({
                    top: 0
                }, 100);
            }
            return false ;
        },
        //加载层,锁屏
        hideLoading: function (time) {
            time = time || 200;
            var colorId = Backend.getColorId();
            theme = Fun.api.getStorage('setFrameTheme');
            var bg = THEME[colorId]['menuLeftBgThis'];
            if (colorId  && theme) $(document).find('.fun-loading').find('span').css('background-color', THEME[colorId]['menuLeftBgHover']);
            setTimeout(function () {
                //判断是否锁定了界面
                $(document).find('.fun-loading').fadeOut();
                if (Fun.api.getStorage('BackendLock')) {

                    title = [__('Input Password')];
                    if(theme) title[1] = 'background:' + THEME[colorId]['menuLeftBgThis'] + ';color:' + THEME[colorId]['menuLeftfontColor'];
                    layui.layer.prompt({
                        btn: [__('Unlock Now')],
                        title: title,
                        closeBtn: 0,
                        formType: 1,
                        success: function (layero, index) {
                            $('body').append(Backend.buildLockScreenHtml());
                            layui.carousel.render({elem: '#lock-screen',width: '100%',height: $(window).height()+'px',interval: 5000});
                            if(theme) layero.find('.layui-layer-btn0').css('background', bg);
                        },
                    }, function (value, index) {
                        if (value.length < 1) {
                            Fun.toastr.error(__('Input Password'));
                            return false;
                        } else {
                            if (value === Fun.api.getStorage('BackendLock')) {
                                Fun.toastr.close(index);
                                //清除密码
                                Fun.api.setStorage('BackendLock', null);
                                Fun.toastr.success(__('Unlock Success'))
                                $('#lock-screen').remove();
                            } else {
                                Fun.toastr.error(__('Password Error'));
                                return false;
                            }
                        }
                    });
                }
            }, time)
        },
        /** tab*/
        initTabs: function (options) {
            options.filter = options.filter || null;
            options.maxTabs = options.maxTabs || 15;
            Backend.listenScroll();
            Backend.listenSwitch(options);
            Backend.listenTabs(options);
            Backend.listenDeltab(options);
            Backend.listenFrameTheme();
        },
        initNav: function (options) {
            //菜单自适应
            var nav = $('.layui-tab .layui-tab-title');
            if(Config.site.site_theme<=0 ){
                if( $(window).width()<=769)  {
                    nav.width('100%'); return;
                }
                pagetab = $('.layui-pagetabs').outerWidth();
                right = $('.layui-nav.layui-layout-right').outerWidth();
                left = $('.layui-nav.layui-layout-left').outerWidth();
                nav.width(pagetab - right- left);
            }
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
        //侧边
        sideFlexible: function () {
            //侧边伸缩
            if($('body').hasClass('menu4') && $(window).width() > 768) return;
            $container.toggleClass(SIDE_SHRINK);
            $container.toggleClass(FUN_APP);
            $('.layui-header #layui-header-nav-pc,.layui-header #layui-header-nav-mobile').toggleClass('layui-layout-nav');
            $('.layui-side-shrink .layui-side-menu .layui-nav-item').removeClass("layui-nav-hover");
            $(window).trigger("resize");
        },
        listenTabs: function (options) {
            var funTabInfo = layui.sessionData("funTabInfo");
            var tabLayId = layui.sessionData('tabLayId');
            var layId = tabLayId.id;
            if (layId === null || layId === undefined) return false;
            if (funTabInfo) {
                $menu = $("[lay-filter='menulist']").find("a[lay-id='"+layId+"']");
                if($menu.length>0){
                    var text = $menu.attr('data-tips') || $menu.attr('title'),
                        url = $menu.attr('data-url'), icon = $menu.find('i').attr('class');
                    Backend.addTab({
                        layId: layId,
                        url: url,
                        text: text,
                        icon: icon,
                        maxTabs: options.maxTabs,
                    });
                    element.tabChange('layui-layout-tabs', layId);
                }
            }
            //菜单自适应
            Backend.initNav();
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
            layId = $('#layui-app-tabs .layui-tab .layui-tab-title').find('.layui-this').attr('lay-id');
            if(!layId){
                layId =  $('#layui-app-tabs .layui-tab .layui-tab-title').children('li:last').attr('lay-id');
            }
            element.tabChange('layui-layout-tabs', layId);
            Backend.changeSessioinTabId(layId);
            $('#layui-nav-righmenu').remove();
        },
        /**
         * 增加tab 公用接口
         */
        addTab: function (options) {
            options.layId = options.layId || '';
            options.url = options.url || '';
            options.text = options.text || '';
            options.icon = Config.site.site_tabicon>0 ? options.icon : 'layui-tab-icon-active';
            options.iframe = options.iframe || null;
            if (top.window.$("#layui-app-tabs .layui-tab .layui-tab-title li").length >= options.maxTabs) {
                Fun.toastr.error(__('window is create by maxnum'));
                return false;
            }
            Backend.changeSessioinTabId(options.layId);
            layui.sessionData('funTabInfo', {
                key: options.layId,
                value: options.layId,
            });
            var ele = element;
            if (options.iframe) ele = top.layui.element;
            var checkLayId = Backend.checkLayId(options.layId), loadindex;
            if(!options.layId) return false;
            if (!checkLayId) {
                loadindex = layui.layer.load();
                ele.tabAdd('layui-layout-tabs', {
                    title: ' <i class="' + options.icon + '"></i><cite>' + options.text + '</cite>' //标题
                    ,
                    content: '<iframe id="'+options.layId+'" width="100%" height="100%" frameborder="no" src="' + options.url + '"></iframe>'
                    ,
                    id: options.layId,
                });
            } else {
                loadindex = layui.layer.load();
                if(Config.site.site_reloadiframe){
                    var current_iframe = window.$("#layui-app-tabs .layui-tab-content div").find("iframe[id='"+options.layId+"']");
                    current_iframe.eq(0).attr('src',options.url)
                    // current_iframe[0].contentWindow.location.reload();
                }
            }
            if (Fun.api.checkScreen()) {
                $container.removeClass(SIDE_SHRINK).addClass('fun-app')
            }
            $('#layui-nav-righmenu').remove();
            layui.layer.close(loadindex);
            ele.tabChange('layui-layout-tabs', options.layId);
            Backend.initNav();
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
            top.window.$("#layui-app-tabs .layui-tab .layui-tab-title li").each(function () {
                var checklayId = $(this).attr('lay-id');
                if (checklayId != null && checklayId === layId) {
                    checkId = true;
                }
            });
            return checkId !== false;
        },
        /**
         * 获取颜色缓存
         */
        getColorId:function(){
            var colorId = Fun.api.getStorage('funColorId');colorId = colorId?colorId:0;
            return colorId;
        },
        /**
         * @returns {string}
         */
        buildLockScreenHtml:function(){
            $str = '<div style="z-index: 999999" class="layui-carousel" id="lock-screen">\n' +
                '  <div carousel-item="">\n' +
                '    <div><img src="/static/backend/images/lockscreen/1.jpg"></div>\n' +
                '    <div><img src="/static/backend/images/lockscreen/2.jpg"></div>\n' +
                '    <div><img src="/static/backend/images/lockscreen/3.jpg"></div>\n' +
                '    <div><img src="/static/backend/images/lockscreen/4.jpg"></div>\n' +
                '    <div><img src="/static/backend/images/lockscreen/5.jpg"></div>\n' +
                '    <div><img src="/static/backend/images/lockscreen/6.jpg"></div>\n' +
                '  </div>\n' +
                '</div>';
            return $str;
        },
        /**
         * 构建背景颜色选择
         * @returns {string}
         */
        buildBgColorHtml: function () {
            var html = '';
            var colorId = Backend.getColorId();
            $.each(THEME, function (key, val) {
                if (key === colorId) {
                    html += '<li class="layui-this" lay-event="setThemeColor" data-color="' + key + '">\n';
                } else {
                    html += '<li  lay-event="setThemeColor" data-color="' + key + '">\n';
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
            var colorId = Backend.getColorId();
            var themeData = THEME[colorId];
            var styleHtml = '.layui-layout-admin .layui-header ul li a{color:' + themeData.headerfontColor + '!important;}' +
                '.layui-layout-admin .layui-header{background-color:' + themeData.headerBg + '!important;}\n' +
                '.layui-header>ul>.layui-nav-item.layui-this:hover{color:' + themeData.headerBgThis + '!important;}\n' +
                // '.layui-header .layui-nav .layui-nav-child dd.layui-this a{background-color:' + themeData.headerBg + '!important;}\n' +
                '.layui-layout-admin .layui-logo {background-color:' + themeData.headerBgLogo + '!important;}\n' +
                '.layui-layout-admin .layui-logo{color:' + themeData.headerfontColor + '!important;}\n' +
                '.layui-layout-admin .layui-logo cite{color:' + themeData.headerLogofontColor + '!important;}\n' +
                '.layui-layout-admin .layui-side-scroll .layui-nav-tree .layui-nav-item a{color:' + themeData.menuLeftfontColor + '}\n' +
                '.layui-layout-admin .layui-side-scroll .layui-nav-tree .layui-nav-item>a:hover{color:' + themeData.menuLeftfontColorHover + '!important;}\n' +
                '.layui-layout-admin .layui-side-scroll .layui-nav-tree .layui-nav-item>a:hover{background-color:' + themeData.menuLeftBgHover + '!important;}\n' +
                '.layui-layout-admin .layui-side-scroll .layui-nav-tree .layui-nav-item>.layui-nav-child:before{background-color:' + themeData.menuLeftBg + '!important;}\n' +
                '.layui-layout-admin .layui-side-scroll .layui-nav-tree>.layui-nav-item>a:before{background-color:' + themeData.menuLeftBgHover + '!important;}\n' +
                '.layui-side.layui-bg-black,.layui-side.layui-bg-black>.layui-side-scroll>ul {background-color:' + themeData.menuLeftBg + '!important;}\n' +
                '.layui-side-scroll .layui-nav .layui-nav-child a:hover:not(.layui-this){background-color:' + themeData.menuLeftBgHover + '!important;}\n' +
                '.layui-layout-admin .layui-nav-tree .layui-this,.layui-layout-admin .layui-nav-tree .layui-this>a,' +
                '.layui-layout-admin .layui-nav-tree .layui-nav-child dd.layui-this,.layui-layout-admin .layui-nav-tree .layui-nav-child dd.layui-this a {background-color: ' + themeData.menuLeftBgThis + ' !important;}\n' +
                '.layui-pagetabs .layui-tab-title li:hover,.layui-pagetabs .layui-tab-title li.layui-this{ color:' + themeData.menuLeftBgThis + '!important;}\n' +
                '.layui-pagetabs .layui-tab-title li.layui-this .layui-tab-icon-active{ background-color:' + themeData.menuLeftBgThis + '!important;}\n' +
                '.layui-layout-admin .layui-nav-tree .layui-nav-bar{background:' + themeData.menuLeftBgThis + '!important;}\n';
            $('#fun-bg-color').html(styleHtml);
            Backend.listenFrameTheme();

        },
        /**
         * 监听tab切换
         * @param options
         */
        listenSwitch: function (options) {
            options.filter = options.filter || '';
            layui.element.on('tab(' + options.filter + ')', function(){
                var layId = $(this).attr('lay-id');
                Backend.changeSessioinTabId(layId);
                Backend.listenSwitchIframe(layId);
                Backend.scrollPostion();
            });
        },
        //监听主题
        listenFrameTheme:function(){
            Fun.api.setFrameTheme();
        },
        /**
         * 监听tab删除
         * @param options
         */
        listenDeltab: function (options) {
            options.filter = options.filter || null;
            element.on('tabDelete(' + options.filter + ')', function () {
                var layId = $(this).parent().attr('lay-id');
                Backend.delTab(layId);
            });
        },
        /**
         * 监听左右滚动
         */
        listenScroll: function (type) {
            var tabNav = $('#layui-app-tabs .layui-tab  .layui-tab-title');
            var left = tabNav.scrollLeft();
            if (type === 'left') {
                tabNav.animate({
                    scrollLeft: left - 550
                }, 100);
            } else {
                tabNav.animate({
                    scrollLeft: left + 550
                }, 100);
            }
        },
        /**
         * 监听切换 左侧菜单的样式和多级菜单的展开
         * @param layId
         */
        listenSwitchIframe: function (layId) {
            $("#layui-side-left-menu").find("li,dd").removeClass("layui-this").removeClass("layui-nav-itemed");//关闭所有展开的菜单
            $("#layui-side-left-menu > li dl.layui-nav-child").removeAttr('style');
            $menu = $("#layui-side-left-menu a[lay-id='"+layId+"']");
            if($menu.length){
                $menu.parents("dd").addClass("layui-nav-itemed");
                $menu.parents("li").addClass("layui-nav-itemed");
                $menu.parents("li").removeAttr("style");
                $menu.parent().removeClass("layui-nav-itemed").addClass("layui-this");
                index = $menu.parents('ul').attr("menu-id");
                if(index){
                    $('#layui-header-nav-pc li a[menu-id="'+index+'"]').trigger('click');
                    $('#layui-header-nav-pc li a[menu-id="'+index+'"]').parent('li').addClass("layui-this").siblings('li').removeClass("layui-this");
                }
            }
        },
        /**
         * 自动定位
         */
        scrollPostion: function () {
            var tabNav = $('#layui-app-tabs .layui-tab .layui-tab-title');
            var autoLeft = 0;
            tabNav.children("li").each(function () {
                if ($(this).hasClass(THIS)) {
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
                var loading = layui.layer.load(0, {shade: false, time: 2 * 1000});
                var clientHeight = (document.documentElement.clientHeight) - 60;
                var bgColorHtml = Backend.buildBgColorHtml();
                var anims = [0, 1, 2, 3, 4, 5, 6];
                var anim = anims[Math.floor(Math.random() * anims.length + 1) - 1];
                var html = '<style>.layui-text-left{text-align: left;padding-right: 0px}' +
                    '.layui-form-item{margin-bottom:5px;}'+
                    '.layui-field-title{margin-bottom:0;}'+
                    '.layui-form-item .layui-quote-nm{margin:10px;border-left: 5px solid #4d70ff;}' +
                    '</style><div class="layui-fun-color">' +
                    '<div class="color-title">' +
                    '<span>' + __('Theme Color') + '</span>' +
                    '</div>\n' +
                    '<div class="color-content">' +
                    '<ul>' + bgColorHtml + '</ul>' +
                    '</div>'+
                    '<form class="layui-form" lay-filter="form" action="">\n' +
                    '<fieldset class="layui-elem-field layui-field-title layui-text-left">\n' +
                    '  <legend>其他设置</legend>' +
                    '</fieldset>'+
                    ' <div class="layui-form-item">' +
                    '        <label class="layui-form-label required">导航模式：</label>\n' +
                    '        <div class="layui-input-block layui-text-left" style="width:auto;">' +
                    '        <select name="site_theme" id="" lay-filter="setTheme">' +
                    '           <option value="0">标准</option>' +
                    '           <option value="1">侧边</option>' +
                    '           <option value="2">水平</option>' +
                    '           <option value="3">顶部</option>' +
                    '           <option value="4">侧栏</option>' +
                    '           <option value="5">顶栏</option>' +
                    '        </select>' +
                    '        </div>\n' +
                    '</div>'+
                    ' <div class="layui-form-item">\n' +
                    '        <label class="layui-form-label">页面主题：</label>' +
                    '        <div class="layui-input-block layui-text-left">' +
                    '            <input lay-filter="setFrameTheme" type="radio" value="1" title="开启" name="setFrameTheme">' +
                    '            <input lay-filter="setFrameTheme" type="radio" value="" title="关闭" name="setFrameTheme">' +
                    '        </div>\n' +
                    '</div>'+
                    '<div class="layui-form-item">\n' +
                    '        <label class="layui-form-label" style="">选项卡：</label>\n' +
                    '        <div class="layui-input-block layui-text-left">' +
                    '            <input lay-filter="setTab" type="radio" value="1" title="显示" tips="标准模式不支持" name="setTab">' +
                    '            <input lay-filter="setTab" type="radio" value="2" title="隐藏" tips="标准模式不支持" name="setTab">' +
                    '        </div>\n' +
                    '</div>'+
                    '</form>'+
                    '<fieldset class="layui-elem-field layui-field-title layui-text-left" >\n' +
                    '              <legend class="">版权信息</legend>\n' +
                    '</fieldset><div class="layui-form-item" style="">\n' +
                    '<blockquote class="layui-elem-quote layui-text-left layui-quote-nm" >\n' +
                    '   <i class="layui-icon layui-icon-about"></i>\n' +
                    '   <span class="layui-font-12">&nbsp; 版权归FunAdmin所有<br>\n' +
                    '    如遇到问题，请点击 <a href="http://doc.funadmin.com/" target="_blank" style="color: red">官方文档</a> 查看源码说明！\n' +
                    '    </span>\n' +
                    '    </blockquote>\n' +
                    '</div>';
                layui.layer.open({
                    type: 1,
                    title: false,
                    closeBtn: 0,
                    shade: 0.2,
                    anim: anim,
                    shadeClose: true,
                    isOutAnim: true,
                    id: 'layui-fun-color',
                    area: ['360px', clientHeight + 'px'],
                    offset: 'rb',
                    content: html,
                });
                data = {
                    'site_theme':Fun.api.getStorage('siteTheme'),
                    'setTab':Fun.api.getStorage('setTab'),
                    'setFrameTheme':Fun.api.getStorage('setFrameTheme'),
                }
                data.site_theme?data.site_theme:0;
                layui.form.val("form", data);
                layui.form.render()
                layui.layer.close(loading);
            },
            /**
             * 设置颜色配置
             */
            setThemeColor: function (othis) {
                var colorId = othis.attr('data-color');
                $('.layui-fun-color .color-content ul .layui-this').attr('class', '');
                $(this).attr('class', THIS);
                Fun.api.setStorage('funColorId',colorId);
                Backend.initBgColor();
            },
            /**
             * 锁定屏幕
             */
            lockScreen: function () {
                var colorId = Backend.getColorId();
                theme = Fun.api.getStorage('setFrameTheme');
                title = [__('Set Password To Lock Screen')];
                if(theme) title[1] =  'background:' + THEME[colorId]['menuLeftBgThis'] + ';color:' + THEME[colorId]['menuLeftfontColor'];
                layui.layer.prompt({
                    btn: [__('Lock Now'),__('Cancel')],
                    title:title,
                    formType: 1,
                    success: function (layero, index) {
                        $('body').append(Backend.buildLockScreenHtml());
                        layui.carousel.render({elem: '#lock-screen',width: '100%',height: $(window).height()+'px',interval: 5000});
                        if(theme)layero.find('.layui-layer-btn0').css('background', THEME[colorId]['menuLeftBgThis']);
                    },yes:function(index, prompt) {
                        var value=(prompt.find(".layui-layer-input").val())
                        if (value.length < 1) {
                            Fun.toastr.error(__('Input Password'));
                            return false;
                        } else {
                            Fun.api.setStorage('BackendLock',value);
                            layui.layer.close(index);
                            title = [__('Input Password')];
                            if(theme) title[1] = 'background:' + THEME[colorId]['menuLeftBgThis'] + ';color:' + THEME[colorId]['menuLeftfontColor'];
                            layui.layer.prompt({
                                btn: [__('Unlock')],
                                title: title,
                                closeBtn: 0,
                                formType: 1,
                                success: function (layero, index) {
                                    if(theme)layero.find('.layui-layer-btn0').css('background', THEME[colorId]['menuLeftBgThis']);
                                }
                            }, function (value, index) {
                                if (value.length < 1) {
                                    Fun.toastr.error(__('Input Password'));
                                    return false;
                                } else {
                                    if (value === Fun.api.getStorage('BackendLock')) {
                                        layui.layer.close(index);
                                        $(".yy").hide();
                                        //清除密码
                                        Fun.api.setStorage('BackendLock', null);
                                        Fun.toastr.success(__('Unlock Success'));
                                    } else {
                                        Fun.toastr.error(__('Password Error'));
                                        return false;
                                    }
                                }
                            });
                        }
                    },btn2:function(){$('#lock-screen').remove();}
                    ,cancel:function(){$('#lock-screen').remove();}
                });
            },
            //伸缩
            flexible: function () {
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
                        if(othis.children('dl').length>0) return false;
                        var tip = othis.data('tips') || othis.children('a').data('tips');
                        layui.layer.tips(tip, othis,{time: 1000});
                    } else {
                        layui.layer.close();
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
                var url = othis.data('ajax') ? othis.data('ajax') : Backend.refreshUrl;
                Fun.ajax({url: url}, function (res) {
                    Fun.toastr.success(res.msg,function(){
                        $("#layui-app-tabs .layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload();
                    });
                }, function (res) {
                    Fun.toastr.error(res.msg,function(){
                        $("#layui-app-tabs .layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload();
                    });
                })
            },
            refresh: function () {  //刷新
                Fun.toastr.success(__('Refresh Success'), setTimeout(function () {
                        $("#layui-app-tabs .layui-tab-content .layui-show").find("iframe")[0].contentWindow.location.reload();
                        Fun.toastr.close();
                    }, 1200)
                );
            }
            //关闭当前标签页
            , closeThisTabs: function () {
                var layId = $("#layui-app-tabs .layui-tab .layui-tab-title li.layui-this").attr('lay-id');
                if (layId) {
                    Backend.delTab(layId)
                }
            }
            //关闭其它标签页
            , closeOtherTabs: function (type) {
                $("#layui-app-tabs .layui-tab .layui-tab-title li").each(function (key, val) {
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
                var url = othis.data('ajax');
                Fun.toastr.confirm(__('Are you sure todo this'), function () {
                    Fun.ajax({url: url, method: 'post'}, function (res) {
                        if (res.code > 0) {
                            Fun.toastr.success(res.msg, setTimeout(function () {
                                window.location = res.url;
                            }, 2000))
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
                var url = othis.data('ajax');
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
                layui.form.on('select(setTheme)', function (data) {
                    url = Fun.url('ajax/setConfig');
                    code = $(data.elem).attr('name');
                    Fun.ajax({url: url,data:{value:data.value,code:code}}, function (res) {
                        Fun.toastr.success(res.msg, function () {
                            Fun.api.setStorage('siteTheme', data.value)
                            window.location.reload();
                        });
                    }, function (res) {
                        Fun.toastr.error(res.msg);
                    })
                });
                layui.form.on('radio(setTab)', function (data) {
                    name = $(data.elem).attr('name');
                    v = Fun.api.getStorage(name)
                    if(v  && v === data.value){
                        return false;
                    }else{
                        if(Config.site.site_theme ==0){
                            Fun.toastr.error('标准模式不支持切换');
                            return false;
                        }
                        Fun.api.setStorage(name, data.value)
                        Backend.initBodyTheme(name);
                    }
                });
                layui.form.on('radio(setFrameTheme)', function (data) {
                    Fun.api.setStorage('setFrameTheme',data.value)
                    window.location.reload();
                });
                //监听导航点击菜单点击*/
                // 主题三 主题四
                $('#layui-header-nav-pc,#layui-header-nav-mobile,#layui-side-nav').on('click','li a',function (){
                    var id  = $(this).attr('menu-id');
                    if(id){
                        var index = layui.layer.load()
                        if(Fun.api.checkScreen()){
                            if($(window).width()>768  && $(window).width()<=1024){
                                $container.hasClass(SIDE_SHRINK)? $container.toggleClass(SIDE_SHRINK):$container.toggleClass(FUN_APP);
                            }else{
                                $container.toggleClass(SIDE_SHRINK);
                                $container.toggleClass(FUN_APP);
                                $(this).parents('li').children('.layui-nav-child').removeClass('layui-show')
                            }
                        }
                        if($('#layui-side-left-menu').find('ul[menu-id="'+id+'"]').length>0){
                            $('#layui-app-body').addClass('layui-sub-body');
                        }
                        $('#layui-side-left-menu').removeClass('layui-hide');//四
                        $('#layui-side-left-menu').find('ul[menu-id="'+id+'"]').removeClass('layui-hide');
                        $('#layui-side-left-menu').find('ul[menu-id="'+id+'"]').siblings('ul').not('[data-rel="external"]').addClass('layui-hide');
                        layui.layer.close(index)
                    }
                })
                //主题2
                $document.on('mouseover click','.layui-nav-header .layui-nav li>a',function (){
                    $(this).parent('li').siblings().children('.layui-nav-child').removeClass('layui-show');
                    $(this).parent('li').children('.layui-nav-child').css('display','none');
                })
                //打开窗口
                $("body").on("click", "*[lay-id],*[lay-event='iframe']", function () {
                    var _t = $(this),target = _t.prop('target')
                        , url = _t.data('url') ? _t.data('url') : _t.data('iframe')
                        , layId = _t.attr('data-id'), text = _t.data('tips') || $(this).attr('title')
                        , icon = _t.find('i').attr('class'), iframe = !!_t.has('data-iframe');
                    layId = layId ? layId : url;
                    var parents = _t.parents('.layui-nav-header');
                    // 如果不存在子级
                    var menuid  = $(this).attr('menu-id');
                    if(menuid==undefined && $(this).parents('#layui-side-nav').length>0){
                        $('#layui-app-body').removeClass('layui-sub-body');//四
                        $('#layui-side-left-menu').addClass('layui-hide');//四

                    }
                    if (_t.siblings().length == 0) {
                        if (target === '_blank') {window.open(url, "_blank");return false;}
                        var options = {layId: layId, text: text, url: url, icon: icon, iframe: iframe};
                        if(_t.data('url')){
                            Backend.addTab(options);
                        }
                        Backend.listenFrameTheme();

                    }// 关闭其他展开的二级标签
                });
                $document.on('click', '*[lay-event]', function () {
                    var _t = $(this), attrEvent = _t.attr('lay-event');
                    Backend.events[attrEvent] && Backend.events[attrEvent].call(this, _t);
                });
                //鼠标放上
                $document.on("mouseenter", ".layui-side-shrink .layui-nav-tree>.layui-nav-item,.layui-side-shrink .layui-nav-itemed", function () {
                    var _t = $(this);
                    if (!Fun.api.checkScreen()) {
                        var top = _t.offset().top;
                        _t.removeClass('layui-nav-itemed');
                        _t.addClass('layui-nav-hover');
                        _t.children('dl').addClass('layui-nav-child-drop');
                        _t.children('dl').css('top', top + 'px');
                        Backend.events.showtips(_t,1)
                    }
                }).on("mouseleave", ".layui-side-shrink .layui-nav-tree>.layui-nav-item", function () {
                    var _t = $(this);
                    _t.removeClass('layui-nav-hover');
                    _t.find('dl').removeClass('layui-nav-child-drop');
                    _t.find('dl').removeAttr('style');
                    Backend.events.showtips(_t,2)
                });
                //鼠标放上
                $document.on("mouseenter", ".layui-side-shrink .layui-side-menu .layui-nav-hover dd", function () {
                    var _t = $(this);
                    if (!Fun.api.checkScreen()) {
                        if (_t.find('dl')) {
                            var top = _t.offset().top;
                            var left = _t.offset().left + _t.width();
                            _t.children('dl').addClass('layui-nav-child-drop');
                            _t.children('dl').css('top', top + 'px');
                            _t.children('dl').css('left', +left + 'px');
                        }
                    }
                }).on("mouseleave", ".layui-side-shrink .layui-side-menu .layui-nav-child-drop," +
                    ".layui-side-shrink .layui-side-menu .layui-nav-child-drop>dd", function () {
                    var _t = $(this);
                    _t.find('dl').removeClass('layui-nav-child-drop');
                    _t.siblings('dd').children('dl').removeClass('layui-nav-child-drop');
                    _t.find('dl').removeAttr('style');
                });
                //鼠标右键盘
                layui.dropdown.render({
                    elem: '#layui-tab-header' //也可绑定到 document，从而重置整个右键
                    ,trigger: 'contextmenu' //contextmenu
                    ,isAllowSpread: false //禁止菜单组展开收缩
                    ,style: 'width: 130px' //定义宽度，默认自适应
                    ,id: 'layui-tab-header-menu' //定义唯一索引
                    ,data: [{
                        title: '  刷新当前页',
                        templet: '<a href="javascript:;" lay-event="refresh"><i class="layui-icon layui-icon-refresh"></i>  {{d.title}}</a>'
                        ,id: 'refresh'
                    }, {type:'-'},{
                        title: '  关闭当前页',
                        templet: '<a href="javascript:;" lay-event="closeThisTabs">&nbsp;<i class="layui-icon layui-icon-close-fill"></i>  {{d.title}}</a>'
                        ,id: 'closeThisTabs'
                    },{type:'-'},{
                        title: '  关闭其它页',
                        templet: '<a href="javascript:;" lay-event="closeOtherTabs">&nbsp;<i class="layui-icon layui-icon-unlink"></i>  {{d.title}}</a>'
                        ,id: 'closeOtherTabs'
                    },{type:'-'},{
                        title: '  关闭全部页',
                        templet: '<a href="javascript:;" lay-event="closeAllTabs">&nbsp;<i class="layui-icon layui-icon-close"></i>  {{d.title}}</a>'
                        ,id: 'closeAllTabs'
                    }],click:function (obj, othis) {
                        var _t = othis.find('a'), attrEvent = obj.id;
                        Backend.events[attrEvent] && Backend.events[attrEvent].call(this, _t);
                    }
                });
                $(window).on("resize", function () {
                    Backend.initBodyTheme();
                    Backend.initNav();
                });
                $(window).trigger('resize');
            },
        }
    };
    return Backend;
});
