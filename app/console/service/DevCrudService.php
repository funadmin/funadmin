<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\CrudGeneration;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\DefinitionValidator;
use app\common\crud\FieldInference;
use app\common\crud\SchemaInspector;
use Closure;
use InvalidArgumentException;
use think\facade\Db;
use Throwable;

/**
 * CRUD Workbench 应用服务：连接边界、M3 Core 编排与无密钥审计。
 */
final class DevCrudService
{
    /** @var Closure(string): SchemaInspector */
    private readonly Closure $inspectorFactory;

    /** @var Closure(string): array */
    private readonly Closure $tableReader;

    /** @var Closure(array): int */
    private readonly Closure $auditWriter;

    /** @var Closure(int): ?array */
    private readonly Closure $auditReader;

    public function __construct(
        private readonly string $projectRoot,
        private readonly array $allowedConnections,
        ?callable $inspectorFactory = null,
        ?callable $auditWriter = null,
        ?callable $auditReader = null,
        ?callable $tableReader = null
    ) {
        $this->inspectorFactory = Closure::fromCallable(
            $inspectorFactory ?? static fn (string $connection): SchemaInspector => new SchemaInspector(
                static fn (string $sql, array $bindings): array => Db::connect($connection)->query($sql, $bindings)
            )
        );
        $this->tableReader = Closure::fromCallable(
            $tableReader ?? static fn (string $connection): array => Db::connect($connection)->query(
                'SELECT TABLE_NAME, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'
            )
        );
        $this->auditWriter = Closure::fromCallable($auditWriter ?? static function (array $row): int {
            $model = CrudGeneration::create($row);
            return (int) $model->id;
        });
        $this->auditReader = Closure::fromCallable($auditReader ?? static function (int $id): ?array {
            $record = CrudGeneration::find($id);
            return $record ? $record->toArray() : null;
        });
    }

    public function connections(): array
    {
        return array_values(array_map(static fn (string $name): array => ['name' => $name], $this->allowedConnections));
    }

    public function tables(string $connection): array
    {
        $this->assertConnection($connection);
        $rows = ($this->tableReader)($connection);
        return array_map(static fn (array $row): array => [
            'name' => (string) $row['TABLE_NAME'],
            'comment' => (string) ($row['TABLE_COMMENT'] ?? ''),
        ], $rows);
    }

    public function inspect(string $connection, string $table): array
    {
        $this->assertConnection($connection);
        return ($this->inspectorFactory)($connection)->inspect($table);
    }

    public function infer(string $connection, string $table): array
    {
        $schema = $this->inspect($connection, $table);
        return ['schema' => $schema, 'fields' => (new FieldInference())->infer($schema)];
    }

    public function validate(array $definition): array
    {
        (new DefinitionValidator())->validate(CrudDefinition::fromArray($definition), $this->projectRoot);
        return ['valid' => true, 'definitionHash' => CrudDefinition::fromArray($definition)->hash()];
    }

    public function preview(array $definition, bool $includeSensitive, bool $canGenerate): array
    {
        try {
            $crudDefinition = CrudDefinition::fromArray($definition);
            $plan = (new CrudGenerator($this->projectRoot))->plan($crudDefinition);
            $token = (string) ($plan['confirmToken'] ?? '');
            unset($plan['confirmToken']);
            $id = $this->audit('preview', 'planned', $crudDefinition, $plan);
            $result = ['generationId' => $id, 'plan' => $plan];
            if ($includeSensitive && $canGenerate) {
                $result['sensitive'] = ['confirmToken' => $token];
            }
            return $result;
        } catch (Throwable $exception) {
            $this->auditFailure('preview', $definition, $exception);
            throw $exception;
        }
    }

    public function generate(
        array $definition,
        string $confirmToken,
        array $allowOverwrite,
        bool $canOverwrite,
        string $operator
    ): array {
        if ($confirmToken === '') {
            throw new InvalidArgumentException('缺少 preview 确认 token');
        }
        if ($allowOverwrite !== [] && !$canOverwrite) {
            throw new InvalidArgumentException('缺少单独的 overwrite 权限');
        }
        try {
            $crudDefinition = CrudDefinition::fromArray($definition);
            $result = (new CrudGenerator($this->projectRoot))->generate(
                $crudDefinition,
                $confirmToken,
                $allowOverwrite,
                $operator
            );
            unset($result['plan']['confirmToken']);
            $id = $this->audit('generate', (string) ($result['write']['status'] ?? 'unknown'), $crudDefinition, $result['manifest'] ?? []);
            return ['generationId' => $id] + $result;
        } catch (Throwable $exception) {
            $this->auditFailure('generate', $definition, $exception);
            throw $exception;
        }
    }

    public function generation(int $id): ?array
    {
        $row = ($this->auditReader)($id);
        return $row === null ? null : $this->sanitize($row);
    }

    private function assertConnection(string $connection): void
    {
        if ($connection === '' || !in_array($connection, $this->allowedConnections, true)) {
            throw new InvalidArgumentException('数据库连接不在配置白名单');
        }
    }

    private function audit(string $operation, string $status, CrudDefinition $definition, array $manifest): int
    {
        return ($this->auditWriter)([
            'operation' => $operation,
            'status' => $status,
            'connection_name' => (string) ($definition->get('metadata', [])['connection'] ?? ''),
            'table_name' => (string) $definition->get('table', ''),
            'definition_hash' => $definition->hash(),
            'definition' => $this->sanitize($definition->toArray()),
            'manifest' => $this->sanitize($manifest),
            'error' => null,
        ]);
    }

    private function auditFailure(string $operation, array $definition, Throwable $exception): void
    {
        ($this->auditWriter)([
            'operation' => $operation,
            'status' => 'failed',
            'connection_name' => (string) (($definition['metadata']['connection'] ?? '')),
            'table_name' => (string) ($definition['table'] ?? ''),
            'definition_hash' => hash('sha256', CrudDefinition::canonicalJson($this->sanitize($definition))),
            'definition' => $this->sanitize($definition),
            'manifest' => null,
            'error' => ['message' => $exception->getMessage()],
        ]);
    }

    private function sanitize(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            if (preg_match('/(?:password|passwd|secret|token|credential|private[_-]?key|api[_-]?key|dsn)/i', (string) $key)) {
                continue;
            }
            $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }
        return $safe;
    }
}
