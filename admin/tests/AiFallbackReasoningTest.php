<?php

declare(strict_types=1);

namespace Tests;

use app\admin\ai\service\AiConfigurationProfileService;
use app\common\ai\provider\AiProtocol;
use app\common\ai\provider\AiProviderException;
use app\common\ai\provider\OpenAiCompatibleGateway;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** 备用模型切换与思考强度：全部使用模拟上游，不连接真实服务。 */
final class AiFallbackReasoningTest extends TestCase
{
    private function client(array $queue): ClientInterface
    {
        return new class($queue) implements ClientInterface {
            public array $requests = [];
            public function __construct(private array $queue) {}
            public function request($method, $uri, array $options = []): ResponseInterface
            {
                $this->requests[] = ['uri' => $uri, 'json' => $options['json'] ?? null, 'stream' => $options['stream'] ?? false];
                $next = array_shift($this->queue);
                if ($next === null) throw new \LogicException('上游请求超出预期');
                if ($next instanceof \Throwable) throw $next;
                return $next;
            }
            public function send(RequestInterface $request, array $options = []): ResponseInterface { return $this->request($request->getMethod(), (string) $request->getUri(), $options); }
            public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface { return Create::promiseFor($this->send($request, $options)); }
            public function requestAsync($method, $uri, array $options = []): PromiseInterface { return Create::promiseFor($this->request($method, $uri, $options)); }
            public function getConfig($option = null) { return $option === null ? [] : null; }
        };
    }

    private function config(array $overrides = []): array
    {
        $cap = static fn (string $model, array $efforts = []): array => ['model' => $model, 'reasoning_efforts' => $efforts, 'context_window' => 200000, 'max_output_tokens' => 64000];
        return array_replace([
            'protocol' => 'openai-chat', 'base_url' => 'https://8.8.8.8/v1', 'api_key' => 'key', 'model' => 'primary',
            'fallback_enabled' => true, 'fallback_models' => ['backup-1', 'backup-2'], 'max_output_tokens' => 20000, 'max_retries' => 0,
            'model_capabilities' => [$cap('primary', ['low', 'high']), $cap('backup-1', ['low', 'high']), $cap('backup-2', ['low', 'high'])],
        ], $overrides);
    }

    private static function ok(string $model, string $text = '完成'): Response
    {
        return new Response(200, [], json_encode(['model' => $model, 'choices' => [['message' => ['content' => $text], 'finish_reason' => 'stop']], 'usage' => ['total_tokens' => 3]], JSON_THROW_ON_ERROR));
    }

    private static function error(int $status, ?string $code = null, ?string $type = null): Response
    {
        return new Response($status, [], json_encode(['error' => array_filter(['code' => $code, 'type' => $type, 'message' => 'x'])], JSON_THROW_ON_ERROR));
    }

    private function gateway(ClientInterface $client, array $config): OpenAiCompatibleGateway
    {
        return new OpenAiCompatibleGateway($client, $config, sleeper: static fn () => null);
    }

    public function test_fallback_follows_configured_order_and_records_each_switch(): void
    {
        $client = $this->client([self::error(503), self::error(429), self::ok('backup-2')]);
        $events = [];
        $result = $this->gateway($client, $this->config())->chat([['role' => 'user', 'content' => 'hi']], [], function (string $type, array $payload) use (&$events): void { $events[] = [$type, $payload]; });
        self::assertSame(['primary', 'backup-1', 'backup-2'], array_map(static fn ($r) => $r['json']['model'], $client->requests));
        self::assertSame('backup-2', $result['model']);
        self::assertSame('backup-2', $result['requestedModel']);
        self::assertSame([
            ['from_model' => 'primary', 'to_model' => 'backup-1', 'reason' => 'provider_unavailable', 'status' => 503],
            ['from_model' => 'backup-1', 'to_model' => 'backup-2', 'reason' => 'rate_limited', 'status' => 429],
        ], $result['fallbacks']);
        $fallbackEvents = array_values(array_filter($events, static fn ($e) => $e[0] === 'provider.fallback'));
        self::assertCount(2, $fallbackEvents);
        self::assertSame(['primary', 'backup-1', 'provider_unavailable', 503], [$fallbackEvents[0][1]['from_model'], $fallbackEvents[0][1]['to_model'], $fallbackEvents[0][1]['reason'], $fallbackEvents[0][1]['status']]);
    }

