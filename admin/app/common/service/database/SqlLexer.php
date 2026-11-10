<?php

namespace app\common\service\database;

/**
 * 按 MySQL 服务器词法规则逐字符切分 SQL（不使用正则）。迁移守卫、DDL 判定与语句切分共用这一份词法结果，
 * 保证“检查的”与“执行的”是同一种切分。
 *
 * - 字符串 '…'、"…"：支持反斜杠转义与重复引号；反引号标识符支持 `` 转义；内部的注释标记、分号一律是内容。
 * - 块注释在第一个 *\/ 结束（MySQL 不支持嵌套），其中的 /*! 等标记都是注释内容。
 * - 可执行注释 /*!NNNNN … *\/ 的注释体按代码分词（children）。版本号高于服务器时 MySQL 把它当普通注释、
 *   在第一个 *\/ 结束；两种读法的结束位置不同则报错，注释体内出现分号或嵌套 /*! 也报错。
 * - -- 注释仅当其后是空白/控制字符或输入结尾（"1--1" 是算术）；# 注释到行尾。
 * - 未闭合的字符串、标识符、注释，代码中的 $（MySQL 9 的 $$ 引用会改变引号边界）一律报错，调用方据此拒绝。
 */
final class SqlLexer
{
    public const WORD = 'word';
    public const IDENTIFIER = 'identifier';
    public const STRING = 'string';
    public const SYMBOL = 'symbol';

    /** 代码状态下需要逐字符判断的字节；其余字节成段并入代码。 */
    private const CODE_SPECIAL = "'\"`/-#;*\$";

    /**
     * 逐个产出词法单元（大文件不必整体驻留内存）；词法错误在迭代到出错位置时抛出 SqlLexerException。
     * @return \Generator<int, SqlToken>
     */
    public static function scan(string $sql): \Generator
    {
        $position = 0;
        yield from self::lex($sql, $position, false);
    }

    /**
     * @return list<SqlToken>
     * @throws SqlLexerException
     */
    public static function tokenize(string $sql): array
    {
        return iterator_to_array(self::scan($sql), false);
    }

    /**
     * 字符串常量的值：去掉两端引号，重复引号还原为一个，反斜杠转义按 MySQL 规则（\% 与 \_ 保留反斜杠）。
     */
    public static function unescape(SqlToken $token): string
    {
        if ($token->type !== SqlToken::SINGLE_QUOTED && $token->type !== SqlToken::DOUBLE_QUOTED) {
            throw new SqlLexerException('只能反转义字符串常量');
        }
        $quote = $token->text[0];
        $body = substr($token->text, 1, -1);
        $length = strlen($body);
        $value = '';
        for ($index = 0; $index < $length; $index++) {
            $plain = strcspn($body, $quote . '\\', $index);
            if ($plain > 0) {
                $value .= substr($body, $index, $plain);
                $index += $plain - 1;
                continue;
            }
            $char = $body[$index];
            $next = $body[$index + 1] ?? '';
            $index++;
            if ($char === $quote) {
                $value .= $quote;
                continue;
            }
            $value .= match ($next) {
                '0' => "\0",
                'b' => "\x08",
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'Z' => "\x1A",
                '%', '_' => '\\' . $next,
                default => $next,
            };
        }
        return $value;
    }

