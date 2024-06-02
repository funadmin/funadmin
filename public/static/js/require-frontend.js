// +----------------------------------------------------------------------
// | FunAdmin全栈开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code

var BASE_URL = location.protocol+'//'+location.host+'/static/';
var urlArgs = '_v=' + (Config.site.app_debug == 0 ? Config.site.site_version :(new Date().getTime()));var _t = '_t='+(new Date().getTime());
var _t = '_t='+(new Date().getTime());
require.config({
    urlArgs:  urlArgs ,
    packages: [
        {
            name: 'dayjs',
            location: 'plugins/dayjs',
            main: 'dayjs.min'
        }
    ],
    baseUrl: BASE_URL,
    include: ['jquery','css','jsoneditor','layCascader','tableSelect','iconPicker','tableFilter', 'toastr','step-lay','inputTags' ,'xmSelect', 'timeago','selects','cxSelect','selectPage','cityPicker','timePicker','autoComplete','Sortable','croppers', 'md5','fun','form','table','upload','addons'],
    paths: {
        'lang'          : 'empty:',
        'jquery'        : 'plugins/jquery/jquery-3.7.1.min', // jquery
        //layui等组件
        'layCascader'      : 'plugins/lay-module/cascader/cascader',
        'jsoneditor'      : 'plugins/lay-module/jsoneditor/jsoneditor.min',
        'tableFilter'   : 'plugins/lay-module/tableFilter/tableFilter',
        'tableSelect'   : 'plugins/lay-module/tableSelect/tableSelect',
        'iconPicker'    : 'plugins/lay-module/iconPicker/iconPicker',
        'toastr'        : 'plugins/lay-module/toastr/toastr',//提示框
        'step-lay'      : 'plugins/lay-module/step-lay/step',
        'inputTags'     : 'plugins/lay-module/inputTags/inputTags',
        'timeago'       : 'plugins/lay-module/timeago/timeago',
        'selects'       : 'plugins/lay-module/selects/selects',
        'cxSelect'       : 'plugins/lay-module/cxSelect/cxSelect',
        'selectPage'    : 'plugins/lay-module/selectPage/selectpage.min',
        'cityPicker'    : 'plugins/lay-module/cityPicker/city-picker',
        'timePicker'    : 'plugins/lay-module/timePicker/timePicker',
        'croppers'      : 'plugins/lay-module/cropper/croppers',
        'xmSelect'      : 'plugins/lay-module/xm-select/xm-select',
        'autoComplete'  : 'plugins/lay-module/autoComplete/autoComplete',
        'Sortable'      : 'plugins/lay-module/Sortable/Sortable.min',
        'md5'           : 'plugins/lay-module/md5/md5.min', // 后台扩展
        'fun'           : 'js/fun', // api扩展
        'form'          : 'js/require-form',
        'table'         : 'js/require-table',
        'upload'        : 'js/require-upload',
        'addons'        : 'js/require-addons',//编辑器以及其他安装的插件
    },
    map: {
        '*': {
            'css': 'plugins/require-css/css.min'
        }
    },
    shim: {
        'cityPicker':{
            deps: ['plugins/lay-module/cityPicker/city-picker-data', 'css!plugins/lay-module/cityPicker/city-picker.css'],
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
        "autoComplete":{
            deps: ['css!plugins/lay-module/autoComplete/autoComplete.css'], exports: "autoComplete"
        },
    },
    waitSeconds: 30,
    charset: 'utf-8' // 文件编码
});

//初始化控制器对应的JS自动加载
require(["jquery"], function ($) {
    // 配置语言包的路径
    var paths = {};
    paths["lang"] = '/frontend/ajax/lang?callback=define&app='+Config.appname+'&controllername=' + Config.controllername;
    // paths['frontend/'] = 'frontend/';
    require.config({paths:paths});
    $(function () {
        require(['fun','addons'], function (Fun) {
            $(function () {
                if ('undefined' != typeof Config.autojs && Config.autojs) {
                    require([BASE_URL+Config.jspath+'?'+_t], function (Controller) {
                        if (Controller.hasOwnProperty(Config.actionname)) {
                            Controller[Config.actionname]();
                        } else if (Controller.hasOwnProperty('api')) {
                            Controller.api.bindevent()
                        } else {
                            console.log('action ' + Config.actionname + ' is not find')
                        }
                    });
                }
            })
        })
    })
});
