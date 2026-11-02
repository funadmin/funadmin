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
