<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/** 将 SchemaInspector/FieldInference 输出转换为插件 CRUD Definition。 */
final class PluginCrudDefinitionFactory
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function fromInspection(string $plugin, string $entity, string $table, string $scope, array $inspection, string $connection = 'mysql'): CrudDefinition
    {
        $fields = $inspection['fields'] ?? null;
        $schema = $inspection['schema'] ?? null;
        if (!is_array($fields) || $fields === [] || !is_array($schema)) {
            throw new InvalidArgumentException('inspect/infer 结果不完整');
        }
        $primary = array_values(array_filter($fields, static fn (array $field): bool => ($field['primary'] ?? false) === true));
        if (count($primary) !== 1) {
            throw new InvalidArgumentException('inspect/infer 必须得到唯一主键');
        }
        $title = trim((string) ($schema['comment'] ?? '')) ?: $entity;
        $management = $scope !== 'application';
        $definition = CrudDefinition::fromArray([
            'schemaVersion' => '1.0', 'connection' => $connection, 'module' => $plugin,
            'entity' => $entity, 'table' => $table, 'title' => $title,
            'apiPrefix' => '/' . $entity, 'routePath' => '/plugin/' . $plugin . '/' . $entity,
            'primaryKey' => (string) $primary[0]['name'], 'timestamps' => $this->has($fields, 'created_at') && $this->has($fields, 'updated_at'),
            'softDeletes' => $this->has($fields, 'deleted_at'),
            'target' => ['type' => 'plugin', 'plugin' => $plugin, 'scope' => $scope],
            'permissionPrefix' => $plugin . ':' . $entity, 'fields' => $fields,
            'relations' => [], 'optionsSource' => [], 'templates' => self::templates(),
            'capabilities' => ['list' => true, 'search' => true, 'form' => $management, 'detail' => true, 'create' => $management, 'update' => $management, 'delete' => $management, 'import' => false, 'export' => false],
            'features' => ['batchDelete' => $management, 'status' => $management && $this->has($fields, 'status'), 'detail' => true, 'import' => false, 'export' => false, 'upload' => false, 'dictionary' => false, 'referenceProtection' => false, 'formMode' => 'dialog', 'importLimit' => 100, 'exportLimit' => 100],
            'dataScope' => ['enabled' => false, 'field' => ''],
            'menu' => ['enabled' => true, 'parentId' => null, 'parentSourceName' => '', 'name' => $title, 'icon' => 'i-ep-document', 'sortOrder' => 999, 'hidden' => false, 'keepAlive' => true, 'affix' => false, 'target' => '_self'],
            'permission' => ['enabled' => true, 'groupName' => $title, 'actions' => []],
        ]);
        (new DefinitionValidator())->validate($definition, $this->projectRoot);
        return $definition;
    }

    private function has(array $fields, string $name): bool
    {
        return in_array($name, array_column($fields, 'name'), true);
    }

    private static function templates(): array
    {
        return [
            'migration' => 'database/migration.sql.tpl', 'model' => 'console/model.php.tpl',
            'validate' => 'console/validate.php.tpl', 'service' => 'console/service.php.tpl',
            'controller' => 'console/controller.php.tpl', 'api' => 'frontend/api.ts.tpl',
            'view' => 'frontend/index.vue.tpl', 'form' => 'frontend/form.vue.tpl',
            'detail' => 'frontend/detail.vue.tpl',
        ];
    }
}
