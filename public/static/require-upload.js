define(["jquery"], function ($) {
    var upload = layui.upload;
    var Upload = {
        init: {
            requests: {
                upload_url: 'ajax/uploads',
                attach_url: 'ajax/getAttach',
            },
            upload_exts: Config.upload.upload_exts,
            upload_size: Config.upload.upload_size,

        },
        //事件
        events: {

            //附件多图
            mutiUpload: function () {
                Upload.api.mutiUpload()
            },
            //单图或多图文
            uploads: function () {
                Upload.api.uploads();
            },

        },
        api: {
            mutiUpload: function () {
                //多文件列表示例
                var uploadListView = $('.uploadList')
                    , uploadListIns = upload.render({
                    elem: '#uploadListBtn'
                    , url: Speed.url(Upload.init.requests.upload_url) //改成您自己的上传接口
                    , accept: 'file'
                    , drag: true
                    , multiple: true
                    , auto: false
                    , bindAction: '.uploadSubBtn'
                    , choose: function (obj) {
                        var files = this.files = obj.pushFile(); //将每次选择的文件追加到文件队列
                        //读取本地文件
                        obj.preview(function (index, file, result) {
                            var tr = $(['<tr id="upload-' + index + '">'
                                , '<td>' + file.name + '</td>'
                                , '<td>' + (file.size / 1024).toFixed(1) + 'kb</td>'
                                , '<td class="progress"> 0 </td>'
                                , '<td>等待上传</td>'
                                , '<td>'
                                , '<button class="layui-btn layui-btn-xs speed-upload-reload layui-hide">重传</button>'
                                , '<button class="layui-btn layui-btn-xs layui-btn-danger speed-upload-delete">删除</button>'
                                , '</td>'
                                , '</tr>'].join(''));
                            //单个重传
                            tr.find('.speed-upload-reload').on('click', function () {
                                obj.upload(index, file);
                            });

                            //删除
                            tr.find('.speed-upload-delete').on('click', function () {
                                delete files[index]; //删除对应的文件
                                tr.remove();
                                uploadListIns.config.elem.next()[0].value = ''; //清空 input file 值，以免删除后出现同名文件不可选
                            });

                            uploadListView.append(tr);
                        });
                    }
                    , progress: function (n, elem) {
                        var percent = n + '%';//获取进度百分比
                        $('.progress').html(percent); //可配合 layui 进度条元素使用
                    }
                    , done: function (res, index, upload) {

                        if (res.code > 0) { //上传成功
                            var tr = uploadListView.find('tr#upload-' + index)
                                , tds = tr.children();
                            tds.eq(3).html('<span style="color: #5FB878;">上传成功</span>');
                            tds.eq(4).html(''); //清空操作
                            return delete this.files[index]; //删除文件队列已经上传成功的文件
                        }
                        this.error(index, upload);
                    }
                    , error: function (index, upload) {
                        var tr = uploadListView.find('tr#upload-' + index)
                            , tds = tr.children();
                        tds.eq(3).html('<span style="color: #FF5722;">上传失败</span>');
                        tds.eq(4).find('.demo-reload').removeClass('layui-hide'); //显示重传
                    }
                });
            },
            uploads: function () {
                var uploadList = document.querySelectorAll("[lay-upload]");
                if (uploadList.length > 0) {
                    $.each(uploadList, function (i, v) {
                        //普通图片上传
                        var uploadExts = $(this).attr('lay-exts'),
                            uploadNum = $(this).attr('lay-num'),
                            uploadPath = $(this).attr('lay-path');
                            uploadAccept = $(this).attr('lay-accept');
                        uploadSize = $(this).attr('lay-size') || Upload.init.upload_size;
                        uploadmultiple = $(this).attr('lay-multiple');
                        uploadExts = uploadExts || Upload.init.upload_exts;
                        uploadNum = uploadNum || 1;
                        uploadmultiple = uploadmultiple || false;
                        uploadAccept = uploadAccept || 'image';
                        var that = $(this).parents('.layui-upload')
                        var input = that.find('input[type="text"]');
                        var uploadInt = upload.render({
                            elem: this
                            , accept: uploadAccept
                            , exts: uploadExts
                            , size: uploadSize
                            ,multiple:uploadmultiple
                            , url: Speed.url(Upload.init.requests.upload_url) + '?path=' + uploadPath
                            , before: function (obj) {
                                var index = Speed.msg.loading(__('uploading...'))
                            },
                            done: function (res) {
                                if (res.code > 0) {
                                    if(uploadAccept=='image'){
                                        html = '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + res.url + '"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + res.url + '"></i></li>\n';

                                    }else if(uploadAccept=='video'){
                                        html = '<li><video controls class="layui-upload-img fl" width="150" src="' + res.url + '"></video><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + res.url + '"></i></li>\n';

                                    }else if(uploadAccept=='audio') {
                                        html = '<li><audio controls class="layui-upload-img fl"  src="' + res.url + '"></audio><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + res.url + '"></i></li>\n';

                                    }else{
                                        html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="upfileDelete" lay-fileurl="' + res.url + '"></i></li>\n';

                                    }
                                    var inputVal = input.val();
                                    if (uploadNum == 1) {
                                        input.val(res.url);
                                        that.find('.layui-upload-list').html(html)
                                    } else if(uploadNum=='*') {
                                        that.find('.layui-upload-list').append(html)
                                        if(inputVal){
                                            val_temp = (inputVal + ',' + res.url)

                                        }else{
                                            val_temp = res.url
                                        }
                                        input.val(val_temp);
                                    }else{
                                        if(that.find('li').length>=uploadNum){
                                            Speed.msg.error(__('File nun is limited'), function () {
                                                setTimeout(function () {
                                                    Speed.msg.close();
                                                }, 2000)
                                            })
                                            return false;
                                        }else{
                                            that.find('.layui-upload-list').append(html)
                                            if(inputVal){
                                                val_temp = (inputVal + ',' + res.url)

                                            }else{
                                                val_temp = res.url
                                            }
                                            input.val(val_temp);
                                        }

                                    }
                                    Speed.msg.success(__('Upload Success'), function () {
                                        setTimeout(function () {
                                            Speed.msg.close();
                                        }, 2000)
                                    })
                                } else {
                                    Speed.msg.error(__('Upload Failed'), function () {
                                        setTimeout(function () {
                                            Speed.msg.close();
                                        }, 2000)
                                    })
                                }

                            }
                            , error: function () {
                                Speed.msg.error(__('Upload Failed'), function () {
                                    setTimeout(function () {
                                        Speed.msg.close();
                                    }, 2000)
                                })
                            }
                        });

                    })

                }

            },

            bindEvent: function () {
                Upload.events.mutiUpload();
                Upload.events.uploads();

            }
        }
    }

    return Upload;

})