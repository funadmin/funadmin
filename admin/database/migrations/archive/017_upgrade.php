<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('upgrade_manifest')) {
        $schema->create('upgrade_manifest', static function (Blueprint $table): void {
            $table->id(); $table->string('manifest_id', 96)->unique(); $table->json('payload'); $table->dateTime('expires_at'); $table->dateTime('consumed_at')->nullable(); $table->timestamps();
            $table->index(['expires_at', 'consumed_at']);
        });
    }
    if (!$schema->hasTable('upgrade_task')) {
        $schema->create('upgrade_task', static function (Blueprint $table): void {
            $table->id(); $table->string('from_version', 64)->default(''); $table->string('to_version', 64)->default(''); $table->string('status', 40)->default('pending'); $table->string('stage', 80)->default(''); $table->unsignedTinyInteger('progress')->default(0); $table->string('idempotency_token', 128)->unique(); $table->string('active_slot', 128)->nullable(); $table->dateTime('lease_expires_at')->nullable(); $table->string('backup_path', 1024)->default(''); $table->string('backup_hash', 64)->default(''); $table->string('db_backup_path', 1024)->default(''); $table->string('db_backup_hash', 64)->default(''); $table->text('error_message')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
            $table->index(['status', 'active_slot']);
        });
    }
    $now = date('Y-m-d H:i:s');
    Db::transaction(static function () use ($now): void {
        foreach (['status', 'check', 'execute', 'upload', 'restore', 'recoverStale'] as $action) {
            $resource = PermissionResource::fromParts('admin', 'SystemUpgrade', $action);
            if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
            Db::table('permission')->insert(['app_name' => 'admin', 'name' => '系统升级 / ' . $action, 'code' => $resource['code'], 'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route', 'source_type' => 'system', 'source_name' => 'system_upgrade', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/admin/system/upgrade')->exists()) {
            $resource = PermissionResource::fromParts('admin', 'SystemUpgrade', 'status');
            Db::table('admin_menu')->insert(['app_name' => 'admin', 'name' => '系统升级', 'href' => '/admin/system/upgrade', 'permission_id' => Db::table('permission')->where('code', $resource['code'])->value('id'), 'query' => http_build_query(['name' => 'SystemUpgrade', 'type' => 'C', 'component' => 'system/upgrade/index', 'permission' => $resource['code']]), 'source_type' => 'admin_web', 'source_name' => 'system_upgrade', 'status' => 1, 'sort_order' => 15, 'icon' => 'i-ep-upload-filled', 'created_at' => $now, 'updated_at' => $now]);
        }
    });
};
