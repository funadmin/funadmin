<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\Member;
use app\backend\model\MemberGroup;
use app\backend\model\MemberGroupRelation;
use app\backend\model\MemberLevel;
use think\facade\Db;
use think\Response;

/**
 * Admin Web 会员管理。
 */
class SystemMember extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $this->filteredQuery($recycled);
        $result = $query->order('id', 'desc')->paginate(['list_rows' => $pageSize, 'page' => $page]);
        $items = $result->items();
        [$memberGroups, $groups, $levels] = $this->relationMaps($items);

        return $this->ok($this->paginationData(
            array_map(fn (Member $member): array => $this->memberData($member, $memberGroups, $groups, $levels), $items),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    public function detail(int $id): Response
    {
        $member = Member::withTrashed()->find($id);
        if (!$member) {
            return $this->fail('会员不存在', 404);
        }
        [$memberGroups, $groups, $levels] = $this->relationMaps([$member]);
        return $this->ok($this->memberData($member, $memberGroups, $groups, $levels));
    }

    public function options(): Response
    {
        return $this->ok([
            'groups' => MemberGroup::where('status', 1)->order('id', 'asc')->field('id,name')->select()->toArray(),
            'levels' => MemberLevel::where('status', 1)->order('sort', 'asc')->order('id', 'asc')->field('id,name')->select()->toArray(),
        ]);
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if ($error = $this->validateUnique($data)) {
            return $this->fail($error, 422);
        }

        $groupIds = $data['groupIds'];
        unset($data['groupIds']);
        $data['password'] = '';
        try {
            $member = Db::transaction(function () use ($data, $groupIds): Member {
                $member = Member::create($data);
                $member->groups()->sync($groupIds);
                return $member;
            });
        } catch (\Throwable $exception) {
            if ($message = $this->duplicateError($exception)) {
                return $this->fail($message, 422);
            }
            throw $exception;
        }
        [$memberGroups, $groups, $levels] = $this->relationMaps([$member]);
        return $this->ok($this->memberData($member, $memberGroups, $groups, $levels), '创建成功');
    }

    public function update(int $id): Response
    {
        $member = Member::find($id);
        if (!$member) {
            return $this->fail('会员不存在', 404);
        }
        $data = $this->payload($member);
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if ($error = $this->validateUnique($data, $id)) {
            return $this->fail($error, 422);
        }

        $groupIds = $data['groupIds'];
        unset($data['groupIds']);
        try {
            Db::transaction(function () use ($member, $data, $groupIds): void {
                $member->save($data);
                $member->groups()->sync($groupIds);
            });
        } catch (\Throwable $exception) {
            if ($message = $this->duplicateError($exception)) {
                return $this->fail($message, 422);
            }
            throw $exception;
        }
        [$memberGroups, $groups, $levels] = $this->relationMaps([$member]);
        return $this->ok($this->memberData($member, $memberGroups, $groups, $levels), '保存成功');
    }

    public function status(int $id): Response
    {
        $member = Member::find($id);
        if (!$member) {
            return $this->fail('会员不存在', 404);
        }
        $member->save(['status' => $this->binaryStatus($this->request->post('status', 0))]);
        [$memberGroups, $groups, $levels] = $this->relationMaps([$member]);
        return $this->ok($this->memberData($member, $memberGroups, $groups, $levels), '状态更新成功');
    }

    public function recycle(): Response
    {
        $members = $this->membersForAction(false);
        if ($members instanceof Response) {
            return $members;
        }
        foreach ($members as $member) {
            $member->delete();
        }
        return $this->ok(['removed' => count($members)], '已移入回收站');
    }

    public function restore(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要恢复的会员', 422);
        }
        $members = Member::onlyTrashed()->whereIn('id', $ids)->select();
        if (count($members) !== count($ids)) {
            return $this->fail('部分会员不存在或不在回收站', 404);
        }
        foreach ($members as $member) {
            $member->restore();
        }
        return $this->ok(['restored' => count($members)], '恢复成功');
    }

    public function destroy(): Response
    {
        $members = $this->membersForAction(true);
        if ($members instanceof Response) {
            return $members;
        }
        Db::transaction(function () use ($members): void {
            foreach ($members as $member) {
                $member->groups()->detach();
                $member->force()->delete();
            }
        });
        return $this->ok(['removed' => count($members)], '永久删除成功');
    }

    public function import(): Response
    {
        $rows = $this->request->post('rows', []);
        if (!is_array($rows) || !$rows) {
            return $this->fail('导入数据不能为空', 422);
        }
        if (count($rows) > 1000) {
            return $this->fail('单次最多导入 1000 条会员', 422);
        }

        $created = 0;
        $errors = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：数据格式错误';
                continue;
            }
            $data = $this->normalizePayload($row);
            if ($error = $this->validatePayload($data)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . $error;
                continue;
            }
            if ($error = $this->validateUnique($data)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . $error;
                continue;
            }
            try {
                $groupIds = $data['groupIds'];
                unset($data['groupIds']);
                $data['password'] = '';
                Db::transaction(function () use ($data, $groupIds): void {
                    $member = Member::create($data);
                    $member->groups()->sync($groupIds);
                });
                $created++;
            } catch (\Throwable $exception) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . ($this->duplicateError($exception) ?? '保存失败');
            }
        }

        return $this->ok([
            'created' => $created,
            'skipped' => count($rows) - $created,
            'errors' => $errors,
        ], $errors ? '导入完成，部分数据未导入' : '导入成功');
    }

    public function export(): Response
    {
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $this->filteredQuery($recycled);
        if ((clone $query)->count() > 10000) {
            return $this->fail('导出数据超过 10000 条，请缩小筛选范围', 422);
        }
        $members = $query->order('id', 'desc')->select()->all();
        [$memberGroups, $groups, $levels] = $this->relationMaps($members);
        return $this->ok(array_map(fn (Member $member): array => $this->memberData($member, $memberGroups, $groups, $levels), $members));
    }

    private function filteredQuery(bool $recycled)
    {
        $query = $recycled ? Member::onlyTrashed() : Member::where('id', '>', 0);
        $keyword = trim((string) $this->request->get('keyword', ''));
        $status = $this->request->get('status', null);
        $groupId = (int) $this->request->get('groupId', 0);
        $levelId = (int) $this->request->get('levelId', 0);
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->whereLike('username', '%' . $keyword . '%')
                    ->whereOr('mobile', 'like', '%' . $keyword . '%')
                    ->whereOr('email', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($groupId > 0) {
            $query->alias('member')->whereExists(function ($relationQuery) use ($groupId): void {
                $relationQuery->table((new MemberGroupRelation())->getTable() . ' member_group_relation')
                    ->field('member_group_relation.member_id')
                    ->whereColumn('member_group_relation.member_id', 'member.id')
                    ->where('member_group_relation.group_id', $groupId);
            });
        }
        if ($levelId > 0) {
            $query->where('level_id', $levelId);
        }
        return $query;
    }

    private function membersForAction(bool $onlyTrashed)
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要操作的会员', 422);
        }
        $query = $onlyTrashed ? Member::onlyTrashed() : Member::where('id', '>', 0);
        $members = $query->whereIn('id', $ids)->select();
        if (count($members) !== count($ids)) {
            return $this->fail($onlyTrashed ? '部分会员不存在或不在回收站' : '部分会员不存在或已在回收站', 404);
        }
        return $members;
    }

    private function payload(?Member $member = null): array
    {
        return $this->normalizePayload([
            'username' => $this->request->post('username', $member?->username ?? ''),
            'mobile' => $this->request->post('mobile', $member?->mobile ?? ''),
            'email' => $this->request->post('email', $member?->email ?? ''),
            'sex' => $this->request->post('sex', $member?->sex ?? 0),
            'groupIds' => $this->request->post('groupIds', $member ? $this->memberGroupIds((int) $member->id) : []),
            'levelId' => $this->request->post('levelId', $member?->level_id ?? 0),
            'avatar' => $this->request->post('avatar', $member?->avatar ?? ''),
            'status' => $this->request->post('status', $member?->status ?? 1),
        ]);
    }

    private function normalizePayload(array $row): array
    {
        $groupIds = $row['groupIds'] ?? $row['group_ids'] ?? $row['groupId'] ?? $row['group_id'] ?? [];
        $groupIds = $this->normalizeIds($groupIds);

        return [
            'username' => trim((string) ($row['username'] ?? '')),
            'mobile' => ($mobile = trim((string) ($row['mobile'] ?? ''))) !== '' ? $mobile : null,
            'email' => ($email = trim((string) ($row['email'] ?? ''))) !== '' ? $email : null,
            'sex' => (string) ($row['sex'] ?? '0'),
            'groupIds' => $groupIds,
            'level_id' => (int) ($row['levelId'] ?? $row['level_id'] ?? 0),
            'avatar' => trim((string) ($row['avatar'] ?? '')),
            'status' => $this->binaryStatus($row['status'] ?? 1),
        ];
    }

    private function validatePayload(array $data): ?string
    {
        $usernameLength = function_exists('mb_strlen') ? mb_strlen($data['username']) : strlen($data['username']);
        if ($usernameLength < 2 || $usernameLength > 80) {
            return '用户名长度必须为 2 至 80 个字符';
        }
        if (preg_match('/[\x00-\x1F\x7F<>]/u', $data['username'])) {
            return '用户名包含非法字符';
        }
        if ($data['mobile'] === null || !preg_match('/^[0-9+\- ]{6,20}$/', $data['mobile'])) {
            return '请输入 6 至 20 位有效手机号';
        }
        if ($data['email'] !== null && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 60)) {
            return '邮箱格式不正确或超过 60 个字符';
        }
        if (!in_array($data['sex'], ['0', '1', '2'], true)) {
            return '性别参数无效';
        }
        if (!$data['groupIds'] || count($data['groupIds']) > 32) {
            return '请选择有效会员组，且最多选择 32 个会员组';
        }
        if (MemberGroup::whereIn('id', $data['groupIds'])->where('status', 1)->count() !== count($data['groupIds'])) {
            return '会员组不存在、已删除或已停用';
        }
        if ($data['level_id'] <= 0 || !MemberLevel::where('id', $data['level_id'])->where('status', 1)->find()) {
            return '会员等级不存在、已删除或已停用';
        }
        if (strlen($data['avatar']) > 255) {
            return '头像地址不能超过 255 个字符';
        }
        return null;
    }

    private function validateUnique(array $data, int $excludeId = 0): ?string
    {
        $usernameQuery = Member::withTrashed()->where('username', $data['username']);
        $mobileQuery = $data['mobile'] !== null ? Member::withTrashed()->where('mobile', $data['mobile']) : null;
        $emailQuery = $data['email'] !== null ? Member::withTrashed()->where('email', $data['email']) : null;
        if ($excludeId > 0) {
            $usernameQuery->where('id', '<>', $excludeId);
            $mobileQuery?->where('id', '<>', $excludeId);
            $emailQuery?->where('id', '<>', $excludeId);
        }
        if ($usernameQuery->find()) {
            return '用户名已存在';
        }
        if ($mobileQuery?->find()) {
            return '手机号已存在';
        }
        if ($emailQuery?->find()) {
            return '邮箱已存在';
        }
        return null;
    }

    private function duplicateError(\Throwable $exception): ?string
    {
        $message = $exception->getMessage();
        if (!str_contains($message, '1062') && !str_contains($message, 'Duplicate entry')) {
            return null;
        }
        foreach (['username' => '用户名已存在', 'mobile' => '手机号已存在', 'email' => '邮箱已存在'] as $field => $error) {
            if (str_contains($message, $field)) {
                return $error;
            }
        }
        return '用户名、手机号或邮箱已存在';
    }

    private function relationMaps(array $members): array
    {
        $memberIds = [];
        $memberGroups = [];
        $levelIds = [];
        foreach ($members as $member) {
            $memberIds[] = (int) $member->id;
            $memberGroups[(int) $member->id] = [];
            $levelIds[] = (int) $member->level_id;
        }
        $relations = $memberIds ? MemberGroupRelation::whereIn('member_id', $memberIds)
            ->field('member_id,group_id')->order('group_id', 'asc')->select()->toArray() : [];
        $groupIds = [];
        foreach ($relations as $relation) {
            $memberId = (int) $relation['member_id'];
            $groupId = (int) $relation['group_id'];
            $memberGroups[$memberId][] = $groupId;
            $groupIds[] = $groupId;
        }
        $groupIds = array_values(array_unique($groupIds));
        $levelIds = array_values(array_unique(array_filter($levelIds)));
        $groups = $groupIds ? MemberGroup::withTrashed()->whereIn('id', $groupIds)->column('name', 'id') : [];
        $levels = $levelIds ? MemberLevel::withTrashed()->whereIn('id', $levelIds)->column('name', 'id') : [];
        return [$memberGroups, $groups, $levels];
    }

    private function memberGroupIds(int $memberId): array
    {
        return array_map('intval', MemberGroupRelation::where('member_id', $memberId)
            ->order('group_id', 'asc')->column('group_id'));
    }

    private function memberData(Member $member, array $memberGroups, array $groups, array $levels): array
    {
        $groupIds = $memberGroups[(int) $member->id] ?? [];
        return [
            'id' => (int) $member->id,
            'username' => (string) $member->username,
            'mobile' => (string) $member->mobile,
            'email' => (string) $member->email,
            'sex' => (string) $member->sex,
            'groupIds' => $groupIds,
            'groupNames' => array_values(array_map(static fn (int $id): string => (string) ($groups[$id] ?? ('#' . $id)), $groupIds)),
            'levelId' => (int) $member->level_id,
            'levelName' => (string) ($levels[(int) $member->level_id] ?? ''),
            'avatar' => (string) ($member->avatar ?? ''),
            'status' => (int) $member->status,
            'loginCount' => (int) $member->login_num,
            'lastLoginAt' => $this->formatTime($member->last_login),
            'lastLoginIp' => (string) $member->last_ip,
            'createdAt' => $this->formatTime($member->create_time),
            'updatedAt' => $this->formatTime($member->update_time),
            'deletedAt' => $this->formatTime($member->delete_time),
        ];
    }
}
