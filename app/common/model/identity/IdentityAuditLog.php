<?php

declare(strict_types=1);

namespace app\common\model\identity;

final class IdentityAuditLog extends TenantScopedIdentityModel
{
    protected $name = 'identity_audit_log';
    protected $json = ['context'];
}
