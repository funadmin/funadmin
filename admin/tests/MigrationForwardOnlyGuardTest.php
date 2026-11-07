<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;

/** 只向前迁移守卫：任何 DROP / TRUNCATE / RENAME 均拒绝，不再有白名单。 */
final class MigrationForwardOnlyGuardTest extends TestCase
{
    private function guard(string $sql): void
    {
        (new \ReflectionMethod(MigrationService::class, 'assertForwardOnly'))->invoke(new MigrationService(), $sql, '999_probe.sql');
    }

    public function test_additive_sql_and_destructive_words_inside_strings_are_allowed(): void
    {
        $this->guard("CREATE TABLE `fun_probe` (`id` bigint unsigned NOT NULL);\nALTER TABLE `fun_probe` ADD COLUMN `name` varchar(20) NULL;\nINSERT INTO `fun_probe` VALUES (1, 'drop table rename');\n-- DROP TABLE `fun_admin`;");
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function forbidden(): iterable
    {
        yield 'core table' => ['DROP TABLE IF EXISTS `fun_admin`;'];
        yield 'retired identity table' => ['DROP TABLE IF EXISTS `fun_oauth_client`;'];
        yield 'legacy guard trigger' => ['DROP TRIGGER IF EXISTS `schema_integrity_guard_006`;'];
        yield 'legacy guard table' => ['DROP TABLE IF EXISTS `fun_schema_integrity_guard_006`;'];
        yield 'drop column' => ['ALTER TABLE `fun_admin` DROP COLUMN `email`;'];
        yield 'truncate' => ['TRUNCATE TABLE `fun_admin`;'];
        yield 'rename' => ['RENAME TABLE `fun_admin` TO `fun_x`;'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbidden')]
    public function test_destructive_statements_are_rejected(string $sql): void
    {
        $this->expectExceptionMessage('Migration 包含破坏性语句');
        $this->guard($sql);
    }
}
