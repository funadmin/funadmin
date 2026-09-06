<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\Permission;
use app\common\traits\Jump;
use think\Request;

class AdminAuthorizationService
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
        if ($this->requestUrl === '/' || (!$authenticated && !(new AdminSessionService())->isLogin())) {
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
        $roleScope = new RoleScopeService();
        if (in_array($requestUrl, config('funadmin.auth_super_only_routes', []), true)) {
            if (!$roleScope->isSuperAdmin()) {
                $this->error(lang('Permission Denied'));
            }
            return true;
        }
        if ($roleScope->isSuperAdmin()) {
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

        $resource = $this->nodeResource($url);
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

    private function nodeResource(string $url): ?array
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        if (count($segments) !== 3) {
            return PermissionResource::fromRoute($this->app, $url);
        }

        [$scope, $object, $action] = array_map('strtolower', $segments);
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $scope)
            || !preg_match('/^[a-z][a-z0-9_-]*$/', $object)
            || !preg_match('/^[a-z][a-z0-9_-]*$/', $action)) {
            return null;
        }
        $obj = $scope . '/' . $object;
        return ['obj' => $obj, 'act' => $action, 'code' => $obj . ':' . $action];
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
        $target = $this->routeAliasTarget($requestUrl, $aliases, strtolower((string) ($resource['act'] ?? '')))
            ?? $aliases[strtolower((string) ($resource['code'] ?? ''))]
            ?? null;
        if ($target === null) {
            return $resource;
        }
        return PermissionResource::fromRoute($this->app, (string) $target) ?: $resource;
    }

    private function routeAliasTarget(string $requestUrl, array $aliases, string $action): ?string
    {
        if (isset($aliases[$requestUrl])) {
            return (string) $aliases[$requestUrl];
        }
        if ($action !== 'deletebyid') {
            return null;
        }
        foreach ($aliases as $pattern => $target) {
            if (!str_contains($pattern, ':')) {
                continue;
            }
            $regex = preg_replace('/\\\\:([A-Za-z_][A-Za-z0-9_]*)/', '[^/]+', preg_quote($pattern, '#'));
            if ($regex !== null && preg_match('#^' . $regex . '$#', $requestUrl) === 1) {
                return (string) $target;
            }
        }
        return null;
    }
}
