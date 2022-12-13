<?php
namespace app\common\service;

use app\common\model\Attach as AttachModel;
use app\common\traits\Jump;
use think\App;
use think\Exception;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Request;
use think\file\UploadedFile;
use think\Image;
use think\Filesystem;

class UploadService extends AbstractService
{
    use Jump;
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
     * @var
     */
    protected $disksdriver;


    protected $saveFilePath = 'uploads';
    /**
     * @var
     */
    protected $disksurl;
    /**
     * oss
     * @var
     */
    protected $ossService;
    /**
     * @var int
     */
    protected $duration = 0;
    /**
     * @var int
     */
    protected $width = 0;
    /**
     * @var int
     */
    protected $height = 0;


    /**
     * Service constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = request();
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
        $this->disksdriver = Config::get('filesystem.default','public');
        $this->disksurl = Config::get('filesystem.disks.'.$this->disksdriver.'.url','/storage');
        $this->ossService = OssService::instance();
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
        $type = input('type', 'file');
        $savePath = input('path', 'uploads');
        $savePath = $savePath =='undefined'?'uploads':$savePath;
        $editor = input('editor', '');
        $files = request()->file();
        foreach ($files as $k => $file) {
            if(is_array($file)){
                foreach($file as $index=>$fl){
                    $this->file = $fl;
                    try {
                        if (!empty(input('chunkId/s'))) {
                            $attach  = $this->chunkUpload($file,input('chunkId/s'), input('chunkIndex/d'),input('chunkCount/d'));
                        } else {
                            $this->checkFile();
                            $attach = $this->attach($file);
                        }
                    }catch (Exception $e){
                        throw new Exception($e->getMessage());
                    }
                    $result['data'][$k][$index] = $attach->path; //兼容wangeditor
                    $result['uploaded'] = true; //兼容ckeditorditor
                    $result['error '] = ["message"=> "ok"]; //兼容ckeditorditor
                    $result['success'] = 1; //兼容editormd
                    $result['id'][$k][$index] = $attach->id;
                    $result['fileType'] = $type;
                    $result["url"][$k][$index] = $attach->path;
                }
            }else{
                $this->file = $file;
                try {
                    if (!empty(input('chunkId/s'))) {
                        $attach = $this->chunkUpload($file,input('chunkId/s'), input('chunkIndex/d'),input('chunkCount/d'));
                    } else {
                        $this->checkFile();
                        $attach = $this->attach($this->file);
                    }
                }catch (Exception $e){
                    throw new Exception($e->getMessage());
                }
                $result['data'][] = $attach->path; //兼容wangeditor
                $result['uploaded'] = true; //兼容ckeditorditor
                $result['error '] = ["message"=> "ok"]; //兼容ckeditorditor
                $result['success'] = 1; //兼容editormd
                $result['id'] = $attach->id;
                $result['fileType'] = $type;
                $result["url"] = $attach->path;
            }
        }
        $result['state'] = 'SUCCESS'; //兼容百度
        $result['errno'] = 0; //兼容wangeditor
        $result['uploaded'] = true; //兼容ckeditorditor
        $result['error'] = ["message"=> "ok"]; //兼容ckeditorditor
        $result['success'] = 1; //兼容editormd
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
        return $result;
    }


    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * 分片上传
     * @param $file
     * @param array $params
     * @return void
     */
    public function chunkUpload($file,string $chunkId,int $chunkIndex,int $chunkCount){
        $this->file = $file??$this->file;
        $chunkId = $chunkId?:input('chunkId/s');
        $chunkIndex = $chunkIndex?:input('chunkIndex/d');
        $chunkCount = $chunkCount?:input('chunkCount/d');
        $fileSize = input('fileSize/d');
        $chunkName = $chunkId . '-' . $chunkIndex . '.tmp';
        $chunkSavePath = runtime_path('chunks');
        @mkdir($chunkSavePath);
        $chunkFileName = $chunkSavePath . $chunkName;
        $attach = '';
        //文件存在
        if(is_file($chunkFileName) && $chunkIndex+1 < $chunkCount){
            $data = [
                'chunkIndex'=>$chunkIndex,
                'chunkId'=>$chunkId,
                'chunkCount'=>$chunkCount,
                'start'=>input('start',0),
                'end'=>input('end'),
                'url'=>$attach?$attach->path:""];
            $this->success('ok','',$data);
        }
        if (!move_uploaded_file($this->file, $chunkFileName)) {
            $this->error(lang('Chunk file upload error'));
        }
        if($chunkIndex+1 == $chunkCount){
            $ext = $this->file->getOriginalExtension() ? $this->file->getOriginalExtension() : substr(strrchr($this->request->post('filename'), '.'), 1);
            $fileName = input('fileName/s');
            try {

                $attach = $this->chunkMerge($chunkId,$chunkCount,$fileName,$ext);
            }catch (\Exception $e) {
                $this->error('failed');
            }
        }
        $data = [
            'chunkIndex'=>$chunkIndex,
            'chunkId'=>$chunkId,
            'chunkCount'=>$chunkCount,
            'start'=>input('start',0),
            'end'=>input('end'),
            'url'=>$attach?$attach->path:"",
        ];
        $this->success('ok','',$data);
    }

