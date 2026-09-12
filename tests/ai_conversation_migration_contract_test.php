<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$files = glob($root . '/database/migrations/116_*.sql');
if (array_map('basename', $files) !== ['116_ai_conversation_group_active_name.sql']) throw new RuntimeException('116 编号必须唯一');
$sql = file_get_contents($files[0]);
if (preg_match('/\b(?:DROP\s+TABLE|TRUNCATE|DELETE\s+FROM|UPDATE\s+`?fun_)\b/i', $sql)) throw new RuntimeException('不得删除或改写历史记录');
foreach (['information_schema.COLUMNS', 'information_schema.STATISTICS', 'GENERATED ALWAYS', 'deleted_at', 'active_name', 'DO 0'] as $part) {
    if (!str_contains($sql, $part)) throw new RuntimeException('缺少重入/唯一性契约：' . $part);
}
if (strpos($sql, 'ADD UNIQUE KEY') >= strpos($sql, 'DROP INDEX')) throw new RuntimeException('必须先建立新约束再移除旧索引');
$method = new ReflectionMethod(app\common\service\MigrationService::class, 'statements');
if (count($method->invoke(new app\common\service\MigrationService(), $sql)) !== 13) throw new RuntimeException('迁移语句解析错误');
$controller = new ReflectionClass(app\console\controller\ai\Ai::class);
foreach (['conversationGroupIndex', 'conversationGroupCreate', 'conversationGroupUpdate', 'conversationGroupDelete', 'conversationStateUpdate'] as $action) {
    if (!$controller->getMethod($action)->getAttributes()) throw new RuntimeException('缺少注解路由：' . $action);
}
echo "AI conversation migration/route contract: PASS\n";
