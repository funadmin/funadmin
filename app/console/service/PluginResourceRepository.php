<?php

declare(strict_types=1);

namespace app\backend\service;

interface PluginResourceRepository
{
    public function all(): array;

    public function replaceForPlugin(string $pluginName, array $records): void;
}