    /**
     * 分片合并
     * @param array $params
     * @return false
     */
    public function chunkMerge(string $chunkId,int $chunkCount,string $fileName='',string $fileExt='',int $fileSize=0){
        $chunkId = $chunkId?:input('chunkId/d');
        $chunkCount = $chunkCount?:input('chunkCount/d');
        $fileExt = $fileExt?:input('fileExt/s');
        $fileSize = $fileSize?:input('fileSize/d');
        $fileName = $fileName?:input('fileName/s');
        if (!preg_match('/^[0-9\-]/', $chunkId)) {
            throw new Exception(lang('file name not right'));
        }
        $filePath = runtime_path('chunks').$chunkId ;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
        if (!$destFile = @fopen($filePath.'.'.$fileExt, "wb")) {
            throw new Exception(lang('file is not readable'));
        }
        $completed = true;
        //检查所有分片是否都存在
        for ($i = 0; $i < $chunkCount; $i++) {
            if (!file_exists("{$filePath}-{$i}.tmp")) {
                    $completed = false;
                    break;
            }
        }
        // 删除
        if(!$completed) {
            for ($i = 0; $i < $chunkCount; $i++) {
                if (file_exists("{$filePath}-{$i}.tmp")) {
                    @unlink("{$filePath}-{$i}.tmp"); //删除分片
                }
            }
            throw new \Exception(lang("chunk file upload failed"));
        }
        try {
            flock($destFile, LOCK_EX);
            for ($i = 0; $i < $chunkCount; $i++) {
                $tmpFile = "{$filePath}-{$i}.tmp";
                if (is_file($tmpFile)) {
                    if (!$handle = @fopen($tmpFile, "rb")) {
                        break;
                    }
                    while ($buff = fread($handle, filesize($tmpFile))) {
                        fwrite($destFile, $buff);
                    }
                    @fclose($handle);
                    @unlink($tmpFile);
                }
            }
            flock($destFile, LOCK_UN);
            @fclose($destFile);
        } catch (\Exception $e) {
            throw new Exception('The file is abnormal, please upload it again');
        }
        $newFilePath = $filePath . '.' . $fileExt;
        if (filesize($newFilePath) != $fileSize && $fileSize) {
            throw new \Exception(lang('The file size not right, please upload it again'));
        }
        //设置文件
        $this->file = new UploadedFile($newFilePath,$fileName);
        try {

            return $this->attach($this->file);
        }catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 上传
     * @param $file
     * @param int $uid
     * @param int $admin_id
     * @return void
     * @throws Exception
     */
    public function attach($file,int $uid=0 ,int $admin_id=0 ){

        $this->file = $file?:$this->file;
        $saveFilePath = input('path','uploads') =='undefined'?:$this->saveFilePath;
        $savename = $this->filesystem->disk($this->disksdriver)->putFile($saveFilePath, $this->file);
        $savename = str_replace('\\','/',$savename);
        $path = $this->disksurl . "/" . $savename;
        $attach = AttachModel::where('md5',$this->file->md5())->find();
        if(!$attach) {
            // 整合上传接口 获取视频音频长度
            $analyzeFileInfo = hook_one('getID3Hook',['path'=>'.'. "/" .$path]);
            if($analyzeFileInfo) {
                $analyzeFileInfo = unserialize($analyzeFileInfo);
                $this->duration = isset($analyzeFileInfo['playtime_seconds'])?$analyzeFileInfo['playtime_seconds']:0;
            }
            if($this->width){
                $this->createWater($path);
            }
            if ($this->driver != 'local') {
                try {
                    $path = $this->ossService->uploads($this->driver,trim($path, "/"), "./" . trim($path, "/"),input('save',1));
                }catch (\Exception $e) {
                    throw new Exception($e->getMessage());
                }
            }
            $data = [
                'admin_id' => $admin_id ?: (session('admin.id') ?: 0),
                'member_id' => $uid ?: (session('member.id') ?: 0),
                'group_id' => input('group_id', 1),
                'original_name' => $this->file->getOriginalName(),
                'name' => basename($savename),
                'path' => $path,
                'thumb' => $path,
                'url' => $this->driver == 'local' ? Request::domain() . $path : $path,
                'ext' => $this->file->getExtension(),
                'size' => $this->file->getSize() / 1024,
                'width' => $this->width,
                'height' => $this->height,
                'duration' => $this->duration,
                'md5' => $this->file->md5(),
                'sha1' => $this->file->sha1(),
                'mime' => $this->file->getMime(),
                'driver' => $this->driver,
            ];
            $attach = AttachModel::create($data);
        }
        hook_one('afterUploadFile',$this->file);
        return $attach;

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
        $file_ext = $this->file->extension();
        if (in_array($this->file->getMime(), ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($file_ext, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
            $imgInfo = getimagesize($this->file->getPathname());
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                throw new Exception(lang('Uploaded file is not a valid image'));
            }
            $this->width = isset($imgInfo[0]) ? $imgInfo[0] : 0;
            $this->height = isset($imgInfo[1]) ? $imgInfo[1] : 0;
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