<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\system\model\AttachGroup;
use app\common\model\Attach;
use app\common\service\storage\UploadService;
use app\common\plugin\hook\Hook;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * Admin Web 通用上传适配器，复用现有上传服务并统一响应结构。
 */
#[RouteGroup('/admin')]
class AdminUpload extends AdminApiController
{

    #[Post('/upload')]
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->fail(msg: '请选择要上传的文件', code: 422);
        }

        $bizType = strtolower(trim((string) $this->request->post('bizType', 'file')));
        if (!in_array($bizType, ['file', 'image', 'avatar'], true)) {
            return $this->fail(msg: '上传业务类型不支持', code: 422);
        }
        $groupId = (int) $this->request->post('groupId', 1);
        if ($groupId <= 0 || !AttachGroup::find($groupId)) {
            return $this->fail(msg: '附件分组不存在', code: 422);
        }
        $this->request->setPost(array_merge($this->request->post(), ['group_id' => $groupId]));
        if (in_array($bizType, ['image', 'avatar'], true)) {
            $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname()));
            if (!in_array($mime, ['image/gif', 'image/jpeg', 'image/png', 'image/bmp', 'image/webp'], true)) {
                return $this->fail(msg: '请选择有效的图片文件', code: 422);
            }
            $imageInfo = @getimagesize($file->getPathname());
            if (!$imageInfo) {
                return $this->fail(msg: '上传文件不是有效图片', code: 422);
            }
        }

        $fileSize = $file->getSize();
        $driver = \support\Container::get(\app\common\storage\StorageDriverRegistry::class)
            ->resolve((string) syscfg('upload', 'upload_driver'))->name();
        $existingAttach = Attach::where('driver', $driver)->where('md5', hash_file('md5', $file->getPathname()))->first();
        try {
            $result = UploadService::instance()->uploads(0, (int) (request()?->context['admin']['id'] ?? 0));
        } catch (\Throwable $e) {
            return $this->fail(msg: $e->getMessage() ?: '上传失败', code: 422);
        }

        $attachId = (int) ($result['id'] ?? 0);
        $attach = $attachId > 0 ? Attach::find($attachId) : null;
        if (!$attach) {
            return $this->fail(msg: '上传记录保存失败', code: 500);
        }

        $data = [
            'url' => (string) $attach->path,
            'name' => (string) $attach->original_name,
            'size' => $fileSize,
            'ext' => strtolower((string) $attach->ext),
            'groupId' => (int) $attach->group_id,
            'driver' => (string) $attach->driver,
            'reused' => $existingAttach && (int) $existingAttach->id === (int) $attach->id,
            'uploadedAt' => time() * 1000,
        ];
        try {
            $filtered = Hook::filter('upload.result', $data, ['attachmentId' => $attachId]);
            if (!is_array($filtered)
                || array_diff(array_keys($filtered), [...array_keys($data), 'extensions']) !== []) {
                throw new \UnexpectedValueException('上传钩子返回结构不正确');
            }
            foreach ($data as $key => $value) {
                if (!array_key_exists($key, $filtered) || $filtered[$key] !== $value) {
                    throw new \UnexpectedValueException('上传钩子不能修改附件核心字段');
                }
            }
            if (array_key_exists('extensions', $filtered)) {
                if (!is_array($filtered['extensions'])) {
                    throw new \UnexpectedValueException('上传钩子扩展数据必须为对象');
                }
                foreach (array_keys($filtered['extensions']) as $code) {
                    if (!is_string($code) || preg_match('/^[a-z][a-z0-9]*$/D', $code) !== 1) {
                        throw new \UnexpectedValueException('上传钩子扩展数据必须使用插件标识');
                    }
                }
            }
            json_encode($filtered, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->fail(msg: '上传已保存，扩展处理失败，请稍后重试', code: 422);
        }
        return $this->ok('上传成功', $filtered);
    }
}
