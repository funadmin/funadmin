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
        $this->driver = syscfg('upload','uplad_driver');
        $this->fileExt = syscfg('upload','upload_file_type');
        $this->fileMaxsize = syscfg('upload', 'upload_file_max') * 1024;
        return $this;
    }

    /**
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 文件上传总入口 集成qiniu ali tenxunoss
     */
    public function uploads()
    {
        //获取上传文件表单字段名
        $type = Request::param('type', 'file');
        $path = Request::param('path', 'uploads');
        $files = request()->file();
        $uploadService = OssService::instance();
        foreach ($files as $k => $file) {
            $this->checkFile($file);
            $file_size = $file->getSize();
            $original_name = $file->getOriginalName();
            $md5 = $file->md5();
            $sha1 = $file->sha1();;
            $file_mime = $file->getMime();
            $attach = AttachModel::where('md5', $md5)->find();
            if (!$attach) {
                try {
                    $savename = \think\facade\Filesystem::disk('public')->putFile($path, $file);
                    $path = DS . 'storage' . DS . $savename;
                    $paths = trim($path, '/');
                    //整合上传接口 获取视频音频长度
                    $getID3 = new getID3();
                    $analyzeFileInfo = $getID3->analyze('./'.$path);
                    $duration = isset($analyzeFileInfo['playtime_seconds'])?$analyzeFileInfo['playtime_seconds']:0;
                    if ($this->driver == 'alioss') {
                        $path = $uploadService->alioss($paths, './' . $paths);
                    } elseif ($this->driver == 'qiniuoss') {
                        $path = $uploadService->qiniuoss($paths, './' . $paths);
                    } elseif ($this->driver == 'teccos') {
                        $path = $uploadService->teccos($paths, './' . $paths);
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
                        'admin_id' => session('admin.id'),
                        'user_id'     => cookie('uid'),
                        'original_name' => $original_name,
                        'name' => $file_name,
                        'path' => $path,
                        'thumb' => $path,
                        'url' => $this->driver == 'local' ? Request::domain() . $path : $path,
                        'ext' => $file_ext,
                        'size' => $file_size / 1024,
                        'width' => $width,
                        'height' => $height,
                        'duration' => $duration,
                        'md5' => $md5,
                        'sha1' => $sha1,
                        'mime' => $file_mime,
                        'driver' => $this->driver,

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
                    return ($result);
                }

            } else {
                $result['data'][] = $attach->path; //兼容wangeditor
                $result['id'] = $attach->id;
                $result['fileType'] = $type;
                $result["url"] = $attach->path;
            }
        }

        $result['state'] = 'SUCCESS'; //兼容百度
        $result['errno'] = 0; //兼容wangeditor
        $result['code'] = 1;//默认
        $result['msg'] = lang('upload success');
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

        //禁止上传PHP和HTML.ssh文件
        if (in_array($file->getMime(), ['application/octet-stream', 'text/html','application/x-javascript']) || in_array($file->extension(), ['php', 'html', 'htm','xml','ssh'])) {
            throw new Exception(lang('File format is limited'));
        }
        //文件大小限制
        if (($file->getSize() > $this->fileMaxsize)) {
            throw new Exception(lang('File size is limited'));
        }
        //文件类型限制
        if ($this->fileExt !='*' && !in_array($file->extension(),explode(',',$this->fileExt))) {
            throw new Exception(lang('File type is limited'));
        }
        return true;
    }


}