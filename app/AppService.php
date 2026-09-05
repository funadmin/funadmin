<?php
declare (strict_types = 1);

namespace app;

use app\common\storage\StorageDriverRegistry;
use think\Service;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register(): void
    {
        // 注册为容器单例，插件服务可在启动时追加自定义存储驱动。
        $this->app->instance(StorageDriverRegistry::class, new StorageDriverRegistry());
    }

    public function boot(): void
    {
        // 服务启动
    }
}
