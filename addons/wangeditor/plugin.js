require.config({
// wang编辑器
    paths: {
        //其他组件
        'wangEditor'      : 'addons/wangEditor/plugins/wangEditor/wangEditor.min',//wang
        'xss'             : 'plugins/xss/xss.min',//xss
    },
    shim: {

    },
});

require(['form'],function (Form){
    Form.events.bindevent = function (form){
        let Editor = {
            init: {
                wangEdiorConfig: {
                    menus: [
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
                    colors: [
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
                    debug: true,
                    pasteIgnoreImg: true,
                    uploadImgServer: Fun.url('ajax/uploads'),
                    uploadImgMaxSize: 50 * 1024 * 1024,
                    uploadImgMaxLength: 5,
                    withCredentials: true,
                    uploadImgTimeout: 10 * 1000

                },

            },
            events: {},
            api: {
                /**
                 * wangEditor  编辑器
                 */
                wangeditor:function () {
                    var editor = document.querySelectorAll('*[lay-editor]')
                    if (editor.length > 0) {
                        require(['wangEditor','xss'], function (wangEditor,xss) {

                            $.each(editor, function (i, v) {
                                var id = $(this).attr('id');
                                var name = $(this).attr('name');
                                var editor = new wangEditor('#'+id)
                                editor.customConfig = Editor.init.wangEdiorConfig
                                editor.customConfig.onchange = function (html) {
                                    html = filterXSS(html)
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
                        })
                    }



                },
            },
        };
        return Editor.api.wangeditor()

    }
})