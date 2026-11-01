<?php

declare(strict_types=1);
namespace app\common\plugin\sdk;

use app\common\crud\CrudDefinition;
use app\common\crud\SchemaInspector;
use Closure;
use InvalidArgumentException;
use think\facade\Db;

/** 外部依赖仅声明运行所需结构；不执行 DDL，不参与卸载与 purge。 */
final class ExternalTableRequirements
{
    private readonly Closure $reader;

    public function __construct(private readonly string $defaultConnection, ?callable $reader = null)
    {
        $this->reader = Closure::fromCallable($reader ?? static fn (string $connection, string $table): array =>
            (new SchemaInspector(static fn (string $sql, array $bindings): array => Db::connect($connection)->query($sql, $bindings)))->inspect($table));
    }

    public static function fromDefinition(CrudDefinition $definition): array
    {
        return [
            'module' => $definition->get('entity'), 'connection' => $definition->get('connection'),
            'table' => $definition->get('table'), 'primaryKey' => [$definition->get('primaryKey')],
            'columns' => array_map(static fn (array $field): array => [
                'name' => $field['name'], 'type' => $field['dbType'], 'nullable' => $field['nullable'],
            ], $definition->fields()),
        ];
    }

    public function assertCompatible(array $requirements): void
    {
        foreach ($requirements as $requirement) {
            if (($requirement['connection'] ?? '') !== $this->defaultConnection
                || !preg_match('/^[a-z][a-z0-9_]*$/D', (string) ($requirement['table'] ?? ''))) {
                throw new InvalidArgumentException('EXTERNAL_TABLE_IDENTITY_INVALID');
            }
            $schema = ($this->reader)($this->defaultConnection, $requirement['table']);
            if (($schema['primaryKey'] ?? []) !== $requirement['primaryKey']) throw new InvalidArgumentException('EXTERNAL_TABLE_PRIMARY_KEY_CONFLICT');
            $columns = array_column($schema['columns'] ?? [], null, 'name');
            foreach ($requirement['columns'] as $column) {
                $actual = $columns[$column['name']] ?? null;
                if ($actual === null || strtolower(trim($actual['type'])) !== strtolower(trim($column['type']))
                    || (bool) $actual['nullable'] !== $column['nullable']) {
                    throw new InvalidArgumentException('EXTERNAL_TABLE_STRUCTURE_CONFLICT');
                }
            }
        }
    }
}
