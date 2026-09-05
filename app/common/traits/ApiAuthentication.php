<?php

declare(strict_types=1);

namespace app\common\traits;

use app\common\middleware\MApi;

/**
 * API 控制器认证中间件配置。
 */
trait ApiAuthentication
{
    protected function registerApiAuthentication(): void
    {
        $publicActions = array_values(array_unique(array_merge($this->noNeedLogin, $this->noNeedRight)));
        if ($publicActions === ['*']) {
            return;
        }

        $middleware = [MApi::class];
        if ($publicActions !== []) {
            $middleware[MApi::class] = ['except' => $publicActions];
            unset($middleware[0]);
        }
        $this->middleware = $middleware + $this->middleware;
    }
}
