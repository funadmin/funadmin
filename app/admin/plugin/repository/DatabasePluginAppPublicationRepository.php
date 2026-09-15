<?php

declare(strict_types=1);

namespace app\admin\plugin\repository;

use app\common\plugin\model\PluginResource;
use app\admin\plugin\contract\PluginAppPublicationRepository;
use think\facade\Db;

final class DatabasePluginAppPublicationRepository implements PluginAppPublicationRepository
{
    public function all(): array
    {
        return PluginResource::select()->toArray();
    }

    public function replaceAppForPlugin(string $pluginCode, array $records): void
    {
        Db::transaction(static function () use ($pluginCode, $records): void {
            PluginResource::where('plugin_code', $pluginCode)->where('resource_type', 'native_app')->delete();
            foreach ($records as $record) {
                PluginResource::create($record);
            }
        });
    }
}
