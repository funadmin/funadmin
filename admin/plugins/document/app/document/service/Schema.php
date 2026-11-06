<?php

declare(strict_types=1);

namespace app\document\service;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use support\Db;

/** 同一份定义同时用于隔离测试建表与生成随插件发布的 MySQL 迁移。 */
final class Schema
{
    public static function tables(): array
    {
        return ['document_categories', 'documents', 'document_category_links', 'document_revisions', 'document_events'];
    }

    public static function install(): void
    {
        self::create(Db::schema());
    }

    public static function drop(): void
    {
        foreach (array_reverse(self::tables()) as $table) {
            Db::schema()->dropIfExists($table);
        }
    }

    public static function mysql(): string
    {
        $connection = new MySqlConnection(static fn () => throw new \RuntimeException('Schema export must not connect'), '', 'fun_', ['driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB']);
        $queries = $connection->pretend(static fn () => self::create($connection->getSchemaBuilder()));
        return "-- Document schema; generated from app/document/service/Schema.php. Prefix rewritten by host migration runner.\n"
            . implode(";\n", array_map(static fn ($q) => $q['query'], $queries)) . ";\n";
    }

    private static function create(Builder $s): void
    {
        $s->create('document_categories', static function (Blueprint $t): void {
            $t->id();
            $t->string('scope', 32);
            $t->unsignedBigInteger('parent_id')->default(0);
            $t->string('name', 100);
            $t->string('slug', 120)->unique();
            $t->integer('sort_order')->default(0);
            $t->string('source', 32)->default('manual');
            $t->timestamps();
            $t->index(['scope', 'parent_id', 'sort_order']);
        });
        $s->create('documents', static function (Blueprint $t): void {
            $t->id();
            $t->string('title', 200);
            $t->string('slug', 160)->unique();
            $t->string('summary', 500)->default('');
            $t->string('scope', 32);
            $t->string('plugin_code', 64)->default('');
            $t->string('type', 32)->default('guide');
            $t->string('status', 16)->default('draft');
            $t->string('visibility', 16)->default('admin');
            $t->string('applies_to', 64)->default('');
            $t->json('content_json');
            $t->longText('plain_text');
            $t->unsignedInteger('lock_version')->default(1);
            $t->unsignedInteger('current_revision_no')->default(0);
            // 草稿与最新发布修订不同（发布状态本身不因编辑改变）。
            $t->boolean('has_unpublished_changes')->default(false);
            $t->unsignedBigInteger('owner_id')->default(0);
            $t->string('source', 32)->default('manual');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['scope', 'status', 'updated_at']);
            $t->index('plugin_code');
        });
        $s->create('document_category_links', static function (Blueprint $t): void {
            $t->unsignedBigInteger('document_id');
            $t->unsignedBigInteger('category_id');
            $t->primary(['document_id', 'category_id']);
            $t->index('category_id');
        });
        $s->create('document_revisions', static function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('document_id');
            $t->unsignedInteger('revision_no');
            $t->string('title', 200);
            $t->string('summary', 500)->default('');
            $t->json('content_json');
            $t->string('change_summary', 500)->default('');
            $t->unsignedBigInteger('created_by')->default(0);
            $t->dateTime('created_at')->nullable();
            $t->unique(['document_id', 'revision_no']);
        });
        $s->create('document_events', static function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('document_id')->default(0);
            $t->string('action', 32);
            $t->unsignedInteger('revision_no')->default(0);
            $t->unsignedBigInteger('actor_id')->default(0);
            $t->json('metadata')->nullable();
            $t->dateTime('created_at')->nullable();
            $t->index(['document_id', 'created_at']);
        });
    }
}
