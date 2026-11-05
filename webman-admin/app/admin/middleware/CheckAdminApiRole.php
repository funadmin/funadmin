<?php

namespace app\admin\middleware;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\AuthorizationService;
use app\admin\authorization\service\PermissionResource;
use app\admin\authorization\model\Permission;
use app\admin\common\http\AdminResponse;
use DomainException;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * Admin Web API 登录与 Casbin 权限校验。
 */
class CheckAdminApiRole implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        unset($request->context['admin'], $request->context['admin_permissions'], $request->context['admin_csrf_token']);
        if (preg_match('/^app\\\\admin\\\\(?:plugin\\\\business\\\\[a-z][a-z0-9]*\\\\controller|controller\\\\plugin\\\\[a-z][a-z0-9]*)\\\\/', (string) $request->controller) === 1) {
            return (new \app\admin\plugin\middleware\CheckPluginPermission())->process($request, $handler);
        }
        try {
            $cookie = (string) $request->cookie(BrowserAuthService::COOKIE, '');
            $auth = new BrowserAuthService();
            $admin = $auth->user($cookie);
            $authorization = new AuthorizationService();
            $info = $authorization->userInfo($admin);
            $resource = PermissionResource::fromRequest($request);
            $permission = Permission::where('obj', $resource['obj'])->where('act', $resource['act'])
                ->where('status', 1)->whereNull('deleted_at')->first();
            $public = $permission !== null && (bool) $permission->is_public;
            $profile = $resource['obj'] === 'admin/adminprofile';
            if (!in_array('*', $info['permissions'], true) && !$public && !$profile
                && ($permission === null || !in_array((string) $permission->code, $info['permissions'], true))) {
                throw new DomainException('没有访问权限', 403);
            }
            $request->context['admin'] = $admin;
            $request->context['admin_permissions'] = $info['permissions'];
            $request->context['admin_csrf_token'] = $auth->bootstrap($cookie)['csrfToken'];
        } catch (DomainException $error) {
            return AdminResponse::create($error->getMessage(), null, $error->getCode());
        }
        return $handler($request);
    }
}
