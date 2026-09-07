<?php

declare(strict_types=1);

namespace app\common\crud;

/** 为现有生产模板提供插件命名空间、路由与前端导入配置。 */
final class PluginTemplateContext
{
    public static function build(CrudDefinition $definition, string $plugin, bool $console): array
    {
        $entity = (string) $definition->get('entity', '');
        $namespace = 'plugin\\' . $plugin . ($console ? '\\console' : '');
        return ProductionTemplateContext::build($definition, [
            'namespace' => $namespace,
            'controllerGroup' => $console ? 'plugin/' . $plugin . '/' . $entity : $entity,
            'apiPrefix' => $console ? '/console/plugin/' . $plugin . '/' . $entity : '/' . $entity,
            'frontendApiImport' => './api',
            'frontendComponentApiImport' => '../api',
            'modelBaseImport' => $console ? 'use app\\console\\model\\BackendModel;' : 'use think\\Model;',
            'modelBaseClass' => $console ? 'BackendModel' : 'Model',
            'consoleController' => $console,
        ]);
    }
}
