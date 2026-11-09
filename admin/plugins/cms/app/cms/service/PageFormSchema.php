<?php

declare(strict_types=1);

namespace app\cms\service;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\{Blueprint, Builder};
use support\Db;

/** 1.5.0：内容修订与栏目译文可附带一个本站表单（表单代码），前台显示在正文或列表下方。 */
final class PageFormSchema
{
    public static function install(): void
    {
        $schema = Db::schema();
        if ($schema->hasTable('cms_content_revision') && !$schema->hasColumn('cms_content_revision', 'form_code')) self::revision($schema);
        if ($schema->hasTable('cms_category_locale') && !$schema->hasColumn('cms_category_locale', 'form_code')) self::category($schema);
    }

    public static function mysql(): string
    {
        $connection = new MySqlConnection(static fn() => throw new \RuntimeException('Export must not connect'), '', 'fun_', ['driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'engine' => 'InnoDB']);
        $queries = $connection->pretend(static function () use ($connection): void {
            self::revision($connection->getSchemaBuilder());
            self::category($connection->getSchemaBuilder());
        });
        return "-- CMS 1.5.0 page attached form; generated from PageFormSchema.php.\n" . implode(";\n", array_column($queries, 'query')) . ";\n";
    }

    private static function revision(Builder $schema): void
    {
        $schema->table('cms_content_revision', static fn(Blueprint $t) => $t->string('form_code', 64)->default('')->after('robots'));
    }

    private static function category(Builder $schema): void
    {
        $schema->table('cms_category_locale', static fn(Blueprint $t) => $t->string('form_code', 64)->default('')->after('detail_template'));
    }
}
