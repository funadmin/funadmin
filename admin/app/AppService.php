<?php

declare(strict_types=1);

namespace app;

use app\admin\authorization\service\AuthzCache;
use app\common\storage\StorageDriverRegistry;
use support\Container;

/** Webman 应用启动服务。 */
final class AppService implements \Webman\Bootstrap
{
    public static function start(?\Workerman\Worker $worker): void
    {
        Container::instance()->addDefinitions([
            StorageDriverRegistry::class => static fn (): StorageDriverRegistry => new StorageDriverRegistry(),
        ]);
        // 授权表写入换新授权版本，必须在本进程任何写入之前注册。
        AuthzCache::listen();
    }
}
