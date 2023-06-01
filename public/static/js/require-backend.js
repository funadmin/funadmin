// +----------------------------------------------------------------------
// | FunAdmin全栈开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code

var BASE_URL = location.protocol+'//'+location.host+'/static/';
require.config({
    urlArgs: 'v=' + (Config.site.app_debug == 0 ? Config.site.site_version :(new Date().getTime())),
    packages: [
        {
            name: 'dayjs',
            location: 'plugins/dayjs',
            main: 'dayjs.min'
        }
    ],
    baseUrl: BASE_URL,
    include: [
        'css','layCascader','tableSelect','tableFilter','iconPicker','iconFonts', 'toastr','step-lay','inputTags', 'timeago','multiSelect','cityPicker', 'selectPlus','selectN','selectPage','xmSelect', 'regionCheckBox','timePicker','croppers', 'backend','md5','fun','form','table','upload','addons'],
    paths: {
        'lang'          : 'empty:',
        'jquery'        : 'plugins/jquery/jquery-3.6.0.min', // jquery
        //layui等组件
        // 'cardTable'     : 'plugins/lay-module/cardTable/cardTable',
        'layCascader'      : 'plugins/lay-module/cascader/cascader',
        'tableFilter'   : 'plugins/lay-module/tableFilter/tableFilter',
        'tableSelect'   : 'plugins/lay-module/tableSelect/tableSelect',
        'iconPicker'    : 'plugins/lay-module/iconPicker/iconPicker',
        'iconFonts'     : 'plugins/lay-module/iconPicker/iconFonts',
        'toastr'        : 'plugins/lay-module/toastr/toastr',//提示框
        'step-lay'      : 'plugins/lay-module/step-lay/step',
        'inputTags'     : 'plugins/lay-module/inputTags/inputTags',
        'timeago'       : 'plugins/lay-module/timeago/timeago',
        'multiSelect'   : 'plugins/lay-module/multiSelect/multiSelect',
        'selectPlus'    : 'plugins/lay-module/selectPlus/selectPlus',
        'selectN'       : 'plugins/lay-module/selectPlus/selectN',
        'selectPage'    : 'plugins/lay-module/selectPage/selectpage.min',
        'cityPicker'    : 'plugins/lay-module/cityPicker/city-picker',
        'regionCheckBox': 'plugins/lay-module/regionCheckBox/regionCheckBox',
        'timePicker'    : 'plugins/lay-module/timePicker/timePicker',
        'croppers'      : 'plugins/lay-module/cropper/croppers',
        'xmSelect'      : 'plugins/lay-module/xm-select/xm-select',
        'md5'           : 'plugins/lay-module/md5/md5.min', // 后台扩展
        'backend'       : 'js/backend', // fun后台扩展
        'fun'           : 'js/fun', // api扩展
        'table'         : 'js/require-table',
        'form'          : 'js/require-form',
        'upload'        : 'js/require-upload',
        'addons'        : 'js/require-addons',//编辑器以及其他安装的插件
    },
    map: {
        '*': {'css': 'plugins/require-css/css.min'}
    },
    shim: {
        // 'cardTable':{
        //     deps: ['css!plugins/lay-module/cardTable/cardTable.css'],
        // },
        'cityPicker':{
            deps: ['plugins/lay-module/cityPicker/city-picker-data',
                'css!plugins/lay-module/cityPicker/city-picker.css'],
        },
        'tableFilter':{
            deps: ['css!plugins/lay-module/tableFilter/tableFilter.css'],
        },
        'timePicker':{
            deps:['css!plugins/lay-module/timePicker/timePicker.css'],
        },
        'croppers': {
            deps: ['plugins/lay-module/cropper/cropper', 'css!plugins/lay-module/cropper/cropper.css'], exports: "cropper"
        },
        "layCascader":{
            deps: ['css!plugins/lay-module/cascader/cascader.css'], exports: "layCascader"
        },
    },
    waitSeconds: 30,
    charset: 'utf-8' // 文件编码
});
//初始化控制器对应的JS自动加载
require(["jquery"], function ($) {
    // 配置语言包的路径
    var paths = {};
    paths["lang"] = '/backend/ajax/lang?callback=define&app='+Config.appname+'&controllername=' + Config.controllername;
    // paths['backend/'] = 'backend/';
    require.config({paths:paths});
    //直接使用$经常出现未定义
    $ = layui.jquery;
    $(function () {
        require(['fun','backend','addons'], function (Fun,Backend) {
            $(function () {
                console.log(Config.jspath)
                if ('undefined' != typeof Config.autojs && Config.autojs) {
                    require([BASE_URL+Config.jspath], function (Controller) {
                        if (typeof Controller!=undefined && Controller.hasOwnProperty(Config.actionname)) {
                            Controller[Config.actionname]();
                        } else {
                            console.log('action'+ Config.actionname+' is not find')
                        }
                    });
                }
            })
        })
    })
});
