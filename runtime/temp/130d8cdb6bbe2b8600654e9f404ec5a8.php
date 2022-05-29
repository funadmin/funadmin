<?php /*a:1:{s:62:"D:\wwwroot\my-space\funadmin\app\backend\view\index\index.html";i:1653656152;}*/ ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo syscfg('site','sys_name'); ?>后台管理</title>
    <meta name="renderer" content="webkit">
    <meta property="og:keywords" content="<?php echo syscfg('site','site_seo_keywords'); ?>" />
    <meta property="og:description" content="<?php echo syscfg('site','site_seo_desc'); ?>" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="referrer" content="origin" />
    <meta name="viewport"  content="width=device-width,initial-scale=1.0,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no"  >
    <link rel="stylesheet" href="/static/plugins/layui/css/layui.css" media="all" />
    <link rel="stylesheet" href="/static/backend/css/style.css" media="all">
    <link rel="stylesheet" href="/static/backend/css/fun.css" media="all">
    <link rel="stylesheet" href="/static/backend/css/global.css" media="all" />

    <?php if(syscfg('site','site_theme')==2): ?>
    <link rel="stylesheet" href="/static/backend/css/theme2.css?v=<?php echo time(); ?>" media="all">
    <?php endif; ?>
    <?php echo token_meta(); ?>
    <style id="fun-bg-color">
    </style>
</head>
<script>
    window.Config = <?php echo json_encode($config); ?>;
    window.Config.formData = <?php echo isset($formData)?(json_encode($formData)):'""'; ?>,
    window.STATIC ='/static'
    window.ADDONS = '/static/addons'
    window.PLUGINS = '/static/plugins';
</script>


