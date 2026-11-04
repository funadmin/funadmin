<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('upgrade_task')) return;
    foreach (['manifest_id' => 96, 'package_hash' => 64, 'db_backup_schema' => 128] as $name => $length) {
        if (!$schema->hasColumn('upgrade_task', $name)) $schema->table('upgrade_task', static function (Blueprint $table) use ($name, $length): void { $table->string($name, $length)->nullable(); });
    }
};
