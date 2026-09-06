<?php

declare(strict_types=1);

namespace app\console\service;

interface PluginResourceRepository
{
    public function all(): array;

    public function replaceForPlugin(string $pluginCode, array $records): void;
}
