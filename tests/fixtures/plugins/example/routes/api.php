<?php

declare(strict_types=1);

use think\Route;

/** 会员端 API 通道：仅在 api 应用请求内注册（内核按应用名门控）。 */
return static function (Route $route): void {
    $route->get('plugin/example/cart', static fn () => json(['code' => 200, 'data' => ['channel' => 'api']]));
};