    /** @return iterable<string, array{0: \Throwable|Response, 1: string}> */
    public static function switchableFailures(): iterable
    {
        $request = new Request('POST', 'https://8.8.8.8/v1/chat/completions');
        yield 'network' => [new ConnectException('Could not connect', $request, null, ['errno' => 7]), 'transient_transport'];
        yield 'network other' => [new ConnectException('SSL connect error', $request, null, ['errno' => 35]), 'network'];
        yield 'timeout' => [new ConnectException('Operation timed out', $request, null, ['errno' => 28]), 'timeout'];
        yield '5xx' => [self::error(502), 'provider_unavailable'];
        yield '429' => [self::error(429), 'rate_limited'];
        yield 'model not found' => [self::error(404, 'model_not_found'), 'model_unavailable'];
        yield 'anthropic not found' => [self::error(404, null, 'not_found_error'), 'model_unavailable'];
        yield 'model unavailable 400' => [self::error(400, 'model_not_available'), 'model_unavailable'];
    }

    #[DataProvider('switchableFailures')]
    public function test_switchable_failures_move_to_next_model(\Throwable|Response $failure, string $reason): void
    {
        $client = $this->client([$failure, self::ok('backup-1')]);
        $result = $this->gateway($client, $this->config())->chat([['role' => 'user', 'content' => 'hi']]);
        self::assertSame('backup-1', $result['model']);
        self::assertSame($reason, $result['fallbacks'][0]['reason']);
    }

    /** @return iterable<string, array{0: Response, 1: string}> */
    public static function businessErrors(): iterable
    {
        yield '400' => [self::error(400, 'invalid_parameter'), 'invalid_request'];
        yield '400 plain' => [self::error(400), 'invalid_request'];
        yield '401' => [self::error(401), 'authentication'];
        yield '403' => [self::error(403), 'authentication'];
        yield '404 route' => [self::error(404), 'invalid_request'];
        yield '422' => [self::error(422), 'invalid_request'];
    }

    public function test_non_network_runtime_errors_never_switch(): void
    {
        $client = $this->client([new \RuntimeException('本地运行错误')]);
        try {
            $this->gateway($client, $this->config())->chat([['role' => 'user', 'content' => 'hi']]);
            self::fail('非网络错误不得切换');
        } catch (AiProviderException $exception) {
            self::assertSame('transport', $exception->category());
        }
        self::assertCount(1, $client->requests);
    }

    #[DataProvider('businessErrors')]
    public function test_business_4xx_errors_never_switch(Response $failure, string $category): void
    {
        $client = $this->client([$failure]);
        $events = [];
        try {
            $this->gateway($client, $this->config())->chat([['role' => 'user', 'content' => 'hi']], [], function (string $type) use (&$events): void { $events[] = $type; });
            self::fail('业务错误不得切换');
        } catch (AiProviderException $exception) {
            self::assertSame($category, $exception->category());
        }
        self::assertCount(1, $client->requests);
        self::assertNotContains('provider.fallback', $events);
    }

    public function test_exhausted_candidates_rethrow_last_failure(): void
    {
        $client = $this->client([self::error(500), self::error(500), self::error(500)]);
        try {
            $this->gateway($client, $this->config())->chat([['role' => 'user', 'content' => 'hi']]);
            self::fail('候选耗尽应报错');
        } catch (AiProviderException $exception) {
            self::assertSame('provider_unavailable', $exception->category());
        }
        self::assertCount(3, $client->requests);
    }

