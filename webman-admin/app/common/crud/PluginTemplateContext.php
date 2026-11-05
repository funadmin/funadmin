<?php

declare(strict_types=1);

namespace app\common\crud;

/** 为现有生产模板提供插件命名空间、路由与前端导入配置。 */
final class PluginTemplateContext
{
    public static function build(CrudDefinition $definition, string $plugin, bool $console): array
    {
        $entity = (string) $definition->get('entity', '');
        $namespace = $console ? 'app\\admin\\plugins\\' . $plugin : 'app\\' . $plugin;
        return ProductionTemplateContext::build($definition, [
            'namespace' => $namespace,
            'modelNamespace' => $namespace . '\\model',
            'validateNamespace' => $namespace . '\\validate',
            'serviceNamespace' => $namespace . '\\service',
            'controllerNamespace' => $namespace . '\\controller',
            'pluginCode' => $console ? $plugin : '',
            // 前台会员控制器只渲染在插件 application 层（app\{plugin}\controller），路由为 /{plugin}/{entity}。
            'memberControllerNamespace' => 'app\\' . $plugin . '\\controller',
            'memberControllerGroup' => $plugin . '/' . $entity,
            'memberRoutePrefix' => '/' . $plugin . '/' . $entity,
            'controllerGroup' => $console ? 'admin/plugin/' . $plugin . '/' . $entity : $plugin . '/' . $entity,
            'apiPrefix' => $console ? '/plugin/' . $plugin . '/' . $entity : '/' . $entity,
            'frontendApiImport' => './api',
            'frontendComponentApiImport' => '../api',
            'modelBaseImport' => $console ? '' : 'use support\\Model;',
            'modelBaseClass' => $console ? '\\app\\common\\model\\BaseModel' : 'Model',
            'consoleController' => $console,
        ]);
    }
}
