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
// 默认后台入口固定注册；自定义入口由 AdminAuth 的动态路由承载，入口不匹配时控制器返回 404。
Route::get('/admin/auth/csrf', [\app\admin\authentication\controller\AdminAuth::class, 'csrf']);
Route::post('/admin/auth/login', [\app\admin\authentication\controller\AdminAuth::class, 'login']);
Route::get('/admin/auth/me', [\app\admin\authentication\controller\AdminAuth::class, 'me']);
Route::get('/admin/auth/menus', [\app\admin\authentication\controller\AdminAuth::class, 'menus']);
Route::post('/admin/auth/logout', [\app\admin\authentication\controller\AdminAuth::class, 'logout']);
Route::group('/api/v2', static function (): void {
    Route::post('/token', [\app\api\controller\v2\Token::class, 'build']);
    Route::post('/token/refresh', [\app\api\controller\v2\Token::class, 'refresh']);
    // 会员鉴权：ApiController 统一挂载 ApiMiddleware，默认需要登录；公开方法标注 #[NoNeedLogin]。
    Route::get('/member', [\app\api\controller\v2\Member::class, 'show']);
    Route::get('/member/status', [\app\api\controller\v2\Member::class, 'status']);
    Route::post('/member', [\app\api\controller\v2\Member::class, 'register']);
    Route::get('/member/profile', [\app\api\controller\v2\Member::class, 'profile']);
    Route::put('/member/profile', [\app\api\controller\v2\Member::class, 'updateProfile']);
    Route::put('/member/password', [\app\api\controller\v2\Member::class, 'password']);
    Route::post('/logout', [\app\api\controller\v2\Token::class, 'logout']);
});
Route::get('/health', static fn () => json(['status' => 'ok', 'service' => 'admin']));
Route::get('/', static fn () => response()->withFile(public_path('index.html'))->withHeader('Cache-Control', 'no-cache'));
Route::fallback(static function (\support\Request $request) {
    $matched = Route::dispatch($request->method(), $request->path());
    if ($matched[0] === \FastRoute\Dispatcher::METHOD_NOT_ALLOWED) {
        return json(['code' => 405, 'message' => '请求方法不允许', 'data' => null])->withStatus(405)->withHeader('Allow', implode(', ', $matched[1]));
    }
    return json(['code' => 404, 'message' => '接口不存在', 'data' => null])->withStatus(404);
})->middleware([\app\common\middleware\ApiCorsMiddleware::class]); // 回退路由不跑全局中间件：/api/* 的 CORS 预检（OPTIONS 无对应路由）在这里应答。
