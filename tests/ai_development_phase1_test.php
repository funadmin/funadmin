<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\model\concern\LaravelSoftDelete;
use app\common\service\MigrationService;
use app\console\ai\model\AiApproval;
use app\console\ai\model\AiChangeSet;
use app\console\ai\model\AiConversation;
use app\console\ai\model\AiMessage;
use app\console\ai\model\AiTask;
use app\console\ai\model\AiToolCall;
use app\console\ai\service\ApprovalPolicyEngine;
use think\App;

function aiPhase1Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function aiPhase1Protected(object $object, string $property): mixed
{
    $reflection = new ReflectionProperty($object, $property);
    $reflection->setAccessible(true);

    return $reflection->getValue($object);
}

$root = dirname(__DIR__);
$migrations = array_map('basename', glob($root . '/database/migrations/*.sql') ?: []);
sort($migrations, SORT_STRING);
aiPhase1Expect(array_values(array_filter($migrations, static fn (string $name): bool => str_starts_with($name, '090_'))) === ['090_ai_development_assistant.sql'], '090 migration 名称必须严格符合批准计划');
aiPhase1Expect(array_values(array_filter($migrations, static fn (string $name): bool => str_starts_with($name, '091_'))) === ['091_ai_development_permissions.sql'], '091 migration 必须保留编号且唯一');

