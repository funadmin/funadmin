<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/console/controller/form/Data.php');
$config = (string) file_get_contents($root . '/config/form.php');

function dataObservabilityExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

dataObservabilityExpect(str_contains($controller, 'FormObservability'), 'Data 必须接入 FormObservability');
dataObservabilityExpect(str_contains($controller, 'FormActionRegistry'), 'Data 必须接入 FormActionRegistry');
dataObservabilityExpect(str_contains($controller, "#[Post('action/:key/:action')]"), '必须提供动作执行路由');
dataObservabilityExpect(str_contains($controller, "header('If-None-Match'"), 'meta 必须读取 If-None-Match');
dataObservabilityExpect(str_contains($controller, 'code(304)'), 'ETag 命中必须返回 HTTP 304');
dataObservabilityExpect(str_contains($controller, "header('Idempotency-Key'"), '动作必须读取幂等键');
dataObservabilityExpect(str_contains($controller, "post('parameters'"), '动作必须读取 parameters 对象');
dataObservabilityExpect(str_contains($controller, 'executeAction('), '动作必须通过运行态服务校验已发布引用');
dataObservabilityExpect(str_contains($controller, 'runtimeContext('), '所有运行态观测必须先解析已发布 schemaHash/nodeId/dataSource');
dataObservabilityExpect(str_contains($controller, "observe(\$key, 'action'"), '动作执行整体必须被 measure 覆盖，包括失败阶段');

foreach (['meta', 'options', 'validate', 'create', 'update', 'detail', 'action'] as $action) {
    dataObservabilityExpect(
        str_contains(strtolower($controller), "'" . strtolower($action) . "'"),
        'Data 观测缺少 action：' . $action
    );
}
foreach (['formKey', 'schemaHash', 'nodeId', 'action', 'dataSource', 'requestId'] as $field) {
    dataObservabilityExpect(str_contains($controller, "'{$field}' =>"), 'Data 观测上下文缺少字段：' . $field);
}
dataObservabilityExpect(str_contains($config, "'idempotency_ttl'"), 'form 配置必须声明幂等 TTL');
dataObservabilityExpect(str_contains($config, "'action_timeout_ms'"), 'form 配置必须声明动作默认超时');
dataObservabilityExpect(str_contains($config, "'action_max_chain'"), 'form 配置必须声明动作最大链');

echo "form data observability contract tests: PASS\n";
