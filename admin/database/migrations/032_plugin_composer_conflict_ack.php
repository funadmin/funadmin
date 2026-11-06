<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if ($schema->hasTable('plugin') && !$schema->hasColumn('plugin', 'composer_conflict_ack')) {
        $schema->table('plugin', static function (Blueprint $table): void {
            $table->string('composer_conflict_ack', 64)->default('');
        });
    }
};
