<?php

declare(strict_types=1);

namespace app\admin\plugin\repository;

use app\common\plugin\model\PluginResource;
use app\admin\plugin\contract\PluginResourceRepository;
use support\Db;

final class DatabasePluginResourceRepository implements PluginResourceRepository
{
    public function all(): array
    {
        return PluginResource::query()->get()->toArray();
    }

    public function replaceForPlugin(string $pluginCode, array $records): void
    {
        Db::transaction(static function () use ($pluginCode, $records): void {
            PluginResource::where('plugin_code', $pluginCode)->where('resource_type', 'file')->delete();
            foreach ($records as $record) {
                PluginResource::create($record);
            }
        });
    }
}
