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
        yield 'line comment marker inside string' => ["INSERT INTO `fun_probe` VALUES ('--');\nDROP TABLE x;\nINSERT INTO `fun_probe` VALUES ('a');"];
        yield 'block comment markers inside strings' => ["SELECT '/*';\nDROP TABLE t;\nSELECT '*/';"];
        yield 'comment opener in string before plain drop' => ["SET @z='/*';\nDROP TABLE fun_admin;\nSET @y='*/';"];
        yield 'block comment between keywords' => ['ALTER TABLE `fun_admin` DROP/**/COLUMN `email`;'];
        yield 'executable comment' => ['/*!50001 DROP TABLE `fun_admin` */;'];
        yield 'double dash without space is not a comment' => ['SELECT 1 --1; DROP TABLE `fun_admin`;'];
        yield 'doubled quote inside string' => ["INSERT INTO `fun_probe` VALUES ('it''s -- ok');\nDROP TABLE x;"];
        // A：MySQL 的块注释在第一个 */ 结束，注释里的 /*! 不开启可执行注释；字符串里的 /*! 也不是注释。
        yield 'executable marker inside block comment' => ['/* note /*! */ DROP TABLE fun_admin; /* end */'];
        yield 'executable marker inside string' => ["SELECT '/*!';\n/* c */ DROP TABLE fun_admin;\n/* x */"];
        // C：超长字符串常量（PCRE JIT 栈上限）不能让守卫放行其后的语句。
        yield 'long literal before drop' => ["INSERT INTO t VALUES ('" . str_repeat('a', 10000) . "');\nDROP TABLE fun_admin;"];
        yield 'executable comment version glued to keyword' => ['/*!50001DROP TABLE `fun_admin` */;'];
        yield 'executable comment with mysql prefix' => ['/*!M50001 DROP TABLE `fun_admin` */;'];
        yield 'float literal glued to keyword' => ['SELECT 1e1DROP TABLE `fun_admin`;'];
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
        yield 'block comment inside dynamic string' => ["SET @s='ALTER TABLE t DROP/**/COLUMN c';\n" . $run];
        yield 'comment opener in string hides dynamic drop' => ["SET @z='/*';\nSET @s='DROP TABLE fun_admin';\nSET @y='*/';\n" . $run];
        // A：字符串里的 /*! 不开启注释，后面的动态 DROP 照常检查。
        yield 'executable marker inside string hides dynamic drop' => ["SET @z='/*!';\n/* c */ SET @s='DROP TABLE fun_admin'; /* x */\n" . $run];
        // B：动态 SQL 字符串反转义后按 SQL 重新分词，里面的字符串常量不能把真实的 DROP 藏进“注释”。
        yield 'nested literal with hash' => ["SET @s='ALTER TABLE t ADD COLUMN x INT COMMENT ''#'', DROP COLUMN c';\n" . $run];
        yield 'nested literal with dash comment marker' => ["SET @s='ALTER TABLE t ADD COLUMN x INT COMMENT ''-- '', DROP COLUMN c';\n" . $run];
        yield 'nested literals with block comment markers' => ["SET @s='ALTER TABLE t ADD COLUMN x INT COMMENT ''/*'', DROP COLUMN c, ADD COLUMN y INT COMMENT ''*/''';\n" . $run];
        yield 'nested backslash-escaped literals with block comment markers' => ["SET @s='ALTER TABLE t ADD COLUMN x INT COMMENT \\'/*\\', DROP COLUMN c, ADD COLUMN y INT COMMENT \\'*/\\'';\n" . $run];
        yield 'nested single quotes inside double quoted string' => ["SET @s=\"ALTER TABLE t COMMENT '#', DROP COLUMN c\";\n" . $run];
        yield 'drop inside nested literal' => ["SET @s='SELECT ''DROP TABLE fun_admin''';\n" . $run];
        yield 'drop key is not drop index' => ["SET @s='ALTER TABLE t DROP KEY `k`';\n" . $run];
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

    public function test_ddl_detection_skips_leading_comments_and_reads_executable_comments(): void
    {
        $service = new MigrationService();
        $hasDdl = static fn (string $sql): bool => (new \ReflectionMethod(MigrationService::class, 'hasDdl'))->invoke($service, (new \ReflectionMethod(MigrationService::class, 'statements'))->invoke($service, $sql));
        self::assertTrue($hasDdl("/* header */ CREATE TABLE `fun_x` (`id` INT);"));
        self::assertTrue($hasDdl("/* a */\n/* b */ ALTER TABLE `fun_x` ADD COLUMN `y` INT;"));
        self::assertTrue($hasDdl("/*!50001 CREATE VIEW `fun_v` AS SELECT 1 */;"));
        self::assertTrue($hasDdl("# note\nCREATE TABLE `fun_x` (`id` INT);"));
        self::assertFalse($hasDdl("/* CREATE TABLE in comment */ UPDATE `fun_x` SET `a`=1;"));
        self::assertFalse($hasDdl("SELECT '/*'; UPDATE `fun_x` SET `a`='*/';"));
        // A：块注释在第一个 */ 结束，注释里的 /*! 不影响后面的 CREATE。
        self::assertTrue($hasDdl('/* x /*! */ CREATE TABLE t (id int)'));
        self::assertTrue($hasDdl('/*!50001CREATE VIEW `fun_v` AS SELECT 1 */;'));
        self::assertFalse($hasDdl("# CREATE TABLE t\nUPDATE `fun_x` SET `a`=1;"));
    }

    public function test_core_migration_005_is_classified_as_ddl(): void
    {
        $service = new MigrationService();
        $sql = (string) file_get_contents(base_path() . '/database/core/migrations/005_soft_delete.sql');
        self::assertTrue((new \ReflectionMethod(MigrationService::class, 'hasDdl'))->invoke($service, $this->statements($sql)));
    }

    /** C：200 KB 的字符串常量后跟 DROP TABLE 也必须拒绝（不依赖正则，也不能因内部错误放行）。 */
    public function test_huge_literal_followed_by_drop_is_rejected(): void
    {
        $this->expectExceptionMessage('Migration 包含破坏性语句');
        $this->guard("INSERT INTO `fun_probe` VALUES ('" . str_repeat("ab\\'c''d", 200 * 1024 / 8) . "');\nDROP TABLE `fun_admin`;");
    }

    /** @return iterable<string, array{string}> */
    public static function unparsable(): iterable
    {
        yield 'unterminated single quote' => ["INSERT INTO t VALUES ('abc);\nDROP TABLE fun_admin;"];
        yield 'unterminated double quote' => ['SELECT "abc'];
        yield 'unterminated backtick' => ['SELECT `abc'];
        yield 'unterminated block comment' => ['SELECT 1; /* DROP TABLE fun_admin;'];
        yield 'unterminated executable comment' => ['/*!50001 SELECT 1;'];
        yield 'separator inside executable comment' => ['/*!50001 SELECT 1; SELECT 2 */;'];
        yield 'executable comment end hidden in string' => ["/*!99999 '*/ DROP TABLE fun_admin; -- ' */;"];
        yield 'trailing backslash in string' => ["SELECT 'abc\\"];
        yield 'dollar quoting' => ['SELECT $$ \'$$; DROP TABLE fun_admin; -- \';'];
        yield 'dynamic literal fragment does not lex' => ["SET @s=CONCAT('ALTER TABLE t COMMENT ', '''', 'x');\nPREPARE s FROM @s;\nEXECUTE s;"];
    }

    /** @return iterable<string, array{string}> */
    public static function sessionLexingChanges(): iterable
    {
        $run = "\nPREPARE s FROM @s;\nEXECUTE s;";
        // sql_mode（ANSI_QUOTES / NO_BACKSLASH_ESCAPES）与客户端字符集（GBK 的 0x5C 尾字节）都会让 MySQL 看到不同的字符串边界。
        yield 'sql_mode bare' => ["SET sql_mode='NO_BACKSLASH_ESCAPES';"];
        yield 'sql_mode session scope' => ["SET SESSION SQL_MODE='ANSI_QUOTES';"];
        yield 'sql_mode backticked' => ["SET `sql_mode`='NO_BACKSLASH_ESCAPES';"];
        yield 'sql_mode system variable' => ["SET @@sql_mode='NO_BACKSLASH_ESCAPES';"];
        yield 'sql_mode session variable backticked' => ["SET @@SESSION.`sql_mode`='NO_BACKSLASH_ESCAPES';"];
        yield 'sql_mode global variable' => ["SET @@GLOBAL.sql_mode='ANSI';"];
        yield 'sql_mode in executable comment' => ["/*!40101 SET SQL_MODE='NO_BACKSLASH_ESCAPES' */;"];
        yield 'sql_mode in dynamic sql' => ["SET @s='SET sql_mode=\"NO_BACKSLASH_ESCAPES\"';" . $run];
        yield 'sql_mode backticked in dynamic sql' => ["SET @s='SET @@SESSION.`sql_mode`=''ANSI_QUOTES''';" . $run];
        yield 'sql_mode in nested dynamic literal' => ["SET @s='SELECT ''SET sql_mode=1''';" . $run];
        yield 'set names' => ["SET NAMES gbk;\nALTER TABLE t COMMENT '中\\', DROP COLUMN c -- ';"];
        yield 'set names lower case quoted' => ["set names 'gbk';"];
        yield 'set names after other assignment' => ['SET @a=1, NAMES gbk;'];
        yield 'set names in executable comment' => ['/*!40101 SET NAMES gbk */;'];
        yield 'set character set' => ['SET CHARACTER SET gbk;'];
        yield 'set charset' => ['SET CHARSET gbk;'];
        yield 'character set in procedure body' => ['CREATE PROCEDURE p() SET CHARACTER SET gbk;'];
        yield 'character_set_client' => ['SET character_set_client=gbk;'];
        yield 'character_set_client session' => ['SET SESSION character_set_client=gbk;'];
        yield 'character_set_client system variable' => ['SET @@character_set_client=gbk;'];
        yield 'character_set_client session variable' => ['SET @@SESSION.character_set_client=gbk;'];
        yield 'character_set_connection global backticked' => ['SET @@GLOBAL.`character_set_connection`=gbk;'];
        yield 'character_set_client backticked' => ['SET `CHARACTER_SET_CLIENT`=gbk;'];
        yield 'set names in dynamic sql' => ["SET @s='SET NAMES gbk';" . $run];
        yield 'character set in dynamic sql' => ["SET @s='SET CHARACTER SET gbk';" . $run];
        yield 'character_set_client in dynamic sql' => ["SET @s='SET @@SESSION.character_set_client=gbk';" . $run];
        yield 'character_set_client in nested dynamic literal' => ["SET @s='SELECT \"SET `character_set_client`=gbk\"';" . $run];
        // MySQL 按 utf8mb3_general_ci 折叠变量名：非 ASCII 的同形写法同样生效，代码里的非 ASCII 词/标识符一律拒绝（静态与动态 SQL）。
        foreach ([
            'long s sql_mode' => "SET ſql_mode='NO_BACKSLASH_ESCAPES'",
            'long s sql_mode backticked' => "SET `ſql_mode`='NO_BACKSLASH_ESCAPES'",
            'accented sql_mode' => "SET sql_mòde='NO_BACKSLASH_ESCAPES'",
            'long s character_set_client' => 'SET character_ſet_client=gbk',
            'accented character_set_client session variable' => 'SET @@SESSION.charactér_set_client=gbk',
        ] as $name => $statement) {
            yield $name => [$statement . ';'];
            yield $name . ' in dynamic sql' => ["SET @s='" . str_replace("'", "''", $statement) . "';" . $run];
            yield $name . ' in nested dynamic literal' => ["SET @s='SELECT \"" . str_replace("'", "''", $statement) . "\"';" . $run];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('sessionLexingChanges')]
    public function test_changing_session_lexing_state_is_rejected(string $sql): void
    {
        $this->expectExceptionMessage('Migration 无法安全解析');
        $this->guard($sql);
    }

    public function test_charset_table_and_column_options_stay_allowed(): void
    {
        $this->guard("CREATE TABLE `fun_probe` (`a` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n"
            . "CREATE TEMPORARY TABLE `fun_tmp` (`a` int) CHARSET=utf8mb4;\n"
            . "ALTER TABLE `fun_probe` MODIFY `a` varchar(30) CHARACTER SET utf8mb4 NOT NULL, DEFAULT CHARSET=utf8mb4;\n"
            . "INSERT INTO `fun_probe` VALUES ('SET NAMES gbk; sql_mode');");
        $this->addToAssertionCount(1);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unparsable')]
    public function test_sql_the_lexer_cannot_read_is_rejected(string $sql): void
    {
        $this->expectExceptionMessage('Migration 无法安全解析');
        $this->guard($sql);
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function splitterMismatch(): iterable
    {
        // D：旧切分器在字符串内删除 -- 行，或把注释里的引号当字符串，执行的语句与守卫看到的不同。
        yield 'dash line inside multi-line string' => [
            "SELECT 'a\n-- ';\nSELECT ';\nDROP TABLE fun_admin;\n';",
            ["SELECT 'a\n-- ';", "SELECT ';\nDROP TABLE fun_admin;\n';"],
        ];
        yield 'quote inside hash comment' => [
            "# it's\nSELECT ';\nDROP TABLE fun_admin;\n';",
            ["# it's\nSELECT ';\nDROP TABLE fun_admin;\n';"],
        ];
        yield 'quote inside block comment' => [
            "/* it's */ SELECT ';\nDROP TABLE fun_admin;\n';",
            ["/* it's */ SELECT ';\nDROP TABLE fun_admin;\n';"],
        ];
    }

    /** D：守卫放行的文件，切分后执行的每一条语句单独过守卫也必须放行，且不会出现独立的 DROP 语句。 */
    #[\PHPUnit\Framework\Attributes\DataProvider('splitterMismatch')]
    public function test_executed_statements_are_exactly_what_the_guard_checked(string $sql, array $expected): void
    {
        $this->guard($sql);
        $statements = $this->statements($sql);
        self::assertSame($expected, $statements);
        foreach ($statements as $statement) {
            $this->guard($statement);
        }
    }

    /** 新切分器对 001–005 产出的执行语句与旧切分器逐条一致（旧实现内联在下方作对照）。 */
    public function test_statements_match_legacy_splitter_for_core_migrations_001_to_005(): void
    {
        $directory = base_path() . '/database/core/migrations';
        foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete'] as $version) {
            $sql = (string) file_get_contents($directory . '/' . $version . '.sql');
            $legacy = self::legacyStatements($sql);
            self::assertNotSame([], $legacy, $version);
            self::assertSame($legacy, $this->statements($sql), $version . ' 的执行语句与旧切分器不一致');
            $rewritten = MigrationService::rewritePrefix($sql, 'fun_', 'custom_');
            self::assertSame(self::legacyStatements($rewritten), $this->statements($rewritten), $version . '（改写前缀后）');
        }
    }

    public function test_core_migrations_001_to_005_pass_the_guard(): void
    {
        $directory = base_path() . '/database/core/migrations';
        foreach (['001_baseline', '002_code_generator', '003_system_secret', '004_plugin_admin_web_directory', '005_soft_delete'] as $version) {
            $file = $directory . '/' . $version . '.sql';
            (new \ReflectionMethod(MigrationService::class, 'assertForwardOnly'))->invoke(new MigrationService(), (string) file_get_contents($file), $file);
        }
        $this->addToAssertionCount(5);
    }

    public function test_every_core_migration_passes_the_guard(): void
    {
        $files = glob(base_path() . '/database/core/migrations/*.sql') ?: [];
        self::assertNotSame([], $files);
        foreach ($files as $file) {
            (new \ReflectionMethod(MigrationService::class, 'assertForwardOnly'))->invoke(new MigrationService(), (string) file_get_contents($file), $file);
        }
        $this->addToAssertionCount(count($files));
    }

    private function statements(string $sql): array
    {
        return (new \ReflectionMethod(MigrationService::class, 'statements'))->invoke(new MigrationService(), $sql);
    }

    /** 52329d3a/b0f3a9ad 的 MigrationService::statements() 原样拷贝，仅用于对照 001–005 的切分结果。 */
    private static function legacyStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = [];
        $buffer = '';
        $quoted = false;
        $quote = '';
        $escaped = false;
        $length = strlen($sql);
        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $buffer .= $char;
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\' && $quoted) {
                $escaped = true;
                continue;
            }
            if (($char === "'" || $char === '"') && (!$quoted || $quote === $char)) {
                $quoted = !$quoted;
                $quote = $quoted ? $char : '';
                continue;
            }
            if ($char === ';' && !$quoted) {
                if (trim($buffer) !== ';') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }

    public function test_comment_like_text_in_strings_without_dynamic_sql_stays_allowed(): void
    {
        $this->guard("INSERT INTO `fun_probe` VALUES ('-- drop /* rename */ # truncate', 'it''s');\nUPDATE `fun_probe` SET `a`=1--1;");
        $this->addToAssertionCount(1);
    }
}
