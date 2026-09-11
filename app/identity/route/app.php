<?php

declare(strict_types=1);

use think\facade\Route;

// Phase 4 仅注册已实现并可测试的 OAuth/OIDC 协议能力；logout 留待 Phase 7。
Route::get('.well-known/openid-configuration', 'Metadata/discovery');
Route::get('.well-known/oauth-authorization-server', 'Metadata/oauth');
Route::get('jwks', 'Metadata/jwks');
Route::get('authorize', 'OAuth/authorize')->middleware(\think\middleware\Throttle::class, ['visit_rate' => '30/m']);
Route::post('decision', 'OAuth/decision')->middleware(\think\middleware\Throttle::class, ['visit_rate' => '20/m']);
Route::post('token', 'OAuth/token')->middleware(\think\middleware\Throttle::class, ['visit_rate' => '30/m']);
Route::post('revoke', 'OAuth/revoke')->middleware(\think\middleware\Throttle::class, ['visit_rate' => '30/m']);
Route::post('introspect', 'OAuth/introspect')->middleware(\think\middleware\Throttle::class, ['visit_rate' => '60/m']);
Route::get('userinfo', 'OAuth/userinfo')->middleware(\think\middleware\Throttle::class, ['visit_rate' => '60/m']);
Route::get('status', 'Status/index');