<?php

declare(strict_types=1);

namespace app\admin\identity\controller;

use app\common\model\identity\IdentityAuditLog;
use app\common\service\identity\AdminIdentityAdapter;
use app\admin\controller\base\AdminApiController;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/admin/identity/audit')]
final class IdentityAudit extends AdminApiController
{
    #[Get('')]
    public function index(): Response
    {
        $query = IdentityAuditLog::forTenant(AdminIdentityAdapter::TENANT_ID)->orderByDesc('id');
        foreach (['eventType' => 'event_type', 'outcome' => 'outcome', 'userId' => 'user_id', 'clientId' => 'client_id'] as $input => $field) {
            $value = trim((string) $this->request->get($input, ''));
            if ($value !== '') $query->where($field, $value);
        }
        $total = (clone $query)->count();
        $rows = $query->forPage($this->page(), min(100, max(1, (int) $this->request->get('pageSize', 20))))->get();
        foreach ($rows as $row) $row->makeHidden(['subject_hash', 'ip_hash']);
        return $this->ok(data: ['list' => $rows->toArray(), 'total' => $total]);
    }
}
