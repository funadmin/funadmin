<?php

declare(strict_types=1);
require __DIR__ . '/ai_openai_provider_test.php';
use GuzzleHttp\Psr7\Response;

foreach (['openai-responses', 'anthropic-messages'] as $protocol) {
    $anthropic = $protocol === 'anthropic-messages';
    $body = $anthropic
        ? ['id'=>'msg_1','type'=>'message','role'=>'assistant','content'=>[['type'=>'text','text'=>'hello'],['type'=>'tool_use','id'=>'call_1','name'=>'inspect','input'=>['x'=>1]]],'stop_reason'=>'tool_use','usage'=>['input_tokens'=>10,'output_tokens'=>4]]
        : ['id'=>'resp_1','status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'hello']]],['type'=>'function_call','call_id'=>'call_1','name'=>'inspect','arguments'=>'{"x":1}']],'usage'=>['input_tokens'=>10,'output_tokens'=>4,'total_tokens'=>14]];
    $history = [];
    $gateway = providerGateway([new Response(200, [], json_encode($body)), new Response(200, [], json_encode($body)), new Response(200, [], '{"data":[{"id":"m"}]}')], $history, ['protocol'=>$protocol,'max_output_tokens'=>100]);
    $tools = [['type'=>'function','function'=>['name'=>'inspect','description'=>'inspect','parameters'=>['type'=>'object']]]];
    $messages = [['role'=>'system','content'=>'system'],['role'=>'user','content'=>'hi']];
    $result = $gateway->chat($messages, $tools);
    providerExpect($result['content'] === 'hello' && $result['toolCalls'][0]['arguments'] === ['x'=>1], $protocol . ' 响应与工具规范化');
    providerExpect($result['usage']['totalTokens'] === 14, $protocol . ' usage');
    $gateway->chat([...$messages, ['role'=>'assistant','content'=>$result['content'],'tool_calls'=>$result['toolCalls']], ['role'=>'tool','tool_call_id'=>'call_1','content'=>'result']], $tools);
    $payload = json_decode((string) $history[1]['request']->getBody(), true);
    providerExpect(str_ends_with((string) $history[0]['request']->getUri(), $anthropic ? '/messages' : '/responses'), '协议路径');
    providerExpect($history[0]['options']['allow_redirects'] === false, '不跟随重定向');
    if ($anthropic) {
        providerExpect($payload['system'] === [['type'=>'text','text'=>'system']] && $payload['max_tokens'] === 100, 'Messages 系统与输出预算');
        providerExpect($payload['messages'][2]['content'][0]['tool_use_id'] === 'call_1', 'Messages 工具回传');
        providerExpect($history[0]['request']->getHeaderLine('x-api-key') === 'sk-sensitive-secret' && $history[0]['request']->getHeaderLine('Authorization') === '', 'Messages 认证隔离');
        providerExpect($history[0]['request']->getHeaderLine('anthropic-version') === '2023-06-01', 'Messages 版本');
    } else {
        providerExpect($payload['input'][3]['call_id'] === 'call_1' && $payload['input'][4]['type'] === 'function_call_output', 'Responses 工具回传');
        providerExpect($payload['store'] === false && $payload['max_output_tokens'] === 100 && !isset($payload['messages']), 'Responses 无状态与输出预算');
    }
    providerExpect($gateway->models() === [['id'=>'m']], '统一模型目录');
}
foreach (['openai-responses','anthropic-messages'] as $protocol) {
    $events = $protocol === 'openai-responses' ? [
        ['type'=>'response.created'],
        ['type'=>'response.output_item.added','output_index'=>0,'item'=>['type'=>'message']],
        ['type'=>'response.output_text.delta','output_index'=>0,'delta'=>'hello'],
        ['type'=>'response.output_item.done','output_index'=>0],
        ['type'=>'response.output_item.added','output_index'=>1,'item'=>['type'=>'function_call','id'=>'fc_1','call_id'=>'call_1','name'=>'inspect','arguments'=>'']],
        ['type'=>'response.function_call_arguments.delta','output_index'=>1,'delta'=>'{"x":'],
        ['type'=>'response.function_call_arguments.delta','output_index'=>1,'delta'=>'1}'],
        ['type'=>'response.output_item.done','output_index'=>1],
        ['type'=>'response.completed','response'=>['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'hello']]],['type'=>'function_call','call_id'=>'call_1','name'=>'inspect','arguments'=>'{"x":1}']],'usage'=>['input_tokens'=>10,'output_tokens'=>4]]],
    ] : [
        ['type'=>'message_start','message'=>['usage'=>['input_tokens'=>10,'output_tokens'=>0]]],
        ['type'=>'content_block_start','index'=>0,'content_block'=>['type'=>'text','text'=>'']],
        ['type'=>'content_block_delta','index'=>0,'delta'=>['type'=>'text_delta','text'=>'hello']],
        ['type'=>'content_block_stop','index'=>0],
        ['type'=>'content_block_start','index'=>1,'content_block'=>['type'=>'tool_use','id'=>'call_1','name'=>'inspect','input'=>new stdClass()]],
        ['type'=>'content_block_delta','index'=>1,'delta'=>['type'=>'input_json_delta','partial_json'=>'{"x":1}']],
        ['type'=>'content_block_stop','index'=>1],
        ['type'=>'message_delta','delta'=>['stop_reason'=>'tool_use'],'usage'=>['output_tokens'=>4]],
        ['type'=>'message_stop'],
    ];
    $sse = implode('', array_map(fn ($e) => 'event: ' . $e['type'] . "\r\ndata: " . json_encode($e) . "\r\n\r\n", $events));
    $history = [];
    $gateway = providerGateway([new Response(200, [], $sse)], $history, ['protocol'=>$protocol,'max_output_tokens'=>100]);
    $chunks = iterator_to_array($gateway->stream([['role'=>'user','content'=>'hi']]));
    providerExpect(current(array_filter($chunks, fn ($c) => $c['type'] === 'content'))['content'] === 'hello', '协议流式文本');
    providerExpect(current(array_filter($chunks, fn ($c) => $c['type'] === 'tool_call'))['arguments'] === ['x'=>1], '协议流式工具合并');
    $usage = array_values(array_filter($chunks, fn ($c) => $c['type'] === 'usage'));
    providerExpect(end($usage)['usage']['totalTokens'] === 14, '协议流式累计 usage');
    foreach (["data: {\"type\":\"error\",\"error\":{\"message\":\"secret\"}}\n\n", "data: {\"type\":\"ping\"}\n\n"] as $broken) {
        $history = [];
        try {
            iterator_to_array(providerGateway([new Response(200, [], $broken)], $history, ['protocol'=>$protocol,'max_output_tokens'=>100])->stream([]));
            throw new RuntimeException('错误流和截断流不得成功');
        } catch (\app\common\ai\provider\AiProviderException $e) {
            providerExpect(!str_contains($e->getMessage(), 'secret'), '错误脱敏');
        }
    }
}
foreach (['openai-responses','anthropic-messages'] as $protocol) {
    $config = ['name'=>'test','provider'=>'custom','protocol'=>$protocol,'base_url'=>'https://api.example.com/v1','model'=>'m','max_output_tokens'=>100];
    providerExpect(\app\console\ai\service\AiConfigurationProfileService::validate($config)['protocol'] === $protocol, '档案接受新协议');
    $captured = [];
    $settings = new \app\console\ai\service\AiProviderSettingsService(['base_url'=>'https://old.example.com/v1','api_key'=>'old-secret'], function ($c) use (&$captured) {
        $captured = $c;
        return new class { public function chat($messages) { return ['finishReason'=>'stop']; } };
    });
    $settings->test($config + ['api_key'=>'new-secret']);
    providerExpect($captured['protocol'] === $protocol && $captured['max_output_tokens'] === 100, '连接测试传递协议与预算');
    try { $settings->test($config); throw new RuntimeException('禁止跨目标继承服务器密钥'); }
    catch (InvalidArgumentException) {}
}
foreach (['openai-responses'=>'output', 'anthropic-messages'=>'content'] as $protocol=>$field) {
    $history = [];
    $gateway = providerGateway([new Response(503, [], json_encode([$field=>[['type'=>'text','text'=>'already started']]])), new Response(200, [], '{}')], $history, ['protocol'=>$protocol,'max_output_tokens'=>100,'max_retries'=>1]);
    try { $gateway->chat([]); throw new RuntimeException('已输出响应不得重试'); }
    catch (\app\common\ai\provider\AiProviderException $e) { providerExpect($e->category() === 'response_started', '新协议已输出响应必须禁止重试和备用'); }
    providerExpect(count($history) === 1, '已输出响应只能发送一次');
}
$history = [];
$gateway = providerGateway([
    new Response(200, [], '{"data":[{"id":"m1"}],"has_more":true,"last_id":"m1"}'),
    new Response(200, [], '{"data":[{"id":"m2"}],"has_more":false,"last_id":"m2"}'),
], $history, ['protocol'=>'anthropic-messages']);
providerExpect($gateway->models() === [['id'=>'m1'],['id'=>'m2']], 'Messages 模型目录遍历分页');
providerExpect($history[1]['request']->getUri()->getQuery() === 'after_id=m1', '分页仅使用固定目标与编码游标');
foreach (['openai-responses','anthropic-messages'] as $protocol) {
    $adapter = new \app\common\ai\provider\AiProtocol($protocol);
    $state = ['done'=>true]; $calls = [];
    try { $adapter->streamEvent(['type'=>'response.output_text.delta','delta'=>'late'], $state, $calls); throw new RuntimeException('完成后不得接受额外输出'); }
    catch (\app\common\ai\provider\AiProviderException) {}
    $state = []; $calls = [];
    $foreign = $protocol === 'openai-responses' ? ['type'=>'message_stop'] : ['type'=>'response.completed','response'=>['status'=>'completed']];
    try { $adapter->streamEvent($foreign, $state, $calls); throw new RuntimeException('不得混用协议流事件'); }
    catch (\app\common\ai\provider\AiProviderException) {}
}
foreach (['openai-chat','openai-responses','anthropic-messages'] as $protocol) {
    $history = [];
    $terminal = match ($protocol) {
        'openai-chat' => ['choices'=>[['delta'=>['content'=>'ok'],'finish_reason'=>'stop']]],
        'openai-responses' => ['type'=>'response.completed','response'=>['status'=>'completed','output'=>[],'usage'=>['input_tokens'=>2,'output_tokens'=>1]]],
        default => ['type'=>'message_start','message'=>['usage'=>['input_tokens'=>2]]],
    };
    $json = json_encode($terminal, JSON_PRETTY_PRINT);
    $sse = 'data: ' . str_replace("\n", "\ndata: ", $json) . "\n\n";
    if ($protocol === 'openai-chat') $sse .= "data: [DONE]\n\n";
    if ($protocol === 'openai-responses') $sse = "data: {\"type\":\"response.created\"}\n\n" . $sse;
    if ($protocol === 'anthropic-messages') $sse .= "data: {\"type\":\"message_delta\",\"delta\":{\"stop_reason\":\"end_turn\"},\"usage\":{\"output_tokens\":1}}\n\ndata: {\"type\":\"message_stop\"}\n\n";
    $chunks = iterator_to_array(providerGateway([new Response(200, [], $sse)], $history, ['protocol'=>$protocol,'max_output_tokens'=>100])->stream([]));
    providerExpect(end($chunks)['type'] === 'done', '三协议多行 SSE 正常结束');
    foreach ([$sse . $sse, "data: {}\n\n"] as $broken) {
        $history = [];
        try { iterator_to_array(providerGateway([new Response(200, [], $broken)], $history, ['protocol'=>$protocol,'max_output_tokens'=>100])->stream([])); throw new RuntimeException('重复结束或截断不能成功'); }
        catch (\app\common\ai\provider\AiProviderException) {}
    }
}
foreach (['openai-responses','anthropic-messages'] as $protocol) {
    $history = [];
    $caps = array_map(fn ($m) => ['model'=>$m,'context_window'=>8000,'max_output_tokens'=>500,'reasoning_efforts'=>['high']], ['test-model','backup']);
    $policy = ['protocol'=>$protocol,'max_output_tokens'=>100,'fallback_enabled'=>true,'fallback_models'=>['backup'],'model_capabilities'=>$caps,'max_retries'=>0];
    $body = $protocol === 'openai-responses' ? ['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'ok']]]],'usage'=>['input_tokens'=>2,'output_tokens'=>1]] : ['content'=>[['type'=>'text','text'=>'ok']],'stop_reason'=>'end_turn','usage'=>['input_tokens'=>2,'output_tokens'=>1]];
    $ok = new Response(200, [], json_encode($body));
    $gateway = providerGateway([new Response(503), $ok, $ok], $history, $policy);
    $gateway->chat([['role'=>'user','content'=>'first']]);
    $gateway->chat([['role'=>'user','content'=>'first'],['role'=>'assistant','content'=>'ok'],['role'=>'user','content'=>'next']]);
    $sent = array_map(fn ($h) => json_decode((string) $h['request']->getBody(), true), $history);
    providerExpect(array_column($sent, 'model') === ['test-model','backup','backup'], '新协议多轮保留备用候选');
    providerExpect(count($sent[2][$protocol === 'openai-responses' ? 'input' : 'messages']) === 3, '新协议不截断多轮历史');
    foreach ($protocol === 'anthropic-messages' ? [['max_input_tokens'=>1], ['reasoning_effort'=>'high']] : [['max_input_tokens'=>1]] as $patch) {
        $history = [];
        try { providerGateway([], $history, array_replace($policy, $patch))->chat([]); throw new RuntimeException('预算或不支持推理必须拒绝'); }
        catch (InvalidArgumentException|\app\common\ai\provider\AiProviderException) {}
        providerExpect($history === [], '请求前拒绝');
    }
    $history = [];
    $gateway = providerGateway(array_fill(0, 13, $ok), $history, ['protocol'=>$protocol,'max_output_tokens'=>100]);
    for ($i = 0; $i < 12; $i++) $gateway->chat([]);
    try { $gateway->chat([]); throw new RuntimeException('跨轮额度必须生效'); }
    catch (\app\common\ai\provider\AiProviderException $e) { providerExpect($e->category() === 'request_budget_exceeded', '新协议十二次上限'); }
    providerExpect(count($history) === 12, '超额不发送');
}
$history = [];
$reasoned = providerGateway([new Response(200, [], '{"choices":[{"message":{"content":"ok","reasoning_content":"hidden"}}]}')], $history)->chat([]);
providerExpect($reasoned['content'] === 'ok' && $reasoned['protocolContext']['data']['reasoning_content'] === 'hidden', '推理仅存入私有协议上下文');
foreach (['openai-chat','openai-responses','anthropic-messages'] as $protocol) {
    $history = [];
    $body = match ($protocol) {
        'openai-chat' => ['choices'=>[['message'=>['content'=>'ok']]]],
        'openai-responses' => ['status'=>'completed','output'=>[]],
        default => ['content'=>[],'stop_reason'=>'end_turn'],
    };
    providerGateway([new Response(200, [], json_encode($body))], $history, ['protocol'=>$protocol,'max_output_tokens'=>100])->chat([
        ['role'=>'assistant','content'=>null,'tool_calls'=>[['id'=>'c','name'=>'inspect','arguments'=>[]]]],
        ['role'=>'tool','tool_call_id'=>'c','content'=>'ok'],
    ]);
    $wire = (string) $history[0]['request']->getBody();
    providerExpect(!str_contains($wire, '"input":[]') && !str_contains($wire, '"arguments":"[]"'), '空工具参数保持对象而非数组');
    $history = [];
    try { providerGateway([], $history, ['protocol'=>$protocol,'max_output_tokens'=>100,'thinking'=>['type'=>'enabled']])->chat([]); throw new RuntimeException('不支持 thinking 配置不得忽略'); }
    catch (InvalidArgumentException) {}
    providerExpect($history === [], '不支持配置不发网络请求');
}
foreach (['openai-responses'=>[
    ['type'=>'response.created'],
    ['type'=>'response.function_call_arguments.delta','output_index'=>0,'delta'=>'{}'],
], 'anthropic-messages'=>[
    ['type'=>'message_start','message'=>[]],
    ['type'=>'content_block_delta','index'=>0,'delta'=>['type'=>'text_delta','text'=>'bad']],
]] as $protocol => $events) {
    $adapter = new \app\common\ai\provider\AiProtocol($protocol);
    $state = []; $calls = [];
    $adapter->streamEvent($events[0], $state, $calls);
    try { $adapter->streamEvent($events[1], $state, $calls); throw new RuntimeException('乱序增量必须拒绝'); }
    catch (\app\common\ai\provider\AiProviderException) {}
}
// 依据官方 SDK/参考响应构造线协议 fixture；签名使用不含真实敏感数据的占位值。
foreach (['openai-chat', 'openai-responses', 'anthropic-messages'] as $protocol) {
    foreach ([false, true] as $streaming) {
        $history = []; $responses = []; $expected = [];
        for ($round = 0; $round < 3; $round++) {
            $call = ['id'=>'call_' . $round, 'type'=>'function', 'function'=>['name'=>'inspect','arguments'=>'{"x":1}']];
            $calls = $round < 2 ? [$call] : [];
            $native = match ($protocol) {
                'openai-chat' => ['reasoning_content'=>'private reasoning ' . $round],
                'openai-responses' => [
                    ['type'=>'reasoning','id'=>'rs_' . $round,'summary'=>[], 'encrypted_content'=>'private-cipher-' . $round],
                    ['type'=>'message','id'=>'msg_' . $round,'role'=>'assistant','status'=>'completed','content'=>[['type'=>'output_text','text'=>'hello','annotations'=>[]]]],
                    ...($calls ? [['type'=>'function_call','id'=>'fc_' . $round,'status'=>'completed','call_id'=>$call['id'],'name'=>'inspect','arguments'=>'{"x":1}']] : []),
                ],
                default => [
                    ['type'=>'thinking','thinking'=>'private reasoning ' . $round,'signature'=>'private-signature-' . $round],
                    ['type'=>'text','text'=>'hello'],
                    ['type'=>'redacted_thinking','data'=>'private-redacted-' . $round],
                    ...($calls ? [['type'=>'tool_use','id'=>$call['id'],'name'=>'inspect','input'=>['x'=>1]]] : []),
                ],
            };
            $expected[] = $native;
            $body = match ($protocol) {
                'openai-chat' => ['choices'=>[['message'=>['role'=>'assistant','content'=>'hello','tool_calls'=>$calls] + $native,'finish_reason'=>$calls ? 'tool_calls' : 'stop']]],
                'openai-responses' => ['status'=>'completed','output'=>$native],
                default => ['content'=>$native,'stop_reason'=>$calls ? 'tool_use' : 'end_turn'],
            };
            $events = [];
            if ($protocol === 'openai-chat') {
                $events[] = ['choices'=>[['delta'=>['reasoning_content'=>'private ']]]];
                $events[] = ['choices'=>[['delta'=>['reasoning_content'=>'reasoning ' . $round,'content'=>'hello']]]];
                if ($calls) $events[] = ['choices'=>[['delta'=>['tool_calls'=>[['index'=>0] + $call]]]]];
                $events[] = ['choices'=>[['delta'=>[], 'finish_reason'=>$calls ? 'tool_calls' : 'stop']]];
            } elseif ($protocol === 'openai-responses') {
                $events[] = ['type'=>'response.created'];
                foreach ($native as $index=>$item) {
                    $events[] = ['type'=>'response.output_item.added','output_index'=>$index,'item'=>array_replace($item, $item['type'] === 'function_call' ? ['arguments'=>''] : [])];
                    if ($item['type'] === 'reasoning') {
                        $events[] = ['type'=>'response.reasoning_summary_part.added','output_index'=>$index,'summary_index'=>0,'part'=>['type'=>'summary_text','text'=>'']];
                        $events[] = ['type'=>'response.reasoning_summary_text.delta','output_index'=>$index,'summary_index'=>0,'delta'=>'private summary'];
                        $events[] = ['type'=>'response.reasoning_summary_text.done','output_index'=>$index,'summary_index'=>0,'text'=>'private summary'];
                        $events[] = ['type'=>'response.reasoning_summary_part.done','output_index'=>$index,'summary_index'=>0,'part'=>['type'=>'summary_text','text'=>'private summary']];
                    } elseif ($item['type'] === 'message') $events[] = ['type'=>'response.output_text.delta','output_index'=>$index,'delta'=>'hello'];
                    else $events[] = ['type'=>'response.function_call_arguments.delta','output_index'=>$index,'delta'=>'{"x":1}'];
                    $events[] = ['type'=>'response.output_item.done','output_index'=>$index,'item'=>$item];
                }
                $events[] = ['type'=>'response.completed','response'=>$body];
            } else {
                $events[] = ['type'=>'message_start','message'=>['role'=>'assistant','content'=>[]]];
                foreach ($native as $index=>$block) {
                    $start = match ($block['type']) {
                        'thinking' => ['type'=>'thinking','thinking'=>'','signature'=>''],
                        'text' => ['type'=>'text','text'=>''],
                        'tool_use' => array_replace($block, ['input'=>new stdClass()]),
                        default => $block,
                    };
                    $events[] = ['type'=>'content_block_start','index'=>$index,'content_block'=>$start];
                    $deltas = match ($block['type']) {
                        'thinking' => [['type'=>'thinking_delta','thinking'=>$block['thinking']], ['type'=>'signature_delta','signature'=>$block['signature']]],
                        'text' => [['type'=>'text_delta','text'=>'hello']],
                        'tool_use' => [['type'=>'input_json_delta','partial_json'=>'{"x":'], ['type'=>'input_json_delta','partial_json'=>'1}']],
                        default => [],
                    };
                    foreach ($deltas as $delta) $events[] = ['type'=>'content_block_delta','index'=>$index,'delta'=>$delta];
                    $events[] = ['type'=>'content_block_stop','index'=>$index];
                }
                $events[] = ['type'=>'message_delta','delta'=>['stop_reason'=>$body['stop_reason']]];
                $events[] = ['type'=>'message_stop'];
            }
            $sse = implode('', array_map(fn ($e) => 'data: ' . json_encode($e) . "\n\n", $events)) . ($protocol === 'openai-chat' ? "data: [DONE]\n\n" : '');
            $responses[] = new Response(200, [], $streaming ? $sse : json_encode($body));
        }
        $gateway = providerGateway($responses, $history, ['protocol'=>$protocol,'max_output_tokens'=>100]);
        $messages = [['role'=>'user','content'=>'hi']];
        for ($round = 0; $round < 3; $round++) {
            $private = null; $publicEvents = [];
            if ($streaming) {
                $chunks = iterator_to_array($gateway->stream($messages, [], function (array $context) use (&$private): void { $private = $context; }));
                providerExpect(!str_contains(json_encode($chunks), 'private'), '流事件绝不暴露私有上下文');
                $toolCalls = array_values(array_filter($chunks, fn ($c) => $c['type'] === 'tool_call'));
            } else {
                $result = $gateway->chat($messages, [], function ($type, $data) use (&$publicEvents): void { $publicEvents[] = $data; });
                $private = $result['protocolContext']; $toolCalls = $result['toolCalls'];
                providerExpect($result['content'] === 'hello' && !str_contains(json_encode($publicEvents), 'private'), '普通文本与推理隔离');
            }
            providerExpect($private['protocol'] === $protocol && $private['data'] === $expected[$round], '原始协议上下文完整保序 ' . $protocol);
            $messages[] = ['role'=>'assistant','content'=>'hello','tool_calls'=>$toolCalls,'protocol_context'=>$private];
            if ($round < 2) $messages[] = ['role'=>'tool','tool_call_id'=>'call_' . $round,'content'=>'ok'];
        }
        $wire = json_decode((string) $history[2]['request']->getBody(), true);
        if ($protocol === 'openai-responses') {
            providerExpect($wire['include'] === ['reasoning.encrypted_content'] && $wire['store'] === false, 'Responses 无状态加密推理');
            providerExpect(array_slice($wire['input'], 1, 3) === $expected[0] && array_slice($wire['input'], 5, 3) === $expected[1], 'Responses 多轮原序回传');
        } elseif ($protocol === 'anthropic-messages') {
            providerExpect($wire['messages'][1]['content'] === $expected[0] && $wire['messages'][3]['content'] === $expected[1], 'Messages 签名和 redacted 原块回传');
        } else providerExpect($wire['messages'][1]['reasoning_content'] === $expected[0]['reasoning_content'] && $wire['messages'][3]['reasoning_content'] === $expected[1]['reasoning_content'], 'Chat 工具多轮推理回传');
    }
}
$history = [];
$first = providerGateway([new Response(200, [], '{"choices":[{"message":{"content":"ok","reasoning_content":"private-origin"}}]}')], $history)->chat([]);
$foreignHistory = [];
try {
    providerGateway([new Response(200, [], '{}')], $foreignHistory, ['base_url'=>'https://other.example.com/v1'])->chat([['role'=>'assistant','content'=>'ok','protocol_context'=>$first['protocolContext']]]);
    throw new RuntimeException('私有上下文不能跨供应商回传');
} catch (InvalidArgumentException) {}
providerExpect($foreignHistory === [], '跨目标上下文请求前拒绝且不能静默丢弃');
$protocol = new \app\common\ai\provider\AiProtocol('openai-responses');
$state = []; $calls = [];
$protocol->streamEvent(['type'=>'response.created'], $state, $calls);
$protocol->streamEvent(['type'=>'response.completed','response'=>['status'=>'completed']], $state, $calls);
providerExpect($state['protocol_context']['data'] === [], '空 Responses 完成事件保留空上下文');
$protocol = new \app\common\ai\provider\AiProtocol('anthropic-messages');
$decoded = $protocol->decode(['content'=>[['type'=>'thinking','thinking'=>'private','signature'=>'signed'],['type'=>'tool_use','id'=>'empty','name'=>'inspect','input'=>[]]],'stop_reason'=>'tool_use']);
$wire = $protocol->encode(['model'=>'m','stream'=>false,'max_tokens'=>100,'messages'=>[['role'=>'assistant','protocol_context'=>$decoded['choices'][0]['message']['protocol_context']]]]);
providerExpect(str_contains(json_encode($wire), '"input":{}'), 'Anthropic 空工具 input 回传必须为对象');
echo "AI protocol adapters: PASS\n";
