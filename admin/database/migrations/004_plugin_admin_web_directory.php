<?php

declare(strict_types=1);

use support\Db;

/**
 * 插件前端统一目录（与核心 SQL 004_plugin_admin_web_directory.sql 等价，可重复执行）：
 * 插件后台前端从 web/src/admin/modules/{code} 迁到 web/src/admin/plugins/{code}，
 * 发布登记中的旧路径同步改写，否则插件生命周期在校验登记时拒绝启用/停用/更新。只改路径，不改文件哈希。
 */
return static function (): void {
    if (!Db::schema()->hasTable('plugin_resource')) return;
    $moves = [
        'target_path' => ['web:src/admin/modules/', 'web:src/admin/plugins/'],
        'source_path' => ['web/src/admin/modules/', 'web/src/admin/plugins/'],
    ];
    foreach ($moves as $column => [$from, $to]) {
        $rows = Db::table('plugin_resource')->where($column, 'like', $from . '%')->get(['id', $column]);
        foreach ($rows as $row) {
            $path = (string) $row->{$column};
            if (!str_starts_with($path, $from)) continue;
            Db::table('plugin_resource')->where('id', $row->id)
                ->update([$column => $to . substr($path, strlen($from)), 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
};
