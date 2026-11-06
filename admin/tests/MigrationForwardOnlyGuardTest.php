<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;

/** 只向前迁移守卫：仅精确放行退役身份表的 DROP TABLE IF EXISTS。 */
final class MigrationForwardOnlyGuardTest extends TestCase
{
    private function guard(string $sql): void
    {
        (new \ReflectionMethod(MigrationService::class, 'assertForwardOnly'))->invoke(new MigrationService(), $sql, '999_probe.sql');
    }

    public function test_dropping_retired_identity_tables_is_allowed(): void
    {
        $this->guard("SET FOREIGN_KEY_CHECKS = 0;\nDROP TABLE IF EXISTS `fun_oauth_client`;\nDROP TABLE IF EXISTS `fun_identity_user`;\nDROP TABLE IF EXISTS `fun_scope`;\nSET FOREIGN_KEY_CHECKS = 1;");
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function forbidden(): iterable
    {
        yield 'core table' => ['DROP TABLE IF EXISTS `fun_admin`;'];
        yield 'without IF EXISTS' => ['DROP TABLE `fun_oauth_client`;'];
        yield 'name prefix trick' => ['DROP TABLE IF EXISTS `fun_oauth_client_backup`;'];
        yield 'scope prefix trick' => ['DROP TABLE IF EXISTS `fun_scope_audit`;'];
        yield 'second table in one statement' => ['DROP TABLE IF EXISTS `fun_scope`, `fun_admin`;'];
        yield 'drop column' => ['ALTER TABLE `fun_admin` DROP COLUMN `email`;'];
        yield 'truncate' => ['TRUNCATE TABLE `fun_oauth_client`;'];
        yield 'rename' => ['RENAME TABLE `fun_admin` TO `fun_x`;'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbidden')]
    public function test_everything_else_destructive_is_still_rejected(string $sql): void
    {
        $this->expectExceptionMessage('Migration 包含破坏性语句');
        $this->guard($sql);
    }
}
