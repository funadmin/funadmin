<?php

declare(strict_types=1);

namespace app\common\ai\provider;

use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/** OpenAI Chat Completions 兼容网关。 */
final class OpenAiCompatibleGateway
{
    public const MIN_CONNECT_TIMEOUT = 1;
    public const MAX_CONNECT_TIMEOUT = 30;
    public const MIN_REQUEST_TIMEOUT = 1;
    public const MAX_REQUEST_TIMEOUT = 300;
    public const MIN_RETRIES = 0;
    public const MAX_RETRIES = 3;

    private readonly string $baseUrl;
    private readonly string $apiKey;
    private readonly string $model;
    private readonly int $connectTimeout;
    private readonly int $requestTimeout;
    private readonly int $maxRetries;
    private readonly mixed $resolver;
    private readonly mixed $sleeper;

    public function __construct(
        private readonly ClientInterface $client,
        array $config,
        ?callable $resolver = null,
        ?callable $sleeper = null
    ) {
        $this->baseUrl = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->model = (string) ($config['model'] ?? '');
        $this->connectTimeout = max(self::MIN_CONNECT_TIMEOUT, min(self::MAX_CONNECT_TIMEOUT, (int) ($config['connect_timeout'] ?? 5)));
        $this->requestTimeout = max(self::MIN_REQUEST_TIMEOUT, min(self::MAX_REQUEST_TIMEOUT, (int) ($config['request_timeout'] ?? 60)));
        $this->maxRetries = max(self::MIN_RETRIES, min(self::MAX_RETRIES, (int) ($config['max_retries'] ?? 2)));
        $this->resolver = $resolver ?? static fn (string $host): array => self::resolveHost($host);
        $this->sleeper = $sleeper ?? static fn (int $milliseconds) => usleep($milliseconds * 1000);
        $this->validateUrl(false);
    }

    public function chat(array $messages, array $tools = []): array
    {
        $response = $this->request($this->payload($messages, $tools, false));
        try {
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiProviderException('invalid_response', 'Provider 返回了无效 JSON', previous: $exception);
        }
        $choice = $body['choices'][0] ?? [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];

        return [
            'id' => (string) ($body['id'] ?? ''),
            'content' => $message['content'] ?? null,
            'toolCalls' => $this->normalizeToolCalls((array) ($message['tool_calls'] ?? [])),
            'finishReason' => (string) ($choice['finish_reason'] ?? ''),
            'usage' => $this->normalizeUsage((array) ($body['usage'] ?? [])),
        ];
    }

    public function stream(array $messages, array $tools = []): iterable
    {
        $response = $this->request($this->payload($messages, $tools, true), true);
        $buffer = '';
        $calls = [];
        while (!$response->getBody()->eof()) {
            $buffer .= $response->getBody()->read(8192);
            foreach ($this->drainEvents($buffer, $calls) as $chunk) {
                yield $chunk;
            }
        }
        if ($buffer !== '') {
            foreach ($this->parseEvent($buffer, $calls) as $chunk) {
                yield $chunk;
            }
        }
        foreach ($calls as $call) {
            yield $this->normalizeStreamToolCall($call);
        }
        yield ['type' => 'done'];
    }

