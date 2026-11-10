<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;

/** 只向前迁移守卫：任何 DROP / TRUNCATE / RENAME 均拒绝；唯一例外是 PREPARE 动态 SQL 字符串中的 DROP INDEX <名> 与 DROP PRIMARY KEY。 */
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

    /** @return iterable<string, array{string}> */
    public static function forbiddenDynamic(): iterable
    {
        $run = "PREPARE s FROM @s;\nEXECUTE s;\nDEALLOCATE PREPARE s;";
        yield 'drop column in string' => ["SET @s='ALTER TABLE fun_admin DROP COLUMN password';\n" . $run];
        yield 'drop table in string' => ["SET @t='DROP TABLE fun_member';\nPREPARE s FROM @t;\nEXECUTE s;"];
        yield 'drop foreign key in string' => ["SET @s='ALTER TABLE `fun_member` DROP FOREIGN KEY `fk_member_level`';\n" . $run];
        yield 'truncate in string' => ["SET @s='TRUNCATE TABLE fun_admin';\n" . $run];
        yield 'rename in string' => ["SET @s='ALTER TABLE fun_admin RENAME TO fun_x';\n" . $run];
        yield 'drop column after allowed drop index' => ["SET @s='ALTER TABLE fun_admin DROP INDEX `username`, DROP COLUMN `email`';\n" . $run];
        yield 'keyword split across literals' => ["SET @s=CONCAT('ALTER TABLE fun_admin DR', 'OP COLUMN email');\n" . $run];
        yield 'double quoted string' => ["SET @s=\"DROP TABLE fun_admin\";\n" . $run];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenDynamic')]
    public function test_destructive_dynamic_sql_strings_are_rejected(string $sql): void
    {
        $this->expectExceptionMessage('Migration 包含破坏性语句');
        $this->guard($sql);
    }

    public function test_dynamic_sql_allows_only_drop_index_and_drop_primary_key(): void
    {
        $this->guard("SET @s=IF(1=1, 'ALTER TABLE `fun_member` DROP INDEX `username`, ADD UNIQUE KEY `username` (`username`,`deleted_token`)', 'DO 0');\n"
            . "SET @s=CONCAT('ALTER TABLE `fun_x` ', 'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY (`id`)', ', ', 'DROP INDEX `id`');\n"
            . "PREPARE s FROM @s;\nEXECUTE s;\nDEALLOCATE PREPARE s;");
        $file = base_path() . '/database/core/migrations/005_soft_delete.sql';
        $this->guard((string) file_get_contents($file));
        $this->addToAssertionCount(1);
    }

    public function test_destructive_words_in_strings_stay_allowed_without_dynamic_sql(): void
    {
        $this->guard("INSERT INTO `fun_probe` VALUES (1, 'drop table rename truncate');");
        $this->addToAssertionCount(1);
    }

    public function test_ddl_detection_is_shared_by_transaction_wrapping_and_outer_transaction_check(): void
    {
        $service = new MigrationService();
        $hasDdl = static fn (string $sql): bool => (new \ReflectionMethod(MigrationService::class, 'hasDdl'))->invoke($service, (new \ReflectionMethod(MigrationService::class, 'statements'))->invoke($service, $sql));
        self::assertTrue($hasDdl("SET @s='ALTER TABLE `fun_x` ADD COLUMN `y` INT';\nPREPARE s FROM @s;\nEXECUTE s;"), '动态 DDL 视为 DDL');
        self::assertTrue($hasDdl("UPDATE `fun_x` SET `a`=1;\nALTER TABLE `fun_x` ADD COLUMN `y` INT;"));
        self::assertFalse($hasDdl("INSERT INTO `fun_x` VALUES ('alter table drop');\n-- CREATE TABLE in comment\nUPDATE `fun_x` SET `a`=1;"));
        $source = (string) file_get_contents(base_path() . '/app/common/service/database/MigrationService.php');
        self::assertSame(2, substr_count($source, '$this->hasDdl('), '外层事务检查与事务包装共用同一判定');
    }
}
