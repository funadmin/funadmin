<?php

declare(strict_types=1);

namespace plugins\document;

use app\common\plugin\sdk\Plugin as BasePlugin;
use app\document\service\SeedService;

final class Plugin extends BasePlugin
{
    public function install(): bool { return true; }
    public function uninstall(): bool { return true; }

    /** 启用时补齐内置目录与入门文档；只插入缺失的稳定 slug，不覆盖管理员修改。 */
    public function enabled(): bool
    {
        (new SeedService(__DIR__ . '/resources/seed'))->run();
        return true;
    }

    public function disabled(): bool { return true; }
    public function purgeData(): bool { return false; }
}
