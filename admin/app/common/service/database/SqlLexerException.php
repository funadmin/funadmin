<?php

namespace app\common\service\database;

use RuntimeException;

/** SQL 无法按 MySQL 规则无歧义地分词（未闭合的字符串/注释、可执行注释边界依赖服务器版本等）。 */
final class SqlLexerException extends RuntimeException
{
}
