<?php

use think\facade\Route;
use think\Request;

Route::redirect('/', '/admin-web/', 302);

Route::miss(static function (Request $request) {
    return json([
        'code' => 404,
        'msg' => '接口不存在',
        'time' => time(),
        'data' => null,
    ], 404);
});
