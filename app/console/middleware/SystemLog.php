<?php

declare(strict_types=1);

namespace app\console\middleware;

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
        $startedAt = microtime(true);
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->record($request, null, 0, $startedAt, $exception->getMessage());
            throw $exception;
        }

        $this->record($request, $response, null, $startedAt);
        return $response;
    }

    private function record(
        Request $request,
        ?Response $response,
        ?int $status,
        float $startedAt,
        string $errorMessage = ''
    ): void {
        try {
            $durationMs = max(0, (int) round((microtime(true) - $startedAt) * 1000));
            AdminLogService::instance()->save($request, $response, $status, $durationMs, $errorMessage);
        } catch (Throwable $exception) {
            Log::error('后台操作日志写入失败：' . $exception->getMessage());
        }
    }
}