// +----------------------------------------------------------------------
// | FunAdmin全栈开发框架 [基于layui开发]
// +----------------------------------------------------------------------
// | Copyright (c) 2020-2030 http://www.funadmin.com
// +----------------------------------------------------------------------
// | git://github.com/funadmin/funadmin.git 994927909
// +----------------------------------------------------------------------
// | Author: yuege <994927909@qq.com> Apache 2.0 License Code
define(["jquery", 'croppers'], function($, croppers) {
    var upload = layui.upload;
    var croppers = layui.croppers;
    var Upload = {
        init: {
            requests: {
                upload_url: 'ajax/uploads',
                attach_url: 'ajax/getAttach',
                select_url:'sys.attach/selectfiles'
            },
            upload_exts: Config.upload.upload_file_type,
            upload_size: Config.upload.upload_file_max,
            upload_chunk: Config.upload.upload_chunk,
            upload_chunksize: Config.upload.upload_chunksize,
        },
        //事件
        events: {
            //单图或多图文
            uploads: function() {
                Upload.api.uploads();
            },
            //裁剪
            cropper: function() {
                Upload.api.cropper();
            },
        },
        api: {
            uploads: function(ele,options,success,error,choose,progress) {
                var uploadList = typeof ele === 'undefined' ? $('*[lay-filter="upload"]') : ele;
                if (uploadList.length > 0) {
                    var opt = [],uploadInt = [];
                    //读取本地文件
                    var blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice
                    var  chunkSize = Upload.init.upload_chunksize*1024*1024;
                    var  maxSize = Upload.init.upload_size*1024*1024;
                    layui.each(uploadList, function(i, v) {
                        //普通图片上传
                        var data = $(this).data();
                        if(typeof data.value == 'object') data = data.value;
                        var uploadNum = data.num,
                            uploadMime = data.mime,
                            uploadAccept = data.accept,
                            uploadPath = data.path || 'upload',
                            uploadSize = data.size,
                            save = data.save || 0,
                            group = data.group || '',
                            uploadmultiple = data.multiple,
                            uploadExts = data.exts,chunk = data.chunk,
                        uploadNum = uploadNum || 1;
                        uploadSize = uploadSize || Upload.init.upload_size;
                        uploadExts = uploadExts || Upload.init.upload_exts;
                        uploadExts = uploadExts.indexOf(',') ? uploadExts.replace(/,/g, '|') : uploadExts
                        uploadmultiple = uploadmultiple || false;
                        uploadNum = uploadmultiple && uploadNum==1 ? 100:uploadNum;
                        uploadAccept = uploadAccept || uploadMime || "*";
                        uploadAccept = uploadAccept === '*' ? 'file' : uploadAccept;
                        var _parent = $(this).parents('.layui-upload'), input = _parent.find('input[type="text"]'),index;
                        var fileList = [],chunkList= [];
                        opt[i] = $.extend({
                            elem: this,
                            accept: uploadAccept,
                            size: uploadSize*1024,
                            number:uploadNum,
                            multiple: uploadmultiple,
                            auto:false,
                            url: Fun.url(Upload.init.requests.upload_url) + '?path=' + uploadPath+'&save='+save+'&group_id='+group,
                            before: function(obj) {
                                    if(chunk==undefined || chunk ==false || chunk == 0){
                                        index = Fun.toastr.loading(__('uploading'),setTimeout(function(){
                                            Fun.toastr.close()
                                        },1200))
                                    }else{
                                        if (!$('#' + this.data.chunkId).length) {
                                            window[this.data.chunkId] = layui.layer.open({
                                                type: 1,
                                                title: false,
                                                skin: 'chunkProgress',
                                                closeBtn: 0,
                                                resize: false,
                                                shade:0.1,
                                                area: ['420px', '20px'],
                                                content: [
                                                    '<style>.chunkProgress {background-color: transparent!important;box-shadow: 0 0 0 rgba(0,0,0,0)!important;' +
                                                    '}</style><div id="' + this.data.chunkId + '" class="layui-progress layui-progress-big" lay-showPercent="yes" lay-filter="uploadProgress">',
                                                    '<div class="layui-progress-bar layui-bg-blue" lay-percent="' + Math.ceil(100 * (0 + this.data.chunkIndex) / this.data.chunkCount) + '%" ></div>',
                                                    '</div>',].join(''),
                                                success: function (layerObj, index) {
                                                    layui.layer.setTop(layerObj);
                                                }
                                            })
                                            layui.element.render();
                                        }else{
                                            layui.element.progress('uploadProgress', Math.ceil((100*(1 + this.data.chunkIndex ) / this.data.chunkCount)) + '%');
                                        }
                                    }
                            },
                            progress: progress===undefined?function(n, elem) {
                            }:progress,
                            choose:choose===undefined? function(obj) {
                                var that = this;
                                var files = this.files = obj.pushFile(); //将每次选择的文件追加到文件队列
                                obj.preview(function (index, file, result) {
                                    if (file.size  > maxSize) {
                                        delete files[index];
                                        Fun.toastr.error(__('文件大小超过限制，最大不超过' + maxSize/1024 + 'KB'));
                                        return false;
                                    }
                                    if (file.size <= chunkSize || chunk == undefined || chunk == 0 || chunk ==false) {
                                        console.log(1)
                                        obj.upload(index, file)
                                        delete files[index];
                                    } else if(chunk == true || chunk == 1) {
                                        var chunkId = file.lastModified +"-"+ file.size,
                                            chunkCount = Math.ceil(file.size / chunkSize),
                                            fileExt = /\.([0-9A-z]+)$/.exec(file.name)[1];
                                        var list = [];
                                        for (i=0;i<chunkCount;i++){
                                            list.push({
                                                status:0,
                                                fileSize: file.size,
                                                fileName: file.name,
                                                fileType: file.type,
                                                fileExt: fileExt,
                                                chunkId: chunkId,
                                                chunkIndex: i,
                                                chunkCount: chunkCount,
                                                chunkSize: chunkSize/(1024*1024),
                                                start : i * chunkSize,
                                                end: Math.min(file.size,  i * chunkSize + chunkSize),
                                            });
                                        }
                                        chunkList[chunkId] = list;
                                        fileList[chunkId] = {_that: that, file: file, obj:obj, index: index};
                                        var progress = 0;
                                        for (var key in chunkList) {
                                            if (chunkList[key].status === 0) {
                                                progress = key;
                                                break;
                                            }
                                        }
                                        that.data = list[progress];
                                        start = progress * chunkSize;
                                        end = parseInt(Math.min(file.size, start + chunkSize));
                                        obj.upload(index, blobSlice.call(file,start,end));
                                    }
                                })
                            }:choose,
                            done: success===undefined?function(res, index, upload) {
                                if (res.code > 0) {
                                    if(res.data['chunkId']!==undefined && !res.data.url){
                                        var chunkIndex = res.data.chunkIndex, chunkId = res.data.chunkId,chunkCount=res.data.chunkCount;
                                        var currentChunkList =chunkList[chunkId][chunkIndex];
                                        if(chunkIndex +1 < chunkCount){
                                            var start = currentChunkList.end;end = start + chunkSize;
                                            chunkList[chunkId][chunkIndex]['status'] = 1;
                                            _that = fileList[chunkId]._that;
                                            _that.data = chunkList[chunkId][1 + chunkIndex ];
                                            fileList[chunkId].obj.upload(fileList[chunkId].file, blobSlice.call(fileList[chunkId].file, start, end));
                                        }else{
                                            layui.element.progress('uploadProgress', Math.ceil((1 + chunkIndex ) * 100 / chunkCount) + '%');//更新进度条
                                        }
                                    }else{
                                        if (res.data['chunkId'] !== undefined) {
                                            layui.layer.close(window[res.data.chunkId]);
                                            delete chunkList[res.data['chunkId']];
                                            delete fileList[res.data['chunkId']];
                                            res.url = res.data.url;
                                        }
                                        var img ='jpg|jpeg|png|gif|svg|bmp|webp';
                                        var video ='mp4|rmvb|avi|ts';
                                        var zip ='jpg|jpeg|png|gif|';
                                        var audio ='mp3|wma|wav';
                                        var office ='ppt|pptx|xls|xlsx|word|ppt|pptx|doc|docx';
                                        var start = res.url.lastIndexOf(".");
                                        uploadAccept =  res.url.substring(start+1, res.url.length).toLowerCase();
                                        if (img.indexOf(uploadAccept) !==-1) {
                                            html = '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + res.url + '"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + res.url + '"></i></li>\n';
                                        } else if (zip.indexOf(uploadAccept) !==-1) {
                                            html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/zip.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + res.url + '"></i></li>\n';
                                        } else if (video.indexOf(uploadAccept) !==-1) {
                                            html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/video.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + res.url + '"></i></li>\n';
                                        } else if (audio.indexOf(uploadAccept) !==-1) {
                                            html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/audio.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + res.url + '"></i></li>\n';
                                        } else if (office.indexOf(uploadAccept) !==-1) {
                                            html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/office.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + res.url + '"></i></li>\n';
                                        } else {
                                            html = '<li><img  class="layui-upload-img fl" width="150" src="/static/backend/images/filetype/file.jpg"><i class="layui-icon layui-icon-close" lay-event="filedelete" data-fileurl="' + res.url + '"></i></li>\n';
                                        }
                                        var inputVal = input.val();
                                        if (uploadNum == 1) {
                                            input.val(res.url);
                                            _parent.find('.layui-upload-list').html(html)
                                        } else if (uploadNum == '*') {
                                            _parent.find('.layui-upload-list').append(html)
                                            if (inputVal) {
                                                val_temp = (inputVal + ',' + res.url)
                                            } else {
                                                val_temp = res.url
                                            }
                                            input.val(val_temp);
                                        } else {
                                            if (_parent.find('li').length >= uploadNum) {
                                                Fun.toastr.error(__('File nums is limited'))
                                                return false;
                                            } else {
                                                _parent.find('.layui-upload-list').append(html)
                                                if (inputVal) {
                                                    val_temp = (inputVal + ',' + res.url)
                                                } else {
                                                    val_temp = res.url
                                                }
                                                input.val(val_temp);
                                            }
                                        }
                                        Fun.toastr.success(__('Upload Success'),setTimeout(function(){
                                            Fun.toastr.close();
                                        },1500));
                                    }

                                } else {
                                    Fun.toastr.error(__('Upload Failed') + __(res.msg),setTimeout(function(){
                                        Fun.toastr.close();
                                    },1500));
                                }
                            }:success,
                            error:error===undefined? function(index, upload) {
                                Fun.toastr.error(__('Upload Failed'),setTimeout(function(){
                                    Fun.toastr.close();
                                },1500));
                            }:error,
                        },options==undefined?{}:options)
                        if(uploadExts!=="*" && uploadExts){
                            opt[i]['exts'] = uploadExts
                        }
                        uploadInt[i] = layui.upload.render(opt[i]);
                        // Toastr.destroyAll();
                    })
                }
            },
            cropper: function(ele,options,success,error) {
                var cropperlist = typeof ele === 'undefined' ? $('*[lay-filter="cropper"]') : ele;
                if (cropperlist.length > 0) {
                    var cropperlistobj = {},opt = [];
                    layui.each(cropperlist, function(i) {
                        //创建一个头像上传组件
                        var _parent = $(this).parents('.layui-upload'), id = $(this).prop('id');
                        var data = $(this).data();
                        if(typeof data.value == 'object') data = data.value;
                        var saveW = data.width, saveH = data.height, mark = data.mark,
                            area = data.area, uploadPath = data.path || 'upload';
                        saveW = saveW || 300;
                        saveH = saveH || 300;
                        mark = mark || 1;
                        area = area || '720px';
                        opt[i] = $.extend({
                            elem: $(this),
                            saveW: saveW, //保存宽度
                            saveH: saveH, //保存高度
                            mark: mark ,//选取比例
                            area: area, //弹窗宽度
                            url: Fun.url(Upload.init.requests.upload_url) + '?path=' + uploadPath //图片上传接口返回和（layui 的upload 模块）返回的JOSN一样
                            ,
                            done:success=== undefined ? function(res) {
                                //上传完毕回调
                                if (res.code > 0) {
                                    Fun.toastr.success(res.msg);
                                    _parent.find('input[type="text"]').val(res.url)
                                    var html = '<li><img lay-event="photos" class="layui-upload-img fl" width="150" src="' + res.url + '"><i class="layui-icon layui-icon-close" lay-event="filedelete" lay-fileurl="' + res.url + '"></i></li>\n';
                                    _parent.find('.layui-upload-list').html(html)
                                } else if (res.code <= 0) {
                                    Fun.toastr.error(res.msg);
                                }
                            }:success,
                            error: error === undefined ? function (index){

                            }:error,
                        },options===undefined?{}:options)
                        cropperlistobj[i] = layui.croppers.render(opt[i]);
                    })
                }
            },
            bindEvent: function() {
                Upload.events.uploads();
                Upload.events.cropper();
            }
        }
    }
    return Upload;
})