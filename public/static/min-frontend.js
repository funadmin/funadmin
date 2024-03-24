({
    baseUrl : './', //基于appDir，项目目录
    name    : 'js/require-frontend', //基于baseUrl，项目文件
    out     : 'js/require-frontend.min.js', //基于baseUrl，输出文件
    // locale  : 'en-us', //国际化配置
    optimize: 'uglify2', //压缩方式
    uglify2: {
        output: {
            beautify: false
        },
        beautify: {
            semicolons: true,
        },
        compress: {
            sequences: true,
            dead_code: true,
            global_defs: {
                DEBUG: false
            }
        },
        warnings: true,
        mangle: false
    },
    throwWhen: {
        optimize: true
    },
    preserveLicenseComments: false,
    optimizeCss:'standard',
    include: [
        'jquery', 'css','layCascader','jsoneditor','tableSelect','tableFilter',
        'iconPicker', 'toastr', 'step-lay','inputTags' ,'cityPicker',
        'timeago','multiSelect','xmSelect','selectPlus','selectN','selectPage',
        'regionCheckBox','timePicker','croppers','autoComplete','Sortable',
        'dayjs', 'md5', 'fun','form', 'table', 'upload'
    ],
    paths: {
        'lang'          : 'empty:',
        'dayjs'         : 'plugins/dayjs/dayjs.min',
        'jquery'        : 'plugins/jquery/jquery-3.7.1.min', // jquery
        //layui等组件
        'layCascader'      : 'plugins/lay-module/cascader/cascader',
        'jsoneditor'      : 'plugins/lay-module/jsoneditor/jsoneditor.min',
        'tableFilter'   : 'plugins/lay-module/tableFilter/tableFilter',
        // 'layui'         : 'plugins/layui/layui', // jquery
        'tableSelect'   : 'plugins/lay-module/tableSelect/tableSelect',
        'iconPicker'    : 'plugins/lay-module/iconPicker/iconPicker',
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
        'autoComplete'  : 'plugins/lay-module/autoComplete/autoComplete',
        'Sortable'      : 'plugins/lay-module/Sortable/Sortable.min',
        // //自定义
        'md5'           : 'plugins/lay-module/md5/md5.min', // md5扩展
        'fun'           : 'js/fun', // api扩展
        'table'         : 'js/require-table',
        'form'          : 'js/require-form',
        'upload'        : 'js/require-upload',
        'addons'        : 'js/require-addons',//编辑器以及其他安装的插件
    },
    map: {
        '*': {
            'css': 'plugins/require-css/css.min'
        }
    },
    shim: {
        // 'layui': {
        //     // deps: ['css!plugins/layui/css/layui.css'],
        //     init: function () {return this.layui.config({dir: '/static/plugins/layui/'})},
        // },
        'cityPicker':{
            deps: [
                'plugins/lay-module/cityPicker/city-picker-data',
                'css!plugins/lay-module/cityPicker/city-picker.css'],
        },
        'jsoneditor':{
            deps: ['css!plugins/lay-module/jsoneditor/jsoneditor.min.css'],
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
        },"autoComplete":{
            deps: ['css!plugins/lay-module/autoComplete/autoComplete.css'], exports: "autoComplete"
        },
    },
})
