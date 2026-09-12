<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\IdentityAuditLog;

/** 写入不含 token、code、secret、密码或原始网络标识的协议审计。 */
final class IdentityAuditService
{
    public function record(int $tenantId, string $event, bool $success, ?int $userId = null, ?int $clientId = null, array $context = [], ?string $subject = null, ?string $ip = null): void
    {
        $safe = array_intersect_key($context, array_flip(['reason', 'grant_type', 'global', 'delivery_id', 'response_status', 'source', 'fields', 'realm']));
        IdentityAuditLog::create([
            'tenant_id' => $tenantId, 'user_id' => $userId, 'client_id' => $clientId,
            'event_type' => substr($event, 0, 64), 'outcome' => $success ? 'success' : 'failure',
            'subject_hash' => $subject !== null && $subject !== '' ? hash('sha256', $subject) : null,
            'ip_hash' => $ip !== null && $ip !== '' ? hash('sha256', $ip) : null,
            'context' => $safe === [] ? null : $safe,
        ]);
    }
}
