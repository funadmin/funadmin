define([], function () {
    require.config({
// 百度编辑器
    paths: {
        //其他组件
        'ueditor'       : 'addons/ueditor/plugins/ueditor/ueditor.all.min',//百度
        'uelang'        : 'addons/ueditor/plugins/ueditor/lang/'+Config.lang+'/'+Config.lang,
        'ueconfig'      : 'addons/ueditor/plugins/ueditor/ueditor.config',
        'ZeroClipboard' : "addons/ueditor/plugins/ueditor/third-party/zeroclipboard/ZeroClipboard.min",
    },
    shim: {
        //百度编辑器依赖
        'uelang':{deps:['ueditor','ueconfig']},
        'ueditor': {
            deps: [
                'ZeroClipboard',
                'ueconfig',
                'css!/static/addons/ueditor/plugins/ueditor/themes/default/css/ueditor.css',],
            exports: 'UE',
            init:function(ZeroClipboard){
                //导出到全局变量，供ueditor使用
                window.ZeroClipboard = ZeroClipboard;
            }
        },
    },
});

require(['form'],function (Form){
    Form.events.bindevent = function (form){
        let Editor = {
            init: {
                ueditorConfig: {
                    toolbars: [[
                        'fullscreen', 'source', '|', 'undo', 'redo', '|',
                        'bold', 'italic', 'underline', 'fontborder', 'strikethrough', 'superscript', 'subscript', 'removeformat', 'formatmatch', 'autotypeset', 'blockquote', 'pasteplain', '|', 'forecolor', 'backcolor', 'insertorderedlist', 'insertunorderedlist', 'selectall', 'cleardoc', '|',
                        'rowspacingtop', 'rowspacingbottom', 'lineheight', '|',
                        'customstyle', 'paragraph', 'fontfamily', 'fontsize', '|',
                        'directionalityltr', 'directionalityrtl', 'indent', '|',
                        'justifyleft', 'justifycenter', 'justifyright', 'justifyjustify', '|', 'touppercase', 'tolowercase', '|',
                        'link', 'unlink', 'anchor', '|', 'imagenone', 'imageleft', 'imageright', 'imagecenter', '|',
                        'simpleupload', 'insertimage', 'emotion', 'scrawl', 'insertvideo', 'music', 'attachment', 'map', 'gmap', 'insertframe', 'insertcode', 'webapp', 'pagebreak', 'template', 'background', '|',
                        'horizontal', 'date', 'time', 'spechars', 'snapscreen', 'wordimage', '|',
                        'inserttable', 'deletetable', 'insertparagraphbeforetable', 'insertrow', 'deleterow', 'insertcol', 'deletecol', 'mergecells', 'mergeright', 'mergedown', 'splittocells', 'splittorows', 'splittocols', 'charts', '|',
                        'print', 'preview', 'searchreplace', 'drafts', 'help'
                    ],],
                    autoHeightEnabled: true,
                    initialFrameHeight: 500,
                    minFrameHeight: 500,
                    initialContent: '',
                    wordCount: true,
                    maximumWords: 100000,

                },

            },
            events: {},
            api: {
                /**
                 * 百度编辑器
                 */
                ueditor: function () {
                    let editor = document.querySelectorAll('*[lay-editor]')
                    if (editor.length > 0) {
                        require(['ueditor'], function (undefined) {
                            $.each(editor, function () {
                                let id = $(this).attr('id');
                                UE.getEditor(id, Editor.init.ueditorConfig);
                            })
                            UE.Editor.prototype._bkGetActionUrl = UE.Editor.prototype.getActionUrl;
                            UE.Editor.prototype.getActionUrl = function (action) {
                                if (
                                    action === 'uploadimage' ||
                                    action === 'uploadscrawl' ||
                                    action === 'uploadvideo' ||
                                    action === 'uploadvoice'
                                ) {
                                    return Fun.url('ajax/uploads');

                                } else if (action === 'listimage') {
                                    return Fun.url('ajax/getList');
                                } else {
                                    return this._bkGetActionUrl.call(this, action);
                                }
                            };

                        })


                    }


                },

            },
        };
        return Editor.api.ueditor()

    }
})



});