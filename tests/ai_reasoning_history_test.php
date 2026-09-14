<?php

declare(strict_types=1);
require __DIR__ . '/ai_phase2_services_test.php';

use app\console\ai\service\AiAgentOrchestrator;
use app\console\ai\service\AiConversationService;
use app\console\ai\job\AiAgentJob;
use app\console\ai\service\AiAuditService;

$memory = new MemoryAiStore();
$service = new AiConversationService($memory, ['max_rounds'=>3]);
$conversation = $service->createConversation(7, []);
$user = $service->appendUserMessage($conversation['id'], 7, ['content'=>[['type'=>'text','text'=>'hello']]]);
$context = ['protocol'=>'openai-chat','data'=>['reasoning_content'=>'private-history-secret']];
$provider = new class($context) {
    public array $received = [];
    public function __construct(private array $context) {}
    public function chat(array $messages, array $tools): array {
        $this->received[] = $messages;
        return ['content'=>'visible','protocolContext'=>$this->context,'toolCalls'=>count($this->received) < 3 ? [['id'=>'c' . count($this->received),'name'=>'inspect','arguments'=>[]]] : [],'usage'=>[]];
    }
};
$task = $service->createPublicTask($conversation['id'], 7, ['message_id'=>$user['id'],'idempotency_key'=>'reasoning']);
(new AiAgentJob($memory, new AiAgentOrchestrator($provider, $executor)))->fire($queueJob, ['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
phase2Expect(($provider->received[2][1]['protocol_context'] ?? null) === $context && ($provider->received[2][3]['protocol_context'] ?? null) === $context, '工具多轮必须保留推理上下文');
phase2Expect(!str_contains(json_encode($memory->events), 'private-history-secret'), '持久化事件不得包含推理');
$next = $service->appendUserMessage($conversation['id'], 7, ['content'=>[['type'=>'text','text'=>'next']]]);
$nextTask = $service->createPublicTask($conversation['id'], 7, ['message_id'=>$next['id'],'idempotency_key'=>'next']);
$history = $nextTask['input']['messages'];
phase2Expect(array_column($history, 'role') === ['user','assistant','tool','assistant','tool','assistant','user'], '新任务保留完整工具往返顺序');
phase2Expect($history[1]['protocol_context'] === $context && $history[5]['protocol_context'] === $context, '包括非工具最终回复的推理历史');
phase2Expect(!str_contains(json_encode($service->listMessages($conversation['id'], 7)), 'private-history-secret'), '消息列表隐藏私有内容');
phase2Expect(!str_contains(json_encode($service->messagePage($conversation['id'], 7, [])), 'private-history-secret'), '分页消息隐藏私有内容');
phase2Expect(!str_contains(json_encode($service->getTask($nextTask['id'], 7)), 'private-history-secret'), '任务详情隐藏输入和恢复上下文');
$audit = new AiAuditService(sys_get_temp_dir());
phase2Expect(!str_contains(json_encode($audit->redact(['protocol_context'=>$context,'reasoning_content'=>'private-history-secret','signature'=>'private-history-secret','encrypted_content'=>'private-history-secret','block'=>['type'=>'redacted_thinking','data'=>'private-history-secret']])), 'private-history-secret'), '日志递归屏蔽协议私有字段和块');
phase2Expect(!str_contains($audit->redact(json_encode(['output'=>[['type'=>'reasoning','encrypted_content'=>'private-history-secret']]])), 'private-history-secret'), '序列化协议日志同样隐藏私有内容');
$approvalTool = new class implements \app\console\ai\contract\AiToolExecutor {
    public function execute(array $call): array { return ['status'=>'awaiting_approval','approvalId'=>1]; }
};
$pausedProvider = new class($context) {
    public function __construct(private array $context) {}
    public function chat(array $messages, array $tools): array { return ['content'=>'visible','protocolContext'=>$this->context,'toolCalls'=>[['id'=>'paused','name'=>'inspect','arguments'=>[]]]]; }
};
$paused = (new AiAgentOrchestrator($pausedProvider, $approvalTool))->run([], [], ['maxRounds'=>1]);
phase2Expect($paused['resume']['messages'][0]['protocol_context'] === $context, '审批恢复快照必须保留协议上下文');
foreach (['openai-chat','openai-responses','anthropic-messages'] as $protocolName) {
    $memory = new MemoryAiStore();
    $service = new AiConversationService($memory, ['max_rounds'=>3]);
    $conversation = $service->createConversation(7, []);
    $user = $service->appendUserMessage($conversation['id'], 7, ['content'=>[['type'=>'text','text'=>'hello']]]);
    $task = $service->createPublicTask($conversation['id'], 7, ['message_id'=>$user['id'],'idempotency_key'=>$protocolName]);
    $wire = [];
    $client = new \GuzzleHttp\Client(['handler'=>static function ($request) use (&$wire, $protocolName) {
        $wire[] = json_decode((string) $request->getBody(), true);
        $first = count($wire) === 1;
        $body = match ($protocolName) {
            'openai-chat' => ['choices'=>[['message'=>['content'=>'visible','reasoning_content'=>'private-job-secret'] + ($first ? ['tool_calls'=>[['id'=>'approval-call','type'=>'function','function'=>['name'=>'inspect','arguments'=>'{}']]]] : [])]]],
            'openai-responses' => ['status'=>'completed','output'=>array_merge([['type'=>'reasoning','id'=>'r1','summary'=>[],'encrypted_content'=>'private-job-secret']], $first ? [['type'=>'function_call','call_id'=>'approval-call','name'=>'inspect','arguments'=>'{}']] : [['type'=>'message','content'=>[['type'=>'output_text','text'=>'visible']]]])],
            default => ['stop_reason'=>$first ? 'tool_use' : 'end_turn','content'=>array_merge([['type'=>'thinking','thinking'=>'private-job-secret','signature'=>'signature']], $first ? [['type'=>'tool_use','id'=>'approval-call','name'=>'inspect','input'=>new stdClass()]] : [['type'=>'text','text'=>'visible']])],
        };
        return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [], json_encode($body)));
    }]);
    $gateway = new \app\common\ai\provider\OpenAiCompatibleGateway($client, ['base_url'=>'https://api.example.com/v1','api_key'=>'test','model'=>'m','protocol'=>$protocolName,'max_output_tokens'=>100], static fn()=>['93.184.216.34']);
    $tool = new class implements \app\console\ai\contract\AiToolExecutor {
        public function execute(array $call): array { return isset($call['name']) ? ['status'=>'awaiting_approval','approvalId'=>1] : ['status'=>'succeeded']; }
    };
    $security = new MemorySecurityStore();
    $runner = new AiAgentJob($memory, new AiAgentOrchestrator($gateway, $tool), null, $security);
    $runner->fire($queueJob, ['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
    phase2Expect($memory->tasks[$task['id']]['status'] === 'paused', $protocolName . ' 真实网关审批暂停');
    $security->createToolCall(['task_id'=>$task['id'],'conversation_id'=>$conversation['id'],'status'=>'awaiting_approval','idempotency_key'=>'approval-call']);
    $memory->tasks[$task['id']]['status'] = 'resume_pending';
    $runner->fire($queueJob, ['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
    phase2Expect($memory->tasks[$task['id']]['status'] === 'succeeded' && str_contains(json_encode($wire[1]), 'private-job-secret'), $protocolName . ' 审批恢复回传原始推理');
    phase2Expect(!str_contains(json_encode($memory->events), 'private-job-secret'), $protocolName . ' 审批事件不泄露推理');
}
echo "AI reasoning history: PASS\n";
