<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\AttachGroup;
use app\common\model\Attach;
use app\common\storage\StorageDriverRegistry;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\App;
use think\Response;

/**
 * Admin Web 附件库管理。
 */
#[Group('system/attachment')]
class SystemAttachment extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function __construct(App $app, private readonly StorageDriverRegistry $storageDrivers)
    {
        parent::__construct($app);
    }

    #[Get('')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = Attach::order('id', 'desc');
        $keyword = trim((string) $this->request->get('keyword', ''));
        $mimeType = trim((string) $this->request->get('mimeType', ''));
        $groupId = $this->request->get('groupId', null);
        if ($keyword !== '') {
            $query->where(function ($where) use ($keyword): void {
                $where->whereLike('original_name', '%' . $keyword . '%')->whereOr('name', 'like', '%' . $keyword . '%');
            });
        }
        if ($groupId !== null && $groupId !== '') {
            $query->where('group_id', (int) $groupId);
        }
        if ($mimeType !== '') {
            $this->applyMimeFilter($query, $mimeType);
        }
        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        return $this->ok(data: $this->paginationData(
            array_map(fn (Attach $attach): array => $this->attachmentData($attach), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    #[Get(':id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        $attach = Attach::find($id);
        return $attach ? $this->ok(data: $this->attachmentData($attach)) : $this->fail(msg: '附件不存在', code: 404);
    }

    #[Put(':id/name')]
    #[Pattern('id', '\\d+')]
    public function rename(int $id): Response
    {
        $attach = Attach::find($id);
        if (!$attach) {
            return $this->fail(msg: '附件不存在', code: 404);
        }
        $name = trim((string) $this->request->post('name', ''));
        $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($name === '' || $length > 255) {
            return $this->fail(msg: '文件名称不能为空且不能超过 255 个字符', code: 422);
        }
        $attach->save(['original_name' => $name]);
        return $this->ok('重命名成功', $this->attachmentData($attach));
    }

    #[Post('move')]
    public function move(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要移动的附件', code: 422);
        }
        $groupId = (int) $this->request->post('groupId', 0);
        if ($groupId > 0 && !AttachGroup::find($groupId)) {
            return $this->fail(msg: '目标附件分组不存在', code: 422);
        }
        $attachments = Attach::whereIn('id', $ids)->select();
        if (count($attachments) !== count($ids)) {
            return $this->fail(msg: '部分附件不存在', code: 404);
        }
        foreach ($attachments as $attach) {
            $attach->save(['group_id' => $groupId]);
        }
        return $this->ok('移动成功', ['moved' => count($attachments)]);
    }

    #[Delete('')]
    public function delete(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的附件', code: 422);
        }
        $attachments = Attach::whereIn('id', $ids)->select();
        if (count($attachments) !== count($ids)) {
            return $this->fail(msg: '部分附件不存在', code: 404);
        }

        $checkedPaths = [];
        foreach ($attachments as $attach) {
            $driverName = (string) ($attach->driver ?: 'local');
            $path = (string) $attach->path;
            $storageKey = (string) ($attach->storage_key ?? '');
            if ($storageKey === '' && $driverName === 'local' && str_starts_with($path, '/storage/')) {
                $storageKey = ltrim(substr($path, strlen('/storage/')), '/');
            }
            $referenceKey = $driverName . ':' . $storageKey;
            if ($storageKey !== '' && !isset($checkedPaths[$referenceKey])) {
                $checkedPaths[$referenceKey] = true;
                $referencedElsewhere = Attach::withTrashed()
                    ->where('driver', $driverName)
                    ->where('storage_key', $storageKey)
                    ->whereNotIn('id', $ids)
                    ->count() > 0;
                if (!$referencedElsewhere) {
                    $this->storageDrivers->resolve($driverName)->delete($storageKey);
                }
            }
            $attach->force()->delete();
        }
        return $this->ok('删除成功', ['removed' => count($attachments)]);
    }

    private function applyMimeFilter($query, string $mimeType): void
    {
        match ($mimeType) {
            'image' => $query->whereLike('mime', 'image/%'),
            'video' => $query->whereLike('mime', 'video/%'),
            'audio' => $query->whereLike('mime', 'audio/%'),
            'document' => $query->whereIn('ext', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt']),
            'archive' => $query->whereIn('ext', ['zip', 'rar', '7z', 'tar', 'gz']),
            default => null,
        };
    }

    private function attachmentData(Attach $attach): array
    {
        return [
            'id' => (int) $attach->id,
            'groupId' => (int) $attach->group_id,
            'name' => (string) ($attach->name ?? ''),
            'originalName' => (string) ($attach->original_name ?? ''),
            'path' => (string) ($attach->path ?? ''),
            'url' => (string) (($attach->url ?? '') ?: ($attach->path ?? '')),
            'thumb' => (string) (($attach->thumb ?? '') ?: ($attach->path ?? '')),
            'ext' => strtolower((string) ($attach->ext ?? '')),
            'size' => (int) round((float) $attach->size * 1024),
            'mime' => (string) ($attach->mime ?? ''),
            'driver' => (string) ($attach->driver ?? ''),
            'width' => (int) $attach->width,
            'height' => (int) $attach->height,
            'status' => (int) $attach->status,
            'createdAt' => $this->formatTime($attach->created_at),
            'updatedAt' => $this->formatTime($attach->updated_at),
        ];
    }
}
