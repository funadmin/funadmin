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
echo "AI profile gateway: PASS\n";
