<?php

declare(strict_types=1);

namespace app\cms\service;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\{Blueprint, Builder};
use support\Db;

/** 1.4.0：自定义表单（表单定义、访客提交、提交附件、处理时间线）。 */
final class FormSchema
{
    public static function tables(): array
    {
        return ['cms_form', 'cms_form_submission', 'cms_form_file', 'cms_form_event'];
    }

    /** 缺哪张建哪张，可重复执行。 */
    public static function install(): void
    {
        $schema = Db::schema();
        foreach (self::tables() as $table) {
            if (!$schema->hasTable($table)) {
                self::createTable($schema, $table);
            }
        }
    }

    public static function mysql(): string
    {
        $connection = new MySqlConnection(static fn() => throw new \RuntimeException('Export must not connect'), '', 'fun_', ['driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB']);
        $queries = $connection->pretend(static function () use ($connection): void {
            foreach (self::tables() as $table) {
                self::createTable($connection->getSchemaBuilder(), $table);
            }
        });
        return "-- CMS 1.4.0 custom forms; generated from FormSchema.php.\n" . implode(";\n", array_column($queries, 'query')) . ";\n";
    }

    private static function createTable(Builder $schema, string $table): void
    {
        match ($table) {
            'cms_form' => $schema->create($table, static function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('site_id');
                $t->string('code', 64);
                $t->string('name', 100);
                $t->tinyInteger('status')->default(1);
                $t->json('fields');
                $t->json('texts');
                $t->json('settings');
                $t->unsignedInteger('lock_version')->default(0);
                $t->timestamps();
                $t->unique(['site_id', 'code'], 'cms_form_site_code_unique');
            }),
            'cms_form_submission' => $schema->create($table, static function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('site_id');
                $t->unsignedBigInteger('form_id');
                $t->string('locale', 20);
                $t->json('fields');
                $t->json('values');
                $t->longText('search_text');
                $t->string('status', 20)->default('new');
                $t->string('source_url', 1024)->default('');
                $t->string('ip', 45)->default('');
                $t->string('user_agent', 255)->default('');
                $t->unsignedBigInteger('member_id')->nullable();
                $t->unsignedInteger('lock_version')->default(0);
                $t->dateTime('handled_at')->nullable();
                $t->timestamps();
                $t->index(['site_id', 'status', 'id'], 'cms_form_submission_status_idx');
                $t->index(['site_id', 'form_id', 'status', 'id'], 'cms_form_submission_form_idx');
            }),
            'cms_form_file' => $schema->create($table, static function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('site_id');
                $t->unsignedBigInteger('submission_id');
                $t->string('field', 64);
                $t->string('original_name', 255);
                $t->string('mime', 100);
                $t->unsignedBigInteger('size');
                $t->string('sha256', 64);
                $t->string('path', 255);
                $t->dateTime('created_at')->nullable();
                $t->index(['site_id', 'submission_id'], 'cms_form_file_submission_idx');
            }),
            'cms_form_event' => $schema->create($table, static function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('site_id');
                $t->unsignedBigInteger('submission_id');
                $t->string('type', 20);
                $t->unsignedBigInteger('admin_id')->nullable();
                $t->text('body');
                $t->string('from_status', 20)->default('');
                $t->string('to_status', 20)->default('');
                $t->dateTime('created_at')->nullable();
                $t->index(['site_id', 'submission_id', 'id'], 'cms_form_event_submission_idx');
            }),
        };
    }
}
