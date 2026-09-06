<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\model\PluginOperation;
use app\common\model\PluginVersionHistory;
use app\common\service\AbstractService;
use RuntimeException;
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

    public function package(string $name, int $id): array
    {
        $record = PluginVersionHistory::where('plugin_name', $name)->where('id', $id)->find();
        $path = (string) ($record?->package_path ?? '');
        $runtimeRoot = realpath(runtime_path());
        $realPath = $path === '' ? false : realpath($path);
        if (!$record || $runtimeRoot === false || $realPath === false || !is_file($realPath)
            || !str_starts_with($realPath, $runtimeRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('历史插件包不存在或不可用');
        }
        return ['path' => $realPath, 'version' => (string) $record->version];
    }

    public function recoveryInfo(string $name): array
    {
        $operation = PluginOperation::where('plugin_name', $name)->where('result', '<>', 'success')->order('id', 'desc')->find();
        return [
            'available' => $operation !== null,
            'stage' => (string) ($operation?->stage ?? ''),
            'message' => $operation
                ? '请保持插件禁用，查看错误详情；可重新上传可信 ZIP 或重部署历史版本，必要时联系管理员按服务端日志恢复。'
                : '当前没有待处理的插件恢复记录。',
        ];
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
                'package_path' => $data['package_path'] ?? null,
                'signature_algorithm' => $data['signature_algorithm'] ?? null,
                'signature_verified' => (int) ($data['signature_verified'] ?? 0),
            ]);
        });
    }

    private function serializeRecord(array $record): array
    {
        $record['createdAt'] = (string) ($record['created_at'] ?? '');
        $record['updatedAt'] = (string) ($record['updated_at'] ?? '');
        $record['downloadable'] = trim((string) ($record['package_path'] ?? '')) !== '';
        unset($record['created_at'], $record['updated_at'], $record['package_path'], $record['recovery_path']);
        return $record;
    }
}
