<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class IdentityAuditLog extends TenantScopedIdentityModel
{
    protected $name = 'identity_audit_log';
    protected $casts = ['context' => 'array'];
    protected $fillable = ['tenant_id', 'user_id', 'client_id', 'event_type', 'outcome', 'subject_hash', 'ip_hash', 'context'];
}
