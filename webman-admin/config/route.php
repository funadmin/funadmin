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
Route::get('/plugins/hooks/frontend', [\app\common\plugin\controller\FrontendHooks::class, 'index']);
Route::get('/admin/plugins/hooks/frontend', [\app\common\plugin\controller\FrontendHooks::class, 'index']);
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
        $route = Route::add([$method], $path, ['app\\identity\\controller\\' . $controller, $action]);
        if (!in_array($controller, ['Metadata', 'Status'], true) && !in_array($action, ['page', 'csrf', 'captcha'], true)) {
            $route->middleware(\app\identity\middleware\IdentityProtocolThrottle::class);
        }
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
        static fn () => new \app\identity\middleware\RequiredScopeMiddleware(['identity.read'], 'AND'),
    ]);
});
Route::group('/api/v2', static function (): void {
    Route::post('/token', [\app\api\controller\v2\Token::class, 'build']);
    Route::post('/token/refresh', [\app\api\controller\v2\Token::class, 'refresh']);
    Route::get('/member', [\app\api\controller\v2\Member::class, 'show'])
        ->middleware(\app\common\middleware\MApi::class);
});
Route::group('/market/api/v3', static function (): void {
    foreach (['login', 'refresh', 'register', 'me', 'password', 'logout'] as $action) {
        Route::post('/auth/' . $action, [\app\market\controller\Auth::class, $action]);
    }
    Route::put('/auth/profile', [\app\market\controller\Auth::class, 'profile']);
    foreach (['' => 'search', '/categories' => 'categories', '/{code:[a-z][a-z0-9]*}' => 'detail', '/{code:[a-z][a-z0-9]*}/versions' => 'versions'] as $path => $action) {
        Route::add(['GET', 'POST'], '/plugins' . $path, [\app\market\controller\Plugins::class, $action]);
    }
    Route::post('/plugins/check-updates', [\app\market\controller\Plugins::class, 'checkUpdates']);
    foreach (['authorize', 'download'] as $action) Route::post('/plugins/{code:[a-z][a-z0-9]*}/' . $action, [\app\market\controller\Plugins::class, $action]);
    Route::get('/packages/{version:\d+}/{member:\d+}/{expires:\d+}/{signature:[a-f0-9]{64}}', [\app\market\controller\Packages::class, 'download']);
    Route::post('/telemetry/installs', [\app\market\controller\Telemetry::class, 'installs']);
});
Route::get('/health', static fn () => json(['status' => 'ok', 'service' => 'webman-admin']));
Route::get('/', static fn () => response()->withFile(public_path('index.html'))->withHeader('Cache-Control', 'no-cache'));
Route::fallback(static function (\support\Request $request) {
    $matched = Route::dispatch($request->method(), $request->path());
    if ($matched[0] === \FastRoute\Dispatcher::METHOD_NOT_ALLOWED) {
        return json(['code' => 405, 'message' => '请求方法不允许', 'data' => null])->withStatus(405)->withHeader('Allow', implode(', ', $matched[1]));
    }
    return json(['code' => 404, 'message' => '接口不存在', 'data' => null])->withStatus(404);
});
