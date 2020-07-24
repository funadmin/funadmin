define(['jquery','ueditor','wangEditor','xss'], function ($,undefined,wangEditor,xss) {

    var Editor = {
        init:{
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
                    autoHeightEnabled:true,
                    initialFrameHeight:500,
                    minFrameHeight:500,
                    initialContent:'',
                    wordCount:true,
                    maximumWords:100000,

            },
            wangEdiorConfig:{
                menus:[
                    'head',  // 标题
                    'bold',  // 粗体
                    'fontSize',  // 字号
                    'fontName',  // 字体
                    'italic',  // 斜体
                    'underline',  // 下划线
                    'strikeThrough',  // 删除线
                    'foreColor',  // 文字颜色
                    'backColor',  // 背景颜色
                    'link',  // 插入链接
                    'list',  // 列表
                    'justify',  // 对齐方式
                    'quote',  // 引用
                    'emoticon',  // 表情
                    'image',  // 插入图片
                    'table',  // 表格
                    'video',  // 插入视频
                    'code',  // 插入代码
                    'undo',  // 撤销
                    'redo'  // 重复
                ],
                colors : [
                    '#000000',
                    '#eeece0',
                    '#1c487f',
                    '#4d80bf',
                    '#c24f4a',
                    '#8baa4a',
                    '#7b5ba1',
                    '#46acc8',
                    '#f9963b',
                    '#ffffff'
                ],
                debug:true,
                pasteIgnoreImg:true,
                uploadImgServer:Speed.url('ajax/uploads'),
                uploadImgMaxSize:50 * 1024*1024,
                uploadImgMaxLength:5,
                withCredentials:true,
                uploadImgTimeout:10*1000

            },

        },
        events:{

        },
        api: {
            /**
             * 百度编辑器
             */
            ueditor: function () {
                var edior = document.querySelectorAll('script[type="text/plain"]')
                if (edior.length > 0) {
                    $.each(edior, function (i, v) {
                        var id = $(this).attr('id');
                        var editor =  UE.getEditor(id ,Editor.init.ueditorConfig);
                    })
                }
                UE.Editor.prototype._bkGetActionUrl = UE.Editor.prototype.getActionUrl;
                UE.Editor.prototype.getActionUrl = function (action) {
                    if (
                        action == 'uploadimage' ||
                        action == 'uploadscrawl' ||
                        action == 'uploadimage' ||
                        action == 'uploadvideo' ||
                        action == 'uploadvoice'
                    ) {
                        return Speed.url('ajax/uploads');

                    } else if (action == 'listimage') {
                        return Speed.url('ajax/getList');
                    } else {
                        return this._bkGetActionUrl.call(this, action);
                    }
                };


            },
            /**
             * wangEditor  编辑器
             */
            wangeditor:function () {
                var edior = document.querySelectorAll('div[type="text/plain"]')
                if (edior.length > 0) {
                    $.each(edior, function (i, v) {
                        var id = $(this).attr('id');
                        var name = $(this).attr('name');
                        var editor = new wangEditor('#'+id)
                        editor.customConfig = Editor.init.wangEdiorConfig
                        editor.customConfig.onchange = function (html) {
                            html = filterXSS(html)
                            console.log(html)
                            var textarea = $('form').find('textarea[name="'+name+'"]');
                            if(textarea.length==0){
                                var textHtml = "<textarea style='display: none' name='"+name+"' value='"+ html +"'>"+html+"</textarea>";
                                $('form').append(textHtml);
                            }else{
                                textarea.val(html)
                            }
                        }
                        editor.create()
                    })
                }


            }
        },
    };
    return Editor;
})