$schemaPath = $root . '/database/migrations/090_ai_development_assistant.sql';
aiPhase1Expect(is_file($schemaPath), '缺少 090_ai_development_assistant.sql');
$schemaSql = (string) file_get_contents($schemaPath);
$schemaWithoutComments = preg_replace('/^\s*--.*$/m', '', $schemaSql) ?? $schemaSql;
aiPhase1Expect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i', $schemaWithoutComments), '090 必须 forward-only');
$tables = ['conversation', 'message', 'task', 'tool_call', 'approval', 'change_set'];
foreach ($tables as $table) {
    aiPhase1Expect(str_contains($schemaSql, "fun_ai_{$table}"), '090 缺少表：fun_ai_' . $table);
    aiPhase1Expect(str_contains($schemaSql, "TABLE_NAME='fun_ai_{$table}'"), '090 必须幂等检查表：fun_ai_' . $table);
}
foreach (['fun_ai_session', 'fun_ai_execution', 'fun_ai_artifact', 'fun_ai_audit_log'] as $wrongTable) {
    aiPhase1Expect(!str_contains($schemaSql, $wrongTable), '090 不得保留错误契约：' . $wrongTable);
}
aiPhase1Expect((bool) preg_match('/`admin_id` bigint unsigned NOT NULL/', $schemaSql), '管理员 ID 必须与当前 fun_admin.id 的 bigint unsigned 类型一致');
foreach (['fk_ai_conversation_admin', 'fk_ai_message_conversation', 'fk_ai_task_conversation', 'fk_ai_tool_call_task', 'fk_ai_approval_tool_call', 'fk_ai_change_set_approval'] as $foreignKey) {
    aiPhase1Expect(str_contains($schemaSql, $foreignKey), '090 缺少关系完整性约束：' . $foreignKey);
}
foreach (['uk_ai_conversation_uuid', 'uk_ai_task_idempotency', 'uk_ai_tool_call_idempotency', 'uk_ai_approval_nonce', 'uk_ai_approval_digest', 'uk_ai_change_set_idempotency', 'uk_ai_change_set_digest'] as $uniqueKey) {
    aiPhase1Expect(str_contains($schemaSql, $uniqueKey), '090 缺少唯一约束：' . $uniqueKey);
}
foreach (['idx_ai_conversation_admin_status', 'idx_ai_message_conversation_sequence', 'idx_ai_task_conversation_status', 'idx_ai_tool_call_task_status', 'idx_ai_approval_conversation_status', 'idx_ai_change_set_conversation_status'] as $index) {
    aiPhase1Expect(str_contains($schemaSql, $index), '090 缺少查询索引：' . $index);
}
foreach (['request_approval', 'agent_approval', 'full_access'] as $mode) {
    aiPhase1Expect(str_contains($schemaSql, "\\'{$mode}\\'"), '090 缺少审批模式：' . $mode);
}
$schemaLines = array_values(array_filter(explode("\n", $schemaSql), static fn (string $line): bool => str_contains($line, "TABLE_NAME='fun_ai_")));
$tableSql = static function (string $table) use ($schemaLines): string {
    $line = current(array_filter($schemaLines, static fn (string $candidate): bool => str_contains($candidate, "TABLE_NAME='fun_ai_{$table}'")));
    aiPhase1Expect(is_string($line), '090 缺少建表语句：fun_ai_' . $table);

    return $line;
};
$assertColumns = static function (string $table, array $columns) use ($tableSql): void {
    $sql = $tableSql($table);
    foreach ($columns as $column) {
        aiPhase1Expect(str_contains($sql, "`{$column}`"), "fun_ai_{$table} 缺少字段：{$column}");
    }
};
$conversationStatuses = ['draft', 'planning', 'awaiting_approval', 'running', 'reviewing', 'applying', 'completed', 'paused', 'cancelled', 'failed', 'conflict', 'recovery_required'];
$conversationSql = $tableSql('conversation');
foreach ($conversationStatuses as $status) {
    aiPhase1Expect(str_contains($conversationSql, "\\'{$status}\\'"), 'conversation 缺少状态：' . $status);
}
$taskTypes = ['chat', 'crud', 'code_change', 'fix', 'test', 'migration'];
$taskSql = $tableSql('task');
foreach ($taskTypes as $type) {
    aiPhase1Expect(str_contains($taskSql, "\\'{$type}\\'"), 'task 缺少类型：' . $type);
}
$assertColumns('task', ['stage', 'provider', 'model', 'container_task_id', 'max_rounds', 'timeout_seconds', 'max_cost', 'input_token_budget', 'output_token_budget', 'total_token_budget', 'usage', 'result_summary', 'test_result', 'change_set_id', 'recovery_status', 'operation_token']);
$assertColumns('tool_call', ['risk_level', 'redacted_arguments', 'approval_decision', 'approval_id', 'exit_code', 'stdout_summary', 'stdout_hash', 'stdout_path', 'stderr_summary', 'stderr_hash', 'stderr_path', 'duration_ms', 'side_effects']);
$assertColumns('approval', ['scope', 'risk_reason', 'impact', 'cas_version', 'nonce', 'digest']);
$assertColumns('change_set', ['patch_path', 'patch_sha256', 'base_file_hashes', 'added_count', 'modified_count', 'deleted_count', 'renamed_count', 'test_status', 'test_result', 'security_status', 'security_result', 'final_approval_id', 'applied_by', 'transaction_id', 'recovery_status']);
aiPhase1Expect(str_contains($tableSql('approval'), "`scope` enum(\\'once\\',\\'session_operation\\')"), 'approval scope 必须限制为 once/session_operation');
aiPhase1Expect(!preg_match('/`arguments`\s+json/', $tableSql('tool_call')), '工具调用不得持久化原始 arguments/secret');
aiPhase1Expect(str_contains($schemaSql, 'fk_ai_task_change_set'), 'task.change_set_id 缺少后置关系约束');
aiPhase1Expect(str_contains($schemaSql, 'fk_ai_tool_call_approval'), 'tool_call.approval_id 缺少后置关系约束');
$toolCallSql = $tableSql('tool_call');
aiPhase1Expect(!str_contains($toolCallSql, '`deleted_at`'), '工具调用安全记录不得软删');

