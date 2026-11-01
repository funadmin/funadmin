<?php
declare(strict_types=1);
namespace app\common\service;
use RuntimeException;

/** 126 专用补偿器：只按固定 HEX 白名单改变注释，绝不从历史迁移重建列。 */
final class CommentEncodingRepair
{
    public const VERSION = '126_comment_encoding_compensation';

    public static function identifier(string $name): string
    {
        if (!preg_match('/\A[A-Za-z0-9_]+\z/', $name)) throw new RuntimeException('不安全的结构标识符');
        return '`' . $name . '`';
    }

    /** 跳过所有字符串、标识符和版本注释，只定位真正的 COMMENT 字符串。 */
    public static function replaceComment(string $definition, string $literal, bool $noBackslash): string
    {
        $spans = self::commentSpans($definition, $noBackslash);
        if (count($spans) !== 1) throw new RuntimeException('列定义必须有且只有一个 COMMENT');
        [$start, $length] = $spans[0];
        return substr_replace($definition, $literal, $start, $length);
    }

    private static function commentSpans(string $sql, bool $noBackslash): array
    {
        $spans = [];
        $length = strlen($sql);
        for ($i = 0; $i < $length;) {
            if (substr($sql, $i, 2) === '/*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) throw new RuntimeException('未闭合版本注释');
                $i = $end + 2;
                continue;
            }
            if (in_array($sql[$i], ["'", '"', '`'], true)) {
                $i = self::quotedEnd($sql, $i, $noBackslash);
                continue;
            }
            if (preg_match('/\GCOMMENT\b\s*(?:=\s*)?/Ai', $sql, $match, 0, $i)
                && ($i === 0 || !preg_match('/[A-Za-z0-9_]/', $sql[$i - 1]))) {
                $start = $i + strlen($match[0]);
                if (($sql[$start] ?? '') !== "'") throw new RuntimeException('不支持的 COMMENT literal');
                $end = self::quotedEnd($sql, $start, $noBackslash);
                $spans[] = [$start, $end - $start];
                $i = $end;
                continue;
            }
            $i++;
        }
        return $spans;
    }

    private static function quotedEnd(string $sql, int $start, bool $noBackslash): int
    {
        $quote = $sql[$start];
        for ($i = $start + 1, $length = strlen($sql); $i < $length; $i++) {
            if ($sql[$i] === '\\' && !$noBackslash && $quote !== '`') { $i++; continue; }
            if ($sql[$i] !== $quote) continue;
            if (($sql[$i + 1] ?? '') === $quote) { $i++; continue; }
            return $i + 1;
        }
        throw new RuntimeException('未闭合 SQL 字符串');
    }

    public static function fingerprint(string $create, bool $noBackslash): string
    {
        foreach (array_reverse(self::commentSpans($create, $noBackslash)) as [$start, $length]) {
            $create = substr_replace($create, "''", $start, $length);
        }
        // SHOW CREATE 在 MODIFY 后可能补出原本由 COLLATE 隐含的 CHARACTER SET。
        // 只规范化列类型后的冗余声明，默认值、表达式、索引等内容原样参与指纹。
        $create = preg_replace('/^(  `(?:``|[^`])+` (?:varchar|char|text|tinytext|mediumtext|longtext)\b(?:\([^\n]*?\))?) CHARACTER SET ([a-z0-9]+) COLLATE (\2_[a-z0-9_]+)/m', '$1 COLLATE $3', $create);
        $create = preg_replace('/^(  `(?:``|[^`])+` (?:enum|set)\((?:\x27(?:\x27\x27|\\\\.|[^\x27])*\x27,?)+\)) CHARACTER SET ([a-z0-9]+) COLLATE (\2_[a-z0-9_]+)/m', '$1 COLLATE $3', $create);
        return hash('sha256', $create);
    }

    public static function createSql(object $connection, string $table): string
    {
        $row = $connection->query('SHOW CREATE TABLE ' . self::identifier($table), [], true)[0];
        if (!isset($row['Create Table'])) throw new RuntimeException('目标不是基础表');
        return $row['Create Table'];
    }

