var BASE_URL = document.scripts[document.scripts.length - 1].src.substring(0, document.scripts[document.scripts.length - 1].src.lastIndexOf('/') + 1);
require.config({
    urlArgs: 'v=' + Config.version? Config.version:(new Date().getTime()),
    // urlArgs: 'v=' + (new Date().getTime()),
    packages: [
        {
        name: 'moment',
        location: 'plugins/moment',
        main: 'moment'
    }
    ],
    baseUrl: BASE_URL,
    include: ['css','jquery','bootstrap', 'layui','layer', 'speed', 'backend', 'table', 'form',],
    paths: {
        'lang'          : 'empty:',
        'jquery'        : 'plugins/jquery/jquery-3.5.1.min', // jquery
        'bootstrap'     : 'plugins/bootstrap-3.3.7/js/bootstrap', // jquery

        //layui等组件
        'layuiall'      : 'plugins/layui/layui.all',
        'layui'         : 'plugins/layui/layui',
        'treeGrid'      : 'plugins/layui/extend/treeGrid/treeGrid',
        'tableSelect'   : 'plugins/layui/extend/tableSelect/tableSelect',
        'treeTable'     : 'plugins/layui/extend/treeTable/treeTable',
        'tableEdit'     : 'plugins/layui/extend/tableTree/tableEdit',
        'tableTree'     : 'plugins/layui/extend/tableTree/tableTree',
        'iconPicker'    : 'plugins/layui/extend/iconPicker/iconPicker',
        'iconFonts'     : 'plugins/layui/extend/iconPicker/iconFonts',
        'xm-select'     : 'plugins/layui/extend/xm-select/xm-select',//下拉多选
        'toastr'        : 'plugins/layui/extend/toastr/toastr',//提示框
        'step-lay'        : 'plugins/layui/extend/step-lay/step',

        //其他组件
        'ueditor'       : 'plugins/ueditor/ueditor.all.min',//百度
        'uelang'        : 'plugins/ueditor/lang/'+Config.lang+'/'+Config.lang,
        'ueconfig'      : 'plugins/ueditor/ueditor.config',
        'ZeroClipboard' : "plugins/ueditor/third-party/zeroclipboard/ZeroClipboard.min",
        'wangEditor'    : 'plugins/wangEditor/wangEditor.min',//wang
        'xss'           : 'plugins/xss/xss.min',//xss
        'vue'           : 'plugins/vue/vue',//vue

        //自定义
        'speed'         : 'speed', // api扩展
        'backend' : 'plugins/lay-module/speed/backend', // speed后台扩展
        'md5'           : 'plugins/lay-module/md5/md5.min', // 后台扩展
        'form'          : 'require-form',
        'icon'          : 'require-icon',
        'table'         : 'require-table',
        'upload'        : 'require-upload',
        'date'          : 'require-date',
        'editor'        : 'require-editor',
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
                return this.layui.config({dir: 'plugins/layui/layui'});
            },
        },
        //百度编辑器依赖
        'uelang':{deps:['ueditor','ueconfig']},
        'ueditor': {
            deps: ['ZeroClipboard','ueconfig','css!plugins/ueditor/themes/default/css/ueditor.css',],
            exports: 'UE',
            init:function(ZeroClipboard){
                //导出到全局变量，供ueditor使用
                window.ZeroClipboard = ZeroClipboard;
            }
        },
    },

    waitSeconds: 30,
    charset: 'utf-8' // 文件编码
});
// 配置语言包的路径
var paths = {};
paths['lang'] = Config.entrance + 'ajax/lang?callback=define&addons='+Config.addonname+'&controllername=' + Config.controllername;
paths['backend/'] = 'backend/';
//初始化控制器对应的JS自动加载
require.config({paths: paths});
require(['jquery'], function ($) {
    $(function () {
        require(['jquery', 'speed'], function ($, Speed) {
            $(function () {
                if ('undefined' != typeof Config.autojs && Config.autojs) {
                    require([BASE_URL + Config.jspath], function (Controller) {
                        if (Controller.hasOwnProperty(Config.actionname)) {
                            Controller[Config.actionname]();
                        } else {
                            console.log('error')
                        }
                    });
                }
            })
        })
    })
})