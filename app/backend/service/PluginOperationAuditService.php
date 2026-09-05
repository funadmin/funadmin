<?php

declare(strict_types=1);

namespace app\backend\service;

use app\common\model\PluginOperation;

/** 持久化插件生命周期阶段、失败与 purge 审计。 */
final class PluginOperationAuditService
{
    public function stage(string $name, string $operation, string $stage, int $progress, array $context = [], ?string $recoveryPath = null): void
    {
        $this->create([
            'plugin_name' => $name,
            'operation' => $operation,
            'stage' => $stage,
            'progress' => $progress,
            'to_version' => (string) ($context['code_version'] ?? ''),
            'source' => (string) ($context['source'] ?? 'local'),
            'package_hash' => (string) ($context['package_hash'] ?? str_repeat('0', 64)),
            'result' => $stage === 'complete' ? 'success' : 'running',
            'recovery_path' => $recoveryPath,
        ]);
    }

    public function failure(string $name, string $stage, int $progress, \Throwable $exception, array $context = [], ?string $recoveryPath = null): void
    {
        $this->create([
            'plugin_name' => $name,
            'operation' => (string) ($context['operation'] ?? 'lifecycle'),
            'stage' => $stage,
            'progress' => $progress,
            'to_version' => (string) ($context['code_version'] ?? ''),
            'source' => (string) ($context['source'] ?? 'local'),
            'package_hash' => (string) ($context['package_hash'] ?? str_repeat('0', 64)),
            'result' => 'failed',
            'error_message' => substr($exception->getMessage(), 0, 2000),
            'recovery_path' => $recoveryPath,
        ]);
    }

    public function purge(array $audit): void
    {
        $this->create([
            'plugin_name' => (string) $audit['name'],
            'operation' => 'purge',
            'stage' => 'complete',
            'progress' => $audit['result'] === 'success' ? 100 : 0,
            'result' => (string) $audit['result'],
            'error_message' => $audit['error'] ?? null,
        ]);
    }

    private function create(array $data): void
    {
        PluginOperation::create($data + [
            'from_version' => '',
            'to_version' => '',
            'source' => 'local',
            'package_hash' => str_repeat('0', 64),
            'error_message' => null,
            'recovery_path' => null,
            'status' => 1,
        ]);
    }
}
