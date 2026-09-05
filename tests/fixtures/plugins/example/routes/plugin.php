<?php

declare(strict_types=1);

use think\Route;

return static function (Route $route): void {
    $route->get('plugin/example/ping', static fn () => json(['code' => 200, 'data' => ['plugin' => 'example']]));
};
