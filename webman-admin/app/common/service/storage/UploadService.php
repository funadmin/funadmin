<?php
namespace app\common\service\storage;

use app\common\service\AbstractService;
use app\common\model\Attach as AttachModel;
use app\common\storage\StorageDriverRegistry;
use InvalidArgumentException as Exception;
use Webman\Event\Event;
use support\Container;
use Webman\Http\UploadFile as UploadedFile;

class UploadService extends AbstractService
{
    /**
     * 应用实例
     * @var App
     */
    protected \support\Request $request {
        get => request() ?? throw new \LogicException('当前请求上下文不存在');
    }
    /**
     * 驱动
     * @var string
     */
    protected string $driver = 'local';
    /**
     * 文件后缀
     * @var
     */
    protected string $fileExt = '';
    /**
     * 文件大小
     * @var
     */
    protected int $fileMaxsize = 0;

    /**
     * 文件对象
     * @var
     */
    protected mixed $file = null;

    protected string $saveFilePath = 'uploads';

    /**
     * 当前应用共享的存储驱动注册表。
     */
    protected StorageDriverRegistry $storageDrivers;
    /**
     * @var int
     */
    protected int $duration = 0;
    /**
     * @var int
     */
    protected int $width = 0;
    /**
     * @var int
     */
    protected int $height = 0;

    protected string $rule = '';
    /**
     * Service constructor.
     * @param App $app
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
        $this->rule = syscfg('upload','upload_file_rule')?:'';
        $this->storageDrivers = Container::get(StorageDriverRegistry::class);
        $this->driver = $this->storageDrivers->resolve((string) syscfg('upload', 'upload_driver'))->name();
        $configuredTypes = strtolower((string) syscfg('upload', 'upload_file_type'));
        $listed = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $configuredTypes) ?: []));
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
                        if (!empty(input('chunkId'))) {
                            return ['code' => 1, 'data' => $this->chunkUpload($fl,(string) $this->request->input('chunkId'), (int) $this->request->input('chunkIndex'),(int) $this->request->input('chunkCount'))];
                        } else {
                            $this->checkFile();
                            $attach = $this->attach($fl, (int) $uid, (int) $adminid);
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
                    if (!empty(input('chunkId'))) {
                        return ['code' => 1, 'data' => $this->chunkUpload($file,input('chunkId'), (int) input('chunkIndex', 0),(int) input('chunkCount', 0))];
                    } else {
                        $this->checkFile();
                        $attach = $this->attach($this->file, (int) $uid, (int) $adminid);
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
        $owner = $this->chunkOwner();
        $directory = $this->chunkDirectory($chunkId, $chunkCount, $owner);
        if ($chunkIndex < 0 || $chunkIndex >= $chunkCount || !$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > $this->fileMaxsize) {
            throw new Exception('分片参数无效');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('无法创建分片目录');
        }
        $file->move($directory . '/' . $chunkIndex . '.tmp');
        $complete = true;
        for ($index = 0; $index < $chunkCount; $index++) {
            if (!is_file($directory . '/' . $index . '.tmp')) $complete = false;
        }
        $attach = null;
        if ($complete) {
            $name = (string) $this->request->input('fileName', $file->getUploadName());
            $attach = $this->chunkMerge($chunkId, $chunkCount, $name, pathinfo($name, PATHINFO_EXTENSION), (int) $this->request->input('fileSize', 0));
        }
        return ['chunkIndex' => $chunkIndex, 'chunkId' => $chunkId, 'chunkCount' => $chunkCount, 'url' => $attach?->path ?? '', 'id' => $attach?->id ?? 0];
    }

    /**
     * 分片合并
     * @param array $params
     * @return false
     */
    public function chunkMerge(string $chunkId,int $chunkCount,string $fileName='',string $fileExt='',int $fileSize=0){
        $owner = $this->chunkOwner();
        $directory = $this->chunkDirectory($chunkId, $chunkCount, $owner);
        $ext = $this->safeExt($fileExt);
        if ($ext === '' || $ext !== $this->safeExt(pathinfo($fileName, PATHINFO_EXTENSION)) || in_array($ext, $this->blockedExtensions(), true) || !in_array($ext, explode(',', $this->fileExt), true)) {
            throw new Exception(lang('File type is limited'));
        }
        $total = 0;
        for ($index = 0; $index < $chunkCount; $index++) {
            $part = $directory . '/' . $index . '.tmp';
            if (!is_file($part) || is_link($part)) throw new Exception('分片尚未完整上传');
            $total += filesize($part);
        }
        if ($total > $this->fileMaxsize || ($fileSize > 0 && $total !== $fileSize)) throw new Exception('分片文件大小不正确');
        $merged = tempnam($directory, 'merged-');
        $output = fopen($merged, 'wb');
        try {
            for ($index = 0; $index < $chunkCount; $index++) {
                $input = fopen($directory . '/' . $index . '.tmp', 'rb');
                try {
                    if (stream_copy_to_stream($input, $output) === false) throw new \RuntimeException('分片合并失败');
                } finally {
                    fclose($input);
                }
            }
            fclose($output);
            $output = null;
            $attach = $this->attach(new UploadedFile($merged, $fileName, (new \finfo(FILEINFO_MIME_TYPE))->file($merged), UPLOAD_ERR_OK), $owner['member'], $owner['admin']);
            for ($index = 0; $index < $chunkCount; $index++) unlink($directory . '/' . $index . '.tmp');
            return $attach;
        } finally {
            if (is_resource($output)) fclose($output);
            if (is_file($merged)) unlink($merged);
        }
    }

