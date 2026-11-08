<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

use DomainException;
use Webman\Http\Request;

class AdminAuthorizationService
{
    private readonly ?Request $request;
    private readonly string $app;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
        $this->app = 'admin';
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
     * 按资源路径判断（"模块/资源/操作" 或旧的 "admin/控制器/操作"）。
     * 仍存在的资源（能力权限等）直接按 obj/act 判断；已改名为推导码的旧接口资源按冻结映射换算为新权限码，须全部满足。
     */
    public function nodeAccess(string $url): bool
    {
        $adminId = (int) ($this->currentRequest()->context['admin']['id'] ?? 0);
        if ($adminId <= 0) {
            return false;
        }
        if ($adminId === (int) config('auth.super_admin_id', 1)) {
            return true;
        }
        // 注册动作以权限码声明（如生成的自定义动作 generated:{实体}:action-{名称}），与 can() 同一判断。
        if (preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D', $url) === 1) {
            return $this->allowed($adminId, $url);
        }
        $resource = $this->nodeResource($url);
        if ($resource === null) {
            return false;
        }
        $exists = \app\admin\authorization\model\Permission::where('obj', strtolower($resource['obj']))->where('act', strtolower($resource['act']))
            ->where('status', 1)->whereNull('deleted_at')->exists();
        if ($exists) {
            return CasbinService::instance()->enforceAdmin($adminId, $resource['obj'], $resource['act']);
        }
        // 旧资源原先是一个权限：换算后要求它拆分出的全部推导码都已授予（升级迁移会一并授予），只授予其中一个不放行。
        $codes = self::legacyMap()[strtolower($resource['obj'] . '|' . $resource['act'])] ?? [];
        foreach ($codes as $code) {
            if (!$this->allowed($adminId, $code)) {
                return false;
            }
        }
        return $codes !== [];
    }

    private function allowed(int $adminId, string $code): bool
    {
        $permission = \app\admin\authorization\model\Permission::where('code', $code)->where('status', 1)->whereNull('deleted_at')->first(['obj', 'act']);
        return $permission !== null && CasbinService::instance()->enforceAdmin($adminId, (string) $permission->obj, (string) $permission->act);
    }

    /** @return array<string, list<string>> 旧接口资源 obj|act → 推导码（升级时冻结） */
    private static function legacyMap(): array
    {
        static $map = null;
        return $map ??= require base_path() . '/database/permission-maps/host-per-method.php';
    }

    private function nodeResource(string $url): ?array
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        if (count($segments) !== 3) {
            return PermissionResource::fromRoute($this->app, $url);
        }

        [$scope, $object, $action] = array_map('strtolower', $segments);
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $scope)
            || !preg_match('/^[a-z][a-z0-9_.-]*$/', $object)
            || !preg_match('/^[a-z][a-z0-9_-]*$/', $action)) {
            return null;
        }
        $obj = $scope . '/' . $object;
        return ['obj' => $obj, 'act' => $action, 'code' => $obj . ':' . $action];
    }
}
