<?php

declare(strict_types=1);

namespace {%plugin_dir%}\{%plugin%};

use fun\Plugins;

/**
 * {%title%}生命周期入口。
 */
final class Plugin extends Plugins
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
