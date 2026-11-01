<?php

declare(strict_types=1);

namespace app\admin\controller\system;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckAdminApiRole;
use app\admin\model\AdminLog;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\Response;

/**
 * 后台操作日志，只暴露已落库的真实审计字段。
 */
#[Group('system/log/operation')]
class SystemOperationLog extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class];

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
        $result = $query->order('id', 'desc')->paginate(['list_rows' => $pageSize, 'page' => $page]);
        return $this->ok(data: $this->paginationData(
            array_map(fn (AdminLog $log): array => $log->toApiData(), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    #[Get(':id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        $log = AdminLog::scopedQuery()->where('id', $id)->find();
        return $log ? $this->ok(data: $log->toApiData(true)) : $this->fail(msg: '日志不存在或无权访问', code: 404);
    }

    #[Delete('clear')]
    public function clear(): Response
    {
        $removed = AdminLog::scopedQuery()->delete();
        return $this->ok('清空成功', ['removed' => $removed]);
    }

    #[Delete(':id')]
    #[Pattern('id', '\\d+')]
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
        $logs = AdminLog::scopedQuery()->whereIn('id', $ids)->select();
        if (count($logs) !== count($ids)) {
            return $this->fail(msg: '包含不存在或无权删除的日志', code: 403);
        }
        foreach ($logs as $log) {
            $log->delete();
        }
        return $this->ok('删除成功', ['removed' => count($logs)]);
    }

}
