<?php
namespace app\common\service;

use app\common\model\Attach as AttachModel;
use think\App;
use think\Exception;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Request;
use think\Image;
use think\Filesystem;

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
     * 文件对象
     * @var
     */
    protected $file;

    /**
     * 上传对象
     */
    protected $filesystem;

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
        $this->filesystem = new Filesystem($this->app);
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
        $group_id = Request::param('group_id', 1);
        $pathSrc = $path =='undefined'?'uploads':$path;
        $editor = Request::param('editor', '');
        $save = Request::param('save', 1);
        $disksdriver = Config::get('filesystem.default','public');
        $disksurl = Config::get('filesystem.disks.'.$disksdriver.'.url','/storage');
        $files = request()->file();
        $error='';
        $ossService = OssService::instance();

        foreach ($files as $k => $file) {
            if(is_array($file)){
                foreach($file as $kk=>$vv){
                    $this->file = $vv;
                    $this->checkFile();
                    $file_size = $vv->getSize();
                    $original_name = $vv->getOriginalName();
                    $md5 = $vv->md5();$sha1 = $vv->sha1();;
                    $file_mime = $vv->getMime();
                    $attach = AttachModel::where('md5', $md5)->find();
                    if (!$attach) {
                        try {
                            $savename = $this->filesystem->disk($disksdriver)->putFile($pathSrc, $vv);
                            $savename = str_replace('\\','/',$savename);
                            $path = $disksurl . "/" . $savename;
                            $paths = trim($path, '/');
                            // 整合上传接口 获取视频音频长度
                            $analyzeFileInfo = hook('getID3Hook',['path'=>'.' . "/" . $path]);
                            $duration=0;
                            if($analyzeFileInfo) {
                                $analyzeFileInfo = unserialize($analyzeFileInfo);
                                $duration = isset($analyzeFileInfo['playtime_seconds'])?$analyzeFileInfo['playtime_seconds']:0;
                            }
                            $file_ext = strtolower(substr($savename, strrpos($savename, '.') + 1));
                            $file_name = basename($savename);
                            $width = $height = 0;
                            if (in_array($file_mime, ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($file_ext, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
                                $imgInfo = getimagesize($vv->getPathname());
                                if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                                    throw new Exception(lang('Uploaded file is not a valid image'));
                                }
                                $this->createWater($path);
                                $width = isset($imgInfo[0]) ? $imgInfo[0] : $width;
                                $height = isset($imgInfo[1]) ? $imgInfo[1] : $height;
                            }
                            if ($this->driver != 'local') {
                                try {
                                    $path = $ossService->uploads($this->driver,$paths, '.' . "/" . $paths,$save);
                                }catch (\Exception $e) {
                                    throw new Exception($e->getMessage());
                                }
                            }
                        }catch (Exception $e){
                            $path = '';
                            $error = $e->getMessage();
                        }
                        if (!empty($path)) {
                            $data = [
                                'admin_id'      => $adminid,
                                'member_id'     => $uid?:0,
                                'group_id'     => $group_id,
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
                            if($editor=='tinymce'){
                                $result['code'] = 0;
                                $result['location'] = "";
                            }
                            if($editor=='vditor'){
                                $result['code'] = 0;
                                $result['data'] = [
                                    'errFiles'          =>[],
                                    'succMap'          =>[
                                        $savename=>$attach->path
                                    ]
                                ];
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
                $this->file = $file;
                $this->checkFile();
                $file_size = $file->getSize();
                $original_name = $file->getOriginalName();
                $md5 = $file->md5();$sha1 = $file->sha1();;
                $file_mime = $file->getMime();
                $attach = AttachModel::where('md5', $md5)->find();
                if (!$attach) {
                    try {
                        $savename = $this->filesystem->disk($disksdriver)->putFile($path, $file);
                        $savename = str_replace('\\','/',$savename);
                        $path = $disksurl . "/" . $savename;
                        $paths = trim($path, "/");
                        // 整合上传接口 获取视频音频长度
                        $analyzeFileInfo = hook_one('getID3Hook',['path'=>'.'. "/" .$path]);
                        $duration=0;
                        if($analyzeFileInfo) {
                            $analyzeFileInfo = unserialize($analyzeFileInfo);
                            $duration = isset($analyzeFileInfo['playtime_seconds'])?$analyzeFileInfo['playtime_seconds']:0;
                        }
                        $file_ext = strtolower(substr($savename, strrpos($savename, '.') + 1));
                        $file_name = basename($savename);
                        $width = $height = 0;
                        if (in_array($file_mime, ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($file_ext, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
                            $imgInfo = getimagesize($file->getPathname());;
                            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                                throw new Exception(lang('Uploaded file is not a valid image'));
                            }
                            $this->createWater($path);
                            $width = isset($imgInfo[0]) ? $imgInfo[0] : $width;
                            $height = isset($imgInfo[1]) ? $imgInfo[1] : $height;
                        }
                        if ($this->driver != 'local') {
                            try {
                                $path = $ossService->uploads($this->driver,$paths, "./" . $paths,$save);
                            }catch (\Exception $e) {
                                throw new Exception($e->getMessage());
                            }
                        }
                    }catch (Exception $e){
                        $path = '';
                        $error = $e->getMessage();
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
                        if($editor=='tinymce'){
                            $result['code'] = 1;
                            $result['location'] = '';
                        }
                        if($editor=='vditor'){
                            $result['code'] = 0;
                            $result['data'] = [
                                'errFiles'          =>[],
                                'succMap'          =>[
                                    $savename=>$attach->path
                                ]
                            ];
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
        if($editor=='tinymce'){
            $result['code'] = 0;
            $result['location'] = $result['data'][0];
        }
        if($editor=='vditor'){
            $result['code'] = 0;
            $result['data'] = [
                'errFiles'          =>[],
                'succMap'          =>[
                    $result['data']['file'][0]=>$result['data']['file'][0],
                ]
            ];
        }
        return ($result);
    }

    /**
     * @param $file
     * @return bool
     * @throws Exception
     * 检测文件是否符合要求
     */
    protected function checkFile()
    {
        //禁止上传PHP和HTML.ssh等脚本文件
        if (
//            in_array($this->file->getMime(),
//                ['application/octet-stream', 'text/html','application/x-javascript','text/x-php','application/x-msdownload','application/java-archive'])
//            ||
            in_array($this->file->extension(),
                ['php', 'html', 'htm','xml','ssh','bat','jar','java'])) {
            throw new Exception(lang('File format is limited'));
        }
        //文件大小限制
        if (($this->file->getSize() > $this->fileMaxsize*1024)) {
            throw new Exception(lang('File size is limited'));
        }
        //文件类型限制
        if ($this->fileExt !='*' && !in_array($this->file->extension(),explode(',',$this->fileExt))) {
            throw new Exception(lang('File type is limited'));
        }
        return true;
    }
    //建立水印
    protected function createWater($path){
        // 读取图片
        $water = syscfg('upload');
        if($water['upload_water']){
            $domain = \request()->domain();
            $path = '.'. "/" .trim($path,"/");
            $image = Image::open($path);
            // 添加水印
            $watermark_pos   = $water['upload_water_position'] == '' ? config('upload_water_position'):  $water['upload_water_position'];
            $watermark_pos = $watermark_pos?:9;
            $watermark_alpha =  $water['upload_water_alpha'] == '' ? config('upload_water_alpha') :  $water['upload_water_alpha'];
            $water_text_thumb  =  $water['upload_water_thumb'] == '' ? config('upload_water_thumb') :  $water['upload_water_thumb'];
            $water_text_size =  $water['upload_water_size'] == '' ? config('upload_water_size') :  $water['upload_water_size'];
            $water_text_color =  $water['upload_water_color'] == '' ? config('upload_water_color') :  $water['upload_water_color'];
            switch ($water['upload_water']){
                case 1:
                    $water_text_thumb =  '.' . "/" .trim(str_replace($domain,'',$water_text_thumb),"/" );
                    $image->water($water_text_thumb, $watermark_pos, $watermark_alpha)->save($path);
                    break;
                case 2:
                    // 添加文字水印
                    $image->text($water_text_thumb,'./static/common/fonts/text/simhei.ttf',$water_text_size,$water_text_color)->save($path);  //添加文字水印
                    break;
                default:
                    break;
            }

        }
    }

}