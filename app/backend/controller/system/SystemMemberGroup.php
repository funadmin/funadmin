<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\MemberGroup;
use think\facade\Db;
use think\Response;

/**
 * Admin Web 会员组管理。
 */
class SystemMemberGroup extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $recycled ? MemberGroup::onlyTrashed() : MemberGroup::order('id', 'asc');
        $name = trim((string) $this->request->get('name', ''));
        $status = $this->request->get('status', null);
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $result = $query->order('id', 'asc')->paginate(['list_rows' => $pageSize, 'page' => $page]);

        return $this->ok([
            'list' => array_map(fn (MemberGroup $group): array => $this->groupData($group), $result->items()),
            'total' => $result->total(),
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    public function detail(int $id): Response
    {
        $group = MemberGroup::withTrashed()->find($id);
        return $group
            ? $this->ok($this->groupData($group))
            : $this->fail('会员组不存在', 404);
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if (MemberGroup::withTrashed()->where('name', $data['name'])->find()) {
            return $this->fail('会员组名称已存在', 422);
        }

        $group = MemberGroup::create($data);
        return $this->ok($this->groupData($group), '创建成功');
    }

    public function update(int $id): Response
    {
        $group = MemberGroup::find($id);
        if (!$group) {
            return $this->fail('会员组不存在', 404);
        }
        $data = $this->payload($group);
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if (MemberGroup::withTrashed()->where('name', $data['name'])->where('id', '<>', $id)->find()) {
            return $this->fail('会员组名称已存在', 422);
        }

        $group->save($data);
        return $this->ok($this->groupData($group), '保存成功');
    }

    public function status(int $id): Response
    {
        $group = MemberGroup::find($id);
        if (!$group) {
            return $this->fail('会员组不存在', 404);
        }
        $group->save(['status' => (int) $this->request->post('status', 0) === 1 ? 1 : 0]);
        return $this->ok($this->groupData($group), '状态更新成功');
    }

    public function recycle(): Response
    {
        $groups = $this->groupsForAction(false);
        if ($groups instanceof Response) {
            return $groups;
        }
        foreach ($groups as $group) {
            $group->delete();
        }
        return $this->ok(['removed' => count($groups)], '已移入回收站');
    }

    public function restore(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要恢复的会员组', 422);
        }
        $groups = MemberGroup::onlyTrashed()->whereIn('id', $ids)->select();
        if (count($groups) !== count($ids)) {
            return $this->fail('部分会员组不存在或不在回收站', 404);
        }
        foreach ($groups as $group) {
            $group->restore();
        }
        return $this->ok(['restored' => count($groups)], '恢复成功');
    }

    public function destroy(): Response
    {
        $groups = $this->groupsForAction(true);
        if ($groups instanceof Response) {
            return $groups;
        }
        foreach ($groups as $group) {
            $group->force()->delete();
        }
        return $this->ok(['removed' => count($groups)], '永久删除成功');
    }

    public function export(): Response
    {
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $recycled ? MemberGroup::onlyTrashed() : MemberGroup::order('id', 'asc');
        $name = trim((string) $this->request->get('name', ''));
        $status = $this->request->get('status', null);
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ((clone $query)->count() > 10000) {
            return $this->fail('导出数据超过 10000 条，请缩小筛选范围', 422);
        }
        return $this->ok(array_map(fn (MemberGroup $group): array => $this->groupData($group), $query->order('id', 'asc')->select()->all()));
    }

    private function groupsForAction(bool $onlyTrashed)
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要操作的会员组', 422);
        }
        if (in_array(1, $ids, true)) {
            return $this->fail('默认会员组不能删除', 422);
        }
        $query = $onlyTrashed ? MemberGroup::onlyTrashed() : MemberGroup::where('id', '>', 0);
        $groups = $query->whereIn('id', $ids)->select();
        if (count($groups) !== count($ids)) {
            return $this->fail($onlyTrashed ? '部分会员组不存在或不在回收站' : '部分会员组不存在或已在回收站', 404);
        }
        if (Db::name('member_group_relation')->whereIn('group_id', $ids)->count() > 0) {
            return $this->fail('会员组仍被会员引用，不能删除', 422);
        }
        return $groups;
    }

    private function payload(?MemberGroup $group = null): array
    {
        return [
            'name' => trim((string) $this->request->post('name', $group?->name ?? '')),
            'status' => (int) $this->request->post('status', $group?->status ?? 1) === 1 ? 1 : 0,
        ];
    }

    private function validatePayload(array $data): ?string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        if ($data['name'] === '') {
            return '会员组名称不能为空';
        }
        if ($length > 50) {
            return '会员组名称不能超过 50 个字符';
        }
        return null;
    }

    private function groupData(MemberGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'name' => (string) $group->name,
            'status' => (int) $group->status,
            'isDefault' => (int) $group->id === 1 ? 1 : 0,
            'createdAt' => $this->formatTime($group->create_time),
            'updatedAt' => $this->formatTime($group->update_time),
            'deletedAt' => $this->formatTime($group->delete_time),
        ];
    }
}
