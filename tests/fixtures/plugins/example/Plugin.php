<?php

declare(strict_types=1);

namespace plugins\example;

use fun\Plugins;
use plugins\example\vendor\Probe;

final class Plugin extends Plugins
{
    public static array $initializedState = [];

    protected function initialize(): void
    {
        self::$initializedState = [
            'app' => $this->app,
            'request' => $this->request,
            'code' => $this->code,
            'plugin_path' => $this->plugin_path,
            'view' => $this->view,
            'plugin_config' => $this->plugin_config,
            'info' => $this->info,
            'plugin_info' => $this->plugin_info,
        ];
    }

    public function install(): bool
    {
        return Probe::loaded();
    }

    public function enabled(): bool
    {
        return Probe::loaded();
    }

    public function disabled(): bool
    {
        return Probe::loaded();
    }

    public function uninstall(): bool
    {
        return Probe::loaded();
    }

    public function purgeData(): bool
    {
        return Probe::loaded();
    }
}
