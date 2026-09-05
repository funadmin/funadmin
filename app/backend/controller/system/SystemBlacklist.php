<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\common\model\Blacklist;
use think\Response;

/**
 * Admin Web 黑名单管理。
 */
class SystemBlacklist extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $this->filteredQuery($recycled);
        $result = $query->order('id', 'desc')->paginate(['list_rows' => $pageSize, 'page' => $page]);

        return $this->ok($this->paginationData(
            array_map(fn (Blacklist $item): array => $this->itemData($item), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    public function detail(int $id): Response
    {
        $item = Blacklist::withTrashed()->find($id);
        return $item
            ? $this->ok($this->itemData($item))
            : $this->fail('黑名单记录不存在', 404);
    }

    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }

        $item = Blacklist::create($data);
        return $this->ok($this->itemData($item), '创建成功');
    }

    public function update(int $id): Response
    {
        $item = Blacklist::find($id);
        if (!$item) {
            return $this->fail('黑名单记录不存在', 404);
        }
        $data = $this->payload($item);
        if ($error = $this->validatePayload($data)) {
            return $this->fail($error, 422);
        }

        $item->save($data);
        return $this->ok($this->itemData($item), '保存成功');
    }

    public function status(int $id): Response
    {
        $item = Blacklist::find($id);
        if (!$item) {
            return $this->fail('黑名单记录不存在', 404);
        }
        $item->save(['status' => $this->binaryStatus($this->request->post('status', 0))]);
        return $this->ok($this->itemData($item), '状态更新成功');
    }

    public function delete(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要移入回收站的记录', 422);
        }
        $items = Blacklist::whereIn('id', $ids)->select();
        if (count($items) !== count($ids)) {
            return $this->fail('部分黑名单记录不存在或已在回收站', 404);
        }
        foreach ($items as $item) {
            $item->delete();
        }
        return $this->ok(['removed' => count($items)], '已移入回收站');
    }

    public function restore(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要恢复的记录', 422);
        }
        $items = Blacklist::onlyTrashed()->whereIn('id', $ids)->select();
        if (count($items) !== count($ids)) {
            return $this->fail('部分黑名单记录不存在或不在回收站', 404);
        }
        foreach ($items as $item) {
            $item->restore();
        }
        return $this->ok(['restored' => count($items)], '恢复成功');
    }

    public function destroy(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要永久删除的记录', 422);
        }
        $items = Blacklist::onlyTrashed()->whereIn('id', $ids)->select();
        if (count($items) !== count($ids)) {
            return $this->fail('部分黑名单记录不存在或不在回收站', 404);
        }
        foreach ($items as $item) {
            $item->force()->delete();
        }
        return $this->ok(['removed' => count($items)], '永久删除成功');
    }

    public function import(): Response
    {
        $rows = $this->request->post('rows', []);
        if (!is_array($rows) || !$rows) {
            return $this->fail('导入数据不能为空', 422);
        }
        if (count($rows) > 1000) {
            return $this->fail('单次最多导入 1000 条记录', 422);
        }

        $created = 0;
        $errors = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：数据格式错误';
                continue;
            }
            $data = [
                'ip' => trim((string) ($row['ip'] ?? '')),
                'remark' => trim((string) ($row['remark'] ?? '')),
                'status' => $this->binaryStatus($row['status'] ?? 1),
            ];
            if ($error = $this->validatePayload($data)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . $error;
                continue;
            }
            try {
                Blacklist::create($data);
                $created++;
            } catch (\Throwable $exception) {
                $errors[] = '第 ' . ($index + 2) . ' 行：保存失败';
            }
        }

        return $this->ok([
            'created' => $created,
            'skipped' => count($errors),
            'errors' => $errors,
        ], $errors ? '导入完成，部分记录已跳过' : '导入成功');
    }

    public function export(): Response
    {
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = $this->filteredQuery($recycled);
        if ((clone $query)->count() > 10000) {
            return $this->fail('导出数据超过 10000 条，请缩小筛选范围', 422);
        }
        $items = $query->order('id', 'desc')->select();
        return $this->ok(array_map(fn (Blacklist $item): array => $this->itemData($item), $items->all()));
    }

    private function filteredQuery(bool $recycled)
    {
        $query = $recycled ? Blacklist::onlyTrashed() : Blacklist::order('id', 'desc');
        $ip = trim((string) $this->request->get('ip', ''));
        $status = $this->request->get('status', null);
        if ($ip !== '') {
            $query->whereLike('ip', '%' . $ip . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        return $query;
    }

    private function payload(?Blacklist $item = null): array
    {
        return [
            'ip' => trim((string) $this->request->post('ip', $item?->ip ?? '')),
            'remark' => trim((string) $this->request->post('remark', $item?->remark ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $item?->status ?? 1)),
        ];
    }

    private function validatePayload(array $data): ?string
    {
        $ipLength = function_exists('mb_strlen') ? mb_strlen($data['ip']) : strlen($data['ip']);
        $remarkLength = function_exists('mb_strlen') ? mb_strlen($data['remark']) : strlen($data['remark']);
        if ($data['ip'] === '') {
            return 'IP/规则不能为空';
        }
        if ($ipLength > 50) {
            return 'IP/规则不能超过 50 个字符';
        }
        if ($remarkLength > 200) {
            return '备注不能超过 200 个字符';
        }
        return null;
    }

    private function itemData(Blacklist $item): array
    {
        return [
            'id' => (int) $item->id,
            'ip' => (string) $item->ip,
            'remark' => (string) ($item->remark ?? ''),
            'status' => (int) $item->status,
            'createdAt' => $this->formatTime($item->create_time),
            'updatedAt' => $this->formatTime($item->update_time),
            'deletedAt' => $this->formatTime($item->delete_time),
        ];
    }
}
