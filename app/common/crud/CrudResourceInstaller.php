<?php

declare(strict_types=1);

namespace app\common\crud;

use Closure;
use RuntimeException;
use think\facade\Db;

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
        $this->executor = Closure::fromCallable($executor ?? static fn (string $sql): int => Db::execute($sql));
        $this->transaction = Closure::fromCallable($transaction ?? static fn (callable $operation): mixed => Db::transaction($operation));
    }

    public function apply(array $definition, array $manifest): array
    {
        $definitionHash = CrudDefinition::fromArray($definition)->hash();
        $manifestDefinitionHash = (string) ($manifest['definitionHash'] ?? '');
        if ($manifestDefinitionHash === '' || !hash_equals($manifestDefinitionHash, $definitionHash)) {
            throw new RuntimeException('Definition 哈希与生成审计不一致');
        }
        $relativePath = (string) ($definition['generationTargets']['permissionMigration'] ?? '');
        if ($relativePath === '') throw new RuntimeException('审计定义缺少 permissionMigration');
        $artifact = $this->artifact($relativePath, $manifest);
        $absolutePath = PathGuard::resolve($this->projectRoot, $relativePath, '项目目录');
        if (!is_file($absolutePath)) throw new RuntimeException('权限菜单 migration 文件不存在');
        $sql = file_get_contents($absolutePath);
        if ($sql === false || trim($sql) === '') throw new RuntimeException('权限菜单 migration 为空');
        $checksum = hash('sha256', $sql);
        if (!hash_equals((string) $artifact['hash'], $checksum)) throw new RuntimeException('权限菜单 migration 哈希与生成审计不一致');
        $statements = $this->statements($sql);
        foreach ($statements as $statement) $this->assertSafe($statement);
        $connection = (string) \think\facade\Config::get('database.default', 'mysql');
        $prefix = (string) \think\facade\Config::get('database.connections.' . $connection . '.prefix', '');
        if ($prefix !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $prefix) !== 1) {
            throw new RuntimeException('资源表前缀不合法');
        }
        // 哈希和白名单校验仍针对原始制品，只替换 SQL 标识符，不改写字符串值。
        $statements = array_map(static fn (string $statement): string => (string) preg_replace_callback(
            '/\x27(?:\\\\.|\x27\x27|[^\x27\\\\])*\x27|`fun_(permission|admin_menu)`/s',
            static fn (array $match): string => isset($match[1]) ? '`' . $prefix . $match[1] . '`' : $match[0],
            $statement
        ), $statements);
        ($this->transaction)(function () use ($statements): void {
            foreach ($statements as $statement) ($this->executor)($statement);
        });
        return ['resourceApplyStatus' => 'applied', 'resourceApplyError' => null, 'resourceChecksum' => $checksum];
    }

    private function artifact(string $relativePath, array $manifest): array
    {
        foreach ((array) ($manifest['files'] ?? []) as $file) {
            if (($file['path'] ?? null) === $relativePath && is_string($file['hash'] ?? null)) return $file;
        }
        throw new RuntimeException('权限菜单 migration 不属于本次生成计划');
    }

    private function assertSafe(string $statement): void
    {
        $plain = preg_replace('/^\s*--.*$/m', '', $statement);
        if (preg_match('/\b(?:DROP|TRUNCATE|ALTER|RENAME|DELETE|GRANT|REVOKE|LOAD|OUTFILE|INFILE|CALL)\b/i', (string) $plain)) {
            throw new RuntimeException('权限菜单 migration 包含非白名单 SQL');
        }
        if (preg_match('/^\s*(?:SET\s+@[a-z0-9_]+\s*=|INSERT\s+INTO\s+`?fun_(?:permission|admin_menu)`?|UPDATE\s+`?fun_(?:permission|admin_menu)`?)/i', (string) $plain) !== 1) {
            throw new RuntimeException('权限菜单 migration 仅允许 SET、INSERT、UPDATE 指定资源表');
        }
        if (preg_match('/\bfun_(?!permission\b|admin_menu\b)[a-z0-9_]+/i', (string) $plain)) {
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
            if ($char === "'" && ($index === 0 || $sql[$index - 1] !== '\\')) $quoted = !$quoted;
            if ($char === ';' && !$quoted) {
                if (trim($buffer, " \t\n\r;") !== '') $statements[] = trim($buffer);
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') $statements[] = trim($buffer);
        return $statements;
    }
}
