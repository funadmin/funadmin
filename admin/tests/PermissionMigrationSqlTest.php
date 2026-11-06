<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * MySQL 核心迁移 025/027 由 tools/permission-migrations 下的脚本按冻结映射生成。
 * 冻结部分（授权展开、改名、退役、菜单改绑）必须与脚本输出一致；"登记推导码"部分随代码中的方法增减而变化，不比较。
 * 授权等价在真实 MySQL 上的验证：tools/permission-migrations/verify-mysql.sh。
 */
final class PermissionMigrationSqlTest extends TestCase
{
    public function test_committed_sql_matches_the_generators_for_the_frozen_sections(): void
    {
        $root = dirname(__DIR__);
        foreach ([
            ['gen-plugin-sql.php', '025_plugin_permission_per_method.sql', '-- 展开授权', null],
            ['gen-host-sql.php', '027_host_permission_per_method.sql', '-- 1)', '-- 3)'],
            ['gen-host-sql.php', '027_host_permission_per_method.sql', '-- 4)', null],
            ['gen-host-sql.php', '027_host_permission_per_method.sql', '-- 5)', null],
        ] as [$generator, $migration, $from, $to]) {
            $output = tempnam(sys_get_temp_dir(), 'permsql');
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tools/permission-migrations/' . $generator) . ' ' . escapeshellarg($output) . ' 2>&1', $lines, $code);
            self::assertSame(0, $code, implode("\n", $lines));
            $section = static function (string $sql) use ($from, $to): string {
                $start = strpos($sql, $from);
                self::assertNotFalse($start, $from);
                $end = $to === null ? strlen($sql) : strpos($sql, $to, $start);
                return substr($sql, $start, $end - $start);
            };
            self::assertSame($section((string) file_get_contents($output)), $section((string) file_get_contents($root . '/database/core/migrations/archive/baseline-2026-11-06/' . $migration)), "$migration $from");
            unlink($output);
        }
    }
}
