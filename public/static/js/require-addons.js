// define([], function () {
//     require.config({
//         // editormd编辑器
//         paths: {
//             // // //其他组件
//             marked          : "addons/editormd/plugins/editormd/lib/marked.min",
//             // prettify        : "addons/editormd/plugins/editormd/lib/prettify.min",
//             // raphael         : "addons/editormd/plugins/editormd/lib/raphael.min",
//             // underscore      : "addons/editormd/plugins/editormd/lib/underscore.min",
//             // flowchart       : "addons/editormd/plugins/editormd/lib/flowchart.min",
//             // jqueryflowchart : "addons/editormd/plugins/editormd/lib/jquery.flowchart.min",
//             // sequenceDiagram : "addons/editormd/plugins/editormd/lib/sequence-diagram.min",
//             // katex           : "addons/editormd/plugins/editormd/lib/katex.min",
//             editormd        : "addons/editormd/plugins/editormd/src/editormd"
//             // Using Editor.md amd version for Require.js
//         },
//         shim: {
//             // editormd
//             'editormd': {
//                 deps: [
//                     'css!/static/addons/editormd/plugins/editormd/css/editormd.min.css',
//                     'css!/static/addons/editormd/plugins/editormd/lib/codemirror/codemirror.min.css',
//                     // "/static/addons/editormd/plugins/editormd/languages/en.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/link-dialog/link-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/reference-link-dialog/reference-link-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/image-dialog/image-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/code-block-dialog/code-block-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/table-dialog/table-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/emoji-dialog/emoji-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/goto-line-dialog/goto-line-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/help-dialog/help-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/html-entities-dialog/html-entities-dialog.js",
//                     // "/static/addons/editormd/plugins/editormd/plugins/preformatted-text-dialog/preformatted-text-dialog.js"
//                 ],
//                 exports: 'editormd',
//             },
//         },
//     });
//     require(['form','upload','editormd'],function (Form,Upload,editormd){
//         console.log(editormd)
//         // editormd.loadCSS("addons/editormd/plugins/editormd/lib/codemirror/addon/fold/foldgutter");
//         Form.events.bindevent = function (form){
//             let Editormd = {
//                 init: {
//                     Config: {
//                     },
//                 },
//                 events: {},
//                 api: {
//                     /**
//                      * editormd  编辑器
//                      */
//                     editor:function () {
//                         var editor = document.querySelectorAll('*[lay-filter="editor"]')
//                         if (editor.length > 0) {
//                             var EditorMd = [];
//                             $.each(editor, function (i, v) {
//                                 var id = $(this).attr('id');
//                                 var name = $(this).attr('name');
//                                 EditorMd[id] = editormd(id, {
//                                     width: "90%",
//                                     height: 640,
//                                     path : '/static/addons/editormd/plugins/editormd/lib/',
//                                     codeFold : true,
//                                     searchReplace : true,
//                                     saveHTMLToTextarea : true,                // 保存HTML到Textarea
//                                     htmlDecode : "style,script,iframe|on*",       // 开启HTML标签解析，为了安全性，默认不开启
//                                     emoji : true,
//                                     taskList : true,
//                                     tex : true,
//                                     tocm            : true,         // Using [TOCM]
//                                     autoLoadModules : false,
//                                     previewCodeHighlight : true,
//                                     flowChart : true,
//                                     sequenceDiagram : true,
//                                     //dialogLockScreen : false,   // 设置弹出层对话框不锁屏，全局通用，默认为true
//                                     //dialogShowMask : false,     // 设置弹出层对话框显示透明遮罩层，全局通用，默认为true
//                                     //dialogDraggable : false,    // 设置弹出层对话框不可拖动，全局通用，默认为true
//                                     //dialogMaskOpacity : 0.4,    // 设置透明遮罩层的透明度，全局通用，默认值为0.1
//                                     //dialogMaskBgColor : "#000", // 设置透明遮罩层的背景颜色，全局通用，默认为#fff
//                                     imageUpload : true,
//                                     imageFormats : ["jpg", "jpeg", "gif", "png", "bmp", "webp"],
//                                     imageUploadURL : "./php/upload.php",
//                                     onload : function() {
//                                         console.log('onload', this);
//                                         //this.fullscreen();
//                                         //this.unwatch();
//                                         //this.watch().fullscreen();
//
//                                         //this.setMarkdown("#PHP");
//                                         //this.width("100%");
//                                         //this.height(480);
//                                         //this.resize("100%", 640);
//                                     }
//                                 });
//                             })
//                         }
//                     },
//                 },
//             };
//             return Editormd.api.editor();
//         }
//     })
// });