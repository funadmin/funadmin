<?php
namespace app\common\service;

use app\common\model\Attach as AttachModel;
use think\App;
use think\Exception;
use think\facade\Request;
use getID3;
class UploadService extends AbstractService
{
    /**
     * 应用实例
     * @var App
     */
    protected $app;
    /**
     * 驱动
     * @var string
     */
    protected $driver = 'local';
    /**
     * 文件后缀
     * @var
     */
    protected $fileExt;
    /**
     * 文件大小
     * @var
     */
    protected $fileMaxsize;
    /**
     * Service constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->initialize();
    }

    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
        $this->driver = syscfg('upload','upload_driver');
        $this->fileExt = syscfg('upload','upload_file_type');
        $this->fileMaxsize = syscfg('upload', 'upload_file_max') * 1024;
        return $this;
    }

    /**
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 文件上传总入口 集成qiniu alioss tenxunoss
     */
    public function uploads($uid,$adminid)
    {
        //获取上传文件表单字段名
        $type = Request::param('type', 'file');
        $path = Request::param('path', 'uploads');
        $editor = Request::param('editor', '');
        $save = Request::param('save', '');
        $files = request()->file();
        $ossService = OssService::instance();
        foreach ($files as $k => $file) {
            if(is_array($file)){
                foreach($file as $kk=>$vv){
                    $this->checkFile($vv);
                    $file_size = $vv->getSize();
                    $original_name = $vv->getOriginalName();
                    $md5 = $vv->md5();$sha1 = $vv->sha1();;
                    $file_mime = $vv->getMime();
                    $attach = AttachModel::where('md5', $md5)->find();
                    if (!$attach) {
                        try {
                            $savename = \think\facade\Filesystem::disk('public')->putFile($path, $vv);
                            $path = DS . 'storage' . DS . $savename;
                            $paths = trim($path, '/');
                            // 整合上传接口 获取视频音频长度
                            $analyzeFileInfo = hook('getID3Hook',['path'=>'./'.$path]);
                            $duration=0;
                            if($analyzeFileInfo) {
                                $analyzeFileInfo = json_decode($analyzeFileInfo,true);
                                $duration = isset($analyzeFileInfo['playtime_seconds'])?$analyzeFileInfo['playtime_seconds']:0;
                            }
                            if ($this->driver != 'local') {
                                try {
                                    $path = $ossService->uploads($this->driver,$paths, './' . $paths,$save);
                                }catch (\Exception $e) {
                                    throw new Exception($e->getMessage());
                                }
                            }
                        }catch (Exception $e){
                            $path = '';
                            $error = $e->getMessage();
                        }
                        $file_ext = strtolower(substr($savename, strrpos($savename, '.') + 1));
                        $file_name = basename($savename);
                        $width = $height = 0;
                        if (in_array($file_mime, ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($file_ext, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
                            $imgInfo = getimagesize($vv->getPathname());;
                            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                                throw new Exception(lang('Uploaded file is not a valid image'));
                            }
                            $width = isset($imgInfo[0]) ? $imgInfo[0] : $width;
                            $height = isset($imgInfo[1]) ? $imgInfo[1] : $height;
                        }
                        if (!empty($path)) {
                            $data = [
                                'admin_id'      => $adminid,
                                'member_id'     => $uid,
                                'original_name' => $original_name,
                                'name'          => $file_name,
                                'path'          => $path,
                                'thumb'         => $path,
                                'url'           => $this->driver == 'local' ? Request::domain() . $path : $path,
                                'ext'           => $file_ext,
                                'size'          => $file_size / 1024,
                                'width'         => $width,
                                'height'        => $height,
                                'duration'      => $duration,
                                'md5'           => $md5,
                                'sha1'          => $sha1,
                                'mime'          => $file_mime,
                                'driver'        => $this->driver,
                            ];
                            $attach = AttachModel::create($data);
                            $result['data'][$k][$kk] = $attach->path; //兼容wangeditor
                            $result['id'][$k][$kk] = $attach->id;
                            $result["url"][$k][$kk] = $path;
                        } else {
                            //上传失败获取错误信息
                            $result['url'] = '';
                            $result['msg'] = $error;
                            $result['code'] = 0;
                            $result['state'] = 'ERROR'; //兼容百度
                            $result['errno'] = 'ERROR'; //兼容wangeditor
                            $result['uploaded'] = false; //兼容ckeditorditor
                            $result['error'] = ["message"=> "ERROR"]; //兼容ckeditorditor
                            if($editor=='layedit'){
                                $result['code'] = 1;
                            }
                            return ($result);
                        }
                    } else {
                        $result['data'][$k][$kk] = $attach->path; //兼容wangeditor
                        $result['uploaded'] = true; //兼容ckeditorditor
                        $result['error '] = ["message"=> "ok"]; //兼容ckeditorditor
                        $result['id'][$k][$kk] = $attach->id;
                        $result['fileType'] = $type;
                        $result["url"][$k][$kk] = $attach->path;
                    }
                }
            }else{
                $this->checkFile($file);
                $file_size = $file->getSize();
                $original_name = $file->getOriginalName();
                $md5 = $file->md5();$sha1 = $file->sha1();;
                $file_mime = $file->getMime();
                $attach = AttachModel::where('md5', $md5)->find();
                if (!$attach) {
                    try {
                        $savename = \think\facade\Filesystem::disk('public')->putFile($path, $file);
                        $path = DS . 'storage' . DS . $savename;
                        $paths = trim($path, '/');
                        // 整合上传接口 获取视频音频长度
                        $analyzeFileInfo = hook('getID3Hook',['path'=>'./'.$path]);
                        $duration=0;
                        if($analyzeFileInfo) {
                            $analyzeFileInfo = json_decode($analyzeFileInfo,true);
                            $duration = isset($analyzeFileInfo['playtime_seconds'])?$analyzeFileInfo['playtime_seconds']:0;
                        }
                        if ($this->driver != 'local') {
                            try {
                                $path = $ossService->uploads($this->driver,$paths, './' . $paths,$save);
                            }catch (\Exception $e) {
                                throw new Exception($e->getMessage());
                            }
                        }
                    }catch (Exception $e){
                        $path = '';
                        $error = $e->getMessage();
                    }
                    $file_ext = strtolower(substr($savename, strrpos($savename, '.') + 1));
                    $file_name = basename($savename);
                    $width = $height = 0;
                    if (in_array($file_mime, ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($file_ext, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
                        $imgInfo = getimagesize($file->getPathname());;
                        if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                            throw new Exception(lang('Uploaded file is not a valid image'));
                        }
                        $width = isset($imgInfo[0]) ? $imgInfo[0] : $width;
                        $height = isset($imgInfo[1]) ? $imgInfo[1] : $height;
                    }
                    if (!empty($path)) {
                        $data = [
                            'admin_id'      => $adminid,
                            'member_id'     => $uid,
                            'original_name' => $original_name,
                            'name'          => $file_name,
                            'path'          => $path,
                            'thumb'         => $path,
                            'url'           => $this->driver == 'local' ? Request::domain() . $path : $path,
                            'ext'           => $file_ext,
                            'size'          => $file_size / 1024,
                            'width'         => $width,
                            'height'        => $height,
                            'duration'      => $duration,
                            'md5'           => $md5,
                            'sha1'          => $sha1,
                            'mime'          => $file_mime,
                            'driver'        => $this->driver,
                        ];
                        $attach = AttachModel::create($data);
                        $result['data'][] = $attach->path; //兼容wangeditor
                        $result['id'] = $attach->id;
                        $result["url"] = $path;
                    } else {
                        //上传失败获取错误信息
                        $result['url'] = '';
                        $result['msg'] = $error;
                        $result['code'] = 0;
                        $result['state'] = 'ERROR'; //兼容百度
                        $result['errno'] = 'ERROR'; //兼容wangeditor
                        $result['uploaded'] = false; //兼容ckeditorditor
                        $result['error'] = ["message"=> "ERROR"]; //兼容ckeditorditor
                        if($editor=='layedit'){
                            $result['code'] = 1;
                        }
                        return ($result);
                    }
                } else {
                    $result['data'][] = $attach->path; //兼容wangeditor
                    $result['uploaded'] = true; //兼容ckeditorditor
                    $result['error '] = ["message"=> "ok"]; //兼容ckeditorditor
                    $result['id'] = $attach->id;
                    $result['fileType'] = $type;
                    $result["url"] = $attach->path;
                }
            }

        }
        $result['state'] = 'SUCCESS'; //兼容百度
        $result['errno'] = 0; //兼容wangeditor
        $result['uploaded'] = true; //兼容ckeditorditor
        $result['error'] = ["message"=> "ok"]; //兼容ckeditorditor
        $result['code'] = 1;//默认
        $result['msg'] = lang('upload success');
        if($editor=='layedit'){
            $result['code'] = 0;
            $result['data'] = ['src'=>$result['data'][0],'title'=>''];
        }
        return ($result);
    }
    /**
     * @param $file
     * @return bool
     * @throws Exception
     * 检测文件是否符合要求
     */
    protected function checkFile($file)
    {
        //禁止上传PHP和HTML.ssh等脚本文件
        if (in_array($file->getMime(),
                ['application/octet-stream', 'text/html','application/x-javascript','text/x-php','application/x-msdownload','application/java-archive'])
            ||
            in_array($file->extension(),
                ['php', 'html', 'htm','xml','ssh','bat','jar','java'])) {
            throw new Exception(lang('File format is limited'));
        }
        //文件大小限制
        if (($file->getSize() > $this->fileMaxsize*1024)) {
            throw new Exception(lang('File size is limited'));
        }
        //文件类型限制
        if ($this->fileExt !='*' && !in_array($file->extension(),explode(',',$this->fileExt))) {
            throw new Exception(lang('File type is limited'));
        }
        return true;
    }


}