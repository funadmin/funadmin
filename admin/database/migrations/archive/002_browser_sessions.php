<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (!Db::schema()->hasTable('browser_sessions')) {
        Db::schema()->create('browser_sessions', static function (Blueprint $table): void {
            $table->string('session_hash', 64)->primary();
            $table->string('csrf_token', 64);
            $table->dateTime('expires_at')->index();
        });
    }
};
