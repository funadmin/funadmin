/*!
 * Cropper v3.0.0
 */
layui.define(["jquery", "cropper"], function (exports) {
    var $ = layui.jquery;
    var obj = {
        config: {
            id:"",
            elem :'#cropper',
            saveW :'300',
            saveH :'300',
            area :'860px',
            mark :1,
            url :'',
            done :'',
            elem_this:"",
            image:"",
            preview:"",
            file:"",
            content:"",
        },
        init:function(e){
            var that = this;
            that.config = $.extend({}, that.config, e);
            return that;
        },
        render: function (e) {
            obj.init(e);var self = this, elem = e.elem, done = e.done;
            elem_this = e.elem+'_croppers'
            obj.config.id =  elem_this.replace('#','');
            var html = '<div class="layui-fluid croppersbox" id="' + obj.config.id  +'" style="display: none;padding-top: 10px;">\n' + '    <div class="layui-form-item">\n' + '        <div class="layui-input-inline layui-btn-container" style="width: auto;">\n' + '            <label for="cropper_avatarImgUpload" class="layui-btn layui-btn-primary">\n' + '                <i class="layui-icon">&#xe67c;</i>上传\n' + "            </label>\n" + '<input class="layui-upload-file" id="'+obj.config.id+'_cropper_avatar_ImgUpload" type="file" value="选择图片" name="file">\n' + "        </div>\n" + '        <div class="layui-form-mid layui-word-aux" style="float:left"> 300x300px,大小2M</div>\n' + "    </div>\n" + '    <div class="layui-row layui-col-space15">\n' + '        <div class="layui-col-xs9">\n' + '            <div class="readyimg" style="height:450px;background-color: rgb(247, 247, 247);">\n' + '                <img src="" >\n' + "            </div>\n" + "        </div>\n" + '        <div class="layui-col-xs3">\n' + '            <div class="img-preview" style="border:1px solid #409EFF;width:200px;height:200px;overflow:hidden">\n' + "            </div>\n" + "        </div>\n" + "    </div>\n" + '    <div class="layui-row layui-col-space15">\n' + '        <div class="layui-col-xs9">\n' + '            <div class="layui-row">\n' + '                <div class="layui-col-xs6">\n' + '<button type="button" class="layui-btn layui-btn-normal layui-icon layui-icon-left" cropper-event="rotate" data-option="-15" title="Rotate -90 degrees"> 向左旋转</button>\n' + '<button type="button" class="layui-btn layui-btn-normal layui-icon  layui-icon-right" cropper-event="rotate" data-option="15" title="Rotate 90 degrees"> 向右旋转</button>\n' + "                </div>\n" + '                <div class="layui-col-xs6" style="text-align: right;">\n' + '                    <button type="button" class="layui-btn layui-btn-normal layui-icon layui-icon-snowflake\n" cropper-event="move" title="移动"></button>\n' + '   <button type="button" class="layui-btn layui-btn-normal layui-icon layui-icon-addition" cropper-event="large" title="放大图片"></button>\n' + '                    <button type="button" class="layui-btn layui-btn-normal layui-icon layui-icon-subtraction\n" cropper-event="small" title="缩小图片"></button>\n' + '<button type="button" class="layui-btn layui-btn-normal layui-icon layui-icon-refresh" cropper-event="reset" title="重置图片"></button>\n' + "                </div>\n" + "            </div>\n" + "        </div>\n" + '        <div class="layui-col-xs3">\n' + '            <button class="layui-btn layui-btn-fluid layui-btn-danger" cropper-event="confirmSave" type="button"> 保存</button>\n' + "        </div>\n" + "    </div>\n" + "\n" + "</div>";
            obj.config.elem_this = elem_this+'.croppersbox';
            if($(obj.config.elem_this).length == 0) $("body").append(html);
            var content = $(obj.config.elem_this),image = $(obj.config.elem_this+" .readyimg img"),
                preview = obj.config.elem_this+" .img-preview",file = $(obj.config.elem_this+ " input[name='file']");
            options = {aspectRatio: obj.config.mark, preview: preview, viewMode: 1};
            $(elem).on("click", function () {
                var index = parent.layer.getFrameIndex(window.name); //先得到当前iframe层的索引
                parent.layer.full(index);
                layer.open({
                    type: 1, content: content, maxmin:true, area: obj.config.area, move: true, shadeClose: true,
                    resize :true, success: function () {
                        image.cropper(options)
                    }, cancel: function (index) {
                        content.css("display", "none");
                        image.cropper("destroy");
                        layer.close(index)
                    }
                })

            });
            $(".croppersbox .layui-btn").on("click", function () {
                var event = $(this).attr("cropper-event");
                if (event === "confirmSave") {
                    image.cropper("getCroppedCanvas", {width:  obj.config.saveW, height:  obj.config.saveH}).toBlob(function (blob) {
                        var formData = new FormData;
                        formData.append("file", blob, "fun-avatar.png");
                        $.ajax({
                            method: "post",
                            url: obj.config.url,
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function (result) {
                                if (result.code > 0) {
                                    layer.closeAll("page");
                                    $(obj.config.elem_this).hide()
                                }
                                return done(result)
                            }
                        })
                    })
                } else if (event === "rotate") {
                    var option = $(this).data("option");
                    image.cropper("rotate", option)
                } else if (event === "reset") {
                    image.cropper("reset")
                } else if (event === "large") {
                    image.cropper("zoom", .1)
                } else if (event === "small") {
                    image.cropper("zoom", -.1)
                } else if (event === "setDragMode") {
                    image.cropper("setDragMode", "move")
                } else if (event === "setDragMode1") {
                    image.cropper("setDragMode", "crop")
                }
                console.log(file)
                console.log(options)
                file.change(function () {
                    var r = new FileReader;
                    var f = this.files[0];
                    r.readAsDataURL(f);
                    r.onload = function (e) {
                        image.cropper("destroy").attr("src", this.result).cropper(options)
                    }
                })
            })

        }
    };
    exports("croppers", obj)
});