    private function chunkOwner(): array
    {
        $admin = (int) ($this->request->context['admin']['id'] ?? 0);
        $member = $admin > 0 ? 0 : (int) ($this->request->member_id ?? 0);
        if ($admin <= 0 && $member <= 0) throw new Exception('分片上传需要认证');
        return ['admin' => $admin, 'member' => $member];
    }

    private function chunkDirectory(string $id, int $count, array $owner): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $id) || $count < 1 || $count > 10000) throw new Exception('分片参数无效');
        return runtime_path('chunks/' . hash('sha256', json_encode($owner) . ':' . $id . ':' . $count));
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
        $this->checkFile();
        $ext = $this->safeExt((string) $this->file->getUploadExtension());
        if (in_array($ext, $this->blockedExtensions(), true)) {
            throw new Exception(lang('File format is limited'));
        }
        if ($this->fileExt != '*' && ($ext === '' || !in_array($ext, explode(',', $this->fileExt), true))) {
            throw new Exception(lang('File type is limited'));
        }
        $source = $this->file->getPathname();
        $metadata = [
            'original_name' => $this->file->getUploadName(),
            'size' => (int) ceil($this->file->getSize() / 1024),
            'md5' => hash_file('md5', $source),
            'sha1' => hash_file('sha1', $source),
            'mime' => (new \finfo(FILEINFO_MIME_TYPE))->file($source),
        ];
        $storageDriver = $this->storageDrivers->resolve($this->driver);
        $attach = AttachModel::where('driver', $storageDriver->name())->where('md5', $metadata['md5'])->first();
        if(!$attach) {
            $storedFile = $storageDriver->store($this->file, $saveFilePath, $this->rule);
            $path = $storedFile->url;
            try {
                // 整合上传接口 获取视频音频长度
                $analyzeFileInfo = Event::dispatch('getID3Hook', ['path' => public_path(ltrim($path, '/'))], true);
                if ($analyzeFileInfo) {
                    $analyzeFileInfo = is_string($analyzeFileInfo) ? unserialize($analyzeFileInfo, ['allowed_classes' => false]) : $analyzeFileInfo;
                    $this->duration = (int) ($analyzeFileInfo['playtime_seconds'] ?? 0);
                }
                if ($this->width && $storageDriver->name() === 'local') {
                    $this->createWater($path);
                }
                $data = [
                    'admin_id' => $admin_id,
                    'member_id' => $uid,
                    'group_id' => input('group_id', 1),
                    'original_name' => $metadata['original_name'],
                    'name' => basename($storedFile->key),
                    'storage_key' => $storedFile->key,
                    'path' => $path,
                    'thumb' => $path,
                    'url' => $path,
                    'ext' => $ext,
                    'size' => $metadata['size'],
                    'width' => $this->width,
                    'height' => $this->height,
                    'duration' => $this->duration,
                    'md5' => $metadata['md5'],
                    'sha1' => $metadata['sha1'],
                    'mime' => $metadata['mime'],
                    'driver' => $storageDriver->name(),
                ];
                $attach = AttachModel::forceCreate($data);
            } catch (\Throwable $e) {
                $storageDriver->delete($storedFile->key);
                throw $e;
            }
        }
        Event::dispatch('afterUploadFile', $this->file, true);
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
        $this->width = $this->height = $this->duration = 0;
        if (!$this->file instanceof UploadedFile || !$this->file->isValid()) {
            throw new Exception('上传文件无效');
        }
        $ext = $this->safeExt((string) $this->file->getUploadExtension());
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
        if (in_array((new \finfo(FILEINFO_MIME_TYPE))->file($this->file->getPathname()), ['image/gif', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/png', 'image/webp']) || in_array($ext, ['gif', 'jpg', 'jpeg', 'bmp', 'png', 'webp'])) {
            $imgInfo = @getimagesize($this->file->getPathname());
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
        if (str_contains($path, '..') || !preg_match('#^[A-Za-z0-9_/-]*$#D', $path)) {
            throw new Exception(lang('file name not right'));
        }
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
        if (!empty($water['upload_water'])) {
            $root = realpath((string) config('filesystem.disks.public.root', public_path('storage')));
            $baseUrl = rtrim((string) config('filesystem.disks.public.url', '/storage'), '/');
            if ($root === false || !str_starts_with($path, $baseUrl . '/')) throw new Exception('水印文件路径无效');
            $target = realpath($root . '/' . substr($path, strlen($baseUrl) + 1));
            if ($target === false || !str_starts_with($target, $root . '/')) throw new Exception('水印文件路径无效');
            $image = (new \Intervention\Image\ImageManager(\Intervention\Image\Drivers\Gd\Driver::class))->decodePath($target);
            $positions = [1 => 'top-left', 2 => 'top', 3 => 'top-right', 4 => 'left', 5 => 'center', 6 => 'right', 7 => 'bottom-left', 8 => 'bottom', 9 => 'bottom-right'];
            if ((int) $water['upload_water'] === 1) {
                $mark = realpath(public_path(ltrim((string) ($water['upload_water_thumb'] ?? ''), '/')));
                $public = realpath(public_path());
                if ($mark === false || $public === false || !str_starts_with($mark, $public . '/')) throw new Exception('水印图片不存在');
                $image->insert($mark, alignment: $positions[(int) ($water['upload_water_position'] ?? 9)] ?? 'bottom-right', transparency: max(0, min(100, (int) ($water['upload_water_alpha'] ?? 100))) / 100);
            } elseif ((int) $water['upload_water'] === 2) {
                $fontPath = public_path('assets/fonts/simhei.ttf');
                if (!is_file($fontPath)) throw new Exception('文字水印字体文件不存在：public/assets/fonts/simhei.ttf');
                $image->text((string) ($water['upload_water_thumb'] ?? ''), $this->width - 10, $this->height - 10, static function (\Intervention\Image\Typography\FontFactory $font) use ($fontPath, $water): void {
                    $font->file($fontPath)->size((float) ($water['upload_water_size'] ?? 20))->color((string) ($water['upload_water_color'] ?? '#ffffff'))->align('right', 'bottom');
                });
            }
            $image->save($target);
        }
    }

}
