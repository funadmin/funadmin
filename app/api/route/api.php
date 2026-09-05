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
use think\facade\Route;
Route::group('v2', function (): void {
    Route::get('member/index', 'v2.member/index');
    Route::get('member/userinfo', 'v2.member/userinfo');
    Route::get('member/verify', 'v2.member/verify');
    Route::post('token', 'v2.token/build')
        ->middleware(\think\middleware\Throttle::class, [
            'visit_method' => ['POST'],
            'visit_rate' => '10/m',
            'key' => '__CONTROLLER__/__ACTION__/__IP__',
        ]);
    Route::post('token/refresh', 'v2.token/refresh');
});