    /**
     * 有意义的词法项（注释与空白丢弃，可执行注释展开）：WORD 为标识符字符（字母、数字、_、$、非 ASCII 字节）连续段，
     * IDENTIFIER 为反引号标识符的名字，STRING 为字符串常量原文，SYMBOL 为其他单个字符（含分隔符 ;）。
     * @param iterable<SqlToken> $tokens
     * @return \Generator<int, array{0: string, 1: string}>
     */
    public static function items(iterable $tokens): \Generator
    {
        foreach ($tokens as $token) {
            switch ($token->type) {
                case SqlToken::CODE:
                    $text = $token->text;
                    $length = strlen($text);
                    for ($index = 0; $index < $length;) {
                        $char = $text[$index];
                        if (self::isIdentifierByte($char)) {
                            $start = $index;
                            while ($index < $length && self::isIdentifierByte($text[$index])) $index++;
                            yield [self::WORD, substr($text, $start, $index - $start)];
                            continue;
                        }
                        if (!ctype_space($char)) yield [self::SYMBOL, $char];
                        $index++;
                    }
                    break;
                case SqlToken::BACKTICK:
                    yield [self::IDENTIFIER, str_replace('``', '`', substr($token->text, 1, -1))];
                    break;
                case SqlToken::SINGLE_QUOTED:
                case SqlToken::DOUBLE_QUOTED:
                    yield [self::STRING, $token->text];
                    break;
                case SqlToken::EXECUTABLE_COMMENT:
                    yield from self::items($token->children);
                    break;
                case SqlToken::SEPARATOR:
                    yield [self::SYMBOL, ';'];
                    break;
            }
        }
    }

    /**
     * 全部字符串常量（含可执行注释体内的），按出现顺序。
     * @param iterable<SqlToken> $tokens
     * @return \Generator<int, SqlToken>
     */
    public static function strings(iterable $tokens): \Generator
    {
        foreach ($tokens as $token) {
            if ($token->type === SqlToken::SINGLE_QUOTED || $token->type === SqlToken::DOUBLE_QUOTED) {
                yield $token;
            } elseif ($token->type === SqlToken::EXECUTABLE_COMMENT) {
                yield from self::strings($token->children);
            }
        }
    }

    private static function isIdentifierByte(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '$' || ord($char) >= 0x80;
    }

    /**
     * @return \Generator<int, SqlToken>
     * @throws SqlLexerException
     */
    private static function lex(string $sql, int &$position, bool $executable): \Generator
    {
        $length = strlen($sql);
        $code = '';
        $codeOffset = $position;
        while ($position < $length) {
            $plain = strcspn($sql, self::CODE_SPECIAL, $position);
            if ($plain > 0) {
                if ($code === '') $codeOffset = $position;
                $code .= substr($sql, $position, $plain);
                $position += $plain;
                continue;
            }
            $char = $sql[$position];
            $next = $sql[$position + 1] ?? '';
            $token = null;
            if ($char === "'" || $char === '"') {
                $token = self::quoted($sql, $position, $char, true);
            } elseif ($char === '`') {
                $token = self::quoted($sql, $position, '`', false);
            } elseif ($char === '/' && $next === '*') {
                $token = self::comment($sql, $position, $executable);
            } elseif ($char === '-' && $next === '-' && self::endsDashMarker($sql, $position + 2)) {
                $token = self::lineComment($sql, $position, SqlToken::DASH_COMMENT);
            } elseif ($char === '#') {
                $token = self::lineComment($sql, $position, SqlToken::HASH_COMMENT);
            } elseif ($char === ';') {
                if ($executable) throw new SqlLexerException('可执行注释内不允许语句分隔符（偏移 ' . $position . '）');
                $token = new SqlToken(SqlToken::SEPARATOR, ';', $position);
            } elseif ($char === '*' && $next === '/' && $executable) {
                if ($code !== '') yield new SqlToken(SqlToken::CODE, $code, $codeOffset);
                $position += 2;
                return;
            } elseif ($char === '$') {
                throw new SqlLexerException('代码中不允许 $（偏移 ' . $position . '）');
            }
            if ($token === null) {
                if ($code === '') $codeOffset = $position;
                $code .= $char;
                $position++;
                continue;
            }
            if ($code !== '') {
                yield new SqlToken(SqlToken::CODE, $code, $codeOffset);
                $code = '';
            }
            yield $token;
            $position = $token->offset + strlen($token->text);
        }
        if ($executable) throw new SqlLexerException('可执行注释未闭合');
        if ($code !== '') yield new SqlToken(SqlToken::CODE, $code, $codeOffset);
    }

