<?php

declare(strict_types=1);

namespace app\backend\middleware;

use app\common\service\AdminLogService;
use Closure;
use think\facade\Log;
use think\Request;
use think\Response;
use Throwable;

/**
 * 在后台请求完成后统一记录操作审计。
 */
class SystemLog
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->record($request, null, 0);
            throw $exception;
        }

        $this->record($request, $response);
        return $response;
    }

    private function record(Request $request, ?Response $response, ?int $status = null): void
    {
        try {
            AdminLogService::instance()->save($request, $response, $status);
        } catch (Throwable $exception) {
            Log::error('后台操作日志写入失败：' . $exception->getMessage());
        }
    }
}