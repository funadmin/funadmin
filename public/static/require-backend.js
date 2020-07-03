var BASE_URL = document.scripts[document.scripts.length - 1].src.substring(0, document.scripts[document.scripts.length - 1].src.lastIndexOf("/") + 1);
require.config({
    // urlArgs: "v=" + Config.version??(new Date().getTime()),
    urlArgs: "v=" + (new Date().getTime()),
    baseUrl: BASE_URL,
    paths: {
        'lang': "empty:",
        "layuiall": "plugins/layui/layui.all",
        "layui": "plugins/layui/layui",
        "treeGrid": "plugins/layui/extend/treeGrid/treeGrid",
        "tableSelect": "plugins/layui/extend/tableSelect/tableSelect",
        'jquery': "plugins/jquery-3.4.1/jquery-3.4.1.min", // jquery
        'speedBackend': "plugins/lay-module/speed/speed-backend", // speed后台扩展
        'md5': "plugins/lay-module/md5/md5.min", // 后台扩展
        'speed': "speed", // api扩展
        'form': 'require-form',
        'table': 'require-table',
        'upload': 'require-upload',
    },
});
// 配置语言包的路径
var paths = {};
paths['lang'] = Config.moduleurl + '/ajax/lang?callback=define&controllername=' + Config.controllername;
paths['backend/'] = 'backend/';
//初始化控制器对应的JS自动加载
require.config({paths: paths});
require(['jquery'], function ($) {
    $(function () {
        require(['jquery', 'speed'], function ($, Speed) {
            $(function () {
                if ("undefined" != typeof Config.autojs && Config.autojs) {
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