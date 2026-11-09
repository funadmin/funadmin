<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\crud\CrudDefinition;

final class CrudDefinitionFixture
{
    public static function make(string $entity = 'console-example', string $connection = 'sqlite'): CrudDefinition
    {
        $class = str_replace(' ', '', ucwords(str_replace('-', ' ', $entity)));
        $templates = [
            'migration' => 'database/migration.sql.tpl', 'model' => 'admin/model.php.tpl',
            'validate' => 'admin/validate.php.tpl', 'service' => 'admin/service.php.tpl', 'controller' => 'admin/controller.php.tpl',
            'permissionMigration' => 'database/permissions.sql.tpl', 'api' => 'frontend/api.ts.tpl', 'view' => 'frontend/index.vue.tpl',
            'form' => 'frontend/form.vue.tpl', 'detail' => 'frontend/detail.vue.tpl',
        ];
        $directory = str_replace('-', '_', $entity);
        $paths = [
            'migration' => 'database/business/' . $entity . '.sql',
            'model' => 'app/admin/business/' . $directory . '/model/' . $class . '.php',
            'validate' => 'app/admin/business/' . $directory . '/validate/' . $class . 'Validate.php',
            'service' => 'app/admin/business/' . $directory . '/service/' . $class . 'Service.php',
            'controller' => 'app/admin/business/' . $directory . '/controller/' . $class . 'Controller.php',
            'permissionMigration' => 'database/business/' . $entity . '_permissions.sql',
            'api' => 'web/src/admin/api/business/' . $entity . '.ts',
            'view' => 'web/src/admin/views/business/' . $entity . '/index.vue',
            'form' => 'web/src/admin/views/business/' . $entity . '/Form.vue',
            'detail' => 'web/src/admin/views/business/' . $entity . '/Detail.vue',
        ];
        return CrudDefinition::fromArray([
            'schemaVersion' => '1.0', 'connection' => $connection, 'entity' => $entity, 'table' => str_replace('-', '_', $entity),
            'title' => '测试数据', 'apiPrefix' => '/business/' . $entity, 'routePath' => '/business/' . $entity,
            'permissionPrefix' => 'business:' . $entity, 'timestamps' => true, 'softDeletes' => true,
            'fields' => [
                ['name' => 'id', 'label' => 'ID', 'dbType' => 'bigint', 'nullable' => false, 'primary' => true, 'component' => 'input', 'writable' => false],
                ['name' => 'title', 'label' => '标题', 'dbType' => 'varchar(80)', 'nullable' => false, 'component' => 'input', 'required' => true, 'maxLength' => 80, 'search' => true, 'searchOperator' => 'like', 'sortable' => true],
                ['name' => 'metadata', 'label' => '数据', 'dbType' => 'json', 'nullable' => true, 'component' => 'json'],
            ],
            'relations' => [], 'optionsSource' => [], 'templates' => $templates, 'generationTargets' => $paths,
            'features' => ['batchDelete' => true, 'status' => false, 'detail' => true, 'import' => true, 'export' => true, 'upload' => false, 'dictionary' => false, 'referenceProtection' => false, 'formMode' => 'dialog', 'importLimit' => 100, 'exportLimit' => 100],
            'dataScope' => ['enabled' => false], 'menu' => ['enabled' => false], 'permission' => ['enabled' => false],
        ]);
    }
}
