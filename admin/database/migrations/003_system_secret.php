<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

/**
 * 系统内部密钥表（与核心 SQL 003_system_secret.sql 等价，可重复执行）：
 * 保存运行时自动生成、无需配置的密钥（如表单列表动作二次确认令牌的 HMAC 密钥），主键 name 保证并发插入去重。
 * 不属于系统配置，不经任何配置接口读取或展示。
 */
return static function (): void {
    $schema = Db::schema();
    if ($schema->hasTable('system_secret')) return;
    $schema->create('system_secret', static function (Blueprint $table): void {
        $table->string('name', 64)->primary();
        $table->string('value', 255);
        $table->dateTime('created_at')->nullable();
    });
};
