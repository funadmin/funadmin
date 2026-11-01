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
use app\admin\authentication\controller\ApiAuth;
use app\admin\authentication\controller\AdminAuth;
use app\admin\authorization\controller\Management;

Route::disableDefaultRoute();
Route::get('/health', static fn () => json(['status' => 'ok', 'service' => 'webman-admin']));
Route::post('/api/auth/login', [ApiAuth::class, 'login']);
Route::get('/api/auth/me', [ApiAuth::class, 'me']);
Route::post('/api/auth/logout', [ApiAuth::class, 'logout']);
Route::get('/admin/auth/csrf', [AdminAuth::class, 'csrf']);
Route::post('/admin/auth/login', [AdminAuth::class, 'login']);
Route::get('/admin/auth/me', [AdminAuth::class, 'me']);
Route::get('/admin/auth/menus', [AdminAuth::class, 'menus']);
Route::post('/admin/auth/logout', [AdminAuth::class, 'logout']);
Route::get('/admin/management/{resource:users|roles|permissions|menus}', [Management::class, 'listing']);
Route::post('/admin/management/{resource:users|roles|permissions|menus}', [Management::class, 'create']);
Route::put('/admin/management/{resource:users|roles|permissions|menus}/{id:[1-9][0-9]*}', [Management::class, 'update']);
Route::delete('/admin/management/{resource:users|roles|permissions|menus}/{id:[1-9][0-9]*}', [Management::class, 'remove']);
Route::fallback(static fn () => json(['code' => 404, 'message' => '接口不存在', 'data' => null])->withStatus(404));
