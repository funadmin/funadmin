<?php

declare(strict_types=1);

namespace plugins\example;

use fun\Plugins;

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