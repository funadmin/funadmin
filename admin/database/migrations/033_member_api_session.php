<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('member_api_session')) {
        $schema->create('member_api_session', static function (Blueprint $table): void {
            $table->id();
            $table->char('session_id', 32)->unique();
            $table->unsignedBigInteger('member_id');
            $table->char('refresh_hash', 64)->unique();
            $table->bigInteger('refresh_expires_at');
            $table->string('ip', 45)->default('');
            $table->dateTime('created_at');
            $table->dateTime('rotated_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->index(['member_id', 'revoked_at']);
        });
    }
    if (!$schema->hasTable('member_api_session_used')) {
        $schema->create('member_api_session_used', static function (Blueprint $table): void {
            $table->char('refresh_hash', 64)->primary();
            $table->char('session_id', 32)->index();
            $table->dateTime('used_at');
        });
    }
};