    private function request(array $json, bool $stream = false): ResponseInterface
    {
        $addresses = $this->validatedAddresses();
        $host = (string) parse_url($this->baseUrl, PHP_URL_HOST);
        $port = (int) (parse_url($this->baseUrl, PHP_URL_PORT) ?: 443);
        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->client->request('POST', $this->baseUrl . '/chat/completions', [
                    'headers' => array_filter([
                        'Accept' => $stream ? 'text/event-stream' : 'application/json',
                        'Authorization' => $this->apiKey === '' ? null : 'Bearer ' . $this->apiKey,
                    ]),
                    'json' => $json,
                    'connect_timeout' => $this->connectTimeout,
                    'timeout' => $this->requestTimeout,
                    'stream' => $stream,
                    'http_errors' => false,
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => array_map(static fn (string $address): string => "{$host}:{$port}:{$address}", $addresses)],
                ]);
            } catch (Throwable $exception) {
                throw new AiProviderException($this->isTimeout($exception) ? 'timeout' : 'transport', $this->isTimeout($exception) ? 'Provider 请求超时' : 'Provider 网络请求失败', previous: $exception);
            }
            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return $response;
            }
            if (($status === 429 || $status >= 500) && $attempt < $this->maxRetries) {
                ($this->sleeper)($this->retryDelay($response, $attempt));
                continue;
            }
            throw new AiProviderException($this->category($status), 'Provider 请求失败', $status);
        }
    }

    private function payload(array $messages, array $tools, bool $stream): array
    {
        $payload = ['model' => $this->model, 'messages' => $messages, 'stream' => $stream];
        if ($tools !== []) {
            $payload['tools'] = $tools;
        }
        return $payload;
    }

    private function normalizeToolCalls(array $calls): array
    {
        return array_map(function (array $call): array {
            $function = (array) ($call['function'] ?? []);
            try {
                $arguments = json_decode((string) ($function['arguments'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new AiProviderException('invalid_response', 'Provider tool arguments 不是有效 JSON', previous: $exception);
            }
            return ['id' => (string) ($call['id'] ?? ''), 'name' => (string) ($function['name'] ?? ''), 'arguments' => is_array($arguments) ? $arguments : []];
        }, $calls);
    }

    private function drainEvents(string &$buffer, array &$calls): array
    {
        $output = [];
        while (preg_match('/\r?\n\r?\n/', $buffer, $match, PREG_OFFSET_CAPTURE)) {
            $separator = $match[0][1];
            $event = substr($buffer, 0, $separator);
            $buffer = substr($buffer, $separator + strlen($match[0][0]));
            $output = array_merge($output, $this->parseEvent($event, $calls));
        }
        return $output;
    }

    private function parseEvent(string $event, array &$calls): array
    {
        $output = [];
        foreach (preg_split('/\r?\n/', $event) ?: [] as $line) {
            if (!str_starts_with($line, 'data:')) {
                continue;
            }
            $data = trim(substr($line, 5));
            if ($data === '' || $data === '[DONE]') {
                continue;
            }
            try {
                $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new AiProviderException('invalid_response', 'Provider SSE chunk 不是有效 JSON', previous: $exception);
            }
            $delta = (array) ($decoded['choices'][0]['delta'] ?? []);
            if (($delta['content'] ?? '') !== '') {
                $output[] = ['type' => 'content', 'content' => (string) $delta['content']];
            }
            foreach ((array) ($delta['tool_calls'] ?? []) as $part) {
                $index = (int) ($part['index'] ?? 0);
                $calls[$index] ??= ['id' => '', 'name' => '', 'arguments' => ''];
                $calls[$index]['id'] .= (string) ($part['id'] ?? '');
                $calls[$index]['name'] .= (string) ($part['function']['name'] ?? '');
                $calls[$index]['arguments'] .= (string) ($part['function']['arguments'] ?? '');
            }
        }
        return $output;
    }

    private function normalizeStreamToolCall(array $call): array
    {
        try {
            $arguments = json_decode($call['arguments'] ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiProviderException('invalid_response', 'Provider 流式 tool arguments 不是有效 JSON', previous: $exception);
        }
        return ['type' => 'tool_call', 'id' => $call['id'], 'name' => $call['name'], 'arguments' => is_array($arguments) ? $arguments : []];
    }

    private function normalizeUsage(array $usage): array
    {
        return ['inputTokens' => (int) ($usage['prompt_tokens'] ?? 0), 'outputTokens' => (int) ($usage['completion_tokens'] ?? 0), 'totalTokens' => (int) ($usage['total_tokens'] ?? 0)];
    }

    private function validateUrl(bool $resolve): void
    {
        $parts = parse_url($this->baseUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('AI Provider base URL 必须是无凭据、查询参数和片段的 HTTPS URL');
        }
        if ($this->isUnsafeHost($host)) {
            throw new InvalidArgumentException('AI Provider base URL 禁止访问本机、私网或元数据地址');
        }
        if ($resolve) {
            $this->validatedAddresses();
        }
    }

    private function validatedAddresses(): array
    {
        $host = strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST));
        $addresses = ($this->resolver)($host);
        if ($addresses === [] || array_filter($addresses, fn (string $address): bool => !$this->isPublicIp($address))) {
            throw new InvalidArgumentException('AI Provider DNS 必须仅解析到公网地址');
        }
        return array_values(array_unique($addresses));
    }

    private function isUnsafeHost(string $host): bool
    {
        return $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_contains($host, 'metadata') || (filter_var($host, FILTER_VALIDATE_IP) !== false && !$this->isPublicIp($host));
    }

    private function isPublicIp(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function resolveHost(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        return array_values(array_filter(array_map(static fn (array $record): string => (string) ($record['ip'] ?? $record['ipv6'] ?? ''), $records)));
    }

    private function retryDelay(ResponseInterface $response, int $attempt): int
    {
        $retryAfter = trim($response->getHeaderLine('Retry-After'));
        return ctype_digit($retryAfter) ? min(2000, (int) $retryAfter * 1000) : min(2000, 100 * (2 ** $attempt));
    }

    private function category(int $status): string
    {
        return match (true) {
            $status === 401 || $status === 403 => 'authentication',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'provider_unavailable',
            default => 'invalid_request',
        };
    }

    private function isTimeout(Throwable $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'timed out') || str_contains(strtolower($exception->getMessage()), 'timeout');
    }
}
