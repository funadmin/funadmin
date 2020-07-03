define(["jquery",'table','tableSelect'], function (undefined,Table,undefined) {

    let
        layer = layui.layer,
        tableSelect = layui.tableSelect,
        table = layui.table,
        upload = layui.upload;

    let Upload = {
        init: {
            requests: {
                upload_url: 'ajax/uploads',
                attach_url: 'ajax/getAttach',
            },
            upload_exts: 'doc|gif|ico|icon|jpg|mp3|mp4|p12|pem|png|rar',

        },

        //事件
        events: {

            //附件多图
            mutiUpload: function () {

            },
            //单图或多图文
            uploads: function () {
                Upload.api.upload();
            },
            select:function(){
                Upload.api.select();
            },
            uploadDelete: function (othis) {
                let uploadName = othis.attr('lay-upload-delete'),
                    deleteUrl = othis.attr('lay-upload-url'),
                    sign = othis.attr('lay-upload-sign');
                let confirm = Speed.msg.confirm('确定删除？', function () {
                    let elem = "input[name='" + uploadName + "']";
                    let currentUrl = $(elem).val();
                    let url = '';
                    if (currentUrl != deleteUrl) {
                        url = currentUrl.replace(sign + deleteUrl, '');
                        $(elem).val(url);
                        $(elem).trigger("input");
                    } else {
                        $(elem).val(url);
                        $('#bing-' + uploadName).remove();
                    }
                    Speed.msg.close(confirm);
                });
                return false;
            }
        },
        api:{
            mutiUpload:function(){
                //多文件列表示例
                var uploadListView = $('.uploadList')
                    , uploadListIns = upload.render({
                    elem: '#UploadListBtn'
                    , url: Speed.url(Upload.init.requests.upload_url) //改成您自己的上传接口
                    , accept: 'file'
                    , drag: true
                    , multiple: true
                    , auto: false
                    , bindAction: '.ListUploadBtn'
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
                            tr.find('.demo-reload').on('click', function () {
                                obj.upload(index, file);
                            });

                            //删除
                            tr.find('.speed-upload-reload').on('click', function () {
                                delete files[index]; //删除对应的文件
                                tr.remove();
                                uploadListIns.config.elem.next()[0].value = ''; //清空 input file 值，以免删除后出现同名文件不可选
                            });

                            uploadListView.append(tr);
                        });
                    }
                    , progress: function (n, elem) {
                        var percent = n + '%';//获取进度百分比
                        console.log(percent);
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

            chooseattach:function(){
                var attachSelectList = document.querySelectorAll("[lay-upload-select]");
                if (attachSelectList.length > 0) {
                    $.each(attachSelectList, function (i, v) {
                        let uploadType = $(this).attr('lay-type'),
                            uploadMine = $(this).attr('lay-mine');
                        uploadMine = uploadMine || 'image';
                        uploadType = uploadType?uploadType:'radio';
                        let input =  $(this).find('input'),
                            uploadElem = $(this).attr('id');
                        tableSelect.render({
                            elem: this,
                            checkedKey: 'id',
                            searchType: 'more',
                            searchList: [
                                {searchKey: 'title', searchPlaceholder: __('FileName or FileMine')},
                            ],
                            table: {
                                url: Speed.url(Upload.init.requests.attach_url),
                                cols: [[
                                    {type: uploadType},
                                    {field: 'id', title: 'ID'},
                                    {
                                        field: 'url',
                                        minWidth: 80,
                                        search: false,
                                        title: '图片信息',
                                        imageHeight: 40,
                                        align: "center",
                                        templet: Table.templet().image()
                                    },
                                    {field: 'original_name', width: 150, title: '文件原名', align: "center"},
                                    {field: 'mime_type', width: 120, title: 'mime类型', align: "center"},
                                    {field: 'create_time', width: 200, title: '创建时间', align: "center", search: 'range'},
                                ]]
                            },
                            done: function (e, data) {
                                let urlArray = [];
                                $.each(data.data, function (index, val) {
                                    urlArray.push(val.url)
                                });
                                let url = urlArray.join(uploadSign);
                                Speed.msg.success('选择成功', function () {
                                    $(elem).val(url);
                                    $(elem).trigger("input");
                                });
                            }
                        })

                    });

                }

            },
            upload:function(){
                let uploadList = document.querySelectorAll("[lay-upload]");
                if (uploadList.length > 0) {
                    $.each(uploadList,function (i,v) {
                        //普通图片上传
                        let uploadExts = $(this).attr('lay-upload-exts'),
                            uploadName = $(this).attr('lay-upload'),
                            uploadNum = $(this).attr('lay-num'),
                            uploadPath = $(this).attr('lay-path');
                            uploadSize = $(this).attr('lay-size');
                        uploadExts = uploadExts || Upload.init.upload_exts;
                        uploadNum = uploadNum || 1;
                        let that = $(this).parents('.layui-upload')
                        let input =  $(this).find('input');
                        let uploadInt = upload.render({
                            elem: this
                            ,accept: 'file'
                            ,exts: uploadExts
                            ,size:uploadSize
                            ,url: Speed.url(Upload.init.requests.upload_url)+'?path='+uploadPath
                            ,before: function(obj){
                                let index = Speed.msg.loading(__('uploading...'))
                                //预读本地文件示例，不支持ie8
                                obj.preview(function(index, file, result){
                                    that.find('img').attr('src', result); //图片链接（base64）
                                });
                            },
                            done: function(res){
                                Speed.msg.close();
                                if(res.code>0){
                                    let inputVal =  input.val();
                                    input.val(inputVal+','+res.url);
                                }else{
                                    //如果上传失败
                                    return layer.msg('上传失败');
                                }
                            }
                            ,error: function(){
                                //演示失败状态，并实现重传
                                let notice = that.find('.notice');
                                notice.html('<span style="color: #FF5722;">上传失败</span> <a class="layui-btn layui-btn-mini demo-reload">重试</a>');
                                notice.find('.demo-reload').on('click', function(){
                                    uploadInt.upload();
                                });
                            }
                        });

                    })
                    // 监听上传文件的删除事件
                    $('body').on('click', '[lay-upload-delete]', function () {
                        let uploadName = $(this).attr('lay-delete'),
                            deleteUrl = $(this).attr('lay-upload-url'),
                            mime = $(this).attr('lay-mime');
                        let confirm = Speed.msg.confirm('确定删除？', function () {
                            let elem = "input[name='" + uploadName + "']";
                            let currentUrl = $(elem).val();
                            let url = '';
                            if (currentUrl !== deleteUrl) {
                                url = currentUrl.replace(mime + deleteUrl, '');
                                $(elem).val(url);
                                $(elem).trigger("input");
                            } else {
                                $(elem).val(url);
                                $('#bing-' + uploadName).remove();
                            }
                            Speed.msg.close(confirm);
                        });
                        return false;
                    });
                }

            },
            bindEvent:function () {
                Upload.events.mutiUpload();
                Upload.events.upload();
                Upload.events.chooseattach();
            }
        }
    }

    return Upload;

})