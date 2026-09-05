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
 * Date: 2019/9/30
 */
use app\common\middleware\MApi;
use think\facade\Route;
use think\middleware\Throttle;

Route::group('v2', function (): void {
    Route::post('token', 'v2.token/build')->middleware(Throttle::class, [
        'visit_method' => ['POST'],
        'visit_rate' => '10/m',
        'key' => '__CONTROLLER__/__ACTION__/__IP__',
    ]);
    Route::post('token/refresh', 'v2.token/refresh');
    Route::get('member', 'v2.member/show')->middleware(MApi::class);

    // API_ROUTE_START
    // API_ROUTE_END
});

