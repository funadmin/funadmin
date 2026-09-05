<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 使用同一操作令牌记录生命周期阶段，并同步插件失败恢复状态。 */
final class PluginOperationRecorder
{
    private const PROGRESS = [
        'validate' => 5,
        'deploy' => 20,
        'hooks' => 35,
        'migration' => 55,
        'resources' => 75,
        'permissions' => 90,
        'complete' => 100,
    ];

    private array $operations = [];

    public function __construct(
        private readonly mixed $persistOperation,
        private readonly mixed $updatePlugin
    ) {
    }

    public function start(string $name, string $operation, string $token, array $context = []): void
    {
        if ($token === '' || isset($this->operations[$token])) {
            throw new RuntimeException('插件生命周期操作令牌无效或重复');
        }
        $this->operations[$token] = [
            'plugin_name' => $name,
            'operation' => $operation,
            'operation_token' => $token,
            'context' => $context,
            'progress' => 0,
        ];
    }

    public function stage(string $token, string $stage, ?string $recoveryPath = null): void
    {
        $operation = $this->operation($token);
        if (!isset(self::PROGRESS[$stage])) {
            throw new RuntimeException('未知插件生命周期阶段：' . $stage);
        }
        $progress = self::PROGRESS[$stage];
        if ($progress <= $operation['progress']) {
            throw new RuntimeException('插件生命周期阶段顺序错误：' . $stage);
        }
        $this->operations[$token]['progress'] = $progress;
        ($this->persistOperation)($this->row($operation, $stage, $progress, $stage === 'complete' ? 'success' : 'running', null, $recoveryPath));
        if ($stage === 'complete') {
            ($this->updatePlugin)($operation['plugin_name'], [
                'last_error' => null,
                'error_stage' => null,
                'recovery_path' => null,
            ]);
        }
    }

    public function fail(string $token, \Throwable $exception, string $stage, ?string $recoveryPath = null): void
    {
        $operation = $this->operation($token);
        $progress = (int) $operation['progress'];
        ($this->persistOperation)($this->row($operation, $stage, $progress, 'failed', $exception->getMessage(), $recoveryPath));
        ($this->updatePlugin)($operation['plugin_name'], [
            'status' => 0,
            'last_error' => substr($exception->getMessage(), 0, 2000),
            'error_stage' => $stage,
            'recovery_path' => $recoveryPath,
        ]);
    }

    private function operation(string $token): array
    {
        if (!isset($this->operations[$token])) {
            throw new RuntimeException('插件生命周期操作令牌不存在');
        }
        return $this->operations[$token];
    }

    private function row(array $operation, string $stage, int $progress, string $result, ?string $error, ?string $recoveryPath): array
    {
        $context = (array) $operation['context'];
        return [
            'plugin_name' => $operation['plugin_name'],
            'operation' => $operation['operation'],
            'operation_token' => $operation['operation_token'],
            'stage' => $stage,
            'progress' => $progress,
            'from_version' => (string) ($context['from_version'] ?? ''),
            'to_version' => (string) ($context['code_version'] ?? ''),
            'source' => (string) ($context['source'] ?? 'local'),
            'package_hash' => (string) ($context['package_hash'] ?? str_repeat('0', 64)),
            'result' => $result,
            'error_message' => $error === null ? null : substr($error, 0, 2000),
            'recovery_path' => $recoveryPath,
            'status' => 1,
        ];
    }
}
