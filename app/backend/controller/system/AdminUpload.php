<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\AttachGroup;
use app\common\model\Attach;
use app\common\service\UploadService;
use think\Response;
use think\facade\Session;

/**
 * Admin Web 通用上传适配器，复用现有上传服务并统一响应结构。
 */
class AdminUpload extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

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
        $this->request->withPost(array_merge($this->request->post(), ['group_id' => $groupId]));
        if (in_array($bizType, ['image', 'avatar'], true)) {
            $mime = strtolower((string) $file->getMime());
            if (!in_array($mime, ['image/gif', 'image/jpeg', 'image/png', 'image/bmp', 'image/webp'], true)) {
                return $this->fail(msg: '请选择有效的图片文件', code: 422);
            }
            $imageInfo = @getimagesize($file->getPathname());
            if (!$imageInfo) {
                return $this->fail(msg: '上传文件不是有效图片', code: 422);
            }
        }

        $driver = app()->make(\app\common\storage\StorageDriverRegistry::class)
            ->resolve((string) syscfg('upload', 'upload_driver'))->name();
        $existingAttach = Attach::where('driver', $driver)->where('md5', $file->md5())->find();
        try {
            $result = UploadService::instance()->uploads(0, (int) Session::get('admin.id', 0));
        } catch (\Throwable $e) {
            return $this->fail(msg: $e->getMessage() ?: '上传失败', code: 422);
        }

        $attachId = (int) ($result['id'] ?? 0);
        $attach = $attachId > 0 ? Attach::find($attachId) : null;
        if (!$attach) {
            return $this->fail(msg: '上传记录保存失败', code: 500);
        }

        return $this->ok('上传成功', [
            'url' => (string) $attach->path,
            'name' => (string) $attach->original_name,
            'size' => (int) round((float) $attach->size * 1024),
            'ext' => strtolower((string) $attach->ext),
            'groupId' => (int) $attach->group_id,
            'driver' => (string) $attach->driver,
            'reused' => $existingAttach && (int) $existingAttach->id === (int) $attach->id,
            'uploadedAt' => time() * 1000,
        ]);
    }
}
