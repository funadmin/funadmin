<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\PluginPurgeCoordinator;

function purgeExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function purgeReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        purgeExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

$calls = [];
$coordinator = new PluginPurgeCoordinator(
    static function (string $name) use (&$calls): object {
        $calls[] = ['load', $name];
        return new class {
            public function purgeData(): bool
            {
                return true;
            }
        };
    },
    static function (array $audit) use (&$calls): void {
        $calls[] = ['audit', $audit];
    }
);
purgeReject(static fn () => $coordinator->purge('demo', 'wrong'), '二次确认');
purgeExpect($calls === [], '确认失败不得加载或执行插件代码');
$coordinator->purge('demo', 'demo');
purgeExpect($calls[0] === ['load', 'demo'], 'purge 必须独立加载显式插件契约');
purgeExpect(($calls[1][1]['operation'] ?? '') === 'purge' && ($calls[1][1]['result'] ?? '') === 'success', 'purge 必须独立审计成功结果');

$failedAudit = [];
$failed = new PluginPurgeCoordinator(
    static fn (): object => new class {
        public function purgeData(): bool
        {
            return false;
        }
    },
    static function (array $audit) use (&$failedAudit): void {
        $failedAudit[] = $audit;
    }
);
purgeReject(static fn () => $failed->purge('demo', 'demo'), '拒绝');
purgeExpect(($failedAudit[0]['operation'] ?? '') === 'purge' && ($failedAudit[0]['result'] ?? '') === 'failed', 'purge 失败也必须独立审计');

echo "plugin purge service tests: PASS\n";