    public function test_fallback_disabled_never_switches(): void
    {
        $client = $this->client([self::error(503)]);
        $this->expectException(AiProviderException::class);
        try {
            $this->gateway($client, $this->config(['fallback_enabled' => false, 'fallback_models' => []]))->chat([['role' => 'user', 'content' => 'hi']]);
        } finally {
            self::assertCount(1, $client->requests);
        }
    }

    public function test_fallback_respects_request_budget(): void
    {
        $client = $this->client([self::error(503)]);
        $this->expectExceptionMessage('累计请求次数或超时额度耗尽');
        try {
            $this->gateway($client, $this->config(['_runtime_state' => ['requests' => 11, 'reserved_seconds' => 0, 'candidate' => 0]]))->chat([['role' => 'user', 'content' => 'hi']]);
        } finally {
            self::assertCount(1, $client->requests);
        }
    }

    private static function sse(array $events): Response
    {
        $body = '';
        foreach ($events as $event) $body .= 'data: ' . (is_string($event) ? $event : json_encode($event, JSON_THROW_ON_ERROR)) . "\n\n";
        return new Response(200, ['Content-Type' => 'text/event-stream'], $body);
    }

    public function test_stream_switches_only_before_output_starts(): void
    {
        $client = $this->client([self::error(503), self::sse([['choices' => [['delta' => ['content' => '你好']]]], ['choices' => [['delta' => [], 'finish_reason' => 'stop']]], '[DONE]'])]);
        $events = [];
        $chunks = iterator_to_array($this->gateway($client, $this->config())->stream([['role' => 'user', 'content' => 'hi']], [], null, function (string $type, array $payload) use (&$events): void { $events[] = [$type, $payload]; }), false);
        self::assertSame(['primary', 'backup-1'], array_map(static fn ($r) => $r['json']['model'], $client->requests));
        self::assertSame('你好', $chunks[0]['content']);
        self::assertContains('provider.fallback', array_column($events, 0));
    }

    public function test_stream_never_switches_after_output_started(): void
    {
        // 第一段已输出后流被截断：必须直接报错，不得再请求备用模型重复输出。
        $client = $this->client([self::sse([['choices' => [['delta' => ['content' => '部分']]]]]), self::ok('backup-1')]);
        $received = [];
        try {
            foreach ($this->gateway($client, $this->config())->stream([['role' => 'user', 'content' => 'hi']]) as $chunk) $received[] = $chunk;
            self::fail('截断的流必须报错');
        } catch (AiProviderException $exception) {
            self::assertSame('invalid_response', $exception->category());
        }
        self::assertSame('部分', $received[0]['content']);
        self::assertCount(1, $client->requests);
    }

    public function test_reasoning_payload_for_chat_completions(): void
    {
        $client = $this->client([self::ok('primary')]);
        $this->gateway($client, $this->config(['reasoning_effort' => 'high', 'fallback_enabled' => false, 'fallback_models' => []]))->chat([['role' => 'user', 'content' => 'hi']]);
        self::assertSame('high', $client->requests[0]['json']['reasoning_effort']);
        self::assertArrayNotHasKey('reasoning', $client->requests[0]['json']);
    }

