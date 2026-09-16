<?php

declare(strict_types=1);
require __DIR__ . '/ai_profile_catalog_test.php';
$profiles = $service;
$repository->rows[2]->configuration = ['provider'=>'custom','protocol'=>'openai-chat','base_url'=>'https://example.com/v1','model'=>'profile-model','max_output_tokens'=>42,'max_input_tokens'=>4000];
require __DIR__ . '/ai_phase2_services_test.php';
$store = new MemoryAiStore();
$service = new \app\console\ai\service\AiConversationService($store, [], null, $profiles);
$conversation = $service->createConversation(7, ['title'=>'档案任务','profile_id'=>2]);
phase2Expect($conversation['profile_id'] === 2 && $conversation['model'] === 'profile-model', '会话选择档案');
$task = $service->createTask($conversation['id'], 7, ['idempotency_key'=>'profile', 'input'=>['messages'=>[['role'=>'user','content'=>'hello']], 'profile_snapshot'=>['api_key'=>'injected']]]);
phase2Expect($task['input']['profile_snapshot']['configuration']['max_output_tokens'] === 42, '任务冻结预算');
phase2Expect(!str_contains(json_encode($task), 'injected') && !str_contains(json_encode($task), 'rotated-key'), '不允许注入快照，不落密钥');
$repository->rows[2]->configuration['model'] = 'changed-model';
$captured = null;
$httpRequests = [];
$factory = static function (array $config) use (&$captured, &$httpRequests, $executor) {
    $captured = $config;
    $client = new \GuzzleHttp\Client(['handler'=>static function ($request) use (&$httpRequests) {
        $httpRequests[] = ['body'=>json_decode((string) $request->getBody(), true), 'authorization'=>$request->getHeaderLine('Authorization')];
        return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [], '{"choices":[{"message":{"content":"ok"}}],"usage":{"total_tokens":1}}'));
    }]);
    return new \app\console\ai\service\AiAgentOrchestrator(new \app\common\ai\provider\OpenAiCompatibleGateway($client, $config, static fn () => ['93.184.216.34']), $executor);
};
$runner = new \app\console\ai\job\AiAgentJob($store, $orchestrator, null, null, $factory, $profiles);
$runner->fire($queueJob, ['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
phase2Expect($captured['model'] === 'profile-model' && $captured['api_key'] === 'rotated-key', 'Job 使用冻结模型与当前凭据');
phase2Expect($store->tasks[$task['id']]['status'] === 'succeeded', '档案 Job 接通');
phase2Expect($httpRequests[0]['body']['max_tokens'] === 42 && $httpRequests[0]['body']['model'] === 'profile-model', '真实网关冻结输出参数');
$security = new MemorySecurityStore();
$security->createToolCall(['task_id'=>$task['id'], 'conversation_id'=>$conversation['id'], 'status'=>'awaiting_approval', 'idempotency_key'=>'profile-resume']);
$store->updateTask($task['id'], ['status'=>'resume_pending', 'output'=>['resume'=>['messages'=>[['role'=>'assistant','content'=>null,'tool_calls'=>[['id'=>'profile-resume','name'=>'stub','arguments'=>[]]]]]]]]);
$repository->rows[2]->cipher = $secret->seal('resume-key', 7);
$resumeRunner = new \app\console\ai\job\AiAgentJob($store, $orchestrator, null, $security, $factory, $profiles);
$resumeRunner->fire($queueJob, ['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
phase2Expect($httpRequests[1]['authorization'] === 'Bearer resume-key' && $httpRequests[1]['body']['model'] === 'profile-model', '审批恢复保持冻结参数，读取当前凭据');
$task = $service->createTask($conversation['id'], 7, ['idempotency_key'=>'disabled']);
$repository->rows[2]->configuration['enabled'] = false;
$captured = null;
try { $runner->fire($queueJob, ['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]); } catch (RuntimeException) {}
phase2Expect($captured === null && $store->tasks[$task['id']]['status'] !== 'succeeded', '停用时 Job 不调用网关');
try { $service->createConversation(8, ['profile_id'=>2]); throw new LogicException('跨管理员档案必须拒绝'); } catch (RuntimeException $e) { phase2Expect($e->getCode() === 404, '档案所有权'); }
$repository->rows[2]->configuration['enabled'] = true;
$updated = $service->updateConversation($conversation['id'], 7, ['profile_id'=>2, 'model'=>'selected']);
phase2Expect($updated['model'] === 'selected', '会话更新档案模型');
$next = $service->createTask($conversation['id'], 7, ['idempotency_key'=>'limits']);
phase2Expect($next['max_rounds'] === 10 && $next['output_token_budget'] === 42 && $next['input_token_budget'] === 4000, '任务列同步冻结档案预算');
phase2Expect(is_file(dirname(__DIR__) . '/database/migrations/archive/120_ai_conversation_profile.sql'), '会话引用需要新增迁移');
$model = new ReflectionClass(\app\console\ai\model\AiConversation::class);
phase2Expect(($model->getDefaultProperties()['type']['profile_id'] ?? '') === 'integer', 'ORM 档案引用整数转换');
$repository->rows[2]->configuration = ['provider'=>'custom','protocol'=>'openai-chat','base_url'=>'https://example.com/v1','model'=>'primary','fallback_enabled'=>true,'fallback_models'=>['backup'],'max_output_tokens'=>100,'max_retries'=>0,'reasoning_effort'=>'high','model_capabilities'=>array_map(static fn ($model) => ['model'=>$model,'reasoning_efforts'=>['high'],'output_token_parameter'=>'max_completion_tokens','context_window'=>8000,'max_output_tokens'=>500], ['primary','backup'])];
$fallbackConversation = $service->createConversation(7, ['profile_id'=>2]);
$fallbackTask = $service->createTask($fallbackConversation['id'], 7, ['idempotency_key'=>'fallback']);
$wire = [];
$executions = 0;
$tool = new class($executions) implements \app\console\ai\contract\AiToolExecutor {
    public function __construct(public int &$executions) {}
    public function execute(array $call): array { $this->executions++; return ['status'=>'done']; }
};
$responses = [new \GuzzleHttp\Psr7\Response(200, [], '{"model":"primary-actual","choices":[{"message":{"tool_calls":[{"id":"call","function":{"name":"stub","arguments":"{}"}}]}}],"usage":{"total_tokens":3}}'),new \GuzzleHttp\Psr7\Response(503),new \GuzzleHttp\Psr7\Response(200, [], '{"model":"backup-actual","choices":[{"message":{"content":"done"}}],"usage":{"total_tokens":4}}')];
$fallbackFactory = static function ($config) use (&$wire, &$responses, $tool) {
    $client = new \GuzzleHttp\Client(['handler'=>static function ($request) use (&$wire, &$responses) { $wire[] = json_decode((string) $request->getBody(), true); return \GuzzleHttp\Promise\Create::promiseFor(array_shift($responses)); }]);
    return new \app\console\ai\service\AiAgentOrchestrator(new \app\common\ai\provider\OpenAiCompatibleGateway($client, $config, static fn () => ['93.184.216.34']), $tool);
};
$fallbackRunner = new \app\console\ai\job\AiAgentJob($store, $orchestrator, null, null, $fallbackFactory, $profiles);
$fallbackRunner->fire($queueJob, ['taskId'=>$fallbackTask['id'],'operationToken'=>$fallbackTask['operation_token']]);
$done = $store->task($fallbackTask['id']);
phase2Expect(($done['output']['model'] ?? null) === 'backup-actual' && $done['model'] === 'primary', '实际模型落结果，原任务选择不修改');
phase2Expect($executions === 1 && $wire[1]['messages'] === $wire[2]['messages'] && $wire[2]['reasoning_effort'] === 'high', '工具只执行一次，备用沿用完整结果 history 和 effort');
phase2Expect($done['usage']['totalTokens'] === 7, '跨模型累计 token');
phase2Expect(count(array_filter($store->events, static fn ($e) => $e['task_id'] === $fallbackTask['id'] && $e['type'] === 'provider.fallback')) === 1, '实际 Job 持久化切换审计');
phase2Expect(($done['output']['provider_state']['requests'] ?? null) === 3, '累计请求状态持久化');
$store->updateTask($fallbackTask['id'], ['status'=>'resume_pending','output'=>array_replace($done['output'], ['provider_state'=>['requests'=>12,'reserved_seconds'=>300,'candidate'=>1]])]);
$before = count($wire);
try { $fallbackRunner->fire($queueJob, ['taskId'=>$fallbackTask['id'],'operationToken'=>$fallbackTask['operation_token']]); } catch (\app\common\ai\provider\AiProviderException $e) { phase2Expect($e->category() === 'request_budget_exceeded', '审批恢复不得重置额度'); }
phase2Expect(count($wire) === $before && $executions === 1, '额度耗尽在恢复副作用工具前拒绝');
// 会话覆盖仅影响未来快照，null 继承，省略保留；所有备用能力必须兼容。
$config = $repository->rows[2]->configuration;
$config['model_capabilities'][0]['reasoning_efforts'] = ['low','high'];
$config['model_capabilities'][1]['reasoning_efforts'] = ['low','high'];
$repository->rows[2]->configuration = $config;
$override = $service->createConversation(7, ['profile_id'=>2, 'reasoning_effort'=>'low']);
phase2Expect($override['reasoning_effort'] === 'low', '创建保存会话推理覆盖');
$frozen = $service->createTask($override['id'], 7, ['idempotency_key'=>'override','input'=>['reasoning_effort'=>'high']]);
phase2Expect($frozen['input']['profile_snapshot']['configuration']['reasoning_effort'] === 'low' && !array_key_exists('reasoning_effort', $frozen['input']), '快照使用会话覆盖且拒绝任务输入覆盖');
$service->updateConversation($override['id'], 7, ['title'=>'保留覆盖']);
phase2Expect($service->getConversation($override['id'], 7)['reasoning_effort'] === 'low', '省略保留覆盖');
foreach (['medium', 'default', '', false, []] as $invalid) {
    try { $service->updateConversation($override['id'], 7, ['reasoning_effort'=>$invalid]); throw new LogicException('非法覆盖必须拒绝'); } catch (InvalidArgumentException) {}
}
phase2Expect($service->getConversation($override['id'], 7)['reasoning_effort'] === 'low', '拒绝后不改变会话');
try { $service->updateConversation($override['id'], 8, ['reasoning_effort'=>null]); throw new LogicException('覆盖不得越权'); } catch (RuntimeException $e) { phase2Expect($e->getCode() === 404, '覆盖所有权'); }
$service->updateConversation($override['id'], 7, ['reasoning_effort'=>null]);
$inherited = $service->createTask($override['id'], 7, ['idempotency_key'=>'inherit']);
phase2Expect($inherited['input']['profile_snapshot']['configuration']['reasoning_effort'] === 'high', 'null 恢复档案继承');
phase2Expect($store->task($frozen['id'])['input']['profile_snapshot']['configuration']['reasoning_effort'] === 'low', '旧任务快照不改变');
$runner->fire($queueJob, ['taskId'=>$frozen['id'], 'operationToken'=>$frozen['operation_token']]);
phase2Expect(end($httpRequests)['body']['reasoning_effort'] === 'low', '真实 Job 和网关仍使用冻结的会话覆盖');
$service->updateConversation($override['id'], 7, ['reasoning_effort'=>'low']);
$repository->rows[2]->configuration['model_capabilities'][1]['reasoning_efforts'] = ['high'];
try { $service->updateConversation($override['id'], 7, ['reasoning_effort'=>'low']); throw new LogicException('备用不兼容必须拒绝'); } catch (InvalidArgumentException) {}
try { $service->createTask($override['id'], 7, ['idempotency_key'=>'capability-changed']); throw new LogicException('新任务必须重新校验覆盖能力'); } catch (InvalidArgumentException) {}
try { $service->updateConversation($override['id'], 7, ['model'=>'unknown']); throw new LogicException('切模型保留覆盖并拒绝未知能力'); } catch (InvalidArgumentException) {}
$sql = file_get_contents(dirname(__DIR__) . '/database/migrations/archive/121_ai_conversation_reasoning_effort.sql');
phase2Expect(str_contains($sql, 'ADD COLUMN `reasoning_effort` varchar(10) NULL DEFAULT NULL'), '独立新增迁移，旧会话默认继承');
try { $service->createConversation(7, ['reasoning_effort'=>'low']); throw new LogicException('无档案不得覆盖'); } catch (InvalidArgumentException) {}
foreach (['xhigh','max','ultra'] as $effort) {
    $config['model_capabilities'][0]['reasoning_efforts'] = [$effort];
    $config['model_capabilities'][1]['reasoning_efforts'] = [$effort];
    $config['reasoning_effort'] = $effort;
    $config['fallback_enabled'] = false;
    $repository->rows[2]->configuration = $config;
    $extended = $service->createConversation(7, ['profile_id'=>2, 'reasoning_effort'=>$effort]);
    $extendedTask = $service->createTask($extended['id'], 7, ['idempotency_key'=>'extended-'.$effort]);
    phase2Expect($extendedTask['input']['profile_snapshot']['configuration']['reasoning_effort'] === $effort, '扩展档位原样冻结');
    $service->updateConversation($extended['id'], 7, ['reasoning_effort'=>null]);
    $repository->rows[2]->configuration['reasoning_effort'] = null;
    $runner->fire($queueJob, ['taskId'=>$extendedTask['id'], 'operationToken'=>$extendedTask['operation_token']]);
    phase2Expect(end($httpRequests)['body']['reasoning_effort'] === $effort, '档案与会话修改不影响已冻结扩展档位');
    $defaultTask = $service->createTask($extended['id'], 7, ['idempotency_key'=>'default-'.$effort]);
    $runner->fire($queueJob, ['taskId'=>$defaultTask['id'], 'operationToken'=>$defaultTask['operation_token']]);
    phase2Expect(!array_key_exists('reasoning_effort', end($httpRequests)['body']), '会话 null 继承档案 null，真实请求不发送字段');
}
unset($repository->rows[2]);
$captured = null;
try { $runner->fire($queueJob, ['taskId'=>$next['id'], 'operationToken'=>$next['operation_token']]); } catch (RuntimeException) {}
phase2Expect($captured === null && $store->tasks[$next['id']]['status'] === 'failed', '删除档案不得回退全局凭据');
echo "AI profile runtime: PASS\n";
