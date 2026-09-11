<?php

declare(strict_types=1);

use think\facade\Route;

// Phase 0 只暴露边界状态，不注册 authorization、token 或 OIDC discovery 端点。
Route::get('status', 'Status/index');