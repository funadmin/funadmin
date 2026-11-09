<?php

declare(strict_types=1);

namespace app\cms\service;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\{Blueprint, Builder};
use support\Db;

/** 1.4.1：表单提交指纹（站点、表单、来源、内容与附件的 SHA-256），用于短时间内的重复提交识别。 */
final class FormFingerprintSchema
{
    public static function install(): void
    {
        $schema = Db::schema();
        if ($schema->hasTable('cms_form_submission') && !$schema->hasColumn('cms_form_submission', 'fingerprint')) {
            self::alter($schema);
        }
    }

    public static function mysql(): string
    {
        $connection = new MySqlConnection(static fn() => throw new \RuntimeException('Export must not connect'), '', 'fun_', ['driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB']);
        $queries = $connection->pretend(static fn() => self::alter($connection->getSchemaBuilder()));
        return "-- CMS 1.4.1 form submission fingerprint; generated from FormFingerprintSchema.php.\n" . implode(";\n", array_column($queries, 'query')) . ";\n";
    }

    private static function alter(Builder $schema): void
    {
        $schema->table('cms_form_submission', static function (Blueprint $t): void {
            $t->char('fingerprint', 64)->default('')->after('member_id');
            $t->index(['site_id', 'form_id', 'fingerprint', 'created_at'], 'cms_form_submission_fingerprint_idx');
        });
    }
}