    public function test_reasoning_payload_for_responses(): void
    {
        $body = ['model' => 'primary', 'status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'ok']]]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]];
        $client = $this->client([new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR))]);
        $this->gateway($client, $this->config(['protocol' => 'openai-responses', 'reasoning_effort' => 'low', 'fallback_enabled' => false, 'fallback_models' => []]))->chat([['role' => 'user', 'content' => 'hi']]);
        self::assertStringEndsWith('/responses', $client->requests[0]['uri']);
        self::assertSame(['effort' => 'low'], $client->requests[0]['json']['reasoning']);
        self::assertArrayNotHasKey('reasoning_effort', $client->requests[0]['json']);
    }

    public function test_reasoning_payload_for_messages_maps_effort_to_thinking_budget(): void
    {
        $body = ['model' => 'primary', 'content' => [['type' => 'thinking', 'thinking' => '...', 'signature' => 's'], ['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn', 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]];
        $client = $this->client([new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR))]);
        $result = $this->gateway($client, $this->config(['protocol' => 'anthropic-messages', 'reasoning_effort' => 'high', 'fallback_enabled' => false, 'fallback_models' => []]))->chat([['role' => 'user', 'content' => 'hi']]);
        $json = $client->requests[0]['json'];
        self::assertSame(['type' => 'enabled', 'budget_tokens' => AiProtocol::MESSAGES_THINKING_BUDGET_TOKENS['high']], $json['thinking']);
        self::assertSame(20000, $json['max_tokens']);
        self::assertArrayNotHasKey('reasoning', $json);
        self::assertArrayNotHasKey('reasoning_effort', $json);
        self::assertSame('ok', $result['content']);
    }

    public function test_messages_thinking_budget_mapping_is_strictly_increasing_and_covers_all_efforts(): void
    {
        $map = AiProtocol::MESSAGES_THINKING_BUDGET_TOKENS;
        self::assertSame(\app\common\ai\provider\AiModelCapabilities::REASONING_EFFORTS, array_keys($map));
        $values = array_values($map);
        $sorted = $values;
        sort($sorted);
        self::assertSame($sorted, $values);
        self::assertGreaterThanOrEqual(1024, $values[0]);
    }

    public function test_messages_thinking_budget_must_stay_below_output_budget(): void
    {
        $base = ['name' => 'p', 'provider' => 'anthropic', 'protocol' => 'anthropic-messages', 'base_url' => 'https://8.8.8.8/v1', 'model' => 'claude', 'model_capabilities' => [['model' => 'claude', 'reasoning_efforts' => ['low', 'max']]]];
        $valid = AiConfigurationProfileService::validate($base + ['max_output_tokens' => 4096, 'reasoning_effort' => 'low']);
        self::assertSame('low', $valid['reasoning_effort']);
        $this->expectExceptionMessage('思考预算');
        AiConfigurationProfileService::validate($base + ['max_output_tokens' => 4096, 'reasoning_effort' => 'max']);
    }

    /** @return iterable<string, array{0: string}> */
    public static function protocols(): iterable
    {
        foreach (AiProtocol::VALUES as $protocol) yield $protocol => [$protocol];
    }

    #[DataProvider('protocols')]
    public function test_declared_effort_is_accepted_for_every_protocol(string $protocol): void
    {
        $config = AiConfigurationProfileService::validate(['name' => 'p', 'provider' => 'x', 'protocol' => $protocol, 'base_url' => 'https://8.8.8.8/v1', 'model' => 'm', 'max_output_tokens' => 64000, 'reasoning_effort' => 'medium', 'model_capabilities' => [['model' => 'm', 'reasoning_efforts' => ['medium']]]]);
        self::assertSame('medium', $config['reasoning_effort']);
    }

    #[DataProvider('protocols')]
    public function test_undeclared_effort_is_rejected_for_every_protocol(string $protocol): void
    {
        $this->expectExceptionMessage('reasoning_effort 能力未验证或不匹配');
        AiConfigurationProfileService::validate(['name' => 'p', 'provider' => 'x', 'protocol' => $protocol, 'base_url' => 'https://8.8.8.8/v1', 'model' => 'm', 'max_output_tokens' => 64000, 'reasoning_effort' => 'high', 'model_capabilities' => [['model' => 'm', 'reasoning_efforts' => ['medium']]]]);
    }

    public function test_fallback_candidates_must_also_declare_selected_effort(): void
    {
        $config = $this->config(['reasoning_effort' => 'high']);
        $config['model_capabilities'][2]['reasoning_efforts'] = ['low'];
        $client = $this->client([]);
        $this->expectExceptionMessage('reasoning_effort 能力未验证或不匹配');
        $this->gateway($client, $config)->chat([['role' => 'user', 'content' => 'hi']]);
    }
}
