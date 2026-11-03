<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('member_group')) {
        $schema->create('member_group', static function (Blueprint $table): void {
            $table->id(); $table->string('name', 50); $table->string('icon', 50)->default(''); $table->boolean('status')->default(true);
            $table->timestamps(); $table->softDeletes(); $table->unique('name');
        });
    }
    if (!$schema->hasTable('member_level')) {
        $schema->create('member_level', static function (Blueprint $table): void {
            $table->id(); $table->string('name', 30); $table->decimal('amount', 10, 2)->default(0); $table->unsignedSmallInteger('discount')->default(100);
            $table->string('thumb', 255)->default(''); $table->boolean('status')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->string('description', 200)->default('');
            $table->timestamps(); $table->softDeletes(); $table->unique('name');
        });
    }
    if (!$schema->hasTable('member_tag')) {
        $schema->create('member_tag', static function (Blueprint $table): void {
            $table->id(); $table->string('name', 50); $table->boolean('status')->default(true); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->softDeletes(); $table->unique('name');
        });
    }
    if (!$schema->hasTable('member')) {
        $schema->create('member', static function (Blueprint $table): void {
            $table->id(); $table->string('username', 80); $table->string('mobile', 20)->nullable(); $table->string('email', 60)->nullable(); $table->string('sex', 1)->default('0');
            $table->string('password', 100)->default(''); $table->unsignedBigInteger('level_id')->default(1); $table->string('avatar', 255)->default(''); $table->boolean('status')->default(true);
            $table->unsignedInteger('login_num')->default(0); $table->dateTime('last_login')->nullable(); $table->string('last_ip', 45)->default(''); $table->timestamps(); $table->softDeletes();
            $table->unique('username'); $table->unique('mobile'); $table->unique('email'); $table->index(['level_id', 'status']);
        });
    }
    foreach (['member_group_relation' => 'group_id', 'member_tag_relation' => 'tag_id'] as $tableName => $related) {
        if (!$schema->hasTable($tableName)) {
            $schema->create($tableName, static function (Blueprint $table) use ($related): void {
                $table->unsignedBigInteger('member_id'); $table->unsignedBigInteger($related); $table->dateTime('created_at')->nullable(); $table->unique(['member_id', $related]);
            });
        }
    }
    $now = date('Y-m-d H:i:s');
    if (!Db::table('member_group')->where('id', 1)->exists()) Db::table('member_group')->insert(['id' => 1, 'name' => '默认组', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
    if (!Db::table('member_level')->where('id', 1)->exists()) Db::table('member_level')->insert(['id' => 1, 'name' => '默认等级', 'amount' => 0, 'discount' => 100, 'status' => 1, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
    Db::transaction(static function () use ($now): void {
        foreach ([
            ['SystemMember', ['index', 'detail', 'options', 'create', 'update', 'status', 'delete', 'restore', 'destroy', 'import', 'export']],
            ['SystemMemberGroup', ['index', 'detail', 'create', 'update', 'status', 'delete', 'restore', 'destroy', 'import', 'export']],
            ['SystemMemberLevel', ['index', 'detail', 'create', 'update', 'status', 'delete', 'restore', 'destroy', 'import', 'export']],
        ] as [$controller, $actions]) {
            foreach ($actions as $action) {
                $resource = \app\admin\authorization\service\PermissionResource::fromParts('admin', $controller, $action);
                if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
                Db::table('permission')->insert(['app_name' => 'admin', 'name' => '会员 / ' . $action, 'code' => $resource['code'], 'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route', 'source_type' => 'system', 'source_name' => 'member', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        foreach ([['/admin/system/member', '会员管理', 'SystemMember', 'system/member/index', 'systemmember:index', 'i-ep-user'], ['/admin/system/member-group', '会员组', 'SystemMemberGroup', 'system/member-group/index', 'systemmembergroup:index', 'i-ep-user-filled'], ['/admin/system/member-level', '会员等级', 'SystemMemberLevel', 'system/member-level/index', 'systemmemberlevel:index', 'i-ep-medal']] as [$href, $name, $routeName, $component, $permission, $icon]) {
            if (Db::table('admin_menu')->where('app_name', 'admin')->where('href', $href)->exists()) continue;
            Db::table('admin_menu')->insert(['app_name' => 'admin', 'name' => $name, 'href' => $href, 'permission_id' => Db::table('permission')->where('code', $permission)->value('id'), 'query' => http_build_query(['name' => $routeName, 'type' => 'C', 'component' => $component, 'permission' => $permission]), 'source_type' => 'admin_web', 'source_name' => 'member', 'status' => 1, 'sort_order' => 90, 'icon' => $icon, 'created_at' => $now, 'updated_at' => $now]);
        }
    });
};
