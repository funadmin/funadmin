<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\model\PluginResource;
use think\facade\Db;

final class DatabasePluginResourceRepository implements PluginResourceRepository
{
    public function all(): array
    {
        return PluginResource::select()->toArray();
    }

    public function replaceForPlugin(string $pluginName, array $records): void
    {
        Db::transaction(static function () use ($pluginName, $records): void {
            PluginResource::where('plugin_name', $pluginName)->delete();
            foreach ($records as $record) {
                PluginResource::create($record);
            }
        });
    }
}