$modelContracts = [
    AiConversation::class => ['name' => 'ai_conversation', 'json' => ['context'], 'softDelete' => true],
    AiMessage::class => ['name' => 'ai_message', 'json' => ['content', 'metadata', 'usage'], 'softDelete' => true],
    AiTask::class => ['name' => 'ai_task', 'json' => ['input', 'output', 'error', 'usage', 'test_result'], 'softDelete' => true],
    AiToolCall::class => ['name' => 'ai_tool_call', 'json' => ['redacted_arguments', 'result', 'error', 'side_effects'], 'softDelete' => false],
    AiApproval::class => ['name' => 'ai_approval', 'json' => ['request', 'decision', 'impact'], 'softDelete' => true],
    AiChangeSet::class => ['name' => 'ai_change_set', 'json' => ['manifest', 'summary', 'base_file_hashes', 'test_result', 'security_result'], 'softDelete' => true],
];
foreach ($modelContracts as $class => $contract) {
    aiPhase1Expect(class_exists($class), '缺少模型：' . $class);
    $model = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    aiPhase1Expect(aiPhase1Protected($model, 'name') === $contract['name'], $class . ' 表名错误');
    aiPhase1Expect(array_diff($contract['json'], (array) aiPhase1Protected($model, 'json')) === [], $class . ' JSON cast 不完整');
    aiPhase1Expect(aiPhase1Protected($model, 'jsonAssoc') === true, $class . ' JSON 必须转换为关联数组');
    $usesSoftDelete = in_array(LaravelSoftDelete::class, class_uses($class), true);
    aiPhase1Expect($usesSoftDelete === $contract['softDelete'], $class . ' 软删除策略错误');
}
foreach (['AiSession', 'AiExecution', 'AiArtifact', 'AiAuditLog'] as $wrongModel) {
    aiPhase1Expect(!class_exists('app\\console\\model\\' . $wrongModel), '不得保留错误模型：' . $wrongModel);
}
$modelRelations = [
    AiConversation::class => ['messages', 'tasks'],
    AiMessage::class => ['conversation'],
    AiTask::class => ['conversation', 'toolCalls'],
    AiToolCall::class => ['task'],
];
foreach ($modelRelations as $class => $relations) {
    foreach ($relations as $relation) {
        aiPhase1Expect(method_exists($class, $relation), "{$class} 缺少项目惯例要求的关键关联：{$relation}");
    }
}

$configPath = $root . '/config/ai.php';
aiPhase1Expect(is_file($configPath), '缺少 config/ai.php');
$configSource = (string) file_get_contents($configPath);
aiPhase1Expect(!preg_match('/[\x27\x22](?:sk-|Bearer\s+)[A-Za-z0-9_-]{8,}/', $configSource), 'config/ai.php 不得硬编码 secret');
$app = new App($root . '/');
$app->initialize();
$aiConfig = require $configPath;
aiPhase1Expect(($aiConfig['approval_mode'] ?? null) === 'request_approval', '默认审批模式必须为 request_approval');
foreach (['provider', 'limits', 'sandbox', 'tools', 'storage'] as $section) {
    aiPhase1Expect(isset($aiConfig[$section]) && is_array($aiConfig[$section]), 'AI 配置缺少分组：' . $section);
}
foreach (['connect_timeout', 'request_timeout'] as $key) {
    aiPhase1Expect(isset($aiConfig['provider'][$key]), 'provider 缺少超时配置：' . $key);
}
foreach (['max_input_tokens', 'max_output_tokens', 'max_total_cost', 'max_rounds'] as $key) {
    aiPhase1Expect(isset($aiConfig['limits'][$key]), 'limits 缺少限制：' . $key);
}
foreach (['image', 'cpu', 'memory_mb', 'disk_mb', 'pids', 'task_timeout', 'network_allowlist'] as $key) {
    aiPhase1Expect(array_key_exists($key, $aiConfig['sandbox']), 'sandbox 缺少配置：' . $key);
}
foreach (['log_max_bytes', 'log_retention_days'] as $key) {
    aiPhase1Expect(isset($aiConfig['storage'][$key]) && $aiConfig['storage'][$key] > 0, 'storage 缺少有效日志限制：' . $key);
}
aiPhase1Expect($aiConfig['sandbox']['network_allowlist'] === [], 'sandbox 网络白名单默认必须为空');
aiPhase1Expect(($aiConfig['sandbox']['network_enabled'] ?? null) === false, 'sandbox 网络默认必须拒绝');
aiPhase1Expect(($aiConfig['tools']['allowlist'] ?? null) === [], 'tool allowlist 默认必须为空');
aiPhase1Expect(($aiConfig['tools']['default'] ?? null) === 'deny', '工具默认策略必须 deny');
foreach (['private_path', 'log_path'] as $key) {
    aiPhase1Expect(isset($aiConfig['storage'][$key]) && str_contains($aiConfig['storage'][$key], 'runtime/'), 'storage 缺少私有路径：' . $key);
}

