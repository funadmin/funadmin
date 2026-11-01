<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('admin')) {
        $schema->create('admin', static function (Blueprint $table): void {
            $table->id();
            $table->string('username', 20)->unique();
            $table->unsignedBigInteger('dept_id')->default(0);
            $table->string('password', 200);
            $table->string('email', 60)->nullable();
            $table->string('real_name', 50)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('last_login_ip', 45)->default('');
            $table->string('token', 100)->nullable();
            $table->boolean('status')->default(true);
            $table->string('avatar', 255)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    if (!$schema->hasTable('login_attempts')) {
        $schema->create('login_attempts', static function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedBigInteger('window_start');
        });
    }
};
