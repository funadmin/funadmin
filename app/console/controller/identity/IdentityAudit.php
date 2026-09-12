<?php

declare(strict_types=1);

namespace app\console\controller\identity;

use app\common\model\identity\IdentityAuditLog;
use app\common\service\identity\AdminIdentityAdapter;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\Response;

#[Group('identity/audit')]
final class IdentityAudit extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('')]
    public function index(): Response
    {
        $query = IdentityAuditLog::forTenant(AdminIdentityAdapter::TENANT_ID)->withoutField('subject_hash,ip_hash')->order('id', 'desc');
        foreach (['eventType' => 'event_type', 'outcome' => 'outcome', 'userId' => 'user_id', 'clientId' => 'client_id'] as $input => $field) {
            $value = trim((string) $this->request->get($input, ''));
            if ($value !== '') $query->where($field, $value);
        }
        $total = (clone $query)->count();
        return $this->ok(data: ['list' => $query->page(max(1, (int) $this->request->get('page', 1)), min(100, max(1, (int) $this->request->get('pageSize', 20))))->select()->toArray(), 'total' => $total]);
    }
}
