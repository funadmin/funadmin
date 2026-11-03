<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (Db::connection()->getDriverName() !== 'pgsql') return;
    $schema = Db::schema();
    foreach (['nickname' => 50, 'motto' => 255, 'real_name' => 50] as $column => $length) {
        if (!$schema->hasColumn('member', $column)) $schema->table('member', static fn (Blueprint $table) => $table->string($column, $length)->default(''));
    }
    foreach (['birthday', 'scores'] as $column) {
        if (!$schema->hasColumn('member', $column)) $schema->table('member', static fn (Blueprint $table) => $table->integer($column)->default(0));
    }
    $create = static function (string $name, callable $columns) use ($schema): void {
        if (!$schema->hasTable($name)) $schema->create($name, $columns);
    };
    $create('market_category', static function (Blueprint $table): void {
        $table->id(); $table->string('name', 50)->unique(); $table->integer('sort')->default(0); $table->smallInteger('status')->default(1); $table->timestamps();
    });
    $create('market_plugin', static function (Blueprint $table): void {
        $table->id(); $table->string('code', 64)->unique(); $table->string('name', 100); $table->string('description', 1000)->default(''); $table->string('author', 100)->default('');
        $table->bigInteger('category_id')->default(0); $table->string('license_type', 16)->default('free'); $table->integer('price_perpetual')->default(0); $table->integer('price_yearly')->default(0);
        $table->string('cover', 500)->default(''); $table->string('homepage', 500)->default(''); $table->smallInteger('status')->default(1); $table->integer('sort')->default(0); $table->timestamps();
    });
    $create('market_version', static function (Blueprint $table): void {
        $table->id(); $table->bigInteger('plugin_id'); $table->string('code_version', 64); $table->text('changelog')->default(''); $table->text('requires')->default('{}');
        $table->char('sha256', 64); $table->bigInteger('size'); $table->char('tree_hash', 64); $table->string('database_capability', 190)->default(''); $table->smallInteger('app_enabled')->default(0); $table->smallInteger('admin_enabled')->default(0);
        $table->string('signature', 128); $table->string('package_path', 512); $table->string('status', 16)->default('draft'); $table->bigInteger('download_count')->default(0); $table->dateTime('published_at')->nullable(); $table->timestamps();
        $table->unique(['plugin_id', 'code_version']); $table->index(['plugin_id', 'status']);
    });
    $create('market_grant', static function (Blueprint $table): void {
        $table->id(); $table->bigInteger('plugin_id'); $table->bigInteger('member_id'); $table->dateTime('expires_at')->nullable(); $table->smallInteger('status')->default(1);
        $table->string('source', 16)->default('admin'); $table->string('plan', 16)->default(''); $table->bigInteger('order_id')->nullable(); $table->string('remark')->default(''); $table->timestamps(); $table->unique(['plugin_id', 'member_id']);
    });
    $create('market_token', static function (Blueprint $table): void {
        $table->id(); $table->bigInteger('member_id'); $table->char('access_hash', 64)->unique(); $table->char('refresh_hash', 64)->unique();
        $table->bigInteger('access_expires_at'); $table->bigInteger('refresh_expires_at'); $table->string('ip', 45)->default(''); $table->dateTime('created_at'); $table->dateTime('revoked_at')->nullable(); $table->index(['member_id', 'revoked_at']);
    });
    $create('market_order', static function (Blueprint $table): void {
        $table->id(); $table->string('order_no', 32)->unique(); $table->bigInteger('member_id'); $table->bigInteger('plugin_id'); $table->string('plan', 16); $table->integer('amount'); $table->integer('paid_amount')->default(0);
        $table->string('subject', 120); $table->string('status', 16)->default('pending'); $table->string('channel', 16); $table->dateTime('expire_at'); $table->string('trade_no', 64)->nullable();
        $table->dateTime('paid_at')->nullable(); $table->bigInteger('grant_id')->nullable(); $table->string('remark')->default(''); $table->string('client_ip', 45)->default(''); $table->timestamps(); $table->index(['member_id', 'status']);
    });
    $create('market_payment_log', static function (Blueprint $table): void {
        $table->id(); $table->string('order_no', 32)->default(''); $table->string('channel', 16); $table->string('event', 32); $table->smallInteger('verified')->default(0); $table->string('result'); $table->text('payload')->nullable(); $table->dateTime('created_at'); $table->index('order_no');
    });
    $create('market_site', static function (Blueprint $table): void {
        $table->id(); $table->char('site_id', 36)->unique(); $table->bigInteger('member_id')->default(0); $table->string('platform_version', 32)->default(''); $table->string('php_version', 32)->default(''); $table->string('ip', 45)->default(''); $table->dateTime('first_seen_at'); $table->dateTime('last_seen_at');
    });
    $create('market_site_plugin', static function (Blueprint $table): void {
        $table->id(); $table->char('site_id', 36); $table->bigInteger('plugin_id'); $table->string('state', 16); $table->string('version', 64)->default(''); $table->dateTime('installed_at'); $table->dateTime('updated_at'); $table->unique(['site_id', 'plugin_id']);
    });
    $create('market_install_event', static function (Blueprint $table): void {
        $table->id(); $table->char('site_id', 36); $table->bigInteger('plugin_id'); $table->string('event', 16); $table->string('version', 64)->default(''); $table->string('from_version', 64)->default(''); $table->bigInteger('member_id')->default(0); $table->dateTime('occurred_at'); $table->dateTime('created_at'); $table->index(['plugin_id', 'occurred_at']);
    });
    $create('market_download', static function (Blueprint $table): void {
        $table->id(); $table->bigInteger('version_id'); $table->bigInteger('member_id'); $table->string('ip', 45)->default(''); $table->dateTime('created_at'); $table->index(['version_id', 'created_at']);
    });
};
