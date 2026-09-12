<?php

declare(strict_types=1);

namespace plugins\example;

use app\common\plugin\sdk\Plugin as BasePlugin;

final class Plugin extends BasePlugin
{
    public function install(): bool
    {
        return true;
    }

    public function uninstall(): bool
    {
        return true;
    }

    public function enabled(): bool
    {
        return true;
    }

    public function disabled(): bool
    {
        return true;
    }
}