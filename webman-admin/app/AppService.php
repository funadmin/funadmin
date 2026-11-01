<?php
declare (strict_types = 1);

namespace app;

use app\common\storage\StorageDriverRegistry;
use think\event\RouteLoaded;
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

        // 在注解服务 boot 前监听，待路由加载时按实际应用决定扫描范围。
        $this->app->event->listen(RouteLoaded::class, function (): void {
            $annotation = $this->app->config->get('annotation', []);
            $directory = $this->app->getBasePath() . 'admin/controller';
            $controllers = $annotation['route']['controllers'] ?? [];
            unset($controllers[$directory]);
            if ($this->app->getAppPath() === $this->app->getBasePath()) {
                $controllers[$directory] = [
                    'namespace' => 'app\\admin\\controller',
                    'name' => 'admin',
                ];
            }
            $annotation['route']['controllers'] = $controllers;
            $this->app->config->set($annotation, 'annotation');
        });
    }

    public function boot(): void
    {
        // 框架 Error 初始化器以 E_ALL 把所有告警转为异常；弃用告警按 php.ini-production 惯例排除，
        // 否则第三方库在新版 PHP 下会让整个请求 500（如 think-captcha 在 8.5 调用 imagedestroy()）。
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    }
}