$permissionSql = (string) file_get_contents($root . '/database/migrations/091_ai_development_permissions.sql');
$permissionWithoutComments = preg_replace('/^\s*--.*$/m', '', $permissionSql) ?? $permissionSql;
aiPhase1Expect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i', $permissionWithoutComments), '091 必须 forward-only');
aiPhase1Expect(!preg_match('/[\x{4e00}-\x{9fff}]/u', $permissionWithoutComments), '091 SQL 中文必须使用 HEX');
foreach (['development:ai:view', 'development:ai:chat', 'development:ai:execute', 'development:ai:approve', 'development:ai:apply', 'development:ai:configure', 'development:ai:audit'] as $permission) {
    aiPhase1Expect(str_contains($permissionSql, "'{$permission}'"), '091 缺少能力权限：' . $permission);
}
foreach (['conversationIndex', 'messageCreate', 'taskExecute', 'approvalDecide', 'changeSetApply', 'configurationUpdate', 'auditIndex'] as $action) {
    $runtimeAction = strtolower($action);
    aiPhase1Expect(str_contains($permissionSql, "'console/development.ai:{$runtimeAction}'"), '091 控制器 action 权限名不明确：' . $runtimeAction);
}
aiPhase1Expect(str_contains($permissionSql, "component=development/ai/index") && (bool) preg_match("/component=development\\/ai\\/index[^\n]*,'_self','[^']*',0,/", $permissionSql), '组件尚不存在时 AI 菜单 status 必须为 0');
$mappingBlock = substr($permissionSql, (int) strpos($permissionSql, 'INNER JOIN ('));
aiPhase1Expect(substr_count($mappingBlock, ' UNION ALL') === 3, '091 只能包含四条可证明等价映射');
aiPhase1Expect(preg_match_all("/'development\\/business'(?: `old_obj`)?,?'view'/", $mappingBlock) === 2, 'view 只能映射 capability view 与 action chat');
aiPhase1Expect(preg_match_all("/'development\\/business'(?: `old_obj`)?,?'generate'/", $mappingBlock) === 2, 'generate 只能映射 capability execute 与 action execute');
foreach (['recover', 'apply-resources', 'save', 'records'] as $forbiddenMapping) {
    aiPhase1Expect(!str_contains($mappingBlock, "'development/business','{$forbiddenMapping}'"), '091 不得自动扩大授权：' . $forbiddenMapping);
}

$engine = new ApprovalPolicyEngine();
$operations = ['read', 'search', 'network', 'write', 'delete', 'shell', 'test', 'build', 'dependency', 'migration', 'git_write', 'apply_workspace', 'host_sensitive', 'deploy'];
$expected = [
    'request_approval' => ['read' => 'allow', 'search' => 'allow'],
    'agent_approval' => ['read' => 'allow', 'search' => 'allow', 'network' => 'allow', 'write' => 'allow', 'test' => 'allow', 'build' => 'allow'],
    'full_access' => array_fill_keys($operations, 'allow'),
];
foreach ($expected as $mode => $overrides) {
    foreach ($operations as $operation) {
        $fallback = $mode === 'full_access' ? 'allow' : 'ask';
        $wanted = $overrides[$operation] ?? $fallback;
        if (in_array($operation, ['host_sensitive', 'deploy'], true)) {
            $wanted = 'deny';
        } elseif ($operation === 'apply_workspace') {
            $wanted = 'ask';
        }
        aiPhase1Expect($engine->decide($mode, $operation) === $wanted, "策略矩阵错误：{$mode}/{$operation}");
        aiPhase1Expect($engine->decide($mode, $operation, false) === $wanted, "调用方布尔值不得降低固定策略：{$mode}/{$operation}");
    }
}
aiPhase1Expect($engine->decide('invalid', 'read') === 'deny', '未知审批模式必须 deny');
aiPhase1Expect($engine->decide('full_access', 'invalid') === 'deny', '未知操作必须 deny');
aiPhase1Expect($engine->decideModeTransition('request_approval', 'agent_approval') === 'ask', '模式升级必须 ask');
aiPhase1Expect($engine->decideModeTransition('agent_approval', 'full_access') === 'ask', '模式升级必须 ask');
aiPhase1Expect($engine->decideModeTransition('full_access', 'request_approval') === 'allow', '模式降级可直接允许');
aiPhase1Expect($engine->decideModeTransition('agent_approval', 'agent_approval') === 'allow', '相同模式无需审批');
aiPhase1Expect($engine->decideModeTransition('invalid', 'full_access') === 'deny', '未知模式切换必须 deny');
aiPhase1Expect($engine->pendingApprovalMode('request_approval', 'full_access') === 'request_approval', '已有 pending approval 必须保持创建时模式快照');

