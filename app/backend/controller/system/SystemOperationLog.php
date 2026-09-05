<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\model\AdminLog;
use app\backend\service\DataScopeService;
use think\Response;

/**
 * 后台操作日志，只暴露已落库的真实审计字段。
 */
class SystemOperationLog extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class];

    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = $this->scopedQuery();
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
        return $this->ok($this->paginationData(
            array_map(fn (AdminLog $log): array => $this->logData($log), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    public function detail(int $id): Response
    {
        $log = $this->scopedQuery()->where('id', $id)->find();
        return $log ? $this->ok($this->logData($log, true)) : $this->fail('日志不存在或无权访问', 404);
    }

    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail('请选择要删除的日志', 422);
        }
        $logs = $this->scopedQuery()->whereIn('id', $ids)->select();
        if (count($logs) !== count($ids)) {
            return $this->fail('包含不存在或无权删除的日志', 403);
        }
        foreach ($logs as $log) {
            $log->delete();
        }
        return $this->ok(['removed' => count($logs)], '删除成功');
    }

    private function scopedQuery()
    {
        $scope = (new DataScopeService())->resolve();
        if ($scope['all']) {
            return AdminLog::where('id', '>', 0);
        }
        return AdminLog::whereIn('admin_id', (new DataScopeService())->visibleAdminIds() ?: [0]);
    }

    private function logData(AdminLog $log, bool $detail = false): array
    {
        $data = [
            'id' => (int) $log->id,
            'username' => (string) $log->username,
            'appName' => (string) $log->app_name,
            'sourceType' => (string) $log->source_type,
            'sourceName' => (string) $log->source_name,
            'controller' => (string) $log->controller,
            'action' => (string) $log->action,
            'name' => (string) $log->name,
            'method' => strtoupper((string) $log->method),
            'url' => (string) $log->url,
            'ip' => (string) $log->ip,
            'status' => (int) $log->status,
            'responseCode' => (int) $log->response_code,
            'durationMs' => (int) $log->duration_ms,
            'requestId' => (string) $log->request_id,
            'createdAt' => $this->formatTime($log->created_at),
        ];
        if ($detail) {
            $data['getData'] = (string) $log->get_data;
            $data['postData'] = (string) $log->post_data;
            $data['agent'] = (string) $log->agent;
            $data['errorMessage'] = (string) $log->error_message;
        }
        return $data;
    }
}