    /** MySQL：-- 后须是空白或控制字符（含输入结尾）才是注释。 */
    private static function endsDashMarker(string $sql, int $position): bool
    {
        if ($position >= strlen($sql)) return true;
        $byte = ord($sql[$position]);
        return $byte <= 0x20 || $byte === 0x7F;
    }

    private static function quoted(string $sql, int $start, string $quote, bool $backslash): SqlToken
    {
        $length = strlen($sql);
        $stops = $backslash ? $quote . '\\' : $quote;
        $index = $start + 1;
        while (true) {
            if ($index < $length) $index += strcspn($sql, $stops, $index);
            if ($index >= $length) {
                throw new SqlLexerException('未闭合的 ' . $quote . ' 引号（偏移 ' . $start . '）');
            }
            if ($sql[$index] === '\\') {
                $index += 2;
                continue;
            }
            if (($sql[$index + 1] ?? '') === $quote) {
                $index += 2;
                continue;
            }
            $type = match ($quote) {
                "'" => SqlToken::SINGLE_QUOTED,
                '"' => SqlToken::DOUBLE_QUOTED,
                default => SqlToken::BACKTICK,
            };
            return new SqlToken($type, substr($sql, $start, $index + 1 - $start), $start);
        }
    }

    private static function lineComment(string $sql, int $start, string $type): SqlToken
    {
        $end = strpos($sql, "\n", $start);
        $end = $end === false ? strlen($sql) : $end;
        return new SqlToken($type, substr($sql, $start, $end - $start), $start);
    }

    private static function comment(string $sql, int $start, bool $insideExecutable): SqlToken
    {
        $marker = $sql[$start + 2] ?? '';
        if ($marker !== '!') {
            $end = strpos($sql, '*/', $start + 2);
            if ($end === false) throw new SqlLexerException('未闭合的块注释（偏移 ' . $start . '）');
            $text = substr($sql, $start, $end + 2 - $start);
            // 优化器提示 /*+ … */ 由独立的提示词法器解析，其引号规则不保证与此一致。
            if ($marker === '+' && strcspn($text, "'\"`") !== strlen($text)) {
                throw new SqlLexerException('优化器提示中不允许引号（偏移 ' . $start . '）');
            }
            return new SqlToken(SqlToken::BLOCK_COMMENT, $text, $start);
        }
        if ($insideExecutable) throw new SqlLexerException('不支持嵌套的可执行注释（偏移 ' . $start . '）');
        $position = $start + 3;
        $version = '';
        if (($sql[$position] ?? '') === 'M' && ctype_digit($sql[$position + 1] ?? '')) {
            $version = 'M';
            $position++;
        }
        $digits = strspn($sql, '0123456789', $position);
        $version .= substr($sql, $position, $digits);
        $position += $digits;
        $bodyStart = $position;
        $children = iterator_to_array(self::lex($sql, $position, true), false);
        if (self::skippedCommentEnd($sql, $bodyStart) !== $position) {
            throw new SqlLexerException('可执行注释的结束位置取决于服务器版本（偏移 ' . $start . '）');
        }
        return new SqlToken(SqlToken::EXECUTABLE_COMMENT, substr($sql, $start, $position - $start), $start, $children, $version);
    }

    /** 版本号高于服务器时 MySQL 跳过可执行注释：允许一层嵌套 /* *\/，在其后的第一个 *\/ 结束（consume_comment）。 */
    private static function skippedCommentEnd(string $sql, int $position): ?int
    {
        $nested = false;
        while (true) {
            $close = strpos($sql, '*/', $position);
            $open = $nested ? false : strpos($sql, '/*', $position);
            if ($close === false) return null;
            if ($open !== false && $open < $close) {
                $nested = true;
                $position = $open + 2;
                continue;
            }
            if ($nested) {
                $nested = false;
                $position = $close + 2;
                continue;
            }
            return $close + 2;
        }
    }
}
