<?php

declare(strict_types=1);
require __DIR__ . '/ai_openai_provider_test.php';
use GuzzleHttp\Psr7\Response;

$history = [];
$gateway = providerGateway([new Response(200, [], '{"data":[{"id":"model-a","context_window":999999},{"id":"model-b"}]}')], $history);
providerExpect(method_exists($gateway, 'models'), '缺少安全模型目录');
providerExpect($gateway->models() === [['id'=>'model-a'], ['id'=>'model-b']], '目录仅返回已验证模型 ID，不信任能力猜测');
providerExpect($history[0]['request']->getMethod() === 'GET' && $history[0]['request']->getUri()->getPath() === '/v1/models', '目录使用 GET models');
providerExpect(isset($history[0]['options']['curl'][CURLOPT_RESOLVE]), '目录复用 DNS pinning');
$history = [];
$gateway = providerGateway([new Response(200, [], '{"choices":[{"message":{"content":"ok"}}]}')], $history, ['max_output_tokens'=>42]);
$gateway->chat([['role'=>'user','content'=>'hello']]);
$payload = json_decode((string) $history[0]['request']->getBody(), true);
providerExpect(($payload['max_tokens'] ?? null) === 42, '实际应用 max output');
$history = [];
$gateway = providerGateway([new Response(200, [], "data: {\"usage\":{\"prompt_tokens\":3,\"completion_tokens\":2,\"total_tokens\":5},\"choices\":[]}\n\ndata: [DONE]\n\n")], $history, ['stream_usage'=>true]);
$chunks = iterator_to_array($gateway->stream([]));
$payload = json_decode((string) $history[0]['request']->getBody(), true);
providerExpect(($payload['stream_options']['include_usage'] ?? false) === true, '请求 stream usage');
providerExpect(($chunks[0]['usage']['totalTokens'] ?? null) === 5, '解析 stream usage');
foreach ([['reasoning_effort'=>'high'], ['max_input_tokens'=>10], ['context_window'=>20,'max_output_tokens'=>15]] as $config) {
    $history = [];
    try {
        providerGateway([], $history, $config)->chat([['role'=>'system','content'=>'关键约束'], ['role'=>'user','content'=>str_repeat('a', 100)]]);
        throw new LogicException('未知推理能力或超预算必须请求前拒绝');
    } catch (InvalidArgumentException|\app\common\ai\provider\AiProviderException) {}
    providerExpect($history === [], '请求前拒绝，不删除关键消息或假发送');
}
$history = [];
try { providerGateway([], $history, ['fallback_enabled'=>true, 'fallback_models'=>['b']])->chat([]); throw new LogicException('未实现备用必须明确拒绝'); }
catch (InvalidArgumentException) {}
$settings = new \app\console\ai\service\AiProviderSettingsService([], static fn () => throw new LogicException('unsupported 不得进入连接测试'));
try { $settings->test(['protocol'=>'anthropic-messages']); throw new LogicException('不支持协议应拒绝'); } catch (InvalidArgumentException) {}
$history = [];
$messages = [['role'=>'assistant','content'=>null,'tool_calls'=>[['id'=>'c1','name'=>'read','arguments'=>['path'=>'x']]]], ['role'=>'tool','tool_call_id'=>'c1','content'=>'ok'], ['role'=>'user','content'=>'继续']];
$gateway = providerGateway([new Response(200, [], '{"choices":[{"message":{"content":"ok"}}]}')], $history);
$gateway->chat($messages);
$sent = json_decode((string) $history[0]['request']->getBody(), true)['messages'];
providerExpect(($sent[0]['tool_calls'][0]['function']['name'] ?? '') === 'read' && count($sent) === 3, '规范化内部工具调用，保留消息配对');
$history = [];
try { providerGateway([], $history)->chat([['role'=>'tool','tool_call_id'=>'orphan','content'=>'x']]); throw new LogicException('孤立工具消息必须请求前拒绝'); } catch (InvalidArgumentException) {}
$caps = array_map(static fn ($model) => ['model'=>$model,'reasoning_efforts'=>['low','medium','high'],'output_token_parameter'=>'max_completion_tokens','context_window'=>8000,'max_output_tokens'=>500], ['test-model','b','c']);
$policy = ['model_capabilities'=>$caps,'max_output_tokens'=>100,'fallback_enabled'=>true,'fallback_models'=>['b','c']];
$ok = new Response(200, [], '{"model":"b-actual","choices":[{"message":{"content":"ok"}}],"usage":{"total_tokens":5}}');
foreach (['low','medium','high','default',null] as $effort) {
    $history = [];
    providerGateway([$ok], $history, ['model_capabilities'=>$caps,'reasoning_effort'=>$effort,'max_output_tokens'=>100])->chat([]);
    $sent = json_decode((string) $history[0]['request']->getBody(), true);
    providerExpect(($sent['reasoning_effort'] ?? null) === (in_array($effort, ['default',null], true) ? null : $effort), '真实发送声明档位，default 不发送');
    providerExpect(($sent['max_completion_tokens'] ?? null) === 100 && !isset($sent['max_tokens']), '明确输出协议包含推理 token');
}
$history = [];
$gateway = providerGateway([new Response(429),new Response(503),new Response(500),new Response(502),$ok], $history, $policy);
$events = [];
$result = $gateway->chat($messages, [], static function ($type, $payload) use (&$events) { $events[] = [$type,$payload]; });
$sent = array_map(static fn ($h) => json_decode((string) $h['request']->getBody(), true), $history);
providerExpect(array_column($sent, 'model') === ['test-model','test-model','test-model','b','c'], '主模型耗尽重试后每备选最多一次');
providerExpect(count(array_unique(array_map(static fn ($p) => json_encode($p['messages']), $sent))) === 1, '重试和备用保留相同工具结果 history');
providerExpect($result['model'] === 'b-actual' && $result['requestedModel'] === 'c', '记录响应实际模型与请求候选');
providerExpect(count(array_filter($events, static fn ($e) => $e[0] === 'provider.fallback')) === 2, '切换可审计');
providerExpect(!str_contains(json_encode($events), 'sk-sensitive') && !str_contains(json_encode($events), '继续'), '审计不包含密钥和 prompt');
foreach ([400,401,403] as $status) {
    $history = [];
    try { providerGateway([new Response($status),$ok], $history, $policy)->chat([]); throw new LogicException('永久错误不得备用'); } catch (\app\common\ai\provider\AiProviderException) {}
    providerExpect(count($history) === 1, '永久错误不重试');
}
foreach (['{"choices":[{"message":{"refusal":"no"},"finish_reason":"stop"}]}','{"choices":[{"message":{"content":null},"finish_reason":"content_filter"}]}','bad-json'] as $body) {
    $history = [];
    try { providerGateway([new Response(200, [], $body),$ok], $history, $policy)->chat([]); throw new LogicException('拒绝或无效响应必须停止'); } catch (\app\common\ai\provider\AiProviderException) {}
    providerExpect(count($history) === 1, '安全拒绝和解析错误不备用');
}
foreach ([['reasoning_effort'=>'high','model_capabilities'=>[]], ['max_input_tokens'=>10], ['model_capabilities'=>array_replace($caps, [1=>array_replace($caps[1], ['context_window'=>100])])]] as $patch) {
    $history = [];
    try { providerGateway([$ok], $history, array_replace($policy, $patch))->chat($messages); throw new LogicException('能力预算不匹配不得请求'); } catch (InvalidArgumentException|\app\common\ai\provider\AiProviderException) {}
    providerExpect($history === [], '所有候选预检，禁止静默跳过');
}
$request = new \GuzzleHttp\Psr7\Request('POST', 'https://api.example.com/v1/chat/completions');
$history = [];
$network = new \GuzzleHttp\Exception\ConnectException('secret', $request, null, ['errno'=>7]);
providerGateway([$network,$network,$network,$ok], $history, $policy)->chat([]);
providerExpect(count($history) === 4, '明确连接故障有限重试后备用');
foreach ([new RuntimeException('timeout'), new \GuzzleHttp\Exception\ConnectException('TLS error', $request, null, ['errno'=>60])] as $error) {
    $history = [];
    try { providerGateway([$error,$ok], $history, $policy)->chat([]); throw new LogicException('未知异常不得备用'); } catch (\app\common\ai\provider\AiProviderException) {}
    providerExpect(count($history) === 1, '不根据错误文本猜测暂时故障');
}
$history = [];
try { iterator_to_array(providerGateway([], $history, $policy)->stream([])); throw new LogicException('流式备用不在本期范围'); } catch (InvalidArgumentException) {}
providerExpect($history === [], '流式启用备用明确拒绝');
$history = [];
$gateway = providerGateway([new Response(500),new Response(500),new Response(500),new Response(500),new Response(500)], $history, $policy);
try { $gateway->chat([]); } catch (\app\common\ai\provider\AiProviderException) {}
try { $gateway->chat([]); } catch (\app\common\ai\provider\AiProviderException) {}
providerExpect(count($history) === 5, '耗尽候选后跨轮不得重复主模型或备用');
echo "AI profile gateway: PASS\n";
