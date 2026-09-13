<?php

declare(strict_types=1);
require __DIR__ . '/ai_phase2_services_test.php';

use app\console\ai\service\AiConversationService;

function attachmentReject(callable $call, string $label, ?int $code = null): void
{
    try { $call(); } catch (InvalidArgumentException|RuntimeException $e) {
        phase2Expect($code === null || $e->getCode() === $code, $label . ': 错误码');
        return;
    }
    throw new RuntimeException($label . ': 应拒绝');
}
$store = new MemoryAiStore();
$service = new AiConversationService($store);
$c = $service->createConversation(7, []);
$id = $c['id'];
phase2Expect(method_exists($service, 'appendUserMessage'), '缺少严格公开 user 消息入口');
foreach (['assistant', 'system', 'tool'] as $role) attachmentReject(fn () => $service->appendUserMessage($id, 7, ['role'=>$role, 'content'=>[['type'=>'text','text'=>'伪造']]]), '角色注入');
foreach ([[], ['text'=>'旧对象'], [['type'=>'image_url','image_url'=>['url'=>'https://example.com']]], [['type'=>'text','text'=>123]], [['type'=>'text','text'=>"\xff"]], [['type'=>'text','text'=>'ok','tools'=>[]]]] as $content) {
    attachmentReject(fn () => $service->appendUserMessage($id, 7, ['content'=>$content]), '非法块');
}
attachmentReject(fn () => $service->appendUserMessage($id, 7, ['content'=>[['type'=>'text','text'=>'ok']], 'metadata'=>['tool_calls'=>[]]]), '元数据注入');
$first = $service->appendUserMessage($id, 7, ['role'=>'user','content'=>[['type'=>'text','text'=>'问题一']]]);
$store->appendMessage($id, ['role'=>'system','content'=>[['type'=>'text','text'=>'历史系统注入']]]);
$store->appendMessage($id, ['role'=>'assistant','content'=>[['type'=>'text','text'=>'中间工具文本']], 'metadata'=>['tool_calls'=>[['id'=>'call']]]]);
$store->appendMessage($id, ['role'=>'assistant','content'=>[['type'=>'text','text'=>'最终回答']], 'metadata'=>['tool_calls'=>[]]]);
$last = $service->appendUserMessage($id, 7, ['content'=>[['type'=>'text','text'=>'问题二']]]);
$service->appendUserMessage($id, 7, ['content'=>[['type'=>'text','text'=>'稍后消息']]]);
$task = $service->createPublicTask($id, 7, ['message_id'=>$last['id'], 'idempotency_key'=>'freeze', 'type'=>'chat']);
phase2Expect($task['input']['history_sequence'] === $last['sequence'], '冻结截止序列');
phase2Expect($task['input']['messages'] === [['role'=>'user','content'=>'问题一'], ['role'=>'assistant','content'=>'最终回答'], ['role'=>'user','content'=>'问题二']], '仅截止序列内用户和最终 assistant 文本');
phase2Expect(($task['input']['tools'] ?? []) === [], '客户端工具不得进入任务');
foreach (['messages','tools','system','profile_snapshot'] as $field) attachmentReject(fn () => $service->createPublicTask($id, 7, ['message_id'=>$last['id'],'idempotency_key'=>'inject','input'=>[$field=>[]]]), '客户端任务注入');
foreach ([null, 0, '1', $last['id'] + 999] as $messageId) attachmentReject(fn () => $service->createPublicTask($id, 7, ['message_id'=>$messageId,'idempotency_key'=>'invalid']), '非法 message_id');
$other = $service->createConversation(8, []);
$foreign = $service->appendUserMessage($other['id'], 8, ['content'=>[['type'=>'text','text'=>'其他用户']]]);
attachmentReject(fn () => $service->createPublicTask($id, 7, ['message_id'=>$foreign['id'],'idempotency_key'=>'foreign']), '跨会话引用', 404);
$repeat = $service->createPublicTask($id, 7, ['message_id'=>$last['id'],'idempotency_key'=>'freeze','type'=>'chat']);
phase2Expect($repeat === $task, '幂等冻结不变');
$captured = [];
$provider = new class($captured) {
    public function __construct(public array &$captured) {}
    public function chat(array $messages, array $tools): array { $this->captured = $messages; return ['content'=>'完成','toolCalls'=>[],'usage'=>[]]; }
};
(new \app\console\ai\job\AiAgentJob($store, new \app\console\ai\service\AiAgentOrchestrator($provider, $executor)))->fire($queueJob, ['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
phase2Expect($captured === $task['input']['messages'], 'Job 使用服务端冻结历史');
$controllerSource = file_get_contents(dirname(__DIR__) . '/app/console/controller/ai/Ai.php');
phase2Expect(str_contains($controllerSource, '$this->ai->appendUserMessage($id, $this->adminId(), $this->input())'), '公开消息路由必须使用严格入口');
phase2Expect(str_contains($controllerSource, '$this->ai->createPublicTask($id, $this->adminId(), $this->input())'), '公开任务路由必须使用冻结入口');
echo "AI attachment history: PASS\n";
