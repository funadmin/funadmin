var BASE_URL = document.scripts[document.scripts.length - 1].src.substring(0, document.scripts[document.scripts.length - 1].src.lastIndexOf('/') -2);
require.config({
    urlArgs: 'v=' + (Config.site.app_debug ? Config.site.site_version :(new Date().getTime())),
    packages: [
        {
            name: 'moment',
            location: 'plugins/moment',
            main: 'moment'
        }
    ],
    baseUrl: BASE_URL,
    include: ['css','jquery','bootstrap','layuiall', 'layui','layer','toastr', 'fun', 'backend', 'table', 'form',],
    paths: {
        'lang'          : 'empty:',
        'jquery'        : 'plugins/jquery/jquery-3.5.1.min', // jquery
        'bootstrap'     : 'plugins/bootstrap-3.3.7/js/bootstrap', // jquery

        //layui等组件
        'layuiall'      : 'plugins/layui/layui.all',
        'layui'         : 'plugins/layui/layui',
        'treeGrid'      : 'plugins/lay-module/treeGrid/treeGrid',
        'tableSelect'   : 'plugins/lay-module/tableSelect/tableSelect',
        'treeTable'     : 'plugins/lay-module/treeTable/treeTable',
        'tableEdit'     : 'plugins/lay-module/tableTree/tableEdit',
        'tableTree'     : 'plugins/lay-module/tableTree/tableTree',
        'iconPicker'    : 'plugins/lay-module/iconPicker/iconPicker',
        'iconFonts'     : 'plugins/lay-module/iconPicker/iconFonts',
        'xm-select'     : 'plugins/lay-module/xm-select/xm-select',//下拉多选
        'toastr'        : 'plugins/lay-module/toastr/toastr',//提示框
        'step-lay'      : 'plugins/lay-module/step-lay/step',
        'inputTags'     : 'plugins/lay-module/inputTags/inputTags',
        'timeago'       : 'plugins/lay-module/timeago/timeago',
        'multiSelect'   : 'plugins/lay-module/multiSelect/multiSelect',
        'cityPicker'    : 'plugins/lay-module/cityPicker/city-picker',
        'regionCheckBox': 'plugins/lay-module/regionCheckBox/regionCheckBox',
        'timePicker'    : 'plugins/lay-module/timePicker/timePicker',
        'moment'        : 'plugins/moment/moment',
        //自定义
        'fun'          : 'fun', // api扩展
        'md5'           : 'plugins/lay-module/md5/md5.min', // md5
        'form'          : 'require-form',
        'fu'            : 'require-fu',
        'table'         : 'require-table',
        'upload'        : 'require-upload',
        'addons'        : 'require-addons',//编辑器以及其他安装的插件
    },
    map: {
        '*': {
            'css': 'plugins/require-css/css.min'
        }
    },
    shim: {
        'layui': {
            deps: ['css!plugins/layui/layui/css/layui.css'],
            init: function () {
                return this.layui.config({dir: 'plugins/layui/'});
            },
        },
        'multiSelect': {
            deps: ['css!plugins/lay-module/multiSelect/multiSelect.css'],
        },
        'timePicker': {
            deps: [
                'moment/moment'
            ],
            exports: "moment"
        },
    },

    waitSeconds: 30,
    charset: 'utf-8' // 文件编码
});
// 配置语言包的路径
var paths = {};
paths['lang'] = Config.entrance + 'ajax/lang?callback=define&addons='+Config.addonname+'&controllername=' + Config.controllername;
paths['frontend/'] = 'frontend/';
//初始化控制器对应的JS自动加载
require.config({paths: paths});
require(['jquery'], function ($) {
    $(function () {
        require(['fun','addons'], function (Fun) {
            $(function () {
                if ('undefined' != typeof Config.autojs && Config.autojs) {
                    require([BASE_URL + Config.jspath], function (Controller) {
                        if (Controller.hasOwnProperty(Config.actionname)) {
                            Controller[Config.actionname]();
                        } else {
                            console.log('no action')
                        }
                    });
                }
            })
        })
    })
})