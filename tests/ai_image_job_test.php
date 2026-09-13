<?php

declare(strict_types=1);
require __DIR__ . '/ai_phase2_services_test.php';
require __DIR__ . '/ai_attachment_repository_test.php';

$memory = new MemoryAiStore();
$conversationService = new \app\console\ai\service\AiConversationService($memory, [], null, null, $service);
\think\facade\Config::set(['provider'=>['name'=>'trusted','model'=>'vision']], 'ai');
$conversation = $conversationService->createConversation(7, ['provider'=>'trusted','model'=>'vision']);
$memory->appendMessage($conversation['id'], ['role'=>'user','content'=>$imageMessage['content']]);
// 保持真实仓储中的消息绑定 ID，内存任务存储仅替代队列表。
$imageMessage['conversation_id'] = $conversation['id'];
$memory->messages = [$imageMessage];
$task = $conversationService->createPublicTask($conversation['id'],7,['message_id'=>$imageMessage['id'],'idempotency_key'=>'vision-job']);
repoExpect(is_array($task['input']['messages'][0]['content']), '公开图片任务冻结引用不再 409');
$memory->tasks[$task['id']]['max_rounds'] = 3;
$wire = [];
$tool = new class implements \app\console\ai\contract\AiToolExecutor {
    public function execute(array $call): array { return isset($call['name']) ? ['status'=>'awaiting_approval','approvalId'=>1] : ['status'=>'succeeded']; }
};
$factory = static function (array $resolved) use (&$wire, $tool): \app\console\ai\service\AiAgentOrchestrator {
    $client = new \GuzzleHttp\Client(['handler'=>static function ($request) use (&$wire) {
        $wire[] = json_decode((string) $request->getBody(), true);
        $message = count($wire) === 1 ? ['content'=>null,'tool_calls'=>[['id'=>'vision-call','type'=>'function','function'=>['name'=>'write','arguments'=>'{}']]]] : ['content'=>'完成'];
        return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [], json_encode(['choices'=>[['message'=>$message]]])));
    }]);
    return new \app\console\ai\service\AiAgentOrchestrator(new \app\common\ai\provider\OpenAiCompatibleGateway($client,$resolved,static fn()=>['93.184.216.34']),$tool);
};
\think\facade\Config::set(['provider'=>array_replace($config,['name'=>$task['provider'], 'model'=>$task['model'], 'model_capabilities'=>[['model'=>$task['model'],'image_input'=>true]]])], 'ai');
$security = new MemorySecurityStore();
$runner = new \app\console\ai\job\AiAgentJob($memory, new \app\console\ai\service\AiAgentOrchestrator(new stdClass(),$tool), null, $security, $factory, null, $service);
$runner->fire($queueJob,['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
repoExpect($memory->tasks[$task['id']]['status'] === 'paused', '图片任务审批暂停');
$resume = $memory->tasks[$task['id']]['output']['resume']['messages'];
repoExpect($resume[0]['content'][0]['type'] === 'private_image', '展开后恢复仍保留私有引用');
repoExpect(!str_contains(json_encode($memory->tasks),'base64'), '任务输入输出审批恢复不含 base64');
$security->createToolCall(['task_id'=>$task['id'],'conversation_id'=>$conversation['id'],'status'=>'awaiting_approval','idempotency_key'=>'vision-call']);
$memory->tasks[$task['id']]['status'] = 'resume_pending';
$runner->fire($queueJob,['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
repoExpect($memory->tasks[$task['id']]['status'] === 'succeeded', '审批恢复完成');
repoExpect(array_column($wire[1]['messages'],'role') === ['user','assistant','tool'], '恢复保留完整工具消息配对');
repoExpect($wire[1]['messages'][0]['content'][0]['image_url'] === $wire[0]['messages'][0]['content'][0]['image_url'], '恢复重新展开相同图片');
repoExpect(!str_contains(json_encode($memory->tasks),'base64'), '完成任务仍无 base64');
echo "AI image job: PASS\n";
