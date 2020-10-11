/*!
 * Cropper v3.0.0
 */

layui.config({
    base: '/static/js/cropper/' //layui自定义layui组件目录
}).define(['jquery', 'layer', 'cropper'], function (exports) {
    var $ = layui.jquery
        , layer = layui.layer;
    var html = "<link rel=\"stylesheet\" href=\"/static/js/cropper/cropper.css\">\n" +
        "<div class=\"layui-fluid showImgEdit\" style=\"display: none\">\n" +
        "    <div class=\"layui-row layui-col-space15\" style=\" margin-top:10px; \">\n" +
        "        <div class=\"layui-col-xs9\">\n" +
        "            <div class=\"readyimg\" style=\"height:450px;background-color: rgb(247, 247, 247);\">\n" +
        "                <img src=\"\" >\n" +
        "            </div>\n" +
        "        </div>\n" +
        "        <div class=\"layui-col-xs3\" style=\"height:200px\">\n" +
        "            <div class=\"img-preview\" style=\"width:200px;height:200px;overflow:hidden\">\n" +
        "            </div>\n" +
        "        </div>\n" +
        "        <div class=\"layui-col-xs3\" style=\"height:150px\">\n" +
        "            <div class=\"img-preview\" style=\"width:150px;height:150px;overflow:hidden\">\n" +
        "            </div>\n" +
        "        </div>\n" +
        "        <div class=\"layui-col-xs3\" style=\"height:100px\">\n" +
        "            <div class=\"img-preview\" style=\"width:100px;height:100px;overflow:hidden\">\n" +
        "            </div>\n" +
        "        </div>\n" +
        "    </div>\n" +
        "    <div class=\"layui-row layui-col-space15\">\n" +
        //"        <div class=\"layui-col-xs9\">\n" +
        //"            <div class=\"layui-row\">\n" +
        "                <div >\n" +
        "                    <button type=\"button\" class=\"layui-btn\" cropper-event=\"rotate\" data-option=\"90\" title=\"Rotate 90 degrees\" style=\"font-size:12px !important;\">顺时针旋转90度</button>\n" +
        "                    <button type=\"button\" class=\"layui-btn\" cropper-event=\"rotate\" data-option=\"-90\" title=\"Rotate -90 degrees\" style=\"font-size:12px !important;\"> 逆时针旋转90度</button>\n" +
        "                    <button type=\"button\" class=\"layui-btn\" cropper-event=\"setDragMode\" title=\"移动图片\" style=\"font-size:12px !important;\">移动图片</button>\n" +
        "                    <button type=\"button\" class=\"layui-btn\" cropper-event=\"setDragMode1\" title=\"裁剪图片\" style=\"font-size:12px !important;\">裁剪图片</button>\n" +
        "                    <button type=\"button\" class=\"layui-btn\" cropper-event=\"zoomLarge\" title=\"放大图片\" style=\"font-size:12px !important;\">放大图片</button>\n" +
        "                    <button type=\"button\" class=\"layui-btn\" cropper-event=\"zoomSmall\" title=\"缩小图片\" style=\"font-size:12px !important;\">缩小图片</button>\n" +
        "                    <button type=\"button\" class=\"layui-btn\" cropper-event=\"reset\" title=\"重置图片\" style=\"font-size:12px !important;\">重置图片</button>\n" +
        "                    <button type=\"button\" class=\"layui-btn layui-bg-red\" cropper-event=\"confirmSave\" type=\"button\" style=\"font-size:12px !important;\"> 保存修改</button>\n" +
        "                </div>\n" +
        //"            </div>\n" +
        //"        </div>\n" +
        //"        <div class=\"layui-col-xs3\">\n" +
        //"            <button type=\"button\" class=\"layui-btn\" cropper-event=\"reset\" title=\"重置图片\" style=\"font-size:12px !important;\">重置图片</button>\n" +
        //"            <button type=\"button\" class=\"layui-btn\" cropper-event=\"confirmSave\" type=\"button\" style=\"font-size:12px !important;\"> 保存修改</button>\n" +
        //"        </div>\n" +
        "    </div>\n" +
        "\n" +
        "</div>";
    var obj = {
        render: function (e) {
            var self = this,
                //elem = e.elem,
                saveW = e.saveW,
                saveH = e.saveH,
                mark = e.mark,
                area = e.area,
                url = e.url,
                imgUrl = e.imgUrl,
                done = e.done;
            $('#cropperdiv').html("");
            $('#cropperdiv').append(html);
            $(".showImgEdit .readyimg img").attr('src', imgUrl);
            var content = $('.showImgEdit')
                , image = $(".showImgEdit .readyimg img")
                , preview = '.showImgEdit .img-preview'
                , file = $(".showImgEdit input[name='file']")
                , options = { aspectRatio: mark, preview: preview, viewMode: 1 };
 
            var openbox = layer.open({
                title: "图片裁剪"
                , type: 1
                , content: content
                , area: area
                , success: function () {
                    image.cropper(options);
                }
                , cancel: function (index) {
                    layer.close(index);
                    image.cropper('destroy');
                }
            });
            $(".layui-btn").on('click', function () {
                var event = $(this).attr("cropper-event");
                //监听确认保存图像
                if (event === 'confirmSave') {
                    image.cropper("getCroppedCanvas", {
                        width: saveW,
                        height: saveH
                    }).toBlob(function (blob) {
                        var formData = new FormData();
                        var timestamp = Date.parse(new Date());
                        formData.append('file', blob, timestamp + '.jpeg');
                        $.ajax({
                            method: "post",
                            url: url, //用于文件上传的服务器端请求地址
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function (result) {
                                //保存图片返回表示
                                var obj = eval('(' + result + ')');
                                if (obj.code == 0) {//成功
                                    layer.msg(obj.msg, { icon: 1 });
                                    parent.layer.closeAll();
                                    return done(obj.data.src);//返回图片src
                                } else if (obj.code == -1) {
                                    layer.alert(obj.msg, { icon: 2 });
                                }

                            }
                        });
                        }, 'image/jpeg');
                    //监听旋转
                } else if (event === 'rotate') {
                    var option = $(this).attr('data-option');
                    image.cropper('rotate', option);
                    //重设图片
                } else if (event === 'reset') {
                    image.cropper('reset');
                }
                else if (event === 'zoomLarge') {
                    image.cropper('zoom', 0.1);
                }
                else if (event === 'zoomSmall') {
                    image.cropper('zoom', -0.1);
                }
                else if (event === 'setDragMode') {
                    image.cropper('setDragMode', "move");
                }
                else if (event === 'setDragMode1') {
                    image.cropper('setDragMode', "crop");
                }
                //文件选择
                file.change(function () {
                    var r = new FileReader();
                    var f = this.files[0];
                    r.readAsDataURL(f);
                    r.onload = function (e) {
                        image.cropper('destroy').attr('src', this.result).cropper(options);
                    };
                });
            });
        }

    };
    exports('croppers', obj);
});