$dbSkipped = true;
if (!extension_loaded('pdo_mysql')) {
    echo "AI development phase 1 MySQL integration: SKIP (pdo_mysql unavailable)\n";
} elseif (!getenv('AI_PHASE1_DB_HOST')) {
    echo "AI development phase 1 MySQL integration: SKIP (AI_PHASE1_DB_HOST not configured)\n";
} else {
    $dbSkipped = false;
    $host = (string) getenv('AI_PHASE1_DB_HOST');
    $port = (string) (getenv('AI_PHASE1_DB_PORT') ?: '3306');
    $user = (string) (getenv('AI_PHASE1_DB_USER') ?: 'root');
    $pass = (string) (getenv('AI_PHASE1_DB_PASS') ?: '');
    $databaseName = 'funadmin_ai_phase1_' . bin2hex(random_bytes(5));
    $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    try {
        $server->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $database = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec("CREATE TABLE fun_admin (id bigint unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY(id)) ENGINE=InnoDB; CREATE TABLE fun_permission (id int unsigned NOT NULL AUTO_INCREMENT,pid int unsigned NOT NULL DEFAULT 0,app_name varchar(50) NOT NULL DEFAULT 'console',code varchar(255) NULL,obj varchar(190) NOT NULL DEFAULT '',act varchar(100) NOT NULL DEFAULT '',name varchar(100) NOT NULL DEFAULT '',resource_type enum('group','route') NOT NULL DEFAULT 'route',status tinyint NOT NULL DEFAULT 1,is_public tinyint NOT NULL DEFAULT 0,source_type varchar(20) NOT NULL DEFAULT 'system',source_name varchar(100) NOT NULL DEFAULT '',created_at datetime NULL,updated_at datetime NULL,sort_order int NOT NULL DEFAULT 999,deleted_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY uk_permission_code(code)); CREATE TABLE fun_admin_menu (id int unsigned NOT NULL AUTO_INCREMENT,pid int unsigned NOT NULL DEFAULT 0,permission_id int unsigned NULL,app_name varchar(50) NOT NULL DEFAULT 'console',name varchar(100) NOT NULL DEFAULT '',href varchar(255) NOT NULL DEFAULT '',query varchar(250) NOT NULL DEFAULT '',target varchar(20) NOT NULL DEFAULT '_self',icon varchar(100) NOT NULL DEFAULT '',status tinyint NOT NULL DEFAULT 1,source_type varchar(20) NOT NULL DEFAULT 'system',source_name varchar(100) NOT NULL DEFAULT '',created_at datetime NULL,updated_at datetime NULL,sort_order int NOT NULL DEFAULT 999,deleted_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY uk_menu_location(app_name,href,query)); CREATE TABLE fun_casbin_rule (id bigint unsigned NOT NULL AUTO_INCREMENT,ptype varchar(10) NOT NULL,v0 varchar(190) NOT NULL DEFAULT '',v1 varchar(190) NOT NULL DEFAULT '',v2 varchar(190) NOT NULL DEFAULT '',v3 varchar(190) NOT NULL DEFAULT '',v4 varchar(190) NOT NULL DEFAULT '',v5 varchar(190) NOT NULL DEFAULT '',rule_hash char(64) NOT NULL,PRIMARY KEY(id),UNIQUE KEY uk_rule_hash(rule_hash)); INSERT INTO fun_admin (id) VALUES (1); INSERT INTO fun_permission (id,pid,code,obj,act,name,resource_type,status,source_type,source_name) VALUES (1,0,NULL,'','','Development','group',1,'admin_web','development_tools'),(2,1,'development:business:view','development/business','view','View','route',1,'admin_web','business_development'),(3,1,'development:business:generate','development/business','generate','Generate','route',1,'admin_web','business_development'); INSERT INTO fun_admin_menu (id,pid,name,href,query,status,source_type,source_name) VALUES (1,0,'Development','development','component=Layout',1,'admin_web','development_tools'); INSERT INTO fun_casbin_rule (ptype,v0,v1,v2,v3,rule_hash) VALUES ('p','role-view','console','development/business','view',SHA2(CONCAT_WS(CHAR(31),'p','role-view','console','development/business','view'),256)),('p','role-generate','console','development/business','generate',SHA2(CONCAT_WS(CHAR(31),'p','role-generate','console','development/business','generate'),256));");
        $statements = new ReflectionMethod(MigrationService::class, 'statements');
        $statements->setAccessible(true);
        $migrationService = new MigrationService();
        foreach ([$schemaSql, $permissionSql] as $sql) {
            foreach ([1, 2] as $_run) {
                foreach ($statements->invoke($migrationService, $sql) as $statement) {
                    $database->exec($statement);
                }
            }
        }
        foreach ($tables as $table) {
            aiPhase1Expect((int) $database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{$databaseName}' AND TABLE_NAME='fun_ai_{$table}'")->fetchColumn() === 1, '真实 migration 未幂等创建表：' . $table);
        }
        foreach (['fun_ai_task' => ['change_set_id', 'operation_token'], 'fun_ai_tool_call' => ['redacted_arguments', 'approval_id'], 'fun_ai_approval' => ['scope', 'cas_version'], 'fun_ai_change_set' => ['patch_sha256', 'base_file_hashes', 'transaction_id']] as $table => $columns) {
            foreach ($columns as $column) {
                aiPhase1Expect((int) $database->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$databaseName}' AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$column}'")->fetchColumn() === 1, "真实 migration 缺少字段：{$table}.{$column}");
            }
        }
        aiPhase1Expect((int) $database->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='{$databaseName}' AND TABLE_NAME='fun_ai_tool_call' AND COLUMN_NAME='arguments'")->fetchColumn() === 0, '真实 migration 不得创建原始 arguments 字段');
        aiPhase1Expect((int) $database->query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='{$databaseName}' AND TABLE_NAME LIKE 'fun_ai_%'")->fetchColumn() >= 14, '真实 migration 关系完整性不足');
        aiPhase1Expect((int) $database->query("SELECT COUNT(*) FROM fun_admin_menu WHERE source_name='ai_development' AND status=0")->fetchColumn() === 1, '091 必须创建唯一禁用菜单');
        aiPhase1Expect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-view' AND ((v2='development/ai' AND v3='view') OR (v2='console/development.ai' AND v3='messagecreate'))")->fetchColumn() === 2, 'view 只能等价映射 view/chat');
        aiPhase1Expect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v0='role-generate' AND ((v2='development/ai' AND v3='execute') OR (v2='console/development.ai' AND v3='taskexecute'))")->fetchColumn() === 2, 'generate 只能等价映射 execute');
        aiPhase1Expect((int) $database->query("SELECT COUNT(*) FROM fun_casbin_rule WHERE v3 IN ('approve','apply','configure','audit','approvaldecide','changesetapply','configurationupdate','auditindex')")->fetchColumn() === 0, '091 不得自动授予高权限');
    } finally {
        $server->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
    }
}

aiPhase1Expect($dbSkipped || getenv('AI_PHASE1_DB_HOST') !== false, '数据库测试状态必须明确');
echo "AI development phase 1 contract tests: PASS\n";
