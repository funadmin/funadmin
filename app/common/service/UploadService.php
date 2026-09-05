<?php
namespace app\common\service;

use app\common\model\Attach as AttachModel;
use app\common\storage\StorageDriverRegistry;
use app\common\traits\Jump;
use think\App;
use think\Exception;
use think\facade\Cache;
use think\facade\Event;
use think\facade\Request;
use think\file\UploadedFile;
use think\Image;

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

    protected $saveFilePath = 'uploads';

    /**
     * 当前应用共享的存储驱动注册表。
     */
    protected StorageDriverRegistry $storageDrivers;
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

    protected $rule  = '';
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
        $this->rule = syscfg('upload','upload_file_rule')?:'';
        $this->storageDrivers = $this->app->make(StorageDriverRegistry::class);
        $this->driver = $this->storageDrivers->resolve((string) syscfg('upload', 'upload_driver'))->name();
        $configuredTypes = strtolower((string) syscfg('upload', 'upload_file_type'));
        $listed = array_filter(array_map('trim', explode(',', $configuredTypes)));
        if ($configuredTypes === '*' || !$listed) {
            $listed = array_filter(array_map('trim', explode(',', 'mp4,mp3,png,gif,jpg,jpeg,webp,rar,zip,7z,tar,gz,csv,xls,xlsx,pdf,doc,docx,ppt,pptx,txt')));
        }
        // 黑名单强制剥离，配置放行也不允许
        $this->fileExt = implode(',', array_values(array_diff($listed, $this->blockedExtensions())));
        $this->fileMaxsize = (int) (syscfg('upload', 'upload_file_max') ?: 8192) * 1024;
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
        $this->saveFilePath = ($savePath === 'undefined' || $savePath === '') ? 'uploads' : $this->sanitizeSavePath($savePath);
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
        $this->checkFile();
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
                'url'=>""];
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
        $ext = $this->safeExt($fileExt ?: input('fileExt/s'));
        if (in_array($ext, $this->blockedExtensions(), true)) {
            throw new Exception(lang('File format is limited'));
        }
        $fileSize = $fileSize ?: input('fileSize/d');
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
        $newFilePath = $filePath . '.' . $ext;
        if (filesize($newFilePath) != $fileSize && $fileSize) {
            throw new \Exception(lang('The file size not right, please upload it again'));
        }
        if (filesize($newFilePath) > $this->fileMaxsize) {
            @unlink($newFilePath);
            throw new \Exception(lang('File size is limited'));
        }
        //设置文件
        $this->file = new UploadedFile($newFilePath,$fileName);
        $this->file->setExtension($ext);
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
        $saveFilePath = input('path','uploads') =='undefined' ? $this->saveFilePath : $this->sanitizeSavePath((string) input('path', 'uploads'));
        // 落库前强制校验扩展名：直传与分片两条路径都经过这里
        $ext = $this->safeExt((string) $this->file->getOriginalExtension());
        if (in_array($ext, $this->blockedExtensions(), true)) {
            throw new Exception(lang('File format is limited'));
        }
        if ($this->fileExt != '*' && ($ext === '' || !in_array($ext, explode(',', $this->fileExt), true))) {
            throw new Exception(lang('File type is limited'));
        }
        $this->file->setExtension($ext);
        $storageDriver = $this->storageDrivers->resolve($this->driver);
        $attach = AttachModel::where('driver', $storageDriver->name())->where('md5',$this->file->md5())->find();
        if(!$attach) {
            $storedFile = $storageDriver->store($this->file, $saveFilePath, $this->rule);
            $path = $storedFile->url;
            // 整合上传接口 获取视频音频长度
            $analyzeFileInfo = Event::trigger('getID3Hook', ['path' => './' . $path], true);
            if($analyzeFileInfo) {
                $analyzeFileInfo = unserialize($analyzeFileInfo);
                $this->duration = isset($analyzeFileInfo['playtime_seconds'])?$analyzeFileInfo['playtime_seconds']:0;
            }
            if ($this->width && $storageDriver->name() === 'local') {
                $this->createWater($path);
            }
            $data = [
                'admin_id' => $admin_id ?: (session('admin.id') ?: 0),
                'member_id' => $uid ?: (session('member.id') ?: 0),
                'group_id' => input('group_id', 1),
                'original_name' => $this->file->getOriginalName(),
                'name' => basename($storedFile->key),
                'storage_key' => $storedFile->key,
                'path' => $path,
                'thumb' => $path,
                'url' => str_starts_with($path, '/') ? Request::domain() . $path : $path,
                'ext' => $ext,
                'size' => $this->file->getSize() / 1024,
                'width' => $this->width,
                'height' => $this->height,
                'duration' => $this->duration,
                'md5' => $this->file->md5(),
                'sha1' => $this->file->sha1(),
                'mime' => $this->file->getMime(),
                'driver' => $storageDriver->name(),
            ];
            try {
                $attach = AttachModel::create($data);
            } catch (\Throwable $e) {
                $storageDriver->delete($storedFile->key);
                throw $e;
            }
        }
        Event::trigger('afterUploadFile', $this->file, true);
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
        //禁止上传可执行/脚本类文件（小写净化后比对，避免大小写绕过）
        $ext = $this->safeExt((string) $this->file->extension());
        if (in_array($ext, $this->blockedExtensions(), true)) {
            throw new Exception(lang('File format is limited'));
        }
        //文件大小限制
        if ($this->file->getSize() > $this->fileMaxsize) {
            throw new Exception(lang('File size is limited'));
        }
        //文件类型限制
        if ($this->fileExt != '*' && ($ext === '' || !in_array($ext, explode(',', $this->fileExt), true))) {
            throw new Exception(lang('File type is limited'));
        }
        if (in_array($this->file->getMime(), ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($ext, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
            $imgInfo = getimagesize($this->file->getPathname());
            if (!$imgInfo || !isset($imgInfo[0]) || !isset($imgInfo[1])) {
                throw new Exception(lang('Uploaded file is not a valid image'));
            }
            $this->width = isset($imgInfo[0]) ? $imgInfo[0] : 0;
            $this->height = isset($imgInfo[1]) ? $imgInfo[1] : 0;
        }
        return true;
    }
    /**
     * 可执行/脚本扩展名黑名单：webshell 与存储型 XSS 载体一律拒绝，配置放行也无效。
     */
    protected function blockedExtensions(): array
    {
        return [
            'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
            'htm', 'html', 'xml', 'ssh', 'bat', 'jar', 'java',
            'asp', 'aspx', 'cgi', 'pl', 'py', 'sh', 'bash', 'vbs', 'wsf',
            'com', 'exe', 'dll', 'so', 'js', 'mjs', 'svg', 'htaccess',
        ];
    }

    /**
     * 扩展名净化：小写 + 仅字母数字 + 长度上限，非法返回空串。
     */
    protected function safeExt(string $ext): string
    {
        $ext = strtolower(trim($ext));
        if ($ext === '' || !preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
            return '';
        }
        return $ext;
    }

    /**
     * 保存目录净化：拒绝穿越与非法字符，空值回退 uploads。
     */
    protected function sanitizeSavePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/[^A-Za-z0-9_\-\/]/', '', $path) ?? '';
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== '' && $s !== '.'));
        if (in_array('..', $segments, true)) {
            throw new Exception(lang('file name not right'));
        }
        return $segments ? implode('/', $segments) : 'uploads';
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
