<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('dict_type')) {
        $schema->create('dict_type', static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('remark', 255)->default('');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'sort_order']);
        });
    }
    if (!$schema->hasTable('dict_item')) {
        $schema->create('dict_item', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('type_id');
            $table->string('label', 100);
            $table->string('value', 100);
            $table->string('css_class', 30)->default('');
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('remark', 255)->default('');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['type_id', 'value']);
            $table->index(['type_id', 'status', 'sort_order']);
        });
    }
    $now = date('Y-m-d H:i:s');
    foreach ([
        ['sys_normal_disable', '系统状态', [['正常', '1', 'success'], ['停用', '0', 'danger']]],
        ['sys_user_sex', '用户性别', [['未知', '0', 'info'], ['男', '1', 'primary'], ['女', '2', 'danger']]],
        ['yes_no', '是否', [['是', '1', 'success'], ['否', '0', 'info']]],
    ] as $index => [$code, $name, $options]) {
        if (Db::table('dict_type')->where('code', $code)->exists()) continue;
        $id = Db::table('dict_type')->insertGetId(['code' => $code, 'name' => $name, 'sort_order' => ($index + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
        foreach ($options as $order => [$label, $value, $css]) {
            Db::table('dict_item')->insert(['type_id' => $id, 'label' => $label, 'value' => $value, 'css_class' => $css, 'sort_order' => ($order + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
};
