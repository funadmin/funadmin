<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('config_group')) {
        $schema->create('config_group', static function (Blueprint $table): void {
            $table->id(); $table->string('name', 30)->unique(); $table->string('title', 60);
            $table->boolean('status')->default(true); $table->timestamps(); $table->softDeletes();
        });
    }
    if (!$schema->hasTable('config')) {
        $schema->create('config', static function (Blueprint $table): void {
            $table->id(); $table->string('code', 30)->unique(); $table->text('value')->nullable();
            $table->string('extra', 255)->default(''); $table->string('remark', 100)->default('');
            $table->string('verify', 50)->default(''); $table->string('type', 30)->default('text');
            $table->string('group', 80)->default('site'); $table->boolean('status')->default(true);
            $table->boolean('is_system')->default(false); $table->timestamps(); $table->softDeletes();
        });
    }
    if (!$schema->hasTable('field_type')) {
        $schema->create('field_type', static function (Blueprint $table): void {
            $table->id(); $table->string('name', 32); $table->string('title', 64)->default('');
            $table->string('default_define', 128)->default(''); $table->boolean('isoption')->default(false);
            $table->string('rules', 256)->default(''); $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0); $table->timestamps(); $table->softDeletes();
        });
    }
    if (!$schema->hasTable('field_verify')) {
        $schema->create('field_verify', static function (Blueprint $table): void {
            $table->id(); $table->string('verify', 50)->unique(); $table->string('title', 100)->default('');
            $table->timestamps(); $table->softDeletes();
        });
    }
    $now = date('Y-m-d H:i:s');
    Db::transaction(static function () use ($now): void {
        foreach (['site' => '网站', 'sms' => '短信', 'upload' => '上传', 'mobile' => '手机'] as $name => $title) {
            if (!Db::table('config_group')->where('name', $name)->exists()) Db::table('config_group')->insert(compact('name', 'title') + ['created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['text' => '文本', 'password' => '密码', 'textarea' => '多行文本', 'number' => '整数', 'float' => '小数', 'select' => '选择', 'radio' => '单选', 'checkbox' => '多选', 'switch' => '开关', 'tags' => '标签', 'array' => '数组', 'date' => '日期', 'datetime' => '日期时间', 'range' => '日期范围', 'json' => 'JSON', 'image' => '图片', 'images' => '多图', 'file' => '文件', 'files' => '多文件'] as $name => $title) {
            if (!Db::table('field_type')->where('name', $name)->exists()) Db::table('field_type')->insert(compact('name', 'title') + ['isoption' => in_array($name, ['select', 'radio', 'checkbox'], true) ? 1 : 0, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['required' => '必须', 'email' => '邮箱', 'url' => '地址', 'number' => '整数'] as $verify => $title) {
            if (!Db::table('field_verify')->where('verify', $verify)->exists()) Db::table('field_verify')->insert(compact('verify', 'title') + ['created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['index', 'detail', 'options', 'create', 'update', 'value', 'status', 'delete', 'groups', 'createGroup', 'updateGroup', 'deleteGroup'] as $action) {
            $resource = PermissionResource::fromParts('admin', 'SystemConfig', $action);
            if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
            Db::table('permission')->insert(['app_name' => 'admin', 'name' => '配置 / ' . $action, 'code' => $resource['code'], 'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route', 'source_type' => 'system', 'source_name' => 'config', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/admin/system/config')->exists()) Db::table('admin_menu')->insert([
            'app_name' => 'admin', 'name' => '配置管理', 'href' => '/admin/system/config', 'permission_id' => Db::table('permission')->where('code', 'systemconfig:index')->value('id'),
            'query' => http_build_query(['name' => 'SystemConfig', 'type' => 'C', 'component' => 'system/config/index', 'permission' => 'systemconfig:index']),
            'source_type' => 'admin_web', 'source_name' => 'config', 'status' => 1, 'sort_order' => 60, 'icon' => 'i-ep-setting', 'created_at' => $now, 'updated_at' => $now,
        ]);
    });
};
