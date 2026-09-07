<?php

declare(strict_types=1);

namespace app\console\service;

interface PluginAppPublicationRepository
{
    public function all(): array;

    public function replaceAppForPlugin(string $pluginCode, array $records): void;
}
