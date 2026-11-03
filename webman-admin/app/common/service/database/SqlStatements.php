<?php

declare(strict_types=1);

namespace app\common\service\database;

use RuntimeException;

final class SqlStatements
{
    public static function split(string $sql, string $driver = 'mysql'): array
    {
        $parts = [];
        $buffer = '';
        $quote = '';
        $dollar = '';
        $block = 0;
        $line = false;
        $escape = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';
            if ($line) {
                if ($char === "\n") { $line = false; $buffer .= "\n"; }
                continue;
            }
            if ($block > 0) {
                if ($char === '/' && $next === '*') { $block++; $i++; }
                elseif ($char === '*' && $next === '/') { $block--; $i++; $buffer .= ' '; }
                continue;
            }
            if ($dollar !== '') {
                if (substr($sql, $i, strlen($dollar)) === $dollar) {
                    $buffer .= $dollar;
                    $i += strlen($dollar) - 1;
                    $dollar = '';
                } else $buffer .= $char;
                continue;
            }
            if ($quote !== '') {
                $buffer .= $char;
                if ($escape && $char === '\\') {
                    if ($next !== '') { $buffer .= $next; $i++; }
                } elseif ($char === $quote) {
                    if ($next === $quote) { $buffer .= $next; $i++; }
                    else $quote = '';
                }
                continue;
            }
            if (($char === '-' && $next === '-') || ($driver === 'mysql' && $char === '#')) { $line = true; $buffer .= ' '; continue; }
            if ($char === '/' && $next === '*') { $block = 1; $i++; $buffer .= ' '; continue; }
            if ($driver === 'pgsql' && $char === '$' && preg_match('/\A\$(?:[a-zA-Z_][a-zA-Z0-9_]*)?\$/', substr($sql, $i), $match)) {
                $dollar = $match[0];
                $buffer .= $dollar;
                $i += strlen($dollar) - 1;
                continue;
            }
            if ($char === "'" || $char === '"' || ($driver === 'mysql' && $char === '`')) {
                $quote = $char;
                $escape = $driver === 'mysql' || ($char === "'" && preg_match('/(?:^|[^a-zA-Z0-9_])[eE]$/', $buffer));
            }
            $buffer .= $char;
            if ($char === ';') {
                if (trim($buffer) !== ';') $parts[] = trim($buffer);
                $buffer = '';
            }
        }
        if ($quote !== '' || $dollar !== '' || $block > 0) throw new RuntimeException('SQL 字符串或注释未闭合');
        if (trim($buffer) !== '') $parts[] = trim($buffer);
        return $parts;
    }
}
