<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use app\admin\controller\base\AdminApiController;
use app\common\model\Blacklist;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * Admin Web 黑名单管理。
 */
#[RouteGroup('/admin/system/blacklist')]
class SystemBlacklist extends AdminApiController
{

    #[Get('')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = Blacklist::filteredQuery($recycled, trim((string) $this->request->get('ip', '')), $this->request->get('status', null));
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get();

        return $this->ok(data: $this->paginationData(
            $items->map(fn (Blacklist $item): array => $item->toApiData())->all(),
            $total,
            $page,
            $pageSize
        ));
    }

    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        $item = Blacklist::withTrashed()->find($id);
        return $item
            ? $this->ok(data: $item->toApiData())
            : $this->fail(msg: '黑名单记录不存在', code: 404);
    }

    #[Post('')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = Blacklist::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }

        $item = new Blacklist();
        $item->forceFill($data)->save();
        return $this->ok('创建成功', $item->toApiData());
    }

    #[Put('/{id:\d+}')]
    public function update(int $id): Response
    {
        $item = Blacklist::find($id);
        if (!$item) {
            return $this->fail(msg: '黑名单记录不存在', code: 404);
        }
        $data = $this->payload($item);
        if ($error = Blacklist::validateAttributes($data)) {
            return $this->fail(msg: $error, code: 422);
        }

        $item->forceFill($data)->save();
        return $this->ok('保存成功', $item->toApiData());
    }

    #[Post('/{id:\d+}/status')]
    public function status(int $id): Response
    {
        $item = Blacklist::find($id);
        if (!$item) {
            return $this->fail(msg: '黑名单记录不存在', code: 404);
        }
        $item->forceFill(['status' => $this->binaryStatus($this->request->post('status', 0))])->save();
        return $this->ok('状态更新成功', $item->toApiData());
    }

    #[Delete('')]
    public function delete(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要移入回收站的记录', code: 422);
        }
        $items = Blacklist::whereIn('id', $ids)->get();
        if (count($items) !== count($ids)) {
            return $this->fail(msg: '部分黑名单记录不存在或已在回收站', code: 404);
        }
        foreach ($items as $item) {
            $item->delete();
        }
        return $this->ok('已移入回收站', ['removed' => count($items)]);
    }

    #[Post('/restore')]
    public function restore(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要恢复的记录', code: 422);
        }
        $items = Blacklist::onlyTrashed()->whereIn('id', $ids)->get();
        if (count($items) !== count($ids)) {
            return $this->fail(msg: '部分黑名单记录不存在或不在回收站', code: 404);
        }
        foreach ($items as $item) {
            $item->restore();
        }
        return $this->ok('恢复成功', ['restored' => count($items)]);
    }

    #[Delete('/destroy')]
    public function destroy(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail(msg: '请选择要永久删除的记录', code: 422);
        }
        $items = Blacklist::onlyTrashed()->whereIn('id', $ids)->get();
        if (count($items) !== count($ids)) {
            return $this->fail(msg: '部分黑名单记录不存在或不在回收站', code: 404);
        }
        foreach ($items as $item) {
            $item->delete();
        }
        return $this->ok('永久删除成功', ['removed' => count($items)]);
    }

    #[Post('/import')]
    public function import(): Response
    {
        $rows = $this->request->post('rows', []);
        if (!is_array($rows) || !$rows) {
            return $this->fail(msg: '导入数据不能为空', code: 422);
        }
        if (count($rows) > 1000) {
            return $this->fail(msg: '单次最多导入 1000 条记录', code: 422);
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
            if ($error = Blacklist::validateAttributes($data)) {
                $errors[] = '第 ' . ($index + 2) . ' 行：' . $error;
                continue;
            }
            try {
                $item = new Blacklist();
                $item->forceFill($data)->save();
                $created++;
            } catch (\Throwable $exception) {
                $errors[] = '第 ' . ($index + 2) . ' 行：保存失败';
            }
        }

        return $this->ok($errors ? '导入完成，部分记录已跳过' : '导入成功', [
            'created' => $created,
            'skipped' => count($errors),
            'errors' => $errors,
        ]);
    }

    #[Get('/export')]
    public function export(): Response
    {
        $recycled = (int) $this->request->get('recycled', 0) === 1;
        $query = Blacklist::filteredQuery($recycled, trim((string) $this->request->get('ip', '')), $this->request->get('status', null));
        if ((clone $query)->count() > 10000) {
            return $this->fail(msg: '导出数据超过 10000 条，请缩小筛选范围', code: 422);
        }
        $items = $query->orderByDesc('id')->get();
        return $this->ok(data: array_map(fn (Blacklist $item): array => $item->toApiData(), $items->all()));
    }

    private function payload(?Blacklist $item = null): array
    {
        return [
            'ip' => trim((string) $this->request->post('ip', $item?->ip ?? '')),
            'remark' => trim((string) $this->request->post('remark', $item?->remark ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $item?->status ?? 1)),
        ];
    }
}