<body class="layui-layout-body">
<div id="fun-app" class="fun-app">
    <!--        加载层-->
    <div class="fun-loading">
        <div class="loading">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>

    <div class="layui-layout layui-layout-admin" >
        <!--    竖屏-->
        <?php if(syscfg('site','site_theme')==1): ?>
        <div class="layui-header layui-bg-green">
            <!-- 头部区域（可配合layui已有的水平导航） -->
            <ul class="layui-nav layui-layout-left">
                <li class="layui-nav-item layui-tool" lay-unselect="" >
                    <a  href="javascript:;" title="<?php echo lang('flexible'); ?>" lay-event="flexible">
                        <i class="layui-icon layui-icon-shrink-right" id="layui-flexible"></i>
                    </a>
                </li>
                <li class="layui-nav-item" lay-unselect="">
                    <a href="http://www.FunAdmin.com/" target="_blank" title="<?php echo lang('Home'); ?>">
                        <i class="layui-icon layui-icon-website"></i>
                    </a>
                </li>
                <li class="layui-nav-item layui-hide-xs" lay-unselect>
                    <a href="javascript:;" lay-event="refresh" title="<?php echo lang('Refresh'); ?>" data-ajax="<?php echo __u('ajax/clearcache'); ?>"><i
                            class="layui-icon layui-icon-refresh-1"></i></a>
                </li>
                <li class="layui-nav-item layui-hide-xs" lay-unselect>
                    <a href="javascript:;">
                        <i class="layui-icon layui-icon-fonts-clear"></i>
                    </a>
                    <dl class="layui-nav-child">
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear All'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'all']); ?>"><i
                                class="layui-icon layui-icon-fonts-clear"><?php echo lang('Clear All'); ?></i></a></dd>
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear Frontend'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'frontend']); ?>"><i
                                class="layui-icon layui-icon-delete"><?php echo lang('Clear Frontend'); ?></i></a></dd>
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear Backend'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'backend']); ?>"><i
                                class="layui-icon layui-icon-delete"><?php echo lang('Clear Backend'); ?></i></a></dd>

                    </dl>
                </li>
            </ul>
            <ul class="layui-nav layui-layout-right">
                <li class="layui-nav-item mobile layui-hide-xs" lay-unselect>
                    <a href="javascript:;" lay-event="fullscreen" title="<?php echo lang('Fullscreen'); ?>"><i class="layui-icon layui-icon-screen-full"></i></a>
                </li>
                <li class="layui-nav-item layui-hide-xs" lay-unselect="">
                    <a lay-event="lockScreen" title="<?php echo lang('Lock'); ?>"><i class="layui-icon layui-icon-password"></i></a>
                </li>
                <li class="layui-nav-item">
                    <a href="javascript:;">
                        <?php echo session('admin.username'); ?>
                    </a>
                    <dl class="layui-nav-child">
                        <dd><a lay-id="fun-info" data-url="<?php echo __u('auth.admin/upme'); ?>?id=<?php echo session('admin.id'); ?>&type=menu" title="<?php echo lang('Info'); ?>"><?php echo lang('Info'); ?></a></dd>
                        <dd><a lay-id="fun-safe" data-url="<?php echo __u('auth.admin/password',['type'=>1]); ?>" title="<?php echo lang('Safe'); ?>"><?php echo lang('Safe'); ?></a></dd>
                        <dd><a lay-event="logout" data-ajax="<?php echo __u('index/logout'); ?>" title="<?php echo lang('Logout'); ?>"><?php echo lang('Logout'); ?></a></dd>
                    </dl>
                </li>
                <li class="layui-nav-item layui-hide-xs">
                    <a href="javascript:;">
                        <?php echo lang('language'); ?>
                    </a>
                    <dl class="layui-nav-child">
                        <?php if(is_array($languages) || $languages instanceof \think\Collection || $languages instanceof \think\Paginator): $i = 0; $__LIST__ = $languages;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($i % 2 );++$i;?>
                        <dd><a lay-event="langset" title="<?php echo lang($vo['name']); ?>" data-ajax="<?php echo __u('enlang',['lang'=>$vo['name']]); ?>"><?php echo lang($vo['name']); ?></a></dd>
                        <?php endforeach; endif; else: echo "" ;endif; ?>
                        <!--                        <dd><a lay-event="langset" title="<?php echo lang('en-us'); ?>" lay-ajax="<?php echo __u('enlang',['langset'=>'en-us']); ?>">en-us</a></dd>-->
                    </dl>
                </li>
                <li class="layui-nav-item layui-hide-xs">
                    <a href="javascript:;" title="<?php echo lang('Theme'); ?>" lay-event="opentheme">
                        <i class="layui-icon layui-icon-theme"></i>
                    </a>
                </li>
            </ul>
        </div>
        <!--        左侧菜单 logo-->
        <div class="layui-side layui-bg-black layui-side-menu">
            <div class="layui-side-scroll">
                <!--logo-->
                <div class="layui-logo">
                    <a href="http://www.FunAdmin.com" target="_blank">
                        <img src="<?php echo syscfg('site','site_logo'); ?>" alt="logo">
                        <cite><?php echo syscfg('site','site_name'); ?></cite></a>
                </div>
                <!-- 左侧导航区域（可配合layui已有的垂直导航） -->
                <ul class="layui-nav layui-nav-tree" lay-filter="menulist"  lay-shrink="all" id="layui-side-left-menu">
                    <?php echo $menulist; ?>
                </ul>
                <ul data-rel="external" class="layui-nav layui-nav-tree header" style="margin-top: auto" lay-filter="test">
                    <li class="layui-nav-item" style="margin-left: 15px;" data-tips="相关链接">相关链接</li>
                    <li class="layui-nav-item" data-tips="系统官网">
                        <a href="http://www.FunAdmin.com" target="_blank">
                            <i class="layui-icon layui-icon-home layui-red"></i>
                            <span>系统官网</span>
                        </a>
                    </li>
                    <li class="layui-nav-item" data-tips="在线文档">
                        <a href="https://doc.funadmin.com" target="_blank">
                            <i class="layui-icon layui-icon-list layui-yellow"></i>
                            <span>在线文档</span>
                        </a>
                    </li>
                    <li class="layui-nav-item" data-tips="QQ交流群"><a href="https://jq.qq.com/?_wv=1027&k=PJkmNv40" target="_blank">
                        <i class="layui-icon layui-icon-login-qq layui-blue"></i>
                        <span>QQ交流群</span></a>
                    </li>
                </ul>
            </div>
        </div>
        <!--     导航按钮 +主题内容 -->
        <div class="layui-pagetabs" id="layui-app-tabs">
            <div class="layui-icon layui-tabs-control layui-icon-next" lay-event="leftPage"></div>
            <div class="layui-icon layui-tabs-control layui-icon-prev" lay-event="rightPage"></div>
            <div class="layui-icon layui-tabs-control layui-icon-down">
                <ul class="layui-nav layui-tabs-select">
                    <li class="layui-nav-item">
                        <a href="javascript:;"><span class="layui-nav-more"></span></a>
                        <dl class="layui-nav-child layui-anim-fadein">
                            <dd lay-event="closeThisTabs"><a href="javascript:;">关闭当前页</a></dd>
                            <dd lay-event="closeOtherTabs"><a href="javascript:;">关闭其它页</a></dd>
                            <dd lay-event="closeAllTabs"><a href="javascript:;">关闭全部页</a></dd>
                        </dl>
                    </li>
                </ul>
            </div>
            <div class="layui-tab layui-tab-card" id="layui-tab"  overflow lay-allowclose="true" lay-filter="layui-layout-tabs">
                <ul class="layui-tab-title" id="layui-tab-header">
                    <li lay-id="" lay-attr="console" class="layui-this">
                        <i class="layui-icon layui-icon-home"></i>
                    </li>
                </ul>
                <!-- 主体内容 -->
                <div class="layui-body layui-tab-content" id="layui-app-body">
                    <div id="homePage" class="layui-body-tabs layui-tab-item layui-show">
                        <iframe width="100%" height="100%" frameborder="no" border="0" marginwidth="0" marginheight="0" src="<?php echo __u('console'); ?>"></iframe>
                    </div>
                </div>
            </div>

        </div>

        <!--    横屏-->
        <?php elseif((syscfg('site','site_theme')==2)): ?>
        <div class="layui-header layui-bg-green">
            <!-- 头部区域（可配合layui已有的水平导航） -->
            <ul class="layui-nav layui-layout-left">
                <li class="layui-nav-item layui-tool layui-hide-xs " lay-unselect="" >
                    <a  href="javascript:;" title="<?php echo lang('flexible'); ?>" lay-event="flexible">
                        <i class="layui-icon layui-icon-shrink-right" id="layui-flexible"></i>
                    </a>
                </li>
                <li class="layui-nav-item" lay-unselect="">
                    <a href="http://www.FunAdmin.com/" target="_blank" title="<?php echo lang('Home'); ?>">
                        <i class="layui-icon layui-icon-website"></i>
                    </a>
                </li>
                <li class="layui-nav-item layui-hide-xs" lay-unselect>
                    <a href="javascript:;" lay-event="refresh" title="<?php echo lang('Refresh'); ?>" data-ajax="<?php echo __u('ajax/clearcache'); ?>"><i
                            class="layui-icon layui-icon-refresh-1"></i></a>
                </li>
                <li class="layui-nav-item" lay-unselect>
                    <a href="javascript:;">
                        <i class="layui-icon layui-icon-fonts-clear"></i>
                    </a>
                    <dl class="layui-nav-child">
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear All'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'all']); ?>"><i
                                class="layui-icon layui-icon-fonts-clear"><?php echo lang('Clear All'); ?></i></a></dd>
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear Frontend'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'frontend']); ?>"><i
                                class="layui-icon layui-icon-delete"><?php echo lang('Clear Frontend'); ?></i></a></dd>
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear Backend'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'backend']); ?>"><i
                                class="layui-icon layui-icon-delete"><?php echo lang('Clear Backend'); ?></i></a></dd>

                    </dl>
                </li>
            </ul>
            <ul class="layui-nav layui-layout-right">
                <li class="layui-nav-item mobile layui-hide-xs" lay-unselect>
                    <a href="javascript:;" lay-event="fullscreen" title="<?php echo lang('Fullscreen'); ?>"><i class="layui-icon layui-icon-screen-full"></i></a>
                </li>
                <li class="layui-nav-item layui-hide-xs" lay-unselect="">
                    <a lay-event="lockScreen" title="<?php echo lang('Lock'); ?>"><i class="layui-icon layui-icon-password"></i></a>
                </li>
                <li class="layui-nav-item">
                    <a href="javascript:;">
                        <?php echo session('admin.username'); ?>
                    </a>
                    <dl class="layui-nav-child">
                        <dd><a lay-id="fun-info" data-url="<?php echo __u('auth.admin/edit'); ?>?id=<?php echo session('admin.id'); ?>&type=menu" title="<?php echo lang('Info'); ?>"><?php echo lang('Info'); ?></a></dd>
                        <dd><a lay-id="fun-safe" data-url="<?php echo __u('auth.admin/password',['type'=>1]); ?>" title="<?php echo lang('Safe'); ?>"><?php echo lang('Safe'); ?></a></dd>
                        <dd><a lay-event="logout" data-ajax="<?php echo __u('index/logout'); ?>" title="<?php echo lang('Logout'); ?>"><?php echo lang('Logout'); ?></a></dd>
                    </dl>
                </li>
                <li class="layui-nav-item layui-hide-xs">
                    <a href="javascript:;">
                        <?php echo lang('language'); ?>
                    </a>
                    <dl class="layui-nav-child">
                        <?php if(is_array($languages) || $languages instanceof \think\Collection || $languages instanceof \think\Paginator): $i = 0; $__LIST__ = $languages;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($i % 2 );++$i;?>
                        <dd><a lay-event="langset" title="<?php echo lang($vo['name']); ?>" data-ajax="<?php echo __u('enlang',['lang'=>$vo['name']]); ?>"><?php echo lang($vo['name']); ?></a></dd>
                        <?php endforeach; endif; else: echo "" ;endif; ?>
                    </dl>
                </li>
                <li class="layui-nav-item layui-hide-xs">
                    <a href="javascript:;" title="<?php echo lang('Theme'); ?>" lay-event="opentheme">
                        <i class="layui-icon layui-icon-theme"></i>
                    </a>
                </li>
            </ul>
        </div>
        <!--        左侧菜单 logo-->
        <div class="layui-side layui-bg-black layui-side-menu">
            <div class="layui-side-scroll" >
                <!--logo-->
                <div class="layui-logo">
                    <a href="http://www.FunAdmin.com" target="_blank">
                        <img src="<?php echo syscfg('site','site_logo'); ?>" alt="logo">
                        <cite ><?php echo syscfg('site','site_name'); ?></cite></a>
                </div>
                <!-- 左侧导航区域（可配合layui已有的垂直导航） -->
            </div>
        </div>

        <div class="layui-nav-header">
            <ul class="layui-nav" lay-filter="menulist"  lay-shrink="all" id="">
                <?php echo $menulist; ?>
            </ul>
        </div>
        <!--     导航按钮 +主题内容 -->
        <div class="layui-pagetabs" id="layui-app-tabs">
            <div class="layui-icon layui-tabs-control layui-icon-next  " lay-event="leftPage"></div>
            <div class="layui-icon layui-tabs-control layui-icon-prev  " lay-event="rightPage"></div>
            <div class="layui-icon layui-tabs-control layui-icon-down  ">
                <ul class="layui-nav layui-tabs-select">
                    <li class="layui-nav-item">
                        <a href="javascript:;"><span class="layui-nav-more"></span></a>
                        <dl class="layui-nav-child layui-anim-fadein">
                            <dd lay-event="closeThisTabs"><a href="javascript:;">关闭当前页</a></dd>
                            <dd lay-event="closeOtherTabs"><a href="javascript:;">关闭其它页</a></dd>
                            <dd lay-event="closeAllTabs"><a href="javascript:;">关闭全部页</a></dd>
                        </dl>
                    </li>
                </ul>
            </div>
            <div class="layui-tab layui-tab-card" id="layui-tab"  overflow lay-allowclose="true" lay-filter="layui-layout-tabs">
                <ul class="layui-tab-title" id="layui-tab-header">
                    <li lay-id="" lay-attr="console" class="layui-this">
                        <i class="layui-icon layui-icon-home"></i>
                    </li>
                </ul>
                <!-- 主体内容 -->
                <div class="layui-body layui-tab-content" id="layui-app-body">
                    <div id="homePage" class="layui-body-tabs layui-tab-item layui-show">
                        <iframe width="100%" height="100%" frameborder="no" border="0" marginwidth="0" marginheight="0" src="<?php echo __u('console'); ?>"></iframe>
                    </div>
                </div>
            </div>

        </div>

        <?php else: ?>
        <!--        主题三-->
        <div class="layui-header layui-bg-green">
            <!-- 头部区域（可配合layui已有的水平导航） -->
            <ul class="layui-nav layui-layout-left">
                <li class="layui-nav-item layui-tool" lay-unselect="" >
                    <a  href="javascript:;" title="<?php echo lang('flexible'); ?>" lay-event="flexible">
                        <i class="layui-icon layui-icon-shrink-right" id="layui-flexible"></i>
                    </a>
                </li>
            </ul>
            <ul class="layui-nav layui-layout-center layui-hide-xs" id="layui-header-nav-pc">
                <?php echo $menulist['nav']; ?>
            </ul>
            <ul class="layui-nav layui-layout-center layui-hide-sm" id="layui-header-nav-mobile">
                <?php echo $menulist['navm']; ?>
            </ul>
            <ul class="layui-nav layui-layout-right">
                <li class="layui-nav-item" lay-unselect>
                    <a href="javascript:;">
                        <i class="layui-icon layui-icon-fonts-clear"></i>
                    </a>
                    <dl class="layui-nav-child">
                        <dd><a href="javascript:;" lay-event="refresh" title="<?php echo lang('Refresh'); ?>" data-ajax="<?php echo __u('ajax/clearcache'); ?>"><i
                                class="layui-icon layui-icon-refresh-1"></i><?php echo lang('Refresh'); ?></a></dd>
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear All'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'all']); ?>"><i
                                class="layui-icon layui-icon-fonts-clear"><?php echo lang('Clear All'); ?></i></a></dd>
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear Frontend'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'frontend']); ?>"><i
                                class="layui-icon layui-icon-delete"><?php echo lang('Clear Frontend'); ?></i></a></dd>
                        <dd><a href="javascript:;" lay-event="clear" title="<?php echo lang('Clear Backend'); ?>" data-ajax="<?php echo __u('ajax/clearcache',['type'=>'backend']); ?>"><i
                                class="layui-icon layui-icon-delete"><?php echo lang('Clear Backend'); ?></i></a></dd>
                    </dl>
                </li>
                <li class="layui-nav-item mobile layui-hide-xs" lay-unselect>
                    <a href="javascript:;" lay-event="fullscreen" title="<?php echo lang('Fullscreen'); ?>"><i class="layui-icon layui-icon-screen-full"></i></a>
                </li>
                <li class="layui-nav-item layui-hide-xs" lay-unselect="">
                    <a lay-event="lockScreen" title="<?php echo lang('Lock'); ?>"><i class="layui-icon layui-icon-password"></i></a>
                </li>
                <li class="layui-nav-item">
                    <a href="javascript:;">
                        <?php echo session('admin.username'); ?>
                    </a>
                    <dl class="layui-nav-child">
                        <dd><a lay-id="fun-info" data-url="<?php echo __u('auth.admin/edit'); ?>?id=<?php echo session('admin.id'); ?>&type=menu" title="<?php echo lang('Info'); ?>"><?php echo lang('Info'); ?></a></dd>
                        <dd><a lay-id="fun-safe" data-url="<?php echo __u('auth.admin/password',['type'=>1]); ?>" title="<?php echo lang('Safe'); ?>"><?php echo lang('Safe'); ?></a></dd>
                        <dd><a lay-event="logout" data-ajax="<?php echo __u('index/logout'); ?>" title="<?php echo lang('Logout'); ?>"><?php echo lang('Logout'); ?></a></dd>
                    </dl>
                </li>
                <li class="layui-nav-item layui-hide-xs">
                    <a href="javascript:;">
                        <?php echo lang('language'); ?>
                    </a>
                    <dl class="layui-nav-child">
                        <?php if(is_array($languages) || $languages instanceof \think\Collection || $languages instanceof \think\Paginator): $i = 0; $__LIST__ = $languages;if( count($__LIST__)==0 ) : echo "" ;else: foreach($__LIST__ as $key=>$vo): $mod = ($i % 2 );++$i;?>
                        <dd><a lay-event="langset" title="<?php echo lang($vo['name']); ?>" data-ajax="<?php echo __u('enlang',['lang'=>$vo['name']]); ?>"><?php echo lang($vo['name']); ?></a></dd>
                        <?php endforeach; endif; else: echo "" ;endif; ?>
                    </dl>
                </li>
                <li class="layui-nav-item layui-hide-xs">
                    <a href="javascript:;" title="<?php echo lang('Theme'); ?>" lay-event="opentheme">
                        <i class="layui-icon layui-icon-theme"></i>
                    </a>
                </li>
            </ul>
        </div>
        <!--        左侧菜单 logo-->
        <div class="layui-side layui-bg-black layui-side-menu">
            <div class="layui-side-scroll">
                <!--logo-->
                <div class="layui-logo">
                    <a href="http://www.FunAdmin.com" target="_blank">
                        <img src="<?php echo syscfg('site','site_logo'); ?>" alt="logo">
                        <cite><?php echo syscfg('site','site_name'); ?></cite></a>
                </div>
                <!-- 左侧导航区域（可配合layui已有的垂直导航） -->
                <div id="layui-side-left-menu">
                    <?php echo $menulist['menu']; ?>
                </div>
                <ul data-rel="external" class="layui-nav layui-nav-tree header" style="margin-top: auto" lay-filter="test">
                    <li class="layui-nav-item" style="margin-left: 15px;" data-tips="相关链接">相关链接</li>
                    <li class="layui-nav-item" data-tips="系统官网">
                        <a href="http://www.FunAdmin.com" target="_blank">
                            <i class="layui-icon layui-icon-home layui-red"></i>
                            <span>系统官网</span>
                        </a>
                    </li>
                    <li class="layui-nav-item" data-tips="在线文档">
                        <a href="https://doc.funadmin.com" target="_blank">
                            <i class="layui-icon layui-icon-list layui-yellow"></i>
                            <span>在线文档</span>
                        </a>
                    </li>
                    <li class="layui-nav-item" data-tips="QQ交流群"><a href="https://jq.qq.com/?_wv=1027&k=PJkmNv40" target="_blank">
                        <i class="layui-icon layui-icon-login-qq layui-blue"></i>
                        <span>QQ交流群</span></a>
                    </li>
                </ul>
            </div>
        </div>

        <!--     导航按钮 +主题内容 -->
        <div class="layui-pagetabs" id="layui-app-tabs">
            <div class="layui-icon layui-tabs-control layui-icon-next  " lay-event="leftPage"></div>
            <div class="layui-icon layui-tabs-control layui-icon-prev  " lay-event="rightPage"></div>
            <div class="layui-icon layui-tabs-control layui-icon-down  ">
                <ul class="layui-nav layui-tabs-select">
                    <li class="layui-nav-item">
                        <a href="javascript:;"><span class="layui-nav-more"></span></a>
                        <dl class="layui-nav-child layui-anim-fadein">
                            <dd lay-event="closeThisTabs"><a href="javascript:;">关闭当前页</a></dd>
                            <dd lay-event="closeOtherTabs"><a href="javascript:;">关闭其它页</a></dd>
                            <dd lay-event="closeAllTabs"><a href="javascript:;">关闭全部页</a></dd>
                        </dl>
                    </li>
                </ul>
            </div>
            <div class="layui-tab layui-tab-card" id="layui-tab" overflow lay-allowclose="true" lay-filter="layui-layout-tabs">
                <ul class="layui-tab-title" id="layui-tab-header">
                    <li lay-id="" lay-attr="console" class="layui-this">
                        <i class="layui-icon layui-icon-home"></i>
                    </li>
                </ul>
                <!-- 主体内容 -->
                <div class="layui-body layui-tab-content" id="layui-app-body">
                    <div id="homePage" class="layui-body-tabs layui-tab-item layui-show">
                        <iframe width="100%" height="100%" frameborder="no" border="0" marginwidth="0" marginheight="0" src="<?php echo __u('console'); ?>"></iframe>
                    </div>
                </div>
            </div>

        </div>

        <?php endif; ?>
        <!-- 遮罩 -->
        <div class="layui-body-shade" lay-event="shade"></div>
        <!--手机导航-->
        <div class="layui-site-mobile layui-hide-lg layui-hide-md" lay-event="flexible"><i class="layui-icon layui-icon-right"></i></div>
        <!-- 底部固定区域 -->
        <div class="layui-footer">
            <?php echo htmlentities($config['site']['site_copyright']); ?> <span class="pull-right">v<?php echo config('app.version'); ?></span>
        </div>
    </div>


</div>
</body>
</html>

<script src="/static/plugins/layui/layui.js" charset="utf-8"></script>
<script defer src="/static/require.min.js?v=<?php echo syscfg('site','site_version'); ?>" data-main="/static/js/require-backend<?php echo syscfg('site','app_debug')?'':'.min'; ?>.js?v=<?php echo syscfg('site','app_debug')?time():syscfg('site','site_version'); ?>" charset="utf-8"></script>
