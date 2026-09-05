<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\AttachGroup;
use app\common\model\Attach;
use think\Response;
use think\facade\Db;

/**
 * Admin Web 附件分组管理；保留模型供旧文件选择器兼容使用。
 */
class SystemAttachmentGroup extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function tree(): Response
    {
        $groups = AttachGroup::order('sort', 'asc')->order('id', 'asc')->select();
        $rows = array_map(fn (AttachGroup $group): array => $this->groupData($group), $groups->all());
        return $this->ok($this->buildTree($rows));
    }

    public function detail(int $id): Response
    {
        $group = AttachGroup::find($id);
        return $group ? $this->ok($this->groupData($group)) : $this->fail('附件分组不存在', 404);
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        $group = AttachGroup::create($data);
        return $this->ok($this->groupData($group), '创建成功');
    }

    public function update(int $id): Response
    {
        $group = AttachGroup::find($id);
        if (!$group) {
            return $this->fail('附件分组不存在', 404);
        }
        $data = $this->payload($group);
        if ($error = $this->validatePayload($data, $id)) {
            return $this->fail($error, 422);
        }
        $group->save($data);
        return $this->ok($this->groupData($group), '保存成功');
    }

    public function delete(int $id): Response
    {
        if ($id === 1) {
            return $this->fail('默认附件分组不能删除', 422);
        }
        $group = AttachGroup::find($id);
        if (!$group) {
            return $this->fail('附件分组不存在', 404);
        }
        if (AttachGroup::where('pid', $id)->count() > 0) {
            return $this->fail('请先删除下级附件分组', 422);
        }

        Db::transaction(function () use ($group, $id): void {
            Attach::where('group_id', $id)->update(['group_id' => 0]);
            $group->force()->delete();
        });
        return $this->ok(null, '删除成功，组内附件已移至未分组');
    }

    private function payload(?AttachGroup $group = null): array
    {
        return [
            'pid' => (int) $this->request->post('parentId', $group?->pid ?? 0),
            'name' => trim((string) $this->request->post('name', $group?->name ?? '')),
            'thumb' => trim((string) $this->request->post('thumb', $group?->thumb ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $group?->status ?? 1)),
            'sort' => max(0, (int) $this->request->post('sort', $group?->sort ?? 999)),
        ];
    }

    private function validatePayload(array $data, int $currentId = 0): ?string
    {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        if ($data['name'] === '') {
            return '附件分组名称不能为空';
        }
        if ($nameLength > 100) {
            return '附件分组名称不能超过 100 个字符';
        }
        if (strlen($data['thumb']) > 255) {
            return '分组图片地址不能超过 255 个字符';
        }
        if ($data['pid'] > 0 && !AttachGroup::find($data['pid'])) {
            return '上级附件分组不存在';
        }
        if ($currentId > 0 && ($data['pid'] === $currentId || $this->isDescendant($data['pid'], $currentId))) {
            return '不能将附件分组移动到自身或下级';
        }
        return null;
    }

    private function isDescendant(int $candidateId, int $currentId): bool
    {
        $visited = [];
        while ($candidateId > 0 && !isset($visited[$candidateId])) {
            if ($candidateId === $currentId) {
                return true;
            }
            $visited[$candidateId] = true;
            $candidateId = (int) (AttachGroup::where('id', $candidateId)->value('pid') ?? 0);
        }
        return false;
    }

    private function groupData(AttachGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'parentId' => (int) $group->pid,
            'name' => (string) $group->name,
            'thumb' => (string) ($group->thumb ?? ''),
            'status' => (int) $group->status,
            'sort' => (int) $group->sort,
            'isDefault' => (int) $group->id === 1 ? 1 : 0,
            'createdAt' => $this->formatTime($group->create_time),
            'updatedAt' => $this->formatTime($group->update_time),
        ];
    }
}
