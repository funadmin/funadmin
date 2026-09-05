<?php

declare(strict_types=1);

namespace app\backend\service;

use app\common\model\PluginOperation;
use app\common\model\PluginVersionHistory;
use app\common\service\AbstractService;

/**
 * 持久化插件包版本和安装/更新结果，不保存 token 或下载 URL。
 */
final class PluginPackageHistoryService extends AbstractService
{
    public function record(array $data): void
    {
        $now = time();
        $common = [
            'plugin_name' => (string) ($data['name'] ?? ''),
            'source' => (string) ($data['source'] ?? ''),
            'package_hash' => (string) ($data['package_hash'] ?? ''),
            'status' => 1,
            'create_time' => $now,
        ];
        PluginOperation::create($common + [
            'operation' => (string) ($data['operation'] ?? ''),
            'from_version' => (string) ($data['from_version'] ?? ''),
            'to_version' => (string) ($data['version'] ?? ''),
            'result' => (string) ($data['status'] ?? 'failed'),
            'error_message' => isset($data['error']) ? substr((string) $data['error'], 0, 2000) : null,
        ]);
        if (($data['status'] ?? '') !== 'success') {
            return;
        }
        PluginVersionHistory::create($common + [
            'version' => (string) ($data['version'] ?? ''),
            'signature_algorithm' => $data['signature_algorithm'] ?? null,
            'signature_verified' => (int) ($data['signature_verified'] ?? 0),
        ]);
    }
}
