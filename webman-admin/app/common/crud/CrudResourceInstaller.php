<?php

declare(strict_types=1);

namespace app\common\crud;

use Closure;
use RuntimeException;
use support\Db;

/**
 * 仅应用 CRUD 本次生成并经哈希绑定的权限菜单 migration。
 */
final class CrudResourceInstaller
{
    /** @var Closure(string): int */
    private readonly Closure $executor;

    /** @var Closure(callable): mixed */
    private readonly Closure $transaction;

    public function __construct(private readonly string $projectRoot, ?callable $executor = null, ?callable $transaction = null)
    {
        $this->executor = Closure::fromCallable($executor ?? static fn (string $sql): int => Db::affectingStatement($sql));
        $this->transaction = Closure::fromCallable($transaction ?? static fn (callable $operation): mixed => Db::transaction($operation));
    }

    public function apply(array $definition, array $manifest, bool $forceRefresh = false): array
    {
        $definitionHash = CrudDefinition::fromArray($definition)->hash();
        $manifestDefinitionHash = (string) ($manifest['definitionHash'] ?? '');
        if ($manifestDefinitionHash === '' || !hash_equals($manifestDefinitionHash, $definitionHash)) {
            throw new RuntimeException('Definition 哈希与生成审计不一致');
        }
        if (Db::connection()->getDriverName() === 'pgsql') return $this->applyPostgresql($definition, $manifest, $forceRefresh);
        $permission = $this->verifiedStatements($definition, $manifest, 'permissionMigration', '权限菜单 migration');
        $lang = null;
        if ((string) ($definition['generationTargets']['langMigration'] ?? '') !== '') {
            $lang = $this->verifiedStatements($definition, $manifest, 'langMigration', '语言包 migration');
        }
        $statements = $permission['statements'];
        $langStatements = $lang['statements'] ?? [];
        foreach (array_merge($statements, $langStatements) as $statement) $this->assertSafe($statement);
        if ($forceRefresh) {
            $langStatements = array_map([$this, 'forceRefreshStatement'], $langStatements);
        }
        $connection = (string) config('database.default', 'mysql');
        $prefix = (string) config('database.connections.' . $connection . '.prefix', '');
        if ($prefix !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $prefix) !== 1) {
            throw new RuntimeException('资源表前缀不合法');
        }
        // 哈希和白名单校验仍针对原始制品，只替换 SQL 标识符，不改写字符串值。
        $rewrite = static fn (string $statement): string => (string) preg_replace_callback(
            '/\x27(?:\\\\.|\x27\x27|[^\x27\\\\])*\x27|`fun_(permission|admin_menu|language_line)`/s',
            static fn (array $match): string => isset($match[1]) ? '`' . $prefix . $match[1] . '`' : $match[0],
            $statement
        );
        $statements = array_map($rewrite, $statements);
        $langStatements = array_map($rewrite, $langStatements);
        $langInserted = null;
        $langSkipped = null;
        if ($langStatements !== [] && !$forceRefresh) {
            $langInserted = 0;
            $langSkipped = 0;
        }
        ($this->transaction)(function () use ($statements, $langStatements, $forceRefresh, &$langInserted, &$langSkipped): void {
            foreach (array_merge($statements, $langStatements) as $statement) {
                $affected = ($this->executor)($statement);
                if (!$forceRefresh && $langInserted !== null && preg_match('/^\s*INSERT\s+IGNORE\s+INTO\s+`?[a-z0-9_]*language_line`?/i', $statement) === 1) {
                    $tuples = $this->langTupleCount($statement);
                    $inserted = max(0, min($affected, $tuples));
                    $langInserted += $inserted;
                    $langSkipped += $tuples - $inserted;
                }
            }
        });
        return [
            'resourceApplyStatus' => 'applied',
            'resourceApplyError' => null,
            'resourceChecksum' => $permission['checksum'],
            'langChecksum' => $lang['checksum'] ?? null,
            'langInserted' => $langInserted,
            'langSkipped' => $langSkipped,
            'langForceRefresh' => $langStatements !== [] ? $forceRefresh : null,
        ];
    }

    private function applyPostgresql(array $definition, array $manifest, bool $forceRefresh): array
    {
        $permission = $this->verifiedStatements($definition, $manifest, 'permissionMigration', '权限菜单 migration');
        $lang = !empty($definition['generationTargets']['langMigration']) ? $this->verifiedStatements($definition, $manifest, 'langMigration', '语言包 migration') : null;
        $prefix = Db::connection()->getTablePrefix();
        if ($prefix !== '' && !preg_match('/\A[a-z_][a-z0-9_]*\z/D', $prefix)) throw new RuntimeException('资源表前缀不合法');
        $inserted = $skipped = 0;
        ($this->transaction)(function () use ($permission, $lang, $prefix, $forceRefresh, &$inserted, &$skipped): void {
            foreach (array_merge($permission['statements'], $lang['statements'] ?? []) as $statement) {
                $plain = preg_replace("/'(?:[^']|'')*'/s", "''", $statement);
                if (!preg_match('/\A(?:INSERT\s+INTO\s+"fun_(?:permission|admin_menu|language_line)"|UPDATE\s+"fun_(?:permission|admin_menu)"|SELECT\s+1\s*;?\s*\z)/i', $plain)
                    || preg_match('/\b(?:DELETE|DROP|ALTER|TRUNCATE|RENAME|GRANT|REVOKE|CALL|COPY|CREATE)\b/i', $plain)
                    || preg_match('/\b(?:FROM|JOIN|INTO)\s+(?!"fun_(?:permission|admin_menu|language_line)"(?:\s|\())[^\s(]+/i', $plain)) throw new RuntimeException('PostgreSQL 资源 SQL 不在白名单');
                $isLanguage = str_starts_with($statement, 'INSERT INTO "fun_language_line"');
                if ($isLanguage && $forceRefresh) $statement = preg_replace('/ON CONFLICT \("locale","key"\) DO NOTHING;\s*\z/', 'ON CONFLICT ("locale","key") DO UPDATE SET "value"=EXCLUDED."value","updated_at"=EXCLUDED."updated_at";', $statement);
                $statement = preg_replace_callback('/\x27(?:\x27\x27|[^\x27])*\x27|"fun_(permission|admin_menu|language_line)"/s', static fn (array $match): string => isset($match[1]) ? '"' . $prefix . $match[1] . '"' : $match[0], $statement);
                $affected = ($this->executor)($statement);
                if ($isLanguage && !$forceRefresh) {
                    $inserted += $affected;
                    $values = substr($statement, stripos($statement, 'VALUES') + 6);
                    $values = substr($values, 0, strripos($values, 'ON CONFLICT'));
                    $values = preg_replace("/'(?:[^']|'')*'/s", "''", $values);
                    $total = preg_match_all('/\(\x27\x27,\x27\x27,\x27\x27,NOW\(\),NOW\(\)\)/', $values);
                    $skipped += max(0, $total - $affected);
                }
            }
        });
        return ['resourceApplyStatus' => 'applied', 'resourceApplyError' => null, 'resourceChecksum' => $permission['checksum'], 'langChecksum' => $lang['checksum'] ?? null, 'langInserted' => $forceRefresh || $lang === null ? null : $inserted, 'langSkipped' => $forceRefresh || $lang === null ? null : $skipped, 'langForceRefresh' => $lang === null ? null : $forceRefresh];
    }

    /** @return array{statements:list<string>,checksum:string} */
    private function verifiedStatements(array $definition, array $manifest, string $target, string $label): array
    {
        $relativePath = (string) ($definition['generationTargets'][$target] ?? '');
        if ($relativePath === '') throw new RuntimeException("审计定义缺少 {$target}");
        $artifact = $this->artifact($relativePath, $manifest, $label);
        $absolutePath = PathGuard::resolve($this->projectRoot, $relativePath, '项目目录');
        if (!is_file($absolutePath)) throw new RuntimeException("{$label}文件不存在");
        $sql = file_get_contents($absolutePath);
        if ($sql === false || trim($sql) === '') throw new RuntimeException("{$label}为空");
        $checksum = hash('sha256', $sql);
        if (!hash_equals((string) $artifact['hash'], $checksum)) throw new RuntimeException("{$label}哈希与生成审计不一致");
        $statements = Db::connection()->getDriverName() === 'pgsql' ? \app\common\service\database\SqlStatements::split($sql, 'pgsql') : $this->statements($sql);
        return ['statements' => $statements, 'checksum' => $checksum];
    }

    /** force-refresh：把语言包 INSERT IGNORE 改写为覆盖式 INSERT，人工修订仅在显式强制时才会被刷新。 */
    private function forceRefreshStatement(string $statement): string
    {
        if (preg_match('/^\s*INSERT\s+IGNORE\s+INTO\s+`?fun_language_line`?/i', $statement) !== 1) return $statement;
        $statement = (string) preg_replace('/^\s*INSERT\s+IGNORE\s+INTO/i', 'INSERT INTO', $statement, 1);
        return rtrim($statement, " \t\n\r;") . ' ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `updated_at`=NOW();';
    }

    /** 统计 INSERT 语句中 VALUES 顶层元组数（跳过引号内容），用于 inserted/skipped 报告。 */
    private function langTupleCount(string $statement): int
    {
        $valuesAt = stripos($statement, 'VALUES');
        if ($valuesAt === false) return 0;
        $tuples = 0;
        $depth = 0;
        $quoted = false;
        $length = strlen($statement);
        for ($index = $valuesAt + 6; $index < $length; $index++) {
            $char = $statement[$index];
            if ($quoted) {
                if ($char === '\\') {
                    $index++;
                } elseif ($char === "'") {
                    if (($statement[$index + 1] ?? '') === "'") {
                        $index++;
                    } else {
                        $quoted = false;
                    }
                }
                continue;
            }
            if ($char === "'") { $quoted = true; continue; }
            if ($char === '(') {
                if ($depth === 0) $tuples++;
                $depth++;
                continue;
            }
            if ($char === ')') $depth--;
        }
        return $tuples;
    }

    private function artifact(string $relativePath, array $manifest, string $label = '权限菜单 migration'): array
    {
        foreach ((array) ($manifest['files'] ?? []) as $file) {
            if (($file['path'] ?? null) === $relativePath && is_string($file['hash'] ?? null)) return $file;
        }
        throw new RuntimeException("{$label}不属于本次生成计划");
    }

    private function assertSafe(string $statement): void
    {
        $plain = preg_replace('/^\s*--.*$/m', '', $statement);
        if (preg_match('/\b(?:DROP|TRUNCATE|ALTER|RENAME|DELETE|GRANT|REVOKE|LOAD|OUTFILE|INFILE|CALL)\b/i', (string) $plain)) {
            throw new RuntimeException('权限菜单 migration 包含非白名单 SQL');
        }
        if (preg_match('/^\s*(?:SET\s+@[a-z0-9_]+\s*=|INSERT(?:\s+IGNORE)?\s+INTO\s+`?fun_(?:permission|admin_menu|language_line)`?|UPDATE\s+`?fun_(?:permission|admin_menu)`?)/i', (string) $plain) !== 1) {
            throw new RuntimeException('权限菜单 migration 仅允许 SET、INSERT、UPDATE 指定资源表');
        }
        if (preg_match('/\bfun_(?!permission\b|admin_menu\b|language_line\b)[a-z0-9_]+/i', (string) $plain)) {
            throw new RuntimeException('权限菜单 migration 引用了非白名单表');
        }
    }

    private function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = [];
        $buffer = '';
        $quoted = false;
        $length = strlen((string) $sql);
        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $buffer .= $char;
            if ($quoted) {
                if ($char === '\\' && $index + 1 < $length) {
                    $buffer .= $sql[++$index];
                } elseif ($char === "'") {
                    if (($sql[$index + 1] ?? '') === "'") {
                        $buffer .= $sql[++$index];
                    } else {
                        $quoted = false;
                    }
                }
                continue;
            }
            if ($char === "'") {
                $quoted = true;
                continue;
            }
            if ($char === ';') {
                if (trim($buffer, " \t\n\r;") !== '') $statements[] = trim($buffer);
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') $statements[] = trim($buffer);
        return $statements;
    }
}
