<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\system\model\AdminLog;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

/**
 * 后台操作日志，只暴露已落库的真实审计字段。
 */
#[RouteGroup('/admin/system/log/operation')]
class SystemOperationLog extends AdminApiController
{

    #[Get('')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = AdminLog::scopedQuery();
        $username = trim((string) $this->request->get('username', ''));
        $appName = trim((string) $this->request->get('appName', ''));
        $sourceType = trim((string) $this->request->get('sourceType', ''));
        $sourceName = trim((string) $this->request->get('sourceName', ''));
        $status = $this->request->get('status', null);
        $startTime = trim((string) $this->request->get('startTime', ''));
        $endTime = trim((string) $this->request->get('endTime', ''));
        if ($username !== '') {
            $query->whereLike('username', '%' . $username . '%');
        }
        if ($appName !== '') {
            $query->where('app_name', $appName);
        }
        if ($sourceType !== '') {
            $query->where('source_type', $sourceType);
        }
        if ($sourceName !== '') {
            $query->where('source_name', $sourceName);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $this->binaryStatus($status));
        }
        if ($startTime !== '' && strtotime($startTime) !== false) {
            $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime($startTime)));
        }
        if ($endTime !== '' && strtotime($endTime) !== false) {
            $query->where('created_at', '<=', date('Y-m-d H:i:s', strtotime($endTime)));
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->forPage($page, $pageSize)->get();
        return $this->ok(data: $this->paginationData(
            $items->map(fn (AdminLog $log): array => $log->toApiData())->all(),
            $total,
            $page,
            $pageSize
        ));
    }

    #[Get('/{id:\d+}')]
    public function detail(int $id): Response
    {
        $log = AdminLog::scopedQuery()->where('id', $id)->first();
        return $log ? $this->ok(data: $log->toApiData(true)) : $this->fail(msg: '日志不存在或无权访问', code: 404);
    }

    #[Delete('/clear')]
    public function clear(): Response
    {
        $removed = AdminLog::scopedQuery()->delete();
        return $this->ok('清空成功', ['removed' => $removed]);
    }

    #[Delete('/{id:\d+}')]
    public function deleteById(int $id): Response
    {
        return $this->delete($id);
    }

    #[Delete('')]
    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的日志', code: 422);
        }
        $logs = AdminLog::scopedQuery()->whereIn('id', $ids)->get();
        if (count($logs) !== count($ids)) {
            return $this->fail(msg: '包含不存在或无权删除的日志', code: 403);
        }
        foreach ($logs as $log) {
            $log->delete();
        }
        return $this->ok('删除成功', ['removed' => count($logs)]);
    }

}
