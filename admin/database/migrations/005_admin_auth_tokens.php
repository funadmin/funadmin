<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (!Db::schema()->hasTable('admin_auth_tokens')) {
        Db::schema()->create('admin_auth_tokens', static function (Blueprint $table): void {
            $table->string('token_hash', 64)->primary();
            $table->unsignedBigInteger('admin_id')->index();
            $table->string('purpose', 16);
            $table->string('credential_version', 64);
            $table->dateTime('expires_at')->index();
            $table->dateTime('created_at');
        });
    }
};
