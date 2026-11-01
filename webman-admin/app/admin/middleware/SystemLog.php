<?php

declare(strict_types=1);

namespace app\admin\middleware;

use app\common\service\AdminLogService;
use support\Log;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use Throwable;

/**
 * 在后台请求完成后统一记录操作审计。
 */
class SystemLog implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $startedAt = microtime(true);
        try {
            $response = $handler($request);
        } catch (Throwable $exception) {
            $this->record($request, null, 0, $startedAt, get_class($exception));
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
            try {
                Log::error('后台操作日志写入失败：' . get_class($exception));
            } catch (Throwable) {
            }
        }
    }
}