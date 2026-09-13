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
$factory = static function (array $config) use (&$captured, $executor) {
    $captured = $config;
    return new \app\console\ai\service\AiAgentOrchestrator(new class {
        public function chat(array $messages, array $tools): array { return ['content'=>'ok','toolCalls'=>[],'usage'=>['totalTokens'=>1]]; }
    }, $executor);
};
$runner = new \app\console\ai\job\AiAgentJob($store, $orchestrator, null, null, $factory, $profiles);
$runner->fire($queueJob, ['taskId'=>$task['id'],'operationToken'=>$task['operation_token']]);
phase2Expect($captured['model'] === 'profile-model' && $captured['api_key'] === 'rotated-key', 'Job 使用冻结模型与当前凭据');
phase2Expect($store->tasks[$task['id']]['status'] === 'succeeded', '档案 Job 接通');
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
phase2Expect(is_file(dirname(__DIR__) . '/database/migrations/120_ai_conversation_profile.sql'), '会话引用需要新增迁移');
$model = new ReflectionClass(\app\console\ai\model\AiConversation::class);
phase2Expect(($model->getDefaultProperties()['type']['profile_id'] ?? '') === 'integer', 'ORM 档案引用整数转换');
echo "AI profile runtime: PASS\n";
