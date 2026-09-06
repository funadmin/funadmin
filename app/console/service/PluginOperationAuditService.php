<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\model\Plugin;
use app\common\model\PluginOperation;
use fun\plugins\PluginOperationRecorder;

/** 持久化插件生命周期阶段、失败与 purge 审计。 */
final class PluginOperationAuditService
{
    private ?PluginOperationRecorder $recorder = null;
    private array $startedTokens = [];

    public function stage(string $name, string $operation, string $token, string $stage, array $context = [], ?string $recoveryPath = null): void
    {
        $this->start($name, $operation, $token, $context);
        $this->recorder()->stage($token, $stage, $recoveryPath);
    }

    public function failure(string $name, string $token, string $stage, \Throwable $exception, array $context = [], ?string $recoveryPath = null): void
    {
        $this->start($name, (string) ($context['operation'] ?? 'lifecycle'), $token, $context);
        $this->recorder()->fail($token, $exception, $stage, $recoveryPath);
    }

    private function start(string $name, string $operation, string $token, array $context): void
    {
        if (isset($this->startedTokens[$token])) {
            return;
        }
        $this->recorder()->start($name, $operation, $token, $context);
        $this->startedTokens[$token] = true;
    }

    private function recorder(): PluginOperationRecorder
    {
        return $this->recorder ??= new PluginOperationRecorder(
            fn (array $data): mixed => PluginOperation::create($data),
            static function (string $name, array $changes): void {
                Plugin::withTrashed()->where('name', $name)->update($changes);
            }
        );
    }

    public function purge(array $audit): void
    {
        $this->create([
            'plugin_name' => (string) $audit['name'],
            'operation' => 'purge',
            'operation_token' => bin2hex(random_bytes(16)),
            'stage' => 'complete',
            'progress' => $audit['result'] === 'success'
                ? PluginOperationRecorder::stagesThrough(0, 'complete')['complete']
                : 0,
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
            'operation_token' => null,
            'status' => 1,
        ]);
    }
}
