<?php

declare(strict_types=1);

namespace app\console\development\service;

use app\console\development\model\CrudGeneration;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\CrudResourceInstaller;
use app\common\crud\DefinitionValidator;
use app\common\crud\FieldInference;
use app\common\crud\PluginCrudDefinitionFactory;
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

    /** @var Closure(int, array): void */
    private readonly Closure $auditUpdater;

    /** @var Closure(): array */
    private readonly Closure $menuReader;

    private readonly CrudResourceInstaller $resourceInstaller;

    public function __construct(
        private readonly string $projectRoot,
        private readonly array $allowedConnections,
        ?callable $inspectorFactory = null,
        ?callable $auditWriter = null,
        ?callable $auditReader = null,
        ?callable $tableReader = null,
        ?callable $auditUpdater = null,
        ?callable $menuReader = null,
        ?CrudResourceInstaller $resourceInstaller = null
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
        $this->auditUpdater = Closure::fromCallable($auditUpdater ?? static function (int $id, array $changes): void {
            CrudGeneration::where('id', $id)->update($changes);
        });
        $this->menuReader = Closure::fromCallable($menuReader ?? static fn (): array => \app\console\authorization\model\AdminMenu::whereIn('source_type', ['admin_web', 'generated'])
            ->where('status', 1)->field('id,pid,name,href,source_name,source_type')->order('sort_order', 'asc')->select()->toArray());
        $this->resourceInstaller = $resourceInstaller ?? new CrudResourceInstaller($projectRoot);
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

    public function options(): array
    {
        $rows = ($this->menuReader)();
        $items = [];
        foreach ($rows as $row) {
            $sourceName = (string) ($row['source_name'] ?? '');
            if ($sourceName === '') continue;
            $items[] = [
                'id' => (int) $row['id'], 'pid' => (int) ($row['pid'] ?? 0), 'sourceName' => $sourceName,
                'name' => (string) ($row['name'] ?? ''), 'path' => (string) ($row['href'] ?? ''),
            ];
        }
        return ['parentMenus' => $this->menuTree($items), 'icons' => self::ICONS];
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

    public function inferPlugin(string $connection, string $table, string $plugin, string $entity, string $scope): array
    {
        $inspection = $this->infer($connection, $table);
        $definition = (new PluginCrudDefinitionFactory($this->projectRoot))->fromInspection(
            $plugin,
            $entity,
            $table,
            $scope,
            $inspection,
            $connection
        );
        return $inspection + ['definition' => $definition->toArray()];
    }

    public function validate(array $definition): array
    {
        $normalized = CrudDefinition::fromArray($definition);
        (new DefinitionValidator())->validate($normalized, $this->projectRoot);
        $this->assertMenuParent($normalized);
        return ['valid' => true, 'definitionHash' => $normalized->hash(), 'definition' => $normalized->toArray()];
    }

    public function preview(array $definition, bool $includeSensitive, bool $canGenerate): array
    {
        try {
            $crudDefinition = CrudDefinition::fromArray($definition);
            $this->assertMenuParent($crudDefinition);
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

    /** 在任何不可逆副作用前验证确认令牌、冲突授权与生成权限。 */
    public function preflightGeneration(
        array $definition,
        string $confirmToken,
        array $allowOverwrite,
        bool $canOverwrite,
        bool $canApplyResources
    ): array {
        if ($confirmToken === '') throw new InvalidArgumentException('缺少 preview 确认 token');
        if ($allowOverwrite !== [] && !$canOverwrite) throw new InvalidArgumentException('缺少单独的 overwrite 权限');
        if (!$canApplyResources) throw new InvalidArgumentException('缺少 resource apply 专用权限');
        $crudDefinition = CrudDefinition::fromArray($definition);
        $this->assertMenuParent($crudDefinition);
        $plan = (new CrudGenerator($this->projectRoot))->plan($crudDefinition);
        $digest = (string) ($plan['planDigest'] ?? '');
        (new \app\common\crud\ConfirmationToken($this->projectRoot))->verify($confirmToken, $digest);
        $allowed = array_fill_keys(array_map(static fn (string $path): string => str_replace('\\', '/', $path), $allowOverwrite), true);
        foreach ((array) ($plan['files'] ?? []) as $file) {
            $path = (string) ($file['path'] ?? '');
            $status = (string) ($file['status'] ?? '');
            if ($status === 'blocked') throw new InvalidArgumentException('目标路径被阻塞：' . $path);
            if ($status === 'conflict' && !isset($allowed[$path])) throw new InvalidArgumentException('冲突文件未确认覆盖：' . $path);
            if (isset($allowed[$path]) && $status !== 'conflict') throw new InvalidArgumentException('只允许授权冲突文件：' . $path);
        }
        if (array_diff(array_keys($allowed), array_column((array) ($plan['files'] ?? []), 'path')) !== []) {
            throw new InvalidArgumentException('allowOverwrite 包含计划外路径');
        }
        return $plan;
    }

    public function generate(
        array $definition,
        string $confirmToken,
        array $allowOverwrite,
        bool $canOverwrite,
        string $operator,
        bool $applyResources = false,
        bool $canApplyResources = false,
        ?array $validatedPlan = null
    ): array {
        if ($confirmToken === '') {
            throw new InvalidArgumentException('缺少 preview 确认 token');
        }
        if ($allowOverwrite !== [] && !$canOverwrite) {
            throw new InvalidArgumentException('缺少单独的 overwrite 权限');
        }
        if ($applyResources && !$canApplyResources) {
            throw new InvalidArgumentException('缺少 resource apply 专用权限');
        }
        try {
            $crudDefinition = CrudDefinition::fromArray($definition);
            $this->assertMenuParent($crudDefinition);
            $generator = new CrudGenerator($this->projectRoot);
            $result = $validatedPlan === null
                ? $generator->generate($crudDefinition, $confirmToken, $allowOverwrite, $operator)
                : $generator->generatePlanned($crudDefinition, $validatedPlan, $confirmToken, $allowOverwrite, $operator);
            unset($result['plan']['confirmToken']);
            $manifest = $result['manifest'] ?? [];
            $manifest['resourceApplyStatus'] = $applyResources ? 'pending' : 'not_requested';
            $manifest['resourceApplyError'] = null;
            $manifest['resourceChecksum'] = null;
            $id = $this->audit('generate', (string) ($result['write']['status'] ?? 'unknown'), $crudDefinition, $manifest);
            $result['manifest'] = $manifest;
            if (!$applyResources) return ['generationId' => $id, 'resourceApplyStatus' => 'not_requested'] + $result;
            return $this->applyGeneratedResources($id, $crudDefinition->toArray(), $manifest, $result);
        } catch (Throwable $exception) {
            $this->auditFailure('generate', $definition, $exception);
            throw $exception;
        }
    }

    public function applyResources(int $id): array
    {
        $row = ($this->auditReader)($id);
        if ($row === null || ($row['operation'] ?? '') !== 'generate') throw new InvalidArgumentException('生成记录不存在或不可应用资源');
        $definition = (array) ($row['definition'] ?? []);
        $manifest = (array) ($row['manifest'] ?? []);
        $applyStatus = (string) ($manifest['resourceApplyStatus'] ?? '');
        if ($applyStatus === 'applied') {
            return ['generationId' => $id, 'resourceApplyStatus' => 'applied', 'resourceChecksum' => $manifest['resourceChecksum'] ?? null];
        }
        if (!in_array($applyStatus, ['pending', 'failed'], true)) {
            throw new InvalidArgumentException('该生成记录的资源状态不可重试');
        }
        return $this->applyGeneratedResources($id, $definition, $manifest);
    }

    public function generation(int $id): ?array
    {
        $row = ($this->auditReader)($id);
        return $row === null ? null : $this->sanitize($row);
    }

    private function applyGeneratedResources(int $id, array $definition, array $manifest, array $result = []): array
    {
        try {
            $applied = $this->resourceInstaller->apply($definition, $manifest);
            $manifest = array_replace($manifest, $applied);
            ($this->auditUpdater)($id, ['status' => 'completed', 'manifest' => $manifest, 'error' => null]);
            return ['generationId' => $id] + $applied + $result + ['manifest' => $manifest];
        } catch (Throwable $exception) {
            $manifest['resourceApplyStatus'] = 'failed';
            $manifest['resourceApplyError'] = $exception->getMessage();
            ($this->auditUpdater)($id, ['status' => 'partial', 'manifest' => $manifest, 'error' => ['message' => $exception->getMessage()]]);
            return ['generationId' => $id, 'resourceApplyStatus' => 'failed', 'resourceApplyError' => $exception->getMessage()] + $result + ['manifest' => $manifest];
        }
    }

    private function assertMenuParent(CrudDefinition $definition): void
    {
        $menu = (array) $definition->get('menu', []);
        if (($menu['enabled'] ?? false) !== true) return;
        $parentSourceName = trim((string) ($menu['parentSourceName'] ?? ''));
        $parentId = $menu['parentId'] ?? null;
        if ($parentSourceName === '' && $parentId === null) return;
        foreach (($this->menuReader)() as $row) {
            if (!in_array((string) ($row['source_type'] ?? ''), ['admin_web', 'generated'], true)) continue;
            if ($parentSourceName !== '' && (string) ($row['source_name'] ?? '') === $parentSourceName) return;
            if ($parentSourceName === '' && (int) ($row['id'] ?? 0) === (int) $parentId) return;
        }
        throw new InvalidArgumentException('父级菜单不存在或来源不允许');
    }

    private function menuTree(array $rows, int $pid = 0): array
    {
        $tree = [];
        foreach ($rows as $row) {
            if ($row['pid'] !== $pid) continue;
            $children = $this->menuTree($rows, $row['id']);
            unset($row['pid']);
            if ($children !== []) $row['children'] = $children;
            $tree[] = $row;
        }
        return $tree;
    }

    private const ICONS = ['i-ep-document', 'i-ep-menu', 'i-ep-folder', 'i-ep-grid', 'i-ep-setting', 'i-ep-user', 'i-ep-lock', 'i-ep-tickets', 'i-ep-data-line'];

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
            'connection_name' => (string) $definition->get('connection', ''),
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
            'connection_name' => (string) ($definition['connection'] ?? $definition['metadata']['connection'] ?? ''),
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
