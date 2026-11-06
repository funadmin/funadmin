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
Route::group('/api/v2', static function (): void {
    Route::post('/token', [\app\api\controller\v2\Token::class, 'build']);
    Route::post('/token/refresh', [\app\api\controller\v2\Token::class, 'refresh']);
    // 会员鉴权：ApiController 统一挂载 ApiMiddleware，默认需要登录；公开方法标注 #[NoNeedLogin]。
    Route::get('/member', [\app\api\controller\v2\Member::class, 'show']);
    Route::get('/member/status', [\app\api\controller\v2\Member::class, 'status']);
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
});
