<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;

Route::disableDefaultRoute();
Route::group('/identity', static function (): void {
    foreach ([
        ['GET', '/.well-known/openid-configuration', 'Metadata', 'discovery'],
        ['GET', '/.well-known/oauth-authorization-server', 'Metadata', 'oauth'],
        ['GET', '/.well-known/jwks.json', 'Metadata', 'jwks'],
        ['GET', '/status', 'Status', 'index'],
        ['GET', '/interaction', 'Identity', 'page'],
        ['GET', '/interaction/login', 'Identity', 'page'],
        ['GET', '/interaction/consent', 'Identity', 'page'],
        ['GET', '/csrf', 'Identity', 'csrf'],
        ['GET', '/captcha', 'Identity', 'captcha'],
        ['POST', '/interaction/login', 'Identity', 'login'],
        ['POST', '/interaction/consent', 'Identity', 'decision'],
        ['GET', '/logout', 'Logout', 'end'],
        ['POST', '/logout', 'Logout', 'end'],
        ['GET', '/authorize', 'OAuth', 'authorize'],
        ['POST', '/decision', 'OAuth', 'decision'],
        ['POST', '/token', 'OAuth', 'token'],
        ['POST', '/revoke', 'OAuth', 'revoke'],
        ['POST', '/introspect', 'OAuth', 'introspect'],
        ['GET', '/userinfo', 'OAuth', 'userinfo'],
    ] as [$method, $path, $controller, $action]) {
        Route::add([$method], $path, ['app\\identity\\controller\\' . $controller, $action]);
    }
    Route::get('/resource/identity', static function (\support\Request $request) {
        return json([
            'tenant_id' => $request->tenant_id,
            'oauth_client_id' => $request->oauth_client_id,
            'oauth_scopes' => $request->oauth_scopes,
            'subject_type' => $request->subject_type,
            'identity_user_id' => $request->identity_user_id,
            'member_id' => $request->member_id,
        ]);
    })->middleware([
        \app\identity\middleware\IdentityBearerMiddleware::class,
        [\app\identity\middleware\RequiredScopeMiddleware::class, [['identity.read'], 'AND']],
    ]);
});
Route::get('/health', static fn () => json(['status' => 'ok', 'service' => 'webman-admin']));
$adminWeb = static fn () => response()->withFile(public_path('admin-web/index.html'))->withHeader('Cache-Control', 'no-cache');
Route::get('/admin-web', $adminWeb);
Route::get('/admin-web/', $adminWeb);
Route::fallback(static function (\support\Request $request) {
    $matched = Route::dispatch($request->method(), $request->path());
    if ($matched[0] === \FastRoute\Dispatcher::METHOD_NOT_ALLOWED) {
        return json(['code' => 405, 'message' => '请求方法不允许', 'data' => null])->withStatus(405)->withHeader('Allow', implode(', ', $matched[1]));
    }
    return json(['code' => 404, 'message' => '接口不存在', 'data' => null])->withStatus(404);
});
