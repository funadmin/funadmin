<?php

declare(strict_types=1);

use fun\plugins\PluginRoute;
use think\Route;

return static function (Route $route): void {
    PluginRoute::adminGroup($route, 'example', 'example:dashboard:view', static function () use ($route): void {
        $route->get('plugin/example/ping', [\plugins\example\controller\Index::class, 'ping']);
    });
};
