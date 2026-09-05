<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\model\Member;
use app\backend\model\MemberLevel;
use think\Response;

/**
 * Admin Web 会员等级管理。
 */
class SystemMemberLevel extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $this->filteredQuery($recycled);
        $result = $query->order('sort', 'asc')->order('id', 'asc')->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
        ]);

        return $this->ok($this->paginationData(
            array_map(fn (MemberLevel $level): array => $this->levelData($level), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    public function detail(int $id): Response
    {
        $level = MemberLevel::withTrashed()->find($id);
        return $level
            ? $this->ok($this->levelData($level))
            : $this->fail('会员等级不存在', 404);
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if (MemberLevel::withTrashed()->where('name', $data['name'])->find()) {
            return $this->fail('会员等级名称已存在', 422);
        }

        $level = MemberLevel::create($data);
        return $this->ok($this->levelData($level), '创建成功');
    }

    public function update(int $id): Response
    {
        $level = MemberLevel::find($id);
        if (!$level) {
            return $this->fail('会员等级不存在', 404);
        }
        $data = $this->payload($level);
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }
        if (MemberLevel::withTrashed()->where('name', $data['name'])->where('id', '<>', $id)->find()) {
            return $this->fail('会员等级名称已存在', 422);
        }

        $level->save($data);
        return $this->ok($this->levelData($level), '保存成功');
    }

    public function status(int $id): Response
    {
        $level = MemberLevel::find($id);
        if (!$level) {
            return $this->fail('会员等级不存在', 404);
        }
        $level->save(['status' => (int) $this->request->post('status', 0) === 1 ? 1 : 0]);
        return $this->ok($this->levelData($level), '状态更新成功');
    }

    public function recycle(): Response
    {
        $levels = $this->levelsForDelete(false);
        if ($levels instanceof Response) {
            return $levels;
        }
        foreach ($levels as $level) {
            $level->delete();
        }
        return $this->ok(['removed' => count($levels)], '已移入回收站');
    }

    public function restore(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要恢复的会员等级', 422);
        }
        $levels = MemberLevel::onlyTrashed()->whereIn('id', $ids)->select();
        if (count($levels) !== count($ids)) {
            return $this->fail('部分会员等级不存在或不在回收站', 404);
        }
        foreach ($levels as $level) {
            $level->restore();
        }
        return $this->ok(['restored' => count($levels)], '恢复成功');
    }

    public function destroy(): Response
    {
        $levels = $this->levelsForDelete(true);
        if ($levels instanceof Response) {
            return $levels;
        }
        foreach ($levels as $level) {
            $level->force()->delete();
        }
        return $this->ok(['removed' => count($levels)], '永久删除成功');
    }

    public function export(): Response
    {
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $this->filteredQuery($recycled);
        if ((clone $query)->count() > 10000) {
            return $this->fail('导出数据超过 10000 条，请缩小筛选范围', 422);
        }
        $levels = $query->order('sort', 'asc')->order('id', 'asc')->select();
        return $this->ok(array_map(fn (MemberLevel $level): array => $this->levelData($level), $levels->all()));
    }

    private function filteredQuery(bool $recycled)
    {
        $query = $recycled ? MemberLevel::onlyTrashed() : MemberLevel::where('id', '>', 0);
        $name = trim((string) $this->request->get('name', ''));
        $status = $this->request->get('status', null);
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        return $query;
    }

    private function levelsForDelete(bool $onlyTrashed)
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要操作的会员等级', 422);
        }
        $query = $onlyTrashed ? MemberLevel::onlyTrashed() : MemberLevel::where('id', '>', 0);
        $levels = $query->whereIn('id', $ids)->select();
        if (count($levels) !== count($ids)) {
            return $this->fail($onlyTrashed ? '部分会员等级不存在或不在回收站' : '部分会员等级不存在或已在回收站', 404);
        }
        if (Member::withTrashed()->whereIn('level_id', $ids)->count() > 0) {
            return $this->fail('会员等级仍被会员引用，不能删除', 422);
        }
        return $levels;
    }

    private function payload(?MemberLevel $level = null): array
    {
        return [
            'name' => trim((string) $this->request->post('name', $level?->name ?? '')),
            'amount' => trim((string) $this->request->post('amount', $level?->amount ?? '0')),
            'discount' => (int) $this->request->post('discount', $level?->discount ?? 100),
            'thumb' => trim((string) $this->request->post('thumb', $level?->thumb ?? '')),
            'status' => (int) $this->request->post('status', $level?->status ?? 1) === 1 ? 1 : 0,
            'sort' => (int) $this->request->post('sort', $level?->sort ?? 0),
            'description' => trim((string) $this->request->post('description', $level?->description ?? '')),
        ];
    }

    private function validatePayload(array &$data): ?string
    {
        $nameLength = function_exists('mb_strlen') ? mb_strlen($data['name']) : strlen($data['name']);
        $descriptionLength = function_exists('mb_strlen') ? mb_strlen($data['description']) : strlen($data['description']);
        if ($data['name'] === '') {
            return '会员等级名称不能为空';
        }
        if ($nameLength > 30) {
            return '会员等级名称不能超过 30 个字符';
        }
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $data['amount'])) {
            return '等级金额必须是 0 至 99999999.99 的数字，最多两位小数';
        }
        $data['amount'] = number_format((float) $data['amount'], 2, '.', '');
        if ($data['discount'] < 0 || $data['discount'] > 100) {
            return '等级折扣必须在 0 至 100 之间';
        }
        if ($data['sort'] < 0) {
            return '排序不能小于 0';
        }
        if ($descriptionLength > 200) {
            return '等级描述不能超过 200 个字符';
        }
        if (strlen($data['thumb']) > 255) {
            return '缩略图地址不能超过 255 个字符';
        }
        return null;
    }

    private function levelData(MemberLevel $level): array
    {
        return [
            'id' => (int) $level->id,
            'name' => (string) $level->name,
            'amount' => number_format((float) $level->amount, 2, '.', ''),
            'discount' => (int) $level->discount,
            'thumb' => (string) ($level->thumb ?? ''),
            'status' => (int) $level->status,
            'sort' => (int) $level->sort,
            'description' => (string) ($level->description ?? ''),
            'createdAt' => $this->formatTime($level->create_time),
            'updatedAt' => $this->formatTime($level->update_time),
            'deletedAt' => $this->formatTime($level->delete_time),
        ];
    }
}
