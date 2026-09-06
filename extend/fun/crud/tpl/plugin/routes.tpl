<?php

declare(strict_types=1);

use fun\plugins\PluginRoute;
use think\Route;

return static function (Route $route): void {
    PluginRoute::adminGroup(
        $route,
        '{%plugin%}',
        '{%plugin%}:dashboard:view',
        static function (Route $route): void {
            $route->get('plugin/{%plugin%}/ping', static fn () => json(['code' => 200, 'data' => ['plugin' => '{%plugin%}']]));
        }
    );
};