<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\SqlStatements;
use PHPUnit\Framework\TestCase;

final class SqlStatementsTest extends TestCase
{
    public function test_postgresql_quotes_comments_and_dollar_strings_are_not_split(): void
    {
        $sql = <<<'SQL'
-- 注释 ;
SELECT '中文;--保留', E'转义\';字符', "a;b";
/* 外层 ; /* 内层 */ */ SELECT $$字符串;--不删除$$;
DO $body$ BEGIN PERFORM 'hello;'; END $body$;
SELECT 'it''s;ok';
SQL;
        $parts = SqlStatements::split($sql, 'pgsql');
        self::assertCount(4, $parts);
        self::assertStringContainsString('中文;--保留', $parts[0]);
        self::assertStringContainsString('$$字符串;--不删除$$', $parts[1]);
        self::assertStringContainsString("PERFORM 'hello;'", $parts[2]);
        self::assertSame("SELECT 'it''s;ok';", $parts[3]);
    }

    public function test_unclosed_sql_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        SqlStatements::split('SELECT $tag$unfinished', 'pgsql');
    }
}
