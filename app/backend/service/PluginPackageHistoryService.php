<?php

declare(strict_types=1);

namespace app\backend\service;

use app\common\model\PluginOperation;
use app\common\model\PluginVersionHistory;
use app\common\service\AbstractService;
use think\facade\Db;

/**
 * 持久化插件包版本和安装/更新结果，不保存 token 或下载 URL。
 */
final class PluginPackageHistoryService extends AbstractService
{
    public function versions(string $name): array
    {
        return array_map([$this, 'serializeRecord'], PluginVersionHistory::where('plugin_name', $name)->order('id', 'desc')->select()->toArray());
    }

    public function operations(string $name): array
    {
        return array_map([$this, 'serializeRecord'], PluginOperation::where('plugin_name', $name)->order('id', 'desc')->limit(100)->select()->toArray());
    }

    public function record(array $data): void
    {
        Db::transaction(static function () use ($data): void {
            $common = [
                'plugin_name' => (string) ($data['name'] ?? ''),
                'source' => (string) ($data['source'] ?? ''),
                'package_hash' => (string) ($data['package_hash'] ?? ''),
                'status' => 1,
            ];
            PluginOperation::create($common + [
                'operation' => (string) ($data['operation'] ?? ''),
                'stage' => (string) ($data['stage'] ?? 'complete'),
                'progress' => (int) ($data['progress'] ?? (($data['status'] ?? '') === 'success' ? 100 : 0)),
                'from_version' => (string) ($data['from_version'] ?? ''),
                'to_version' => (string) ($data['version'] ?? ''),
                'result' => (string) ($data['status'] ?? 'failed'),
                'error_message' => isset($data['error']) ? substr((string) $data['error'], 0, 2000) : null,
                'recovery_path' => $data['recovery_path'] ?? null,
            ]);
            if (($data['status'] ?? '') !== 'success') {
                return;
            }
            PluginVersionHistory::create($common + [
                'version' => (string) ($data['version'] ?? ''),
                'max_db_version' => (string) ($data['max_db_version'] ?? ''),
                'signature_algorithm' => $data['signature_algorithm'] ?? null,
                'signature_verified' => (int) ($data['signature_verified'] ?? 0),
            ]);
        });
    }

    private function serializeRecord(array $record): array
    {
        $record['createdAt'] = (string) ($record['created_at'] ?? '');
        $record['updatedAt'] = (string) ($record['updated_at'] ?? '');
        unset($record['created_at'], $record['updated_at']);
        return $record;
    }
}
