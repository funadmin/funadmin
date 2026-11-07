<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('attach_group')) {
        $schema->create('attach_group', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->string('name', 100);
            $table->string('thumb', 255)->default('');
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(999);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['pid', 'status']);
        });
    }
    if (!$schema->hasTable('attach')) {
        $schema->create('attach', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id')->default(0);
            $table->unsignedBigInteger('member_id')->default(0);
            $table->unsignedBigInteger('group_id')->default(1);
            $table->string('original_name', 255)->default('');
            $table->string('name', 255)->default('');
            $table->string('thumb', 1024)->default('');
            $table->string('path', 768)->default('');
            $table->string('url', 1024)->default('');
            $table->string('storage_key', 500)->default('');
            $table->string('ext', 20)->default('');
            $table->unsignedInteger('size')->default(0);
            $table->string('width', 30)->default('0');
            $table->string('height', 30)->default('0');
            $table->char('md5', 32)->default('');
            $table->string('sha1', 40)->default('');
            $table->string('mime', 80)->default('');
            $table->string('duration', 50)->default('0');
            $table->string('driver', 30)->default('local');
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(50);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['admin_id', 'member_id']);
            $table->index(['group_id', 'status']);
            $table->index('md5');
        });
    }
    $driver = Db::connection()->getDriverName();
    if ($driver === 'mysql') {
        $indexExists = Db::select("SHOW INDEX FROM `" . Db::connection()->getTablePrefix() . "attach` WHERE Key_name = 'attach_driver_storage_key_index'");
        if ($indexExists === []) Db::statement('ALTER TABLE `' . Db::connection()->getTablePrefix() . 'attach` ADD INDEX `attach_driver_storage_key_index` (`driver`, `storage_key`(500))');
    } elseif ($driver === 'sqlite') {
        $indexExists = Db::select("PRAGMA index_list('" . Db::connection()->getTablePrefix() . "attach')");
        if (!array_filter($indexExists, static fn (object $index): bool => (string) ($index->name ?? '') === 'attach_driver_storage_key_index')) {
            Db::statement('CREATE INDEX attach_driver_storage_key_index ON ' . Db::connection()->getTablePrefix() . 'attach (driver, storage_key)');
        }
    }
    $now = date('Y-m-d H:i:s');
    if (!Db::table('attach_group')->where('id', 1)->exists()) {
        Db::table('attach_group')->insert(['id' => 1, 'pid' => 0, 'name' => '默认', 'thumb' => '', 'status' => 1, 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now]);
    }
    Db::transaction(static function () use ($now): void {
        foreach ([
            ['SystemAttachment', 'index'], ['SystemAttachment', 'detail'], ['SystemAttachment', 'rename'], ['SystemAttachment', 'move'], ['SystemAttachment', 'delete'],
            ['SystemAttachmentGroup', 'tree'], ['SystemAttachmentGroup', 'create'], ['SystemAttachmentGroup', 'update'], ['SystemAttachmentGroup', 'deleteGroup'],
            ['SystemStorage', 'index'], ['SystemStorage', 'update'],
        ] as [$controller, $action]) {
            $resource = PermissionResource::fromParts('admin', $controller, $action);
            if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
            Db::table('permission')->insert([
                'app_name' => 'admin', 'name' => '附件 / ' . $action, 'code' => $resource['code'],
                'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route',
                'source_type' => 'system', 'source_name' => 'attachment', 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (!Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/admin/system/attachment')->exists()) {
            $resource = PermissionResource::fromParts('admin', 'SystemAttachment', 'index');
            Db::table('admin_menu')->insert([
                'app_name' => 'admin', 'name' => '附件库', 'href' => '/admin/system/attachment',
                'permission_id' => Db::table('permission')->where('code', $resource['code'])->value('id'),
                'query' => http_build_query(['name' => 'SystemAttachment', 'type' => 'C', 'component' => 'system/attachment/index', 'permission' => $resource['code']]),
                'source_type' => 'admin_web', 'source_name' => 'attachment', 'status' => 1, 'sort_order' => 50,
                'icon' => 'i-ep-picture-filled', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    });
};
