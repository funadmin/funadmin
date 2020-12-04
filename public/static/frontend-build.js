({
    baseUrl : './', //基于appDir，项目目录
    name    : 'js/require-frontend.js', //基于baseUrl，项目文件
    out     : 'js/require-frontend.min.js', //基于baseUrl，输出文件
    // locale  : 'en-us', //国际化配置
    optimize: 'uglify', //压缩方式
    optimizeCss:'standard',
    include: [
        'css','jquery','bootstrap',
        'layui','treeGrid','tableSelect',
        'treeTable','tableEdit','tableTree',
        'iconPicker','iconFonts',
        'toastr','step-lay','inputTags' ,
        'timeago','multiSelect','cityPicker',
        'regionCheckBox','timePicker','croppers',
        'moment','md5','fun','form','fu', 'table','upload'],
    paths: {
        'lang'          : 'empty:',
        'jquery'        : 'plugins/jquery/jquery-3.5.1.min', // jquery
        'bootstrap'     : 'plugins/bootstrap-3.3.7/js/bootstrap', // jquery

        //layui等组件
        'layui'         : 'plugins/layui/layui.all',
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
        'moment'        : 'plugins/moment/moment',

        //自定义
        'md5'           : 'plugins/lay-module/md5/md5.min', // md5扩展
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
        'bootstrap': ['jquery'],
        'layui': {
            deps: ['css!plugins/layui/css/layui.css'],
            init: function () {
                return this.layui.config({dir: 'plugins/'})
            },
        },
        'regionCheckBox':{
            deps: ['css!plugins/lay-module/regionCheckBox/regionCheckBox.css'],
        },
        'multiSelect': {
            deps: ['css!plugins/lay-module/multiSelect/multiSelect.css'],
        },
        'croppers': {
            deps: [
                'plugins/lay-module/cropper/cropper'
            ],
            exports: "cropper"
        },
    },
})