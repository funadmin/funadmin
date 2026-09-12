<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function aiPhase2ContractExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$migrations = array_map('basename', glob($root . '/database/migrations/092_*.sql') ?: []);
sort($migrations);
aiPhase2ContractExpect($migrations === ['092_ai_task_events_queue.sql'], '092 必须以唯一 forward-only migration 新增事件、队列与权限');
foreach ($migrations as $migration) {
    $sql = (string) file_get_contents($root . '/database/migrations/' . $migration);
    $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    aiPhase2ContractExpect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i', $withoutComments), $migration . ' 必须 forward-only');
}
$schema = (string) file_get_contents($root . '/database/migrations/092_ai_task_events_queue.sql');
foreach (['fun_ai_task_event', 'fun_ai_stream_nonce', 'fun_jobs', 'fun_failed_jobs'] as $table) aiPhase2ContractExpect(str_contains($schema, $table), '092 缺少表：' . $table);
foreach (['uk_ai_task_event_sequence', 'idx_ai_task_event_cursor', 'uk_ai_stream_nonce', 'fk_ai_task_event_task'] as $key) aiPhase2ContractExpect(str_contains($schema, $key), '092 缺少约束：' . $key);
$permissions = $schema;
foreach (['conversationCreate', 'conversationRead', 'conversationUpdate', 'conversationDelete', 'messageIndex', 'taskCancel', 'eventTicket', 'eventStream', 'settingsRead', 'settingsTest'] as $action) {
    $runtimeAction = strtolower($action);
    aiPhase2ContractExpect(str_contains($permissions, "'console/development.ai:{$runtimeAction}'"), '092 缺少运行时 action：' . $runtimeAction);
}
$contractSource = (string) file_get_contents(__FILE__);
aiPhase2ContractExpect(str_contains($contractSource, "['090_ai_development_assistant.sql', '091_ai_development_permissions.sql', '092_ai_task_events_queue.sql']"), '092 集成测试必须执行真实 090→091→092 顺序');
aiPhase2ContractExpect(substr_count($contractSource, "source_name,resource_type,name) VALUES ('admin_web','ai_development'") === 1, '092 集成测试不得手工伪造 AI 权限组');
aiPhase2ContractExpect(str_contains($contractSource, 'AI phase 2 MySQL integration: SKIP'), '无 DB 时必须明确报告 SKIP');

$queueSource = (string) file_get_contents($root . '/config/queue.php');
aiPhase2ContractExpect(str_contains($queueSource, "'ai-agent' => ["), '缺少 ai-agent connection');
aiPhase2ContractExpect(str_contains($queueSource, "'queue'      => 'ai-agent'"), 'AI queue 名称错误');
aiPhase2ContractExpect(str_contains($queueSource, "'type'  => 'database'"), 'failed jobs 必须落数据库');

$controller = (string) file_get_contents($root . '/app/console/controller/ai/Ai.php');
aiPhase2ContractExpect(str_contains($controller, 'extends AdminApiController'), 'AI 控制器必须继承 AdminApiController');
foreach (['CheckAdminApiRole::class', 'CheckAdminApiCsrf::class', 'SystemLog::class'] as $middleware) aiPhase2ContractExpect(str_contains($controller, $middleware), 'AI 控制器缺少中间件：' . $middleware);
foreach (['conversationIndex', 'conversationCreate', 'conversationRead', 'conversationUpdate', 'conversationDelete', 'messageIndex', 'messageCreate', 'taskExecute', 'taskCancel', 'eventTicket', 'eventStream', 'settingsRead', 'settingsTest'] as $method) aiPhase2ContractExpect(str_contains($controller, "function {$method}("), 'AI 控制器缺少阶段二契约方法：' . $method);
foreach (['configurationUpdate', 'auditIndex'] as $futureMethod) aiPhase2ContractExpect(!str_contains($controller, "function {$futureMethod}("), '尚未交付能力不得被当前控制器宣称完成：' . $futureMethod);
aiPhase2ContractExpect(str_contains($controller, 'function changeSetApply('), '阶段四扩展不得破坏阶段二控制器契约');
aiPhase2ContractExpect(!str_contains($controller, 'notImplemented'), '阶段二控制器不得保留可误认为已交付的 501 占位方法');
aiPhase2ContractExpect(!str_contains($controller, 'OpenAiCompatibleGateway') || str_contains($controller, 'settingsTest'), 'Provider 只能由 settings test/服务使用');
$jobSource = (string) file_get_contents($root . '/app/console/ai/job/AiAgentJob.php');
aiPhase2ContractExpect(!str_contains($jobSource, 'StubAiToolExecutor'), '生产 Job 默认不得注入测试 stub');
aiPhase2ContractExpect(str_contains($jobSource, 'ContainerAiToolExecutor'), '阶段三后生产 Job 必须接入真实容器工具执行器');
aiPhase2ContractExpect(!is_file($root . '/app/console/ai/infrastructure/StubAiToolExecutor.php'), '测试 stub 不得作为生产服务保留');
$notConfigured = new app\console\ai\infrastructure\NotConfiguredAiToolExecutor();
try {
    $notConfigured->execute(['name' => 'write_file']);
    throw new RuntimeException('未配置工具执行器必须拒绝执行');
} catch (app\common\ai\provider\AiProviderException $exception) {
    aiPhase2ContractExpect($exception->category() === 'not_configured', '未配置工具执行器必须返回明确 not_configured 分类');
}

if (!extension_loaded('pdo_mysql')) {
    echo "AI phase 2 MySQL integration: SKIP (pdo_mysql unavailable)\n";
} elseif (!getenv('AI_PHASE2_DB_HOST')) {
    echo "AI phase 2 MySQL integration: SKIP (AI_PHASE2_DB_HOST not configured)\n";
} else {
    $host = (string) getenv('AI_PHASE2_DB_HOST');
    $port = (string) (getenv('AI_PHASE2_DB_PORT') ?: '3306');
    $user = (string) (getenv('AI_PHASE2_DB_USER') ?: 'root');
    $pass = (string) (getenv('AI_PHASE2_DB_PASS') ?: '');
    $databaseName = 'funadmin_ai_phase2_' . bin2hex(random_bytes(5));
    $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    try {
        $server->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $database = new PDO("mysql:host={$host};port={$port};dbname={$databaseName};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database->exec("CREATE TABLE fun_admin (id bigint unsigned NOT NULL AUTO_INCREMENT,PRIMARY KEY(id)); CREATE TABLE fun_permission (id int unsigned NOT NULL AUTO_INCREMENT,pid int unsigned NOT NULL DEFAULT 0,app_name varchar(50) NOT NULL DEFAULT 'console',code varchar(255) NULL,obj varchar(190) NOT NULL DEFAULT '',act varchar(100) NOT NULL DEFAULT '',name varchar(100) NOT NULL DEFAULT '',resource_type enum('group','route') NOT NULL DEFAULT 'route',status tinyint NOT NULL DEFAULT 1,is_public tinyint NOT NULL DEFAULT 0,source_type varchar(20) NOT NULL DEFAULT 'system',source_name varchar(100) NOT NULL DEFAULT '',created_at datetime NULL,updated_at datetime NULL,sort_order int NOT NULL DEFAULT 999,deleted_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY uk_permission_code(code)); CREATE TABLE fun_admin_menu (id int unsigned NOT NULL AUTO_INCREMENT,pid int unsigned NOT NULL DEFAULT 0,permission_id int unsigned NULL,app_name varchar(50) NOT NULL DEFAULT 'console',name varchar(100) NOT NULL DEFAULT '',href varchar(255) NOT NULL DEFAULT '',query varchar(250) NOT NULL DEFAULT '',target varchar(20) NOT NULL DEFAULT '_self',icon varchar(100) NOT NULL DEFAULT '',status tinyint NOT NULL DEFAULT 1,source_type varchar(20) NOT NULL DEFAULT 'system',source_name varchar(100) NOT NULL DEFAULT '',created_at datetime NULL,updated_at datetime NULL,sort_order int NOT NULL DEFAULT 999,deleted_at datetime NULL,PRIMARY KEY(id),UNIQUE KEY uk_menu_location(app_name,href,query)); CREATE TABLE fun_casbin_rule (id bigint unsigned NOT NULL AUTO_INCREMENT,ptype varchar(10) NOT NULL,v0 varchar(190) NOT NULL DEFAULT '',v1 varchar(190) NOT NULL DEFAULT '',v2 varchar(190) NOT NULL DEFAULT '',v3 varchar(190) NOT NULL DEFAULT '',v4 varchar(190) NOT NULL DEFAULT '',v5 varchar(190) NOT NULL DEFAULT '',rule_hash char(64) NOT NULL,PRIMARY KEY(id),UNIQUE KEY uk_rule_hash(rule_hash)); INSERT INTO fun_admin VALUES (1); INSERT INTO fun_permission (id,source_type,source_name,resource_type,name) VALUES (1,'admin_web','development_tools','group','Development'); INSERT INTO fun_admin_menu (id,name,href,query,source_type,source_name) VALUES (1,'Development','development','component=Layout','admin_web','development_tools');");
        $adminIdSchema = $database->query("SELECT COLUMN_TYPE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin' AND COLUMN_NAME='id'")->fetch(PDO::FETCH_ASSOC);
        aiPhase2ContractExpect($adminIdSchema === ['COLUMN_TYPE' => 'bigint unsigned', 'EXTRA' => 'auto_increment'], 'AI phase 2 fixture 的 fun_admin.id 必须匹配生产 030：bigint unsigned auto_increment');
        $statements = new ReflectionMethod(app\common\service\MigrationService::class, 'statements');
        $statements->setAccessible(true);
        $migrationService = new app\common\service\MigrationService();
        foreach (['090_ai_development_assistant.sql', '091_ai_development_permissions.sql', '092_ai_task_events_queue.sql'] as $file) {
            foreach ([1, 2] as $_run) foreach ($statements->invoke($migrationService, (string) file_get_contents($root . '/database/migrations/' . $file)) as $statement) $database->exec($statement);
        }
        foreach (['fun_ai_task_event', 'fun_ai_stream_nonce', 'fun_jobs', 'fun_failed_jobs'] as $table) aiPhase2ContractExpect((int) $database->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{$databaseName}' AND TABLE_NAME='{$table}'")->fetchColumn() === 1, '真实 092 migration 未幂等创建：' . $table);
        aiPhase2ContractExpect((int) $database->query("SELECT COUNT(*) FROM fun_permission WHERE source_name='ai_development' AND resource_type='group'")->fetchColumn() === 1, '真实 091→092 权限组链路错误');
    } finally {
        $server->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
    }
}

echo "AI phase 2 contract tests: PASS\n";
