<?php

declare(strict_types=1);

namespace Tests\Console;

use Illuminate\Database\Schema\Blueprint;
use support\Db;

final class UpgradeRecoveryFixture
{
    public static function create(): void
    {
        Db::schema()->create('upgrade_task', static function (Blueprint $table): void {
            $table->id(); $table->string('idempotency_token')->unique(); $table->string('manifest_id')->nullable();
            $table->string('from_version')->default('1.0.0'); $table->string('to_version')->default('2.0.0');
            $table->string('status'); $table->string('stage'); $table->integer('progress')->default(0);
            $table->string('package_hash', 64)->nullable(); $table->integer('active_slot')->nullable()->unique();
            $table->dateTime('lease_expires_at')->nullable(); $table->json('metadata')->nullable(); $table->text('error_message')->nullable();
            foreach (['backup_path', 'backup_hash', 'db_backup_path', 'db_backup_hash', 'db_backup_schema'] as $name) $table->string($name)->nullable();
            $table->timestamps();
        });
    }

    public static function seed(string $stage = 'download', bool $expired = true, array $attributes = []): int
    {
        return Db::table('upgrade_task')->insertGetId($attributes + [
            'idempotency_token' => bin2hex(random_bytes(16)), 'status' => 'running', 'stage' => $stage,
            'active_slot' => 1, 'lease_expires_at' => date('Y-m-d H:i:s', time() + ($expired ? -60 : 3600)), 'metadata' => '{}',
        ]);
    }
}
