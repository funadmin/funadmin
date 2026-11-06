<?php

declare (strict_types=1);
namespace app\cms\service;

use Illuminate\Database\Schema\{Blueprint, Builder};
use Illuminate\Database\MySqlConnection;
use support\Db;
final class ContributionSchema
{
    public static function tables(): array
    {
        return ['cms_member_submission'];
    }
    public static function install(): void
    {
        if (!Db::schema()->hasTable('cms_member_submission')) {
            self::create(Db::schema());
        }
    }
    public static function mysql(): string
    {
        $connection = new MySqlConnection(static fn() => throw new \RuntimeException('Export must not connect'), '', 'fun_', ['driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB']);
        $queries = $connection->pretend(static fn() => self::create($connection->getSchemaBuilder()));
        return "-- CMS member contributions; generated from ContributionSchema.php.\n" . implode(";\n", array_column($queries, 'query')) . ";\n";
    }
    private static function create(Builder $schema): void
    {
        $schema->create('cms_member_submission', static function (Blueprint $t) use ($schema): void {
            $t->id();
            $t->unsignedBigInteger('site_id');
            $t->string('locale', 20);
            $t->unsignedBigInteger('member_id');
            $t->string('kind', 20);
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->string('title');
            $t->longText('body_html');
            $t->json('data');
            $t->json('result');
            $t->string('request_key', 64)->collation($schema->getConnection()->getDriverName() === 'mysql' ? 'utf8mb4_bin' : 'BINARY');
            $t->string('payload_hash', 64);
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('lock_version')->default(0);
            $t->unsignedBigInteger('reviewer_admin_id')->nullable();
            $t->string('review_reason', 1000)->default('');
            $t->dateTime('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['site_id', 'member_id', 'request_key'], 'cms_submission_request_unique');
            $t->index(['site_id', 'locale', 'kind', 'status', 'id'], 'cms_submission_pending_idx');
            $t->index(['site_id', 'locale', 'parent_id', 'status', 'id'], 'cms_submission_public_idx');
            $t->index(['site_id', 'member_id', 'created_at'], 'cms_submission_owner_idx');
        });
    }
}
