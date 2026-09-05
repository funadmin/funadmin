<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\common\service;

use app\backend\model\AdminLog;
use app\backend\model\Permission;
use app\backend\service\PermissionResource;
use think\facade\Session;
use think\Request;
use think\Response;

class AdminLogService extends AbstractService
{
    public function save(Request $request, ?Response $response = null, ?int $status = null): void
    {
        $module = app('http')->getName();
        $controller = (string) $request->controller(true);
        $action = (string) ($request->action() ?: 'index');
        $url = str_replace('.' . config('view.view_suffix'), '', $request->pathinfo());
        $resource = PermissionResource::fromParts($module, $controller, $action);
        $title = (string) (Permission::where('code', $resource['code'])->value('title') ?: '');
        if ($title === '') {
            $title = $controller . '/' . $action;
        }

        $headers = $request->header();
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower((string) $name), ['authorization', 'cookie', 'x-csrf-token'], true)) {
                unset($headers[$name]);
            }
        }
        $responseCode = $response?->getCode() ?? 500;
        $succeeded = $status ?? ($responseCode >= 200 && $responseCode < 400 ? 1 : 0);

        AdminLog::create([
            'title' => $title,
            'admin_id' => (int) Session::get('admin.id', 0),
            'username' => (string) Session::get('admin.username', 'Unknown'),
            'url' => $url,
            'plugins' => 'app',
                'module' => $module,
            'controller' => $controller,
            'action' => $action,
            'get_data' => $this->encode($this->sanitize($request->get())),
            'post_data' => $this->encode($this->sanitize($request->post())),
            'header_data' => $this->encode($headers),
            'agent' => (string) $request->server('HTTP_USER_AGENT', ''),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'status' => $succeeded,
        ]);
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    private function sanitize(array $data): array
    {
        $sensitive = ['password', 'oldpassword', 'newpassword', 'confirmpassword', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'secret', '__token__'];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }
        return $data;
    }

}