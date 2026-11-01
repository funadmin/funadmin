<?php

declare(strict_types=1);

namespace app\admin\plugin\contract;

interface PluginResourceRepository
{
    public function all(): array;

    public function replaceForPlugin(string $pluginCode, array $records): void;
}
