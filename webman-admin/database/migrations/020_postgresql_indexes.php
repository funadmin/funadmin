<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (Db::connection()->getDriverName() !== 'pgsql') return;
    $schema = Db::schema();
    if ($schema->hasTable('attach') && !$schema->hasIndex('attach', ['driver', 'storage_key'])) {
        $schema->table('attach', static function (Blueprint $table): void {
            $table->index(['driver', 'storage_key'], 'attach_driver_storage_key_index');
        });
    }
};
