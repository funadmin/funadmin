<?php

declare(strict_types=1);

namespace app\backend\service;

use app\backend\model\Permission;
use app\common\traits\Jump;
use think\Request;

final class AdminAuthorizationService
{
    use Jump;

    private Request $request;
    private string $app;
    private string $requestUrl;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? request();
        $this->app = app('http')->getName();
        $this->requestUrl = $this->normalizeRequestUrl($this->request->pathinfo());
    }

    public function roleAccess(bool $authenticated = false): bool
    {
        $requiresLogin = static fn (bool $authenticated = false): bool => !$authenticated;
        if ($this->requestUrl === '/' || ($requiresLogin($authenticated) && !(new AdminSessionService())->isLogin())) {
            $this->error(lang('Please Login First'));
        }

        $cfg = config('funadmin');
        if (isset($cfg['auth_on']) && $cfg['auth_on'] == false) {
            return true;
        }
        if ($this->request->isPost() && $cfg['isDemo'] == 1) {
            $this->error(lang('Demo is not allow to change data'));
        }

        $requestUrl = strtolower(trim($this->requestUrl, '/'));
        if (in_array($requestUrl, config('funadmin.auth_login_only_routes', []), true)) {
            return true;
        }
        if (in_array($requestUrl, config('funadmin.auth_super_only_routes', []), true)) {
            if (!(new RoleScopeService())->isSuperAdmin()) {
                $this->error(lang('Permission Denied'));
            }
            return true;
        }
        if ((new RoleScopeService())->isSuperAdmin()) {
            return true;
        }

        $resource = PermissionResource::fromRequest($this->request, $this->app);
        $resource = $this->aliasResource($requestUrl, $resource);
        if ($this->isLoginOnlyResource($resource)) {
            return true;
        }
        if (!CasbinService::instance()->enforceAdmin((int) session('admin.id'), $resource['obj'], $resource['act'])) {
            $this->error(lang('Permission Denied'));
        }
        return true;
    }

    public function nodeAccess(string $url): bool
    {
        $cfg = config('funadmin');
        if (isset($cfg['auth_on']) && $cfg['auth_on'] == false) {
            return true;
        }
        if ((new RoleScopeService())->isSuperAdmin()) {
            return true;
        }

        $resource = PermissionResource::fromRoute($this->app, $url);
        if (!$resource) {
            return false;
        }
        if ($this->isLoginOnlyResource($resource)) {
            return true;
        }
        return CasbinService::instance()->enforceAdmin(
            (int) session('admin.id'),
            $resource['obj'],
            $resource['act']
        );
    }

    private function normalizeRequestUrl(string $requestUrl): string
    {
        $suffix = trim((string) config('view.view_suffix'));
        if ($suffix !== '' && str_ends_with(strtolower($requestUrl), '.' . strtolower($suffix))) {
            $requestUrl = substr($requestUrl, 0, -strlen($suffix) - 1);
        }
        return trim($requestUrl, '/');
    }

    private function isLoginOnlyResource(array $resource): bool
    {
        $codes = db_cache('auth-login-only-resource-codes', static function (): array {
            $result = [];
            $permissions = Permission::where('status', 1)
                ->where('is_public', 1)
                ->where('code', '<>', '')
                ->column('code');
            foreach ($permissions as $code) {
                $result[(string) $code] = true;
            }
            return $result;
        });
        return isset($codes[$resource['code']]);
    }

    private function aliasResource(string $requestUrl, array $resource): array
    {
        $aliases = array_change_key_case(config('funadmin.auth_route_aliases', []), CASE_LOWER);
        $target = $aliases[$requestUrl] ?? $aliases[strtolower((string) ($resource['code'] ?? ''))] ?? null;
        if ($target === null) {
            return $resource;
        }
        return PermissionResource::fromRoute($this->app, (string) $target) ?: $resource;
    }
}
