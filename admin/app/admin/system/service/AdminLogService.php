<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\admin\system\service;

use app\common\service\AbstractService;
use app\common\service\member\MemberInput;
use app\admin\system\model\AdminLog;
use app\admin\authorization\model\Permission;
use app\admin\authorization\service\PermissionResource;
use Webman\Http\Request;
use Webman\Http\Response;

class AdminLogService extends AbstractService
{
    public function save(
        Request $request,
        ?Response $response = null,
        ?int $status = null,
        int $durationMs = 0,
        string $errorMessage = ''
    ): void {
        $pluginCode = trim((string) ($request->plugin ?? ''));
        $appName = 'admin';
        $sourceType = $pluginCode === '' ? 'system' : 'plugin';
        $sourceName = $pluginCode === '' ? 'core' : $pluginCode;
        $resource = PermissionResource::fromRequest($request, $appName);
        $controller = substr($resource['obj'], strlen($appName) + 1);
        $action = $resource['act'];
        $url = $request->path();
        $title = (string) (Permission::where('code', $resource['code'])->value('name') ?: '');
        if ($title === '') {
            $title = $controller . '/' . $action;
        }

        $headers = $request->header();
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower((string) $name), ['authorization', 'proxy-authorization', 'cookie', 'x-csrf-token', 'x-api-key', 'api-key', 'x-auth-token', 'x-access-token'], true)) {
                unset($headers[$name]);
            }
        }
        $responseCode = $response?->getStatusCode() ?? 500;
        $businessCode = null;
        if ($response !== null && str_contains((string) $response->getHeader('Content-Type'), 'application/json')) {
            $body = json_decode($response->rawBody(), true);
            $businessCode = is_array($body) ? ($body['code'] ?? null) : null;
        }
        $succeeded = $status ?? ($responseCode >= 200 && $responseCode < 400
            && ($businessCode === null || in_array($businessCode, [0, 200], true)) ? 1 : 0);
        $requestId = trim((string) $request->header('X-Request-ID', ''));
        if ($requestId === '' || strlen($requestId) > 64) {
            $requestId = bin2hex(random_bytes(16));
        }

        $postData = $this->sanitize($request->post());
        if ($resource['obj'] === 'admin/systemconfig' && array_key_exists('value', $postData)) {
            $postData['value'] = '[REDACTED]';
        }
        $log = new AdminLog();
        $log->forceFill([
            'name' => $title,
            'admin_id' => (int) ($request->context['admin']['id'] ?? 0),
            'username' => (string) ($request->context['admin']['username'] ?? 'Unknown'),
            'url' => $url,
            'app_name' => $appName,
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'controller' => $controller,
            'action' => $action,
            'get_data' => $this->encode($this->sanitize($request->get())),
            'post_data' => $this->encode($postData),
            'header_data' => $this->encode($headers),
            'agent' => (string) $request->header('user-agent', ''),
            'ip' => MemberInput::normalizeIp($request->getRemoteIp()),
            'method' => $request->method(),
            'status' => $succeeded,
            'response_code' => $responseCode,
            'duration_ms' => $durationMs,
            'request_id' => $requestId,
            'error_message' => mb_substr($errorMessage, 0, 500),
        ])->save();
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }

    private function sanitize(array $data): array
    {
        $sensitive = ['password', 'oldpassword', 'newpassword', 'confirmpassword', 'password_confirmation', 'token', 'confirmtoken', 'confirm_token', 'access_token', 'refresh_token', 'secret', 'api_key', 'apikey', 'client_secret', 'access_key', 'secret_key', 'accesskey', 'accesskeyid', 'accesskeysecret', 'secretid', 'secretkey', 'signing_key', 'private_key', 'authorization', 'cookie', 'x-csrf-token', 'csrf_token', '__token__'];
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
