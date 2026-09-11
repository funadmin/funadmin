<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\ai\provider\AiProviderException;
use app\common\ai\provider\OpenAiCompatibleGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

function providerExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function providerGateway(array $responses, array &$history, array $overrides = []): OpenAiCompatibleGateway
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    return new OpenAiCompatibleGateway(new Client(['handler' => $stack]), array_replace([
        'base_url' => 'https://api.example.com/v1',
        'api_key' => 'sk-sensitive-secret',
        'model' => 'test-model',
        'connect_timeout' => 1,
        'request_timeout' => 2,
        'max_retries' => 2,
    ], $overrides), static fn (string $host): array => ['93.184.216.34'], static function (int $milliseconds): void {
    });
}

$history = [];
$gateway = providerGateway([
    new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'id' => 'chatcmpl-1',
        'choices' => [[
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call-1',
                    'type' => 'function',
                    'function' => ['name' => 'inspect_schema', 'arguments' => '{"table":"users"}'],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4, 'total_tokens' => 14],
    ], JSON_THROW_ON_ERROR)),
], $history);
$result = $gateway->chat([['role' => 'user', 'content' => 'Inspect users']], [[
    'type' => 'function',
    'function' => ['name' => 'inspect_schema', 'parameters' => ['type' => 'object']],
]]);
providerExpect($result['toolCalls'][0]['name'] === 'inspect_schema', '必须规范化 tool call 名称');
providerExpect($result['toolCalls'][0]['arguments']['table'] === 'users', '必须解析 tool call JSON arguments');
providerExpect($result['usage']['totalTokens'] === 14, '必须规范化 token usage');
$request = $history[0]['request'];
providerExpect($request->getHeaderLine('Authorization') === 'Bearer sk-sensitive-secret', '请求必须携带 Bearer token');
providerExpect(($history[0]['options']['timeout'] ?? null) === 2, '必须设置 request timeout');
providerExpect(($history[0]['options']['connect_timeout'] ?? null) === 1, '必须设置 connect timeout');
providerExpect(($history[0]['options']['curl'][CURLOPT_RESOLVE][0] ?? '') === 'api.example.com:443:93.184.216.34', '必须固定已校验 DNS 地址以阻断 rebinding');

$history = [];
$streamBody = "data: {\"choices\":[{\"delta\":{\"content\":\"你\"}}]}\n\n"
    . "data: {\"choices\":[{\"delta\":{\"tool_calls\":[{\"index\":0,\"id\":\"call-2\",\"function\":{\"name\":\"read_file\",\"arguments\":\"{\\\"pa\"}}]}}]}\n\n"
    . "data: {\"choices\":[{\"delta\":{\"tool_calls\":[{\"index\":0,\"function\":{\"arguments\":\"th\\\":\\\"a.php\\\"}\"}}]},\"finish_reason\":\"tool_calls\"}]}\n\n"
    . "data: [DONE]\n\n";
$gateway = providerGateway([new Response(200, ['Content-Type' => 'text/event-stream'], Utils::streamFor($streamBody))], $history);
$chunks = iterator_to_array($gateway->stream([['role' => 'user', 'content' => '读取文件']]));
providerExpect($chunks[0]['type'] === 'content' && $chunks[0]['content'] === '你', '必须解析 SSE content chunk');
$tool = current(array_filter($chunks, static fn (array $chunk): bool => $chunk['type'] === 'tool_call'));
providerExpect($tool['name'] === 'read_file' && $tool['arguments']['path'] === 'a.php', '必须合并流式 tool call arguments');
providerExpect(end($chunks)['type'] === 'done', '必须输出 done 事件');

$history = [];
$streamParts = [
    "data: {\"choices\":[{\"delta\":{\"content\":\"A\"}}]}\r\n\r",
    "\ndata: {\"choices\":[{\"delta\":{\"content\":\"B\"}}]}\n",
    "\ndata: {\"choices\":[{\"delta\":{\"content\":\"C\"}}]}",
];
$streamBody = new PumpStream(static function () use (&$streamParts): ?string {
    return array_shift($streamParts);
});
$gateway = providerGateway([new Response(200, ['Content-Type' => 'text/event-stream'], $streamBody)], $history);
$chunks = iterator_to_array($gateway->stream([['role' => 'user', 'content' => '测试分帧边界']]));
$content = implode('', array_column(array_filter($chunks, static fn (array $chunk): bool => $chunk['type'] === 'content'), 'content'));
providerExpect($content === 'ABC', 'SSE 必须同时支持 CRLF、LF、跨 chunk 分隔符及 EOF 最后一帧');
providerExpect(end($chunks)['type'] === 'done', '边界分帧完成后必须输出 done 事件');

$history = [];
$gateway = providerGateway([
    new Response(429, ['Retry-After' => '0'], '{"error":{"message":"rate limited"}}'),
    new Response(500, [], '{"error":{"message":"temporary"}}'),
    new Response(200, [], '{"choices":[{"message":{"role":"assistant","content":"ok"},"finish_reason":"stop"}]}'),
], $history);
providerExpect($gateway->chat([])['content'] === 'ok', '429/5xx 必须有限重试后成功');
providerExpect(count($history) === 3, '429/5xx 重试次数必须受限');

$history = [];
$gateway = providerGateway([new Response(429, [], '{"error":{"message":"Bearer sk-leaked-secret"}}')], $history, ['max_retries' => 0]);
try {
    $gateway->chat([]);
    throw new RuntimeException('429 必须抛出映射异常');
} catch (AiProviderException $exception) {
    providerExpect($exception->category() === 'rate_limited', '429 必须映射 rate_limited');
    providerExpect(!str_contains($exception->getMessage(), 'sk-leaked-secret'), '异常不得泄露 Authorization/secret');
}

$history = [];
$gateway = providerGateway([new RuntimeException('Connection timed out for Bearer sk-sensitive-secret')], $history, ['max_retries' => 0]);
try {
    $gateway->chat([]);
    throw new RuntimeException('timeout 必须抛出映射异常');
} catch (AiProviderException $exception) {
    providerExpect($exception->category() === 'timeout', 'timeout 必须映射 timeout');
    providerExpect(!str_contains($exception->getMessage(), 'sk-sensitive-secret'), 'timeout 不得泄露 secret');
}

foreach (['http://api.example.com/v1', 'https://127.0.0.1/v1', 'https://metadata.google.internal/v1'] as $unsafeUrl) {
    try {
        new OpenAiCompatibleGateway(new Client(), ['base_url' => $unsafeUrl], static fn (string $host): array => $host === 'metadata.google.internal' ? ['169.254.169.254'] : ['93.184.216.34']);
        throw new RuntimeException('不安全 base URL 必须拒绝：' . $unsafeUrl);
    } catch (InvalidArgumentException) {
    }
}

$gateway = new OpenAiCompatibleGateway(new Client(), ['base_url' => 'https://api.example.com/v1'], static fn (string $host): array => ['127.0.0.1', '93.184.216.34']);
try {
    $gateway->chat([]);
    throw new RuntimeException('DNS rebinding/private answer 必须拒绝');
} catch (InvalidArgumentException) {
}

echo "AI OpenAI-compatible provider tests: PASS\n";
