<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function phase3ContractExpect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$root = dirname(__DIR__);
$controller = (string)file_get_contents($root.'/app/console/controller/ai/Ai.php');
foreach (['approvalIndex','approvalDecide','taskToolCalls','toolCallLog','sandboxStatus','sandboxCleanup'] as $method) phase3ContractExpect(str_contains($controller,"function {$method}("),"控制器缺少 {$method}");
foreach (["#[Get('approvals')]","#[Post('approvals/:id/decision')]","#[Get('tasks/:id/tool-calls')]","#[Get('tool-calls/:id/logs/:stream')]","#[Get('sandbox/status')]","#[Post('sandbox/cleanup')]"] as $route) phase3ContractExpect(str_contains($controller,$route),"缺少真实 API {$route}");
phase3ContractExpect(str_contains($controller,'changeSetApply('),'阶段四扩展不得破坏阶段三安全控制器契约');
$job = (string)file_get_contents($root.'/app/console/ai/job/AiAgentJob.php');
phase3ContractExpect(str_contains($job,'ContainerAiToolExecutor') && !str_contains($job,'NotConfiguredAiToolExecutor'), '生产 Job 默认必须使用真实容器执行器');
phase3ContractExpect(str_contains($job,"new AgentToolRegistry((array) config('ai.tools.allowlist', []))"), '生产 Job 必须应用 AI_TOOL_ALLOWLIST 且默认 deny');
phase3ContractExpect(str_contains($job,'container_task_id') && str_contains($job,'cleanup('), 'Job 必须管理 sandbox 生命周期');
phase3ContractExpect(str_contains($job, "!== 'paused'") && str_contains($job,'exportChanges('), 'ask 时必须保留 sandbox，终态 cleanup 前必须导出变更');
phase3ContractExpect(str_contains($job,'AiChangeSet::create') && str_contains($job, 'AiChangeSetService::attributes'), 'Job 必须将导出 artifact 保存为 proposed AiChangeSet');
phase3ContractExpect(!preg_match('/catch \(Throwable \$exportException\)[\s\S]*?finally\s*\{[\s\S]*?cleanup\(/', $job), 'artifact 导出失败必须保留 sandbox，禁止 finally cleanup 丢失结果');
phase3ContractExpect(str_contains($controller, 'exportChanges(') && str_contains($controller, 'AiChangeSet::create') && strpos($controller, 'exportChanges(') < strpos($controller, 'cleanup('), '拒绝或过期清理前必须持久化 proposed AiChangeSet');
$resumeOutbox = (string)file_get_contents($root.'/app/console/ai/service/AiResumeOutboxService.php');
phase3ContractExpect(str_contains($controller, 'AiResumeOutboxService') && str_contains($controller, 'Db::transaction') && str_contains($controller, 'AiOutbox::create'), '审批 Controller 必须将事务、状态 CAS 与 outbox 持久化注入恢复服务');
phase3ContractExpect(str_contains($resumeOutbox, "'status'=>'resume_pending'") && str_contains($resumeOutbox, 'appendOutbox') && str_contains($resumeOutbox, '$this->transaction'), '恢复服务必须在同一事务内写 resume_pending 与 outbox');
phase3ContractExpect(!preg_match("/if \(\(\$approval\['status'\].*?Queue::connection/s", $controller), '审批决定请求不得直接投递队列');
phase3ContractExpect(str_contains($controller,'getTask((int) $pending') && str_contains($controller, '$adminId'), '审批决定必须验证真实管理员任务所有权');
phase3ContractExpect(str_contains($controller, "=== 'expired'") && str_contains($controller, 'finalizeRetainedSandbox('), '过期审批必须导出结果并清理 retained sandbox');
phase3ContractExpect(str_contains($controller, "['status'=>'denied','approval_decision'=>'expired'"), '过期审批的工具调用必须使用 schema 支持的 denied 状态与 expired decision');
phase3ContractExpect(str_contains($controller, "\$hasChangeSet = (int)(\$task['change_set_id'] ?? 0) > 0") && str_contains($controller, 'if (!$hasChangeSet) {') && !str_contains($controller, "if ((int)(\$task['change_set_id'] ?? 0) > 0) return;"), '终止审批重复请求必须跳过重复导出，但仍补偿 cleanup');
phase3ContractExpect(str_contains($job, "['paused', 'resume_pending']") && str_contains($job, 'container_task_id') && str_contains($job, 'workspace_path'), 'paused 与 resume_pending 任务必须复用原 sandbox，不得重新创建');
phase3ContractExpect(str_contains($job, 'awaitingToolCall(') && str_contains($job, 'resume('), '恢复必须由数据库 awaiting_approval 工具调用驱动，不依赖模型携带 approvalId');
$securityStore = (string)file_get_contents($root.'/app/console/ai/repository/DatabaseAiSecurityStore.php');
phase3ContractExpect(str_contains($securityStore, "where('status', 'approved')->update(['status' => 'consumed'])") && !str_contains($securityStore, 'lock(true)'), 'once 审批必须以单条条件 UPDATE 原子消费');
$migrations = glob($root.'/database/migrations/094_*.sql') ?: [];
phase3ContractExpect(count($migrations) === 1 && basename($migrations[0]) === '094_ai_agent_runtime.sql', '已有 093 时阶段三迁移必须顺延为唯一 094');
$latest = (string)file_get_contents($root.'/database/migrations/100_ai_phase3_security_hardening.sql');
$compensationPath = $root.'/database/migrations/101_ai_phase3_access_and_permission_compensation.sql';
phase3ContractExpect(is_file($compensationPath), '必须使用最大编号后的 101 forward-only 补偿 migration');
$compensation = (string)file_get_contents($compensationPath);
phase3ContractExpect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME|DELETE)\b/i', preg_replace('/^\s*--.*$/m','',$compensation) ?? $compensation), '101 必须 forward-only');
phase3ContractExpect(str_contains($compensation, "'development:ai:full-access'") && str_contains($compensation, "'capability'"), '101 必须新增独立 full-access capability');
phase3ContractExpect(substr_count($latest, "\\'") >= 12, '100 动态 ALTER TABLE 的 enum 引号必须转义');
phase3ContractExpect(str_contains($latest,"\\'consumed\\'") && !preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i',preg_replace('/^\s*--.*$/m','',$latest)??$latest), '100 必须 forward-only 支持 once 原子消费');
phase3ContractExpect(str_contains($latest, "enum(\\'pending\\',\\'approved\\',\\'consumed\\'") && str_contains($latest, "enum(\\'pending\\',\\'created\\',\\'running\\',\\'exported\\'"), '100 动态 ALTER TABLE 的 enum 引号必须转义');
$sql = (string)file_get_contents($migrations[0]); $withoutComments=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
phase3ContractExpect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME)\b/i',$withoutComments), '094 必须 forward-only');
foreach (['approvalIndex','approvalDecide','taskToolCalls','toolCallLog','sandboxStatus','sandboxCleanup'] as $permission) {
    $resource = \app\console\authorization\service\PermissionResource::fromParts('console', 'ai\\Ai', $permission);
    phase3ContractExpect($resource['obj'] === 'console/development.ai', "AI Controller 目录变化不得改变稳定权限资源：{$resource['obj']}");
    phase3ContractExpect(str_contains($compensation, "'{$resource['act']}'"), "101 必须启用运行时 action：{$resource['act']}");
}
phase3ContractExpect(!str_contains($compensation, 'console/ai.ai') && str_contains($compensation, "`obj`='console/development.ai'") && str_contains($compensation, '`status`=1'), '101 必须沿用稳定权限资源并启用现有权限');
foreach (['workspace_path','sandbox_status','cleanup_at'] as $column) phase3ContractExpect(str_contains($sql,"`{$column}`"),"094 缺少字段 {$column}");
$reliability = (string)file_get_contents($root.'/database/migrations/105_ai_phase3_reliability.sql');
foreach (['base_digest','cleanup_lease_owner','cleanup_lease_expires_at','sandbox_volume','fun_ai_outbox'] as $contract) phase3ContractExpect(str_contains($reliability, $contract), "105 缺少可靠性契约 {$contract}");
phase3ContractExpect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME|DELETE)\b/i', preg_replace('/^\s*--.*$/m','',$reliability) ?? $reliability), '105 必须 forward-only');
$migrationService = new \app\common\service\MigrationService();
$hexCompatibility = new ReflectionMethod($migrationService, 'prepareAiPermissionHexCompatibility');
$hexCompatibility->setAccessible(true);
$legacy101 = (string)file_get_contents($root.'/database/migrations/101_ai_phase3_access_and_permission_compensation.sql');
$prepared101 = $hexCompatibility->invoke($migrationService, 'core', '101_ai_phase3_access_and_permission_compensation', $legacy101);
phase3ContractExpect(!str_contains($prepared101, "X'E585A8E9809AE8AEFE5968E'") && str_contains($prepared101, "X'E585A8E9809AE69D83E99990'"), '迁移执行器必须在不改历史 101 checksum 的前提下修复畸形 capability hex');
foreach (['091_ai_development_permissions.sql','092_ai_task_events_queue.sql','094_ai_agent_runtime.sql'] as $migrationName) {
    $permissionSql = (string)file_get_contents($root.'/database/migrations/'.$migrationName);
    phase3ContractExpect(!preg_match("/'console\\/development\\.ai:[^']*[A-Z][^']*'/", $permissionSql) && !preg_match("/'console\\/development\\.ai','[^']*[A-Z][^']*'/", $permissionSql), "{$migrationName} 权限必须使用运行时 lowercase");
}
phase3ContractExpect(str_contains($latest, 'LOWER(`act`)') && str_contains($latest, 'LOWER(`code`)'), '100 必须修复已部署 camelCase 权限数据');
foreach (['admin-web/','public/admin-web'] as $forbidden) phase3ContractExpect(!str_contains($sql,$forbidden),'阶段三 migration 不得修改前端/public');
echo "AI phase 3 contract tests: PASS\n";
