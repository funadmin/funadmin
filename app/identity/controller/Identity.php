<?php

declare(strict_types=1);

namespace app\identity\controller;

use app\common\model\identity\EnterpriseApplication;
use app\common\model\identity\OAuthAuthorization;
use app\common\model\identity\OAuthAuthorizationScope;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\OAuthScope;
use app\identity\service\AuthorizationTransactionService;
use app\identity\service\IdentityCredentialService;
use app\identity\service\IdentitySessionResolverFactory;
use DomainException;
use think\captcha\facade\Captcha;
use think\facade\Session;
use think\Request;
use think\Response;

/** 独立 Identity 登录与授权交互页面。 */
final class Identity
{
    public function page(Request $request): Response
    {
        return $this->render((string) $request->get('transaction_id', ''));
    }

    public function csrf(): Response
    {
        return json(['csrf_token' => $this->csrfToken()])->header($this->securityHeaders());
    }

    public function captcha(): Response
    {
        return Captcha::create();
    }

    public function login(Request $request): Response
    {
        $transactionId = (string) $request->post('transaction_id', '');
        if (!$this->validCsrf((string) $request->post('csrf_token', ''))) {
            return $this->render($transactionId, '页面已过期，请重新提交', 419);
        }
        try {
            $authorization = $this->authorization($transactionId);
            (new IdentityCredentialService())->login((int) $authorization->tenant_id, trim((string) $request->post('username', '')), (string) $request->post('password', ''), (string) $request->post('captcha', ''), (string) $request->ip());
            return redirect('/identity/interaction?transaction_id=' . rawurlencode($transactionId))->header($this->securityHeaders());
        } catch (DomainException $exception) {
            return $this->render($transactionId, $this->loginError($exception->getMessage()), 422);
        }
    }

    public function decision(Request $request): Response
    {
        if (!$this->validCsrf((string) $request->post('csrf_token', ''))) {
            return $this->render((string) $request->post('transaction_id', ''), '页面已过期，请重新提交', 419);
        }
        $identity = IdentitySessionResolverFactory::make()->resolve($request);
        if ($identity === null) {
            return $this->render((string) $request->post('transaction_id', ''), '登录状态已失效', 401);
        }
        try {
            $result = (new AuthorizationTransactionService())->decide((string) $request->post('transaction_id', ''), $identity, (string) $request->post('approved', '') === '1');
            $parameters = $result['approved'] ? ['code' => $result['code']] : ['error' => 'access_denied'];
            if ($result['state'] !== null) $parameters['state'] = $result['state'];
            return redirect($result['redirect_uri'] . (str_contains($result['redirect_uri'], '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
        } catch (DomainException) {
            return $this->render((string) $request->post('transaction_id', ''), '授权请求无效或已被使用', 400);
        }
    }

    private function render(string $transactionId, string $error = '', int $status = 200): Response
    {
        try {
            $authorization = $this->authorization($transactionId);
        } catch (DomainException) {
            return response('授权请求无效或已过期', 400, $this->securityHeaders());
        }
        $identity = IdentitySessionResolverFactory::make()->resolve(request());
        $service = new AuthorizationTransactionService();
        if ($identity !== null) {
            try {
                $denied = $service->preflight($transactionId, $identity);
                if ($denied !== null) return $this->authorizationRedirect($denied);
                if (!$service->requiresConsent($authorization, $identity)) {
                    return $this->authorizationRedirect($service->decide($transactionId, $identity, true));
                }
            } catch (DomainException $exception) {
                if ($exception->getMessage() === 'access_denied') return response('Forbidden', 403, $this->securityHeaders());
                return response('授权请求无效或已过期', 400, $this->securityHeaders());
            }
        }
        $client = OAuthClient::forTenant((int) $authorization->tenant_id)->where('id', (int) $authorization->client_id)->findOrFail();
        $application = EnterpriseApplication::forTenant((int) $authorization->tenant_id)->where('id', (int) $client->application_id)->findOrFail();
        $brandConfig = $application->brand_config;
        $brand = is_array($brandConfig) ? $brandConfig : json_decode((string) ($brandConfig ?: '{}'), true);
        $brand = is_array($brand) ? $brand : [];
        $scopeIds = OAuthAuthorizationScope::forTenant((int) $authorization->tenant_id)->where('authorization_id', (int) $authorization->id)->column('scope_id');
        $scopeRows = $scopeIds === [] ? [] : OAuthScope::forTenant((int) $authorization->tenant_id)->whereIn('id', $scopeIds)->select()->toArray();
        $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $data = ['escape' => static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'nonce' => $nonce, 'mode' => $identity === null ? 'login' : 'consent', 'transactionId' => $transactionId, 'csrfToken' => $this->csrfToken(), 'error' => $error, 'applicationName' => (string) $application->name, 'brandName' => (string) ($brand['name'] ?? 'FunAdmin Identity'), 'brandMark' => mb_substr((string) ($brand['mark'] ?? 'F'), 0, 2), 'brandHeadline' => (string) ($brand['headline'] ?? '一个身份，连接所有工作。'), 'brandDescription' => (string) ($brand['description'] ?? '安全访问你的企业应用、数据与协作空间。'), 'scopes' => array_map(fn (array $scope): array => ['name' => (string) $scope['name'], 'label' => $this->scopeLabel((string) $scope['name']), 'description' => (string) ($scope['description'] ?: '允许应用使用此权限')], $scopeRows)];
        extract($data, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__) . '/view/interaction.php';
        $html = (string) ob_get_clean();
        return response($html, $status, $this->securityHeaders("default-src 'none'; style-src 'nonce-{$nonce}'; img-src 'self' https: data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'"));
    }

    private function authorization(string $plain): OAuthAuthorization
    {
        if (preg_match('/^[A-Za-z0-9_-]{40,64}$/', $plain) !== 1) throw new DomainException('invalid_request');
        $record = (new OAuthAuthorization())->where('transaction_hash', hash('sha256', $plain))->where('status', 'pending')->find();
        if (!$record || strtotime((string) $record->expires_at) <= time()) throw new DomainException('invalid_request');
        return $record;
    }

    private function csrfToken(): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        Session::set('identity.csrf_token_hash', hash('sha256', $token));
        return $token;
    }

    private function validCsrf(string $token): bool
    {
        $expected = (string) Session::pull('identity.csrf_token_hash', '');
        return $token !== '' && $expected !== '' && hash_equals($expected, hash('sha256', $token));
    }

    private function authorizationRedirect(array $result): Response
    {
        $parameters = ($result['approved'] ?? false) ? ['code' => $result['code']] : ['error' => 'access_denied'];
        if ($result['state'] !== null) $parameters['state'] = $result['state'];
        return redirect($result['redirect_uri'] . (str_contains($result['redirect_uri'], '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
    }

    private function securityHeaders(?string $csp = null): array
    {
        return ['Content-Security-Policy' => $csp ?? "default-src 'none'; frame-ancestors 'none'", 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY'];
    }


    private function loginError(string $error): string
    {
        return match ($error) {'invalid_captcha' => '验证码错误或已过期', 'temporarily_locked' => '尝试次数过多，请稍后再试', default => '用户名或密码错误'};
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {'openid' => '确认你的身份', 'profile' => '基础个人资料', 'email' => '邮箱地址', 'phone' => '手机号码', 'offline_access' => '保持离线访问', default => $scope};
    }
}
