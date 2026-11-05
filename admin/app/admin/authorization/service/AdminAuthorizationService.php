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
        $resource = PermissionResource::fromRequest($request, $this->app);
        $resource = $this->aliasResource(strtolower($this->normalizeRequestUrl($request->path())), $resource);
        if (!CasbinService::instance()->enforceAdmin($adminId, $resource['obj'], $resource['act'])) {
            throw new DomainException('没有访问权限', 403);
        }
        return true;
    }

    public function nodeAccess(string $url): bool
    {
        $adminId = (int) ($this->currentRequest()->context['admin']['id'] ?? 0);
        if ($adminId <= 0) {
            return false;
        }
        if ($adminId === (int) config('auth.super_admin_id', 1)) {
            return true;
        }
        $resource = $this->nodeResource($url);
        return $resource !== null && CasbinService::instance()->enforceAdmin($adminId, $resource['obj'], $resource['act']);
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

    private function normalizeRequestUrl(string $requestUrl): string
    {
        $suffix = trim((string) config('view.view_suffix'));
        if ($suffix !== '' && str_ends_with(strtolower($requestUrl), '.' . strtolower($suffix))) {
            $requestUrl = substr($requestUrl, 0, -strlen($suffix) - 1);
        }
        return trim($requestUrl, '/');
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
