({
    baseUrl : './', //基于appDir，项目目录
    name    : 'js/require-backend.js', //基于baseUrl，项目文件
    out     : 'js/require-backend.min.js', //基于baseUrl，输出文件
    // locale  : 'en-us', //国际化配置
    optimize: 'uglify', //压缩方式
    optimizeCss:'standard',
    //下面的复制require-backend.js
    include: [
        'css','treeGrid','tableSelect',
        'treeTable','tableEdit','tableTree',
        'iconPicker','iconFonts',
        'toastr','step-lay','inputTags' ,
        'timeago','multiSelect','cityPicker','xmSelect',
        'regionCheckBox','timePicker','croppers',
        'moment', 'backend','md5','fun','fu', 'form','table','upload','Vue'],
    paths: {
        'lang'          : 'empty:',
        'jquery'        : 'plugins/jquery/jquery-3.5.1.min', // jquery
        //layui等组件
        'treeGrid'      : 'plugins/lay-module/treeGrid/treeGrid',
        'tableSelect'   : 'plugins/lay-module/tableSelect/tableSelect',
        'treeTable'     : 'plugins/lay-module/treeTable/treeTable',
        'tableEdit'     : 'plugins/lay-module/tableTree/tableEdit',
        'tableTree'     : 'plugins/lay-module/tableTree/tableTree',
        'iconPicker'    : 'plugins/lay-module/iconPicker/iconPicker',
        'iconFonts'     : 'plugins/lay-module/iconPicker/iconFonts',
        'toastr'        : 'plugins/lay-module/toastr/toastr',//提示框
        'step-lay'      : 'plugins/lay-module/step-lay/step',
        'inputTags'     : 'plugins/lay-module/inputTags/inputTags',
        'timeago'       : 'plugins/lay-module/timeago/timeago',
        'multiSelect'   : 'plugins/lay-module/multiSelect/multiSelect',
        'cityPicker'    : 'plugins/lay-module/cityPicker/city-picker',
        'regionCheckBox': 'plugins/lay-module/regionCheckBox/regionCheckBox',
        'timePicker'    : 'plugins/lay-module/timePicker/timePicker',
        'croppers'      : 'plugins/lay-module/cropper/croppers',
        'xmSelect'      : 'plugins/lay-module/xm-select/xm-select',
        'moment'        : 'plugins/moment/moment',
        'Vue'           : 'plugins/vue/vue.global',

        //自定义
        'backend'       : 'plugins/lay-module/fun/backend.min', // fun后台扩展
        'md5'           : 'plugins/lay-module/md5/md5.min', // 后台扩展
        'fun'           : 'js/fun', // api扩展
        'fu'            : 'js/require-fu',
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
        // 'layui': {
        //     init: function () {return this.layui.config({dir: '/static/plugins/layui'})},
        //     exports:"layui",
        // },
        'cityPicker':{
            deps: [
                'plugins/lay-module/cityPicker/city-picker-data',
                'css!plugins/lay-module/cityPicker/city-picker.css'],
        },
        'inputTags':{
            deps: ['css!plugins/lay-module/inputTags/inputTags.css'],
        },
        'regionCheckBox':{
            deps: ['css!plugins/lay-module/regionCheckBox/regionCheckBox.css'],
        },
        'multiSelect': {
            deps: ['css!plugins/lay-module/multiSelect/multiSelect.css'],
        },
        'timePicker':{
            deps:['css!plugins/lay-module/timePicker/timePicker.css'],
        },
        'step': {
            deps: ['css!plugins/lay-module/step/step.css'],
        },
        'croppers': {
            deps: [
                'plugins/lay-module/cropper/cropper',
                'css!plugins/lay-module/cropper/cropper.css'
            ],
            exports: "cropper"
        },
    },
})