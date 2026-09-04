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
    Route::get('member/index', 'api/v2.member/index');
    Route::get('member/userinfo', 'api/v2.member/userinfo');
    Route::post('token', 'api/v2.token/build')
        ->middleware(\think\middleware\Throttle::class, [
            'visit_method' => ['POST'],
            'visit_rate' => '10/m',
            'key' => '__CONTROLLER__/__ACTION__/__IP__',
        ]);
    Route::post('token/refresh', 'api/v2.token/refresh');
});

