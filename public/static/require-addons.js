define([], function () {
    require.config({
// quill编辑器
    paths: {
        //其他组件
        'quill' : 'addons/quill/plugins/quill/quill',//wang
        'xss'   : 'plugins/xss/xss.min',//xss

    },
    shim: {
        // quill
        'quill': {
            deps: [
                'css!/static/addons/quill/plugins/quill/quill.snow.css',
                '/static/addons/quill/plugins/quill/highlight',
            ],
        },
    },
});

require(['form','upload'],function (Form,Upload){

    Form.events.bindevent = function (form){
        const toolbarOptions = [
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],        // 标题字体
            [{ 'font': [] }],                                 // 字体
            ['bold', 'italic', 'underline', 'strike'],        // 切换按钮
            [{ 'align': [] }],                                // 对齐方式
            ['blockquote', 'code-block'],                     // 文本块/代码块
            [{ 'header': 1 }, { 'header': 2 }],               // 用户自定义按钮值
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],     // 有序/无序列表
            [{ 'script': 'sub'}, { 'script': 'super' }],      // 上标/下标
            [{ 'indent': '-1'}, { 'indent': '+1' }],          // 减少缩进/缩进
            [{ 'color': [] }, { 'background': [] }],          // 主题默认下拉，使用主题提供的值
            ['clean'],                                        // 清除格式
            ['image', 'link', 'video']                        // 图片 / 链接 / 视频
        ];
        const titleConfig=[
            {Choice:'.ql-bold',title:'加粗'},
            {Choice:'.ql-italic',title:'斜体'},
            {Choice:'.ql-underline',title:'下划线'},
            {Choice:'.ql-header',title:'段落格式'},
            {Choice:'.ql-strike',title:'删除线'},
            {Choice:'.ql-blockquote',title:'块引用'},
            {Choice:'.ql-code',title:'插入代码'},
            {Choice:'.ql-code-block',title:'插入代码段'},
            {Choice:'.ql-font',title:'字体'},
            {Choice:'.ql-size',title:'字体大小'},
            {Choice:'.ql-list[value="ordered"]',title:'编号列表'},
            {Choice:'.ql-list[value="bullet"]',title:'项目列表'},
            {Choice:'.ql-direction',title:'文本方向'},
            {Choice:'.ql-header[value="1"]',title:'h1'},
            {Choice:'.ql-header[value="2"]',title:'h2'},
            {Choice:'.ql-align',title:'对齐方式'},
            {Choice:'.ql-color',title:'字体颜色'},
            {Choice:'.ql-background',title:'背景颜色'},
            {Choice:'.ql-image',title:'图片'},
            {Choice:'.ql-video',title:'视频'},
            {Choice:'.ql-link',title:'添加链接'},
            {Choice:'.ql-formula',title:'插入公式'},
            {Choice:'.ql-clean',title:'清除字体格式'},
            {Choice:'.ql-script[value="sub"]',title:'下标'},
            {Choice:'.ql-script[value="super"]',title:'上标'},
            {Choice:'.ql-indent[value="-1"]',title:'向左缩进'},
            {Choice:'.ql-indent[value="+1"]',title:'向右缩进'},
            {Choice:'.ql-header .ql-picker-label',title:'标题大小'},
            {Choice:'.ql-header .ql-picker-item[data-value="1"]',title:'标题一'},
            {Choice:'.ql-header .ql-picker-item[data-value="2"]',title:'标题二'},
            {Choice:'.ql-header .ql-picker-item[data-value="3"]',title:'标题三'},
            {Choice:'.ql-header .ql-picker-item[data-value="4"]',title:'标题四'},
            {Choice:'.ql-header .ql-picker-item[data-value="5"]',title:'标题五'},
            {Choice:'.ql-header .ql-picker-item[data-value="6"]',title:'标题六'},
            {Choice:'.ql-header .ql-picker-item:last-child',title:'标准'},
            {Choice:'.ql-size .ql-picker-item[data-value="small"]',title:'小号'},
            {Choice:'.ql-size .ql-picker-item[data-value="large"]',title:'大号'},
            {Choice:'.ql-size .ql-picker-item[data-value="huge"]',title:'超大号'},
            {Choice:'.ql-size .ql-picker-item:nth-child(2)',title:'标准'},
            {Choice:'.ql-align .ql-picker-item:first-child',title:'居左对齐'},
            {Choice:'.ql-align .ql-picker-item[data-value="center"]',title:'居中对齐'},
            {Choice:'.ql-align .ql-picker-item[data-value="right"]',title:'居右对齐'},
            {Choice:'.ql-align .ql-picker-item[data-value="justify"]',title:'两端对齐'}
        ];
        const handlers ={
            image: function image() {
                var self = this;

                var fileInput = this.container.querySelector('input.ql-image[type=file]');
                if (fileInput === null) {
                    fileInput = document.createElement('input');
                    fileInput.setAttribute('type', 'file');
                    // 设置图片参数名
                    fileInput.setAttribute('type', 'file');
                    fileInput.setAttribute('accept', 'image/*');
                    fileInput.classList.add('ql-image');
                    // 监听选择文件
                    fileInput.addEventListener('change', function () {
                        // 创建formData
                        var formData = new FormData();
                        formData.append('file', fileInput.files[0]);
                        // 图片上传
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', Fun.url(Upload.init.requests.upload_url), true);
                        // 上传数据成功，会触发
                        xhr.onload = function (e) {
                            console.log(JSON.parse(xhr.responseText))
                            if (xhr.status === 200) {
                                var res = JSON.parse(xhr.responseText);
                                let length = self.quill.getSelection(true).index;
                                //这里很重要，你图片上传成功后，img的src需要在这里添加，res.path就是你服务器返回的图片链接。
                                self.quill.insertEmbed(length, 'image', res.url);
                                self.quill.setSelection(length + 1)
                            }
                            fileInput.value = ''
                        };
                        // 开始上传数据
                        xhr.upload.onloadstart = function (e) {
                            fileInput.value = ''
                        };
                        // 当发生网络异常的时候会触发，如果上传数据的过程还未结束
                        xhr.upload.onerror = function (e) {
                        };
                        // 上传数据完成（成功或者失败）时会触发
                        xhr.upload.onloadend = function (e) {
                            // console.log('上传结束')
                        };
                        xhr.send(formData)
                    });
                    this.container.appendChild(fileInput);
                }
                fileInput.click();
            }
        };
        let Editor = {
            init: {
                quillConfig: {
                    // debug: 'info',
                    modules: {
                        toolbar: {
                            container: toolbarOptions,  // 工具栏选项
                            // handlers:handlers,  // 事件重写
                        },
                        history: {
                            delay: 2000,
                            maxStack: 500,
                            userOnly: true
                        },
                        syntax: true,
                    },
                    placeholder: __('Please input content'),
                    // readOnly: true,
                    theme: 'snow',
                },

            },
            events: {},
            api: {
                /**
                 * quilleditor  编辑器
                 */
                quilleditor:function () {
                    var editor = document.querySelectorAll('*[lay-editor]')
                    if (editor.length > 0) {
                        require(['quill','xss'], function (Quill,xss) {
                            $.each(editor, function (i, v) {
                                var id = $(this).attr('id');
                                var name = $(this).attr('name');
                                quill = new Quill('#'+id,Editor.init.quillConfig)
                                //定义工具提示
                                for(let item of titleConfig){
                                    let tip = document.querySelector('.ql-toolbar '+ item.Choice)
                                    if (!tip) continue
                                    tip.setAttribute('title',item.title)
                                }
                                $('#'+id).height('400px')
                                quill.on('editor-change', function(eventName, ...args) {
                                    if (eventName === 'text-change') {
                                        if(!document.querySelector('textarea[name="'+name+'"]')){
                                            fileInput = document.createElement('textarea');
                                            fileInput.setAttribute('name',name);
                                            fileInput.setAttribute('style','display:none');
                                            fileInput.value =  filterXSS(quill.container.firstChild.innerHTML);
                                            $('form').append(fileInput);
                                        }else{
                                            $('textarea[name="'+name+'"]').val(quill.container.firstChild.innerHTML)
                                        }

                                    }
                                });
                            })
                        })
                    }

                },
            },
        };
        return Editor.api.quilleditor();

    }
})
});