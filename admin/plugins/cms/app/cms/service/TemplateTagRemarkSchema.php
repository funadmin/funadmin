<?php

declare(strict_types=1);

namespace app\cms\service;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\{Blueprint, Builder};
use support\Db;

/** 1.3.1：自定义模板标签的备注（说明标签用途，不随版本变化）。 */
final class TemplateTagRemarkSchema
{
    public static function install(): void
    {
        $schema = Db::schema();
        if ($schema->hasTable('cms_template_tag') && !$schema->hasColumn('cms_template_tag', 'remark')) {
            self::alter($schema);
        }
    }

    public static function mysql(): string
    {
        $connection = new MySqlConnection(static fn() => throw new \RuntimeException('Export must not connect'), '', 'fun_', ['driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB']);
        $queries = $connection->pretend(static fn() => self::alter($connection->getSchemaBuilder()));
        return "-- CMS 1.3.1 template tag remark; generated from TemplateTagRemarkSchema.php.\n" . implode(";\n", array_column($queries, 'query')) . ";\n";
    }

    private static function alter(Builder $schema): void
    {
        $schema->table('cms_template_tag', static function (Blueprint $t): void {
            $t->string('remark', 255)->default('')->after('name');
        });
    }
}
