({
    baseUrl : './', //基于appDir，项目目录
    name    : 'js/require-backend', //基于baseUrl，项目文件
    out     : 'js/require-backend.min.js', //基于baseUrl，输出文件
    // locale  : 'en-us', //国际化配置
    optimize: 'uglify', //压缩方式
    // optimize: 'none', //压缩方式
    throwWhen: {
        optimize: true
    },
    output: {
        comments: false, // 去掉注释
    },
    optimizeCss:'standard',
    //下面的复制require-backend.js
    include: [
        'css','layCascader','tableSelect','tableFilter',
        'iconPicker','iconFonts', 'toastr',
        'step-lay','inputTags' ,'cityPicker',
        'timeago','multiSelect','xmSelect','selectPlus','selectN','selectPage',
        'regionCheckBox','timePicker','croppers',
        'dayjs', 'md5','fun','form', 'table', 'upload', 'backend'
    ],
    paths: {
        'lang'          : 'empty:',
        'jquery'        : 'plugins/jquery/jquery-3.6.0.min', // jquery
        //layui等组件
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
        'selectN'       : 'plugins/lay-module/selectPlus/selectN',
        'selectPlus'    : 'plugins/lay-module/selectPlus/selectPlus',
        'selectPage'    : 'plugins/lay-module/selectPage/selectpage.min',
        'cityPicker'    : 'plugins/lay-module/cityPicker/city-picker',
        'regionCheckBox': 'plugins/lay-module/regionCheckBox/regionCheckBox',
        'timePicker'    : 'plugins/lay-module/timePicker/timePicker',
        'croppers'      : 'plugins/lay-module/cropper/croppers',
        'xmSelect'      : 'plugins/lay-module/xm-select/xm-select',
        'dayjs'         : 'plugins/dayjs/dayjs.min',
        'md5'           : 'plugins/lay-module/md5/md5.min',
        //自定义 后台扩展
        'fun'           : 'js/fun', // api扩展
        'table'         : 'js/require-table',
        'form'          : 'js/require-form',
        'upload'        : 'js/require-upload',
        'addons'        : 'js/require-addons',//编辑器以及其他安装的插件
        'backend'       : 'js/backend', // fun后台扩展
    },
    map: {
        '*': {
            'css': 'plugins/require-css/css.min'
        }
    },
    shim: {
        // 'layui': {
        //     init: function () {return this.layui.config({dir: '/static/plugins/layui'})},
        //     exports:"layui",
        // },
        'cityPicker':{
            deps: [
                'plugins/lay-module/cityPicker/city-picker-data',
                'css!plugins/lay-module/cityPicker/city-picker.css'],
        },
        'tableFilter':{
            deps: ['css!plugins/lay-module/tableFilter/tableFilter.css'],
        },
        'timePicker':{
            deps:['css!plugins/lay-module/timePicker/timePicker.css'],
        },
        'croppers': {
            deps: [
                'plugins/lay-module/cropper/cropper',
                'css!plugins/lay-module/cropper/cropper.css'
            ],
            exports: "cropper"
        }, "layCascader":{
            deps: ['css!plugins/lay-module/cascader/cascader.css'], exports: "layCascader"
        },
    },
})