    public static function currentHex(object $connection, array $item): string
    {
        $rows = $item['c'] === ''
            ? $connection->query("SELECT HEX(TABLE_COMMENT) h FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND TABLE_TYPE='BASE TABLE'", [$item['t']], true)
            : $connection->query('SELECT HEX(COLUMN_COMMENT) h FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$item['t'], $item['c']], true);
        if (count($rows) !== 1) throw new RuntimeException('白名单目标缺失：' . $item['t'] . '.' . $item['c']);
        return $rows[0]['h'];
    }

    public static function plan(object $connection, array $items): array
    {
        DdlEncodingGuard::check($connection, 'SELECT 1');
        $mode = $connection->query('SELECT @@sql_mode mode', [], true)[0]['mode'];
        $noBackslash = in_array('NO_BACKSLASH_ESCAPES', explode(',', $mode), true);
        $plans = [];
        $seen = [];
        foreach ($items as $item) {
            $key = $item['t'] . '.' . $item['c'];
            if (isset($seen[$key])) throw new RuntimeException('白名单重复');
            $seen[$key] = true;
            foreach (['old_hex', 'target_hex'] as $field) {
                if (!preg_match('/\A(?:[0-9A-F]{2})+\z/', $item[$field])) throw new RuntimeException('无效 HEX 白名单');
                DdlEncodingGuard::assertUtf8(hex2bin($item[$field]));
            }
            // MySQL 8 元数据注释不能无损保存四字节字符，非严格模式会静默替换成问号。
            if (preg_match('/[\x{10000}-\x{10FFFF}]/u', hex2bin($item['target_hex']))) {
                throw new RuntimeException('COMMENT 不支持四字节字符');
            }
            $hex = self::currentHex($connection, $item);
            if ($hex !== $item['old_hex'] && $hex !== $item['target_hex']) throw new RuntimeException('未知注释，拒绝执行：' . $key);
            $create = self::createSql($connection, $item['t']);
            // COMMENT 语法不接受任意表达式；用服务端 HEX 构造后按实际 SQL mode 转义为字符串。
            $literal = $connection->query("SELECT CONVERT(UNHEX(?) USING utf8mb4) value", [$item['target_hex']], true)[0]['value'];
            $literal = "'" . ($noBackslash ? str_replace("'", "''", $literal) : strtr($literal, ["\\"=>"\\\\", "'"=>"\\'", "\0"=>"\\0", "\n"=>"\\n", "\r"=>"\\r", "\x1a"=>"\\Z"])) . "'";
            $definition = '';
            if ($item['c'] !== '') {
                $prefix = '  ' . self::identifier($item['c']) . ' ';
                $matches = array_values(array_filter(explode("\n", $create), static fn ($line) => str_starts_with($line, $prefix)));
                if (count($matches) !== 1) throw new RuntimeException('无法唯一提取当前完整列定义：' . $key);
                $definition = rtrim(trim($matches[0]), ',');
                $sql = 'ALTER TABLE ' . self::identifier($item['t']) . ' MODIFY COLUMN ' . self::replaceComment($definition, $literal, $noBackslash);
            } else {
                $sql = 'ALTER TABLE ' . self::identifier($item['t']) . ' COMMENT = ' . $literal;
            }
            $plans[] = $item + ['current_hex'=>$hex, 'definition'=>$definition, 'create'=>$create, 'fingerprint'=>self::fingerprint($create, $noBackslash), 'sql'=>$sql, 'no_backslash'=>$noBackslash];
        }
        return $plans;
    }

    /** 先完整预检，再逐条复核；DDL 隐式提交后失败可依据旧/目标 HEX 续跑。 */
    public static function apply(object $connection, array $items): array
    {
        $plans = self::plan($connection, $items);
        $result = ['repaired'=>0, 'skipped'=>0];
        foreach ($plans as $plan) {
            DdlEncodingGuard::check($connection, $plan['sql']);
            $hex = self::currentHex($connection, $plan);
            if ($hex === $plan['target_hex']) { $result['skipped']++; continue; }
            if ($hex !== $plan['old_hex']) throw new RuntimeException('执行前注释发生并发变化');
            if (self::fingerprint(self::createSql($connection, $plan['t']), $plan['no_backslash']) !== $plan['fingerprint']) {
                throw new RuntimeException('执行前结构发生并发变化');
            }
            $connection->execute($plan['sql']);
            if (self::currentHex($connection, $plan) !== $plan['target_hex']
                || self::fingerprint(self::createSql($connection, $plan['t']), $plan['no_backslash']) !== $plan['fingerprint']) {
                throw new RuntimeException('注释修复后结构或 HEX 校验失败：' . $plan['t'] . '.' . $plan['c']
                    . ' hex=' . (self::currentHex($connection, $plan) === $plan['target_hex'] ? 'ok' : 'different'));
            }
            $result['repaired']++;
        }
        return $result;
    }
}
