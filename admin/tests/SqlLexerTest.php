<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\SqlLexer;
use app\common\service\database\SqlLexerException;
use app\common\service\database\SqlToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** MySQL 逐字符词法器：迁移守卫、DDL 判定与语句切分共用。 */
final class SqlLexerTest extends TestCase
{
    /** @return list<array{string, string}> [类型, 原文] */
    private static function shape(array $tokens): array
    {
        return array_map(static fn (SqlToken $token): array => [$token->type, $token->text], $tokens);
    }

    public function test_code_and_separator(): void
    {
        self::assertSame([
            [SqlToken::CODE, 'SELECT 1'], [SqlToken::SEPARATOR, ';'], [SqlToken::CODE, ' SELECT 2'],
        ], self::shape(SqlLexer::tokenize('SELECT 1; SELECT 2')));
    }

    public function test_single_quoted_string_with_backslash_and_doubled_quotes(): void
    {
        $tokens = SqlLexer::tokenize("SELECT 'it''s \\' -- /* # ;' x");
        self::assertSame([
            [SqlToken::CODE, 'SELECT '], [SqlToken::SINGLE_QUOTED, "'it''s \\' -- /* # ;'"], [SqlToken::CODE, ' x'],
        ], self::shape($tokens));
        self::assertSame("it's ' -- /* # ;", SqlLexer::unescape($tokens[1]));
    }

    public function test_double_quoted_string_with_backslash_and_doubled_quotes(): void
    {
        $tokens = SqlLexer::tokenize('SELECT "a""b\\"c\'d"');
        self::assertSame([[SqlToken::CODE, 'SELECT '], [SqlToken::DOUBLE_QUOTED, '"a""b\\"c\'d"']], self::shape($tokens));
        self::assertSame('a"b"c\'d', SqlLexer::unescape($tokens[1]));
    }

    public function test_unescape_follows_mysql_backslash_sequences(): void
    {
        [, $token] = SqlLexer::tokenize("SELECT '\\0\\b\\n\\r\\t\\Z\\\\\\%\\_\\q'");
        self::assertSame("\0\x08\n\r\t\x1A\\\\%\\_q", SqlLexer::unescape($token));
    }

    public function test_backtick_identifier_keeps_quotes_and_comment_markers(): void
    {
        $tokens = SqlLexer::tokenize("SELECT `a``'b -- #` FROM t");
        self::assertSame([
            [SqlToken::CODE, 'SELECT '], [SqlToken::BACKTICK, "`a``'b -- #`"], [SqlToken::CODE, ' FROM t'],
        ], self::shape($tokens));
    }

    public function test_block_comment_ends_at_first_close_and_swallows_markers(): void
    {
        self::assertSame([
            [SqlToken::BLOCK_COMMENT, "/* it's /*! -- */"], [SqlToken::CODE, ' DROP'],
        ], self::shape(SqlLexer::tokenize("/* it's /*! -- */ DROP")));
    }

    public function test_executable_comment_body_is_lexed_as_code(): void
    {
        $tokens = SqlLexer::tokenize("/*!50001 CREATE VIEW v AS SELECT 'x;#' /* c */ */;");
        self::assertSame(SqlToken::EXECUTABLE_COMMENT, $tokens[0]->type);
        self::assertSame("/*!50001 CREATE VIEW v AS SELECT 'x;#' /* c */ */", $tokens[0]->text);
        self::assertSame('50001', $tokens[0]->version);
        self::assertSame([
            [SqlToken::CODE, ' CREATE VIEW v AS SELECT '], [SqlToken::SINGLE_QUOTED, "'x;#'"], [SqlToken::CODE, ' '],
            [SqlToken::BLOCK_COMMENT, '/* c */'], [SqlToken::CODE, ' '],
        ], self::shape($tokens[0]->children));
        self::assertSame([SqlToken::SEPARATOR, ';'], self::shape($tokens)[1]);
        self::assertSame(['CREATE', 'VIEW', 'v', 'AS', 'SELECT'], array_column(array_filter(
            iterator_to_array(SqlLexer::items($tokens), false),
            static fn (array $item): bool => $item[0] === SqlLexer::WORD
        ), 1));
    }

    public function test_dash_comment_requires_whitespace_control_char_or_end(): void
    {
        self::assertSame([
            [SqlToken::CODE, 'SELECT 1'], [SqlToken::DASH_COMMENT, "-- x;"], [SqlToken::CODE, "\n"],
            [SqlToken::CODE, 'SELECT 1'], [SqlToken::DASH_COMMENT, "--\tx"], [SqlToken::CODE, "\n1--1"],
            [SqlToken::DASH_COMMENT, '--'],
        ], self::shape(array_merge(
            SqlLexer::tokenize("SELECT 1-- x;\n"),
            SqlLexer::tokenize("SELECT 1--\tx\n1--1--"),
        )));
    }

    public function test_hash_comment_runs_to_end_of_line(): void
    {
        self::assertSame([
            [SqlToken::HASH_COMMENT, "# it's; */"], [SqlToken::CODE, "\nSELECT 1"],
        ], self::shape(SqlLexer::tokenize("# it's; */\nSELECT 1")));
    }

    public function test_items_split_words_symbols_and_identifiers(): void
    {
        self::assertSame([
            [SqlLexer::WORD, 'ALTER'], [SqlLexer::WORD, 'TABLE'], [SqlLexer::IDENTIFIER, 'a`b'],
            [SqlLexer::WORD, 'DROP'], [SqlLexer::WORD, 'INDEX'], [SqlLexer::WORD, 'x_1'],
            [SqlLexer::SYMBOL, ','], [SqlLexer::STRING, "'s'"], [SqlLexer::SYMBOL, ';'],
        ], iterator_to_array(SqlLexer::items(SqlLexer::scan("ALTER TABLE `a``b` DROP/**/INDEX x_1,'s';")), false));
    }

    /** @return iterable<string, array{string}> */
    public static function errors(): iterable
    {
        yield 'single quote' => ["SELECT 'a"];
        yield 'single quote ending in backslash' => ["SELECT 'a\\'"];
        yield 'double quote' => ['SELECT "a'];
        yield 'backtick' => ['SELECT `a'];
        yield 'block comment' => ['SELECT /* a'];
        yield 'executable comment' => ['/*!50001 SELECT 1'];
        yield 'separator in executable comment' => ['/*!50001 SELECT 1; */'];
        yield 'nested executable comment' => ['/*!50001 SELECT /*!50001 1 */ */'];
        yield 'executable comment end depends on server version' => ["/*!99999 '*/' */"];
        yield 'dollar sign in code' => ['SELECT $$ 1 $$'];
    }

    #[DataProvider('errors')]
    public function test_unterminated_or_ambiguous_input_is_an_error(string $sql): void
    {
        $this->expectException(SqlLexerException::class);
        SqlLexer::tokenize($sql);
    }

    public function test_long_input_is_lexed_without_regex_limits(): void
    {
        $tokens = SqlLexer::tokenize("SELECT '" . str_repeat("a\\'b''", 100000) . "'; DROP");
        self::assertSame(SqlToken::SINGLE_QUOTED, $tokens[1]->type);
        self::assertSame([SqlToken::CODE, ' DROP'], self::shape($tokens)[3]);
    }
}
