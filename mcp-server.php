#!/usr/bin/env php
<?php

declare(strict_types=1);

use app\common\service\McpService;
use Psr\Log\AbstractLogger;

require __DIR__ . '/vendor/autoload.php';

final class StderrLogger extends AbstractLogger
{
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $suffix = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        fwrite(STDERR, sprintf("[%s] [%s] %s%s\n", date('Y-m-d H:i:s'), strtoupper((string) $level), $message, $suffix));
    }
}

try {
    $app = new think\App();
    $app->initialize();

    $service = app(McpService::class);
    $service->setLogger(new StderrLogger());
    exit($service->startWithStdio());
} catch (Throwable $exception) {
    fwrite(STDERR, 'MCP服务器启动失败: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
