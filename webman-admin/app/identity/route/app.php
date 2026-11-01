<?php

declare(strict_types=1);

use think\facade\Route;
use app\identity\middleware\IdentityBearerMiddleware;
use app\identity\middleware\RequiredScopeMiddleware;
use think\middleware\Throttle;

// 协议限流键只保留 endpoint、公开 'client_id' 与 IP 的不可逆摘要，不记录 secret/token；IP 使用 '__IP__' 标记。
$oauthThrottleKey = static function (Throttle $throttle, \think\Request $request): string {
    $clientId = (string) $request->post('client_id', $request->get('client_id', ''));
    $authorization = (string) $request->header('authorization', '');
    if ($clientId === '' && str_starts_with(strtolower($authorization), 'basic ')) {
        $decoded = base64_decode(substr($authorization, 6), true);
        if (is_string($decoded) && str_contains($decoded, ':')) {
            [$clientId] = explode(':', $decoded, 2);
        }
    }

    $endpoint = strtolower((string) $request->action());
    $clientKey = hash('sha256', $clientId !== '' ? $clientId : 'anonymous');
    $ipKey = hash('sha256', (string) $request->ip());
    return 'identity:' . $endpoint . ':client_id:' . $clientKey . ':' . '__IP__' . ':' . $ipKey;
};

// Identity 交互入口与 OAuth/OIDC 协议端点并存。
Route::get('interaction', 'Identity/page');
Route::get('interaction/login', 'Identity/page');
Route::get('interaction/consent', 'Identity/page');
Route::get('csrf', 'Identity/csrf');
Route::get('captcha', 'Identity/captcha');
Route::post('interaction/login', 'Identity/login')->middleware(Throttle::class, [
    'visit_method' => ['POST'],
    'visit_rate' => '10/m',
    'key' => $oauthThrottleKey,
]);
Route::post('interaction/consent', 'Identity/decision')->middleware(Throttle::class, [
    'visit_method' => ['POST'],
    'visit_rate' => '10/m',
    'key' => $oauthThrottleKey,
]);
Route::get('logout', 'Logout/end')->middleware(Throttle::class, [
    'visit_method' => ['GET'],
    'visit_rate' => '10/m',
    'key' => $oauthThrottleKey,
]);
Route::post('logout', 'Logout/end')->middleware(Throttle::class, [
    'visit_method' => ['POST'],
    'visit_rate' => '10/m',
    'key' => $oauthThrottleKey,
]);
Route::get('.well-known/openid-configuration', 'Metadata/discovery');
Route::get('.well-known/oauth-authorization-server', 'Metadata/oauth');
Route::get('.well-known/jwks.json', 'Metadata/jwks');
Route::get('authorize', 'OAuth/authorize')->middleware(Throttle::class, [
    'visit_method' => ['GET'],
    'visit_rate' => '20/m',
    'key' => $oauthThrottleKey,
]);
Route::post('decision', 'OAuth/decision')->middleware(Throttle::class, [
    'visit_method' => ['POST'],
    'visit_rate' => '10/m',
    'key' => $oauthThrottleKey,
]);
Route::post('token', 'OAuth/token')->middleware(Throttle::class, [
    'visit_method' => ['POST'],
    'visit_rate' => '30/m',
    'key' => $oauthThrottleKey,
]);
Route::post('revoke', 'OAuth/revoke')->middleware(Throttle::class, [
    'visit_method' => ['POST'],
    'visit_rate' => '30/m',
    'key' => $oauthThrottleKey,
]);
Route::post('introspect', 'OAuth/introspect')->middleware(Throttle::class, [
    'visit_method' => ['POST'],
    'visit_rate' => '60/m',
    'key' => $oauthThrottleKey,
]);
Route::get('userinfo', 'OAuth/userinfo')->middleware(Throttle::class, [
    'visit_method' => ['GET'],
    'visit_rate' => '60/m',
    'key' => $oauthThrottleKey,
]);
Route::get('status', 'Status/index');
Route::get('resource/identity', static function (\think\Request $request) {
    return json([
        'tenant_id' => $request->tenant_id,
        'oauth_client_id' => $request->oauth_client_id,
        'oauth_scopes' => $request->oauth_scopes,
        'subject_type' => $request->subject_type,
        'identity_user_id' => $request->identity_user_id ?? null,
        'member_id' => $request->member_id ?? null,
    ]);
})->middleware(IdentityBearerMiddleware::class)->middleware(RequiredScopeMiddleware::class, ['identity.read'], 'AND');
