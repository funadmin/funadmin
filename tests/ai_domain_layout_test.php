<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expected = [
    'job/AiAgentJob.php',
    'service/AgentSandboxManager.php',
    'service/AgentToolRegistry.php',
    'service/AiAgentOrchestrator.php',
    'service/AiApprovalService.php',
    'service/AiAuditService.php',
    'service/AiConversationService.php',
    'service/AiEventStreamService.php',
    'service/ApprovalPolicyEngine.php',
    'contract/AiConversationStore.php',
    'contract/AiSecurityStore.php',
    'contract/AiToolExecutor.php',
    'contract/DockerProcessRunner.php',
    'repository/DatabaseAiConversationStore.php',
    'repository/DatabaseAiSecurityStore.php',
    'infrastructure/ContainerAiToolExecutor.php',
    'infrastructure/NativeDockerProcessRunner.php',
    'infrastructure/NotConfiguredAiToolExecutor.php',
    'infrastructure/ProcessResult.php',
];
foreach ($expected as $relative) {
    $path = $root . '/app/admin/ai/' . $relative;
    $expect(is_file($path), 'AI 领域文件必须位于 app/admin/ai：' . $relative);
}
foreach (glob($root . '/app/admin/model/Ai*.php') ?: [] as $path) {
    $expect(false, 'AI Model 不得继续平铺在 app/admin/model：' . basename($path));
}
foreach (glob($root . '/app/admin/service/*Ai*.php') ?: [] as $path) {
    $expect(false, 'AI Service 不得继续平铺在 app/admin/service：' . basename($path));
}
$controllerPath = $root . '/app/admin/controller/ai/Ai.php';
$expect(is_file($controllerPath), 'AI Controller 必须位于 app/admin/controller/ai，HTTP adapter 必须留在 controller tree');
$expect(!is_file($root . '/app/admin/ai/controller/Ai.php'), '旧 AI Controller 路径必须不存在');
$controller = (string) file_get_contents($controllerPath);
$expect(str_contains($controller, "namespace app\\admin\\controller\\ai;"), 'AI Controller namespace 必须与目录一致');
$expect(str_contains($controller, "#[Group('development/ai', ['complete_match' => true])]"), 'AI HTTP 路径必须保持不变');
$expect(is_dir($root . '/app/admin/ai'), 'AI 其余 service/model/job/contract/repository/infrastructure 必须仍在 app/admin/ai');
$job = (string) file_get_contents($root . '/app/admin/ai/job/AiAgentJob.php');
$expect(str_contains($job, 'namespace app\\admin\\ai\\job;'), 'AI Job namespace 必须与目录一致');

echo "AI domain layout tests passed\n";
