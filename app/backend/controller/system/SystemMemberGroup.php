<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\MemberGroup;
use app\backend\model\MemberGroupRelation;
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

        return $this->ok(data: $this->paginationData(
            array_map(fn (MemberGroup $group): array => $this->groupData($group), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    public function detail(int $id): Response
    {
        $group = MemberGroup::withTrashed()->find($id);
        return $group
            ? $this->ok(data: $this->groupData($group))
            : $this->fail(msg: '会员组不存在', code: 404);
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (MemberGroup::withTrashed()->where('name', $data['name'])->find()) {
            return $this->fail(msg: '会员组名称已存在', code: 422);
        }

        $group = MemberGroup::create($data);
        return $this->ok('创建成功', $this->groupData($group));
    }

    public function update(int $id): Response
    {
        $group = MemberGroup::find($id);
        if (!$group) {
            return $this->fail(msg: '会员组不存在', code: 404);
        }
        $data = $this->payload($group);
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (MemberGroup::withTrashed()->where('name', $data['name'])->where('id', '<>', $id)->find()) {
            return $this->fail(msg: '会员组名称已存在', code: 422);
        }

        $group->save($data);
        return $this->ok('保存成功', $this->groupData($group));
    }

    public function status(int $id): Response
    {
        $group = MemberGroup::find($id);
        if (!$group) {
            return $this->fail(msg: '会员组不存在', code: 404);
        }
        $group->save(['status' => $this->binaryStatus($this->request->post('status', 0))]);
        return $this->ok('状态更新成功', $this->groupData($group));
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
        return $this->ok('已移入回收站', ['removed' => count($groups)]);
    }

    public function restore(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要恢复的会员组', code: 422);
        }
        $groups = MemberGroup::onlyTrashed()->whereIn('id', $ids)->select();
        if (count($groups) !== count($ids)) {
            return $this->fail(msg: '部分会员组不存在或不在回收站', code: 404);
        }
        foreach ($groups as $group) {
            $group->restore();
        }
        return $this->ok('恢复成功', ['restored' => count($groups)]);
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
        return $this->ok('永久删除成功', ['removed' => count($groups)]);
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
            return $this->fail(msg: '导出数据超过 10000 条，请缩小筛选范围', code: 422);
        }
        return $this->ok(data: array_map(fn (MemberGroup $group): array => $this->groupData($group), $query->order('id', 'asc')->select()->all()));
    }

    private function groupsForAction(bool $onlyTrashed)
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要操作的会员组', code: 422);
        }
        if (in_array(1, $ids, true)) {
            return $this->fail(msg: '默认会员组不能删除', code: 422);
        }
        $query = $onlyTrashed ? MemberGroup::onlyTrashed() : MemberGroup::where('id', '>', 0);
        $groups = $query->whereIn('id', $ids)->select();
        if (count($groups) !== count($ids)) {
            return $this->fail(msg: $onlyTrashed ? '部分会员组不存在或不在回收站' : '部分会员组不存在或已在回收站', code: 404);
        }
        if (MemberGroupRelation::whereIn('group_id', $ids)->count() > 0) {
            return $this->fail(msg: '会员组仍被会员引用，不能删除', code: 422);
        }
        return $groups;
    }

    private function payload(?MemberGroup $group = null): array
    {
        return [
            'name' => trim((string) $this->request->post('name', $group?->name ?? '')),
            'icon' => trim((string) $this->request->post('icon', $group?->icon ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $group?->status ?? 1)),
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
        $iconLength = function_exists('mb_strlen') ? mb_strlen($data['icon']) : strlen($data['icon']);
        if ($iconLength > 50) {
            return '会员组图标不能超过 50 个字符';
        }
        return null;
    }

    private function groupData(MemberGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'name' => (string) $group->name,
            'icon' => (string) ($group->icon ?? ''),
            'status' => (int) $group->status,
            'isDefault' => (int) $group->id === 1 ? 1 : 0,
            'createdAt' => $this->formatTime($group->created_at),
            'updatedAt' => $this->formatTime($group->updated_at),
            'deletedAt' => $this->formatTime($group->deleted_at),
        ];
    }
}
