<?php

declare(strict_types=1);

namespace app\common\crud;

/** 为现有生产模板提供插件命名空间、路由与前端导入配置。 */
final class PluginTemplateContext
{
    public static function build(CrudDefinition $definition, string $plugin, bool $console): array
    {
        $entity = (string) $definition->get('entity', '');
        $namespace = $console ? 'app\\console' : 'app\\' . $plugin;
        $pluginNamespace = 'plugin\\' . $plugin;
        return ProductionTemplateContext::build($definition, [
            'namespace' => $namespace,
            'modelNamespace' => $console ? $namespace . '\\model\\' . $pluginNamespace : $namespace . '\\model',
            'validateNamespace' => $console ? $namespace . '\\validate\\' . $pluginNamespace : $namespace . '\\validate',
            'serviceNamespace' => $console ? $namespace . '\\service\\' . $pluginNamespace : $namespace . '\\service',
            'controllerNamespace' => $console ? $namespace . '\\controller\\' . $pluginNamespace : $namespace . '\\controller',
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
