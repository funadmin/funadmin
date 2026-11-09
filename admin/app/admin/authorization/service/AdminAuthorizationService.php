<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use DomainException;
use Webman\Http\Request;

class AdminAuthorizationService
{
    private readonly ?Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    private function currentRequest(): Request
    {
        return $this->request ?? request();
    }

    public function roleAccess(bool $authenticated = false): bool
    {
        $request = $this->currentRequest();
        $adminId = (int) ($request->context['admin']['id'] ?? 0);
        if ($adminId <= 0) {
            throw new DomainException('请先登录', 401);
        }
        if (config('funadmin.isDemo', false) && !in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            throw new DomainException('演示环境不允许修改数据', 403);
        }
        if ($adminId === (int) config('auth.super_admin_id', 1)) {
            return true;
        }
        $code = PermissionCode::derive((string) ($request->controller ?? ''), (string) ($request->action ?: 'index'));
        if ($code === null || !$this->allowed($adminId, $code)) {
            throw new DomainException('没有访问权限', 403);
        }
        return true;
    }

    /** 当前管理员是否拥有某个权限码（接口推导码或能力权限码）。 */
    public function can(string $code): bool
    {
        $adminId = (int) ($this->currentRequest()->context['admin']['id'] ?? 0);
        if ($adminId <= 0) {
            return false;
        }
        if ($adminId === (int) config('auth.super_admin_id', 1)) {
            return true;
        }
        return $this->allowed($adminId, $code);
    }

    /**
     * 业务运行时的权限检查器（表单运行码 form:data:*、生成模块码 business:{实体}:*、注册动作码等）：
     * 与 can() 同一判断（超级管理员放行）；非超级管理员只接受 {模块}:{资源}:{操作} 格式的权限码。
     */
    public function nodeAccess(string $code): bool
    {
        $adminId = (int) ($this->currentRequest()->context['admin']['id'] ?? 0);
        if ($adminId <= 0) {
            return false;
        }
        if ($adminId === (int) config('auth.super_admin_id', 1)) {
            return true;
        }
        return preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D', $code) === 1 && $this->allowed($adminId, $code);
    }

    private function allowed(int $adminId, string $code): bool
    {
        $permission = \app\admin\authorization\model\Permission::where('code', $code)->where('status', 1)->whereNull('deleted_at')->first(['obj', 'act']);
        return $permission !== null && CasbinService::instance()->enforceAdmin($adminId, (string) $permission->obj, (string) $permission->act);
    }
}
