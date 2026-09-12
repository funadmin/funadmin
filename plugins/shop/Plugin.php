<?php

declare(strict_types=1);

namespace plugins\shop;

use app\common\plugin\sdk\Plugin as BasePlugin;

final class Plugin extends BasePlugin
{
    public function install(): bool { return true; }
    public function uninstall(): bool { return true; }
    public function enabled(): bool { return true; }
    public function disabled(): bool { return true; }
    public function beforeUpdate(string $fromVersion, string $toVersion, bool $migrate): bool { return true; }
    public function afterUpdate(string $fromVersion, string $toVersion, bool $migrate): bool { return true; }
    public function purgeData(): bool { return false; }
}