<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (!Db::schema()->hasTable('language')) {
        Db::schema()->create('language', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 20)->unique();
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    if (!Db::schema()->hasTable('language_line')) {
        Db::schema()->create('language_line', static function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 20);
            $table->string('key', 190);
            $table->text('value');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['locale', 'key']);
        });
    }
    $now = date('Y-m-d H:i:s');
    foreach (['zh-cn' => 1, 'en-us' => 0] as $name => $default) {
        if (!Db::table('language')->where('name', $name)->exists()) {
            Db::table('language')->insert(['name' => $name, 'is_default' => $default, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
    Db::transaction(static function () use ($now): void {
        foreach (['index', 'detail', 'create', 'update', 'delete', 'deleteById', 'pack', 'namespaces', 'lines', 'saveLine', 'removeLine'] as $action) {
            $resource = PermissionResource::fromParts('admin', 'SystemLanguage', $action);
            if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
            Db::table('permission')->insert([
                'app_name' => 'admin', 'name' => '多语言 / ' . $action, 'code' => $resource['code'],
                'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route',
                'source_type' => 'system', 'source_name' => 'language', 'status' => 1, 'is_public' => $action === 'pack' ? 1 : 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (!Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/admin/system/language')->exists()) {
            Db::table('admin_menu')->insert([
                'app_name' => 'admin', 'name' => '多语言', 'href' => '/admin/system/language',
                'permission_id' => Db::table('permission')->where('code', 'systemlanguage:index')->value('id'),
                'query' => http_build_query(['name' => 'SystemLanguage', 'type' => 'C', 'component' => 'system/language/index', 'permission' => 'systemlanguage:index']),
                'source_type' => 'admin_web', 'source_name' => 'language', 'status' => 1, 'sort_order' => 50,
                'icon' => 'i-ep-chat-dot-round', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    });
};
