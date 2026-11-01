<?php

declare(strict_types=1);

namespace app\common\middleware;

use app\common\service\InstallSupport;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** 安装完成后在每个 worker 中切换到新配置，不干预已有站点认证。 */
final class Install implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        InstallSupport::activateInstalledConfiguration();
        return $handler($request);
    }
}
