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
    private int $candidate = 0;
    private bool $exhausted = false;
    private int $requestCount = 0;
    private float $reservedSeconds = 0;
    private mixed $event = null;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly array $config,
        ?callable $resolver = null,
        ?callable $sleeper = null
    ) {
        $this->baseUrl = self::normalizeBaseUrl((string) ($config['base_url'] ?? ''));
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->model = (string) ($config['model'] ?? '');
        $this->connectTimeout = max(self::MIN_CONNECT_TIMEOUT, min(self::MAX_CONNECT_TIMEOUT, (int) ($config['connect_timeout'] ?? 5)));
        $this->requestTimeout = max(self::MIN_REQUEST_TIMEOUT, min(self::MAX_REQUEST_TIMEOUT, (int) ($config['request_timeout'] ?? 60)));
        $this->maxRetries = max(self::MIN_RETRIES, min(self::MAX_RETRIES, (int) ($config['max_retries'] ?? 2)));
        $this->resolver = $resolver ?? static fn (string $host): array => self::resolveHost($host);
        $this->sleeper = $sleeper ?? static fn (int $milliseconds) => usleep($milliseconds * 1000);
        $this->validateUrl(false);
        $state = $config['_runtime_state'] ?? [];
        $this->requestCount = (int) ($state['requests'] ?? 0);
        $this->reservedSeconds = (float) ($state['reserved_seconds'] ?? 0);
        $this->candidate = (int) ($state['candidate'] ?? 0);
        if ($this->requestCount < 0 || $this->reservedSeconds < 0 || $this->candidate < 0 || $this->candidate > count($config['fallback_models'] ?? [])) throw new InvalidArgumentException('运行额度状态无效');
        if ($this->requestCount >= 12 || $this->reservedSeconds + $this->requestTimeout > 300) throw new AiProviderException('request_budget_exceeded', '累计请求额度耗尽');
    }

    /** 只为根域补默认版本；代理前缀保持原意，不猜测或剥离完整 endpoint。 */
    public static function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~/(?:chat/completions|models|responses|messages)$~', $path)) {
            throw new InvalidArgumentException('AI Provider 请填写 Base URL，不支持完整 endpoint');
        }
        return $path === '' ? $url . '/v1' : $url;
    }

    /** 目录只证明端点返回了模型 ID，不推断窗口或推理能力。 */
    public function models(): array
    {
        $response = $this->request([], false, '/models', 'GET');
        try {
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AiProviderException('invalid_response', '模型目录不是有效 JSON');
        }
        if (!is_array($body['data'] ?? null) || !array_is_list($body['data'])) {
            throw new AiProviderException('invalid_response', '模型目录格式无效');
        }
        $models = [];
        foreach ($body['data'] as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            if (!is_string($id) || trim($id) !== $id || $id === '' || strlen($id) > 200 || preg_match('/[\x00-\x1f\x7f]/', $id)) {
                throw new AiProviderException('invalid_response', '模型目录 ID 无效');
            }
            $models[$id] = ['id'=>$id];
        }
        return array_values($models);
    }

    public function chat(array $messages, array $tools = [], ?callable $event = null): array
    {
        $this->event = $event;
        AiModelCapabilities::validateSelection($this->config);
        $models = array_merge([$this->model], ($this->config['fallback_enabled'] ?? false) ? $this->config['fallback_models'] : []);
        // 在任何请求前检查全部候选，不能通过跳过不兼容模型悄悄降级。
        $payloads = [];
        foreach ($models as $model) $payloads[] = $this->payload($messages, $tools, false, $model);
        if ($this->exhausted) throw new AiProviderException('fallback_exhausted', '候选模型已耗尽');
        while (true) {
            try {
                $response = $this->request($payloads[$this->candidate], retries: $this->candidate === 0 ? $this->maxRetries : 0);
                break;
            } catch (AiProviderException $exception) {
                if (!in_array($exception->category(), ['rate_limited','provider_unavailable','transient_transport'], true)) throw $exception;
                if (!isset($models[$this->candidate + 1])) {
                    $this->exhausted = true;
                    throw $exception;
                }
                $from = $models[$this->candidate++];
                $event && $event('provider.fallback', ['from_model'=>$from,'to_model'=>$models[$this->candidate],'category'=>$exception->category(),'candidate'=>$this->candidate]);
            }
        }
        try {
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new AiProviderException('invalid_response', 'Provider 返回了无效 JSON', previous: $exception);
        }
        // 供应商回显的图片不得进入任务输出、审批参数或事件。
        if (preg_match('/data:[^,\s]*;base64,/i', json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))) throw new AiProviderException('invalid_response', '供应商响应包含内联二进制数据');
        $choice = $body['choices'][0] ?? [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];

        if (!empty($message['refusal']) || ($choice['finish_reason'] ?? '') === 'content_filter') throw new AiProviderException('safety_refusal', '模型安全拒绝');
        $actual = $body['model'] ?? $models[$this->candidate];
        if (!is_string($actual) || $actual === '' || strlen($actual) > 200 || preg_match('/[\x00-\x1f\x7f]/', $actual)) throw new AiProviderException('invalid_response', '返回模型标识无效');
        $event && $event('provider.completed', ['model'=>$actual,'requested_model'=>$models[$this->candidate],'requests'=>$this->requestCount]);
        return [
            'model' => $actual,
            'requestedModel' => $models[$this->candidate],
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

    private function request(array $json, bool $stream = false, string $path = '/chat/completions', string $method = 'POST', ?int $retries = null): ResponseInterface
    {
        $addresses = $this->validatedAddresses();
        $host = (string) parse_url($this->baseUrl, PHP_URL_HOST);
        $port = (int) (parse_url($this->baseUrl, PHP_URL_PORT) ?: 443);
        $retries ??= $this->maxRetries;
        for ($attempt = 0; ; $attempt++) {
            if ($path === '/chat/completions') {
                if ($this->requestCount >= 12 || $this->reservedSeconds + $this->requestTimeout > 300) throw new AiProviderException('request_budget_exceeded', '累计请求次数或超时额度耗尽');
                $this->requestCount++;
                $this->reservedSeconds += $this->requestTimeout;
                $this->event && ($this->event)('provider.request', ['model'=>$json['model'],'attempt'=>$attempt + 1,'requests'=>$this->requestCount,'reserved_seconds'=>$this->reservedSeconds,'candidate'=>$this->candidate]);
            }
            try {
                $response = $this->client->request($method, $this->baseUrl . $path, [
                    'headers' => array_filter([
                        'Accept' => $stream ? 'text/event-stream' : 'application/json',
                        'Authorization' => $this->apiKey === '' ? null : 'Bearer ' . $this->apiKey,
                    ]),
                    'json' => $method === 'GET' ? null : $json,
                    'connect_timeout' => $this->connectTimeout,
                    'timeout' => $this->requestTimeout,
                    'stream' => $stream,
                    'http_errors' => false,
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => array_map(static fn (string $address): string => "{$host}:{$port}:{$address}", $addresses)],
                ]);
            } catch (Throwable $exception) {
                // 仅明确连接层故障可重试；无 errno、TLS/证书错误及一般运行错误均拒绝猜测。
                // errno 28 可能发生在发送请求或接收部分响应后，必须停止，不能重试或备用。
                $transient = $exception instanceof \GuzzleHttp\Exception\ConnectException && in_array($exception->getHandlerContext()['errno'] ?? null, [6,7], true);
                if ($transient && $attempt < $retries) { ($this->sleeper)(min(2000, 100 * (2 ** $attempt))); continue; }
                throw new AiProviderException($transient ? 'transient_transport' : ($this->isTimeout($exception) ? 'timeout' : 'transport'), 'Provider 网络请求失败');
            }
            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return $response;
            }
            // 非成功响应若携带内容、工具调用或安全拒绝，不能视为无输出暂时故障。
            $errorBody = json_decode((string) $response->getBody(), true);
            $errorCode = $errorBody['error']['code'] ?? null;
            if (in_array($errorCode, ['content_policy_violation','content_filter','safety_refusal','context_length_exceeded','unsupported_parameter','invalid_parameter','invalid_api_key','insufficient_quota'], true)) throw new AiProviderException('invalid_request', 'Provider 明确拒绝请求，禁止降级');
            foreach ($errorBody['choices'] ?? [] as $choice) {
                if ((isset($choice['message']['content']) && $choice['message']['content'] !== '') || !empty($choice['message']['tool_calls']) || isset($choice['message']['refusal']) || ($choice['finish_reason'] ?? '') === 'content_filter') throw new AiProviderException('response_started', '非成功响应已包含模型输出');
            }
            if (($status === 429 || ($status >= 500 && $status <= 599)) && $attempt < $retries) {
                ($this->sleeper)($this->retryDelay($response, $attempt));
                continue;
            }
            throw new AiProviderException($this->category($status), 'Provider 请求失败', $status);
        }
    }

    private function payload(array $messages, array $tools, bool $stream, ?string $model = null): array
    {
        if (($this->config['protocol'] ?? 'openai-chat') !== 'openai-chat') {
            throw new InvalidArgumentException('仅支持 openai-chat 协议');
        }
        if ($stream && ($this->config['fallback_enabled'] ?? false)) throw new InvalidArgumentException('流式请求暂不支持备用');
        AiModelCapabilities::validateSelection($this->config);
        $model ??= $this->model;
        $cap = AiModelCapabilities::forModel($this->config, $model);
        $pending = [];
        foreach ($messages as &$message) {
            if (($message['role'] ?? '') === 'tool') {
                $id = $message['tool_call_id'] ?? '';
                if (!isset($pending[$id])) throw new InvalidArgumentException('工具结果缺少配对调用');
                unset($pending[$id]);
                continue;
            }
            if ($pending !== []) throw new InvalidArgumentException('工具调用缺少结果，禁止静默丢弃');
            foreach ($message['tool_calls'] ?? [] as $index => $call) {
                $id = $call['id'] ?? '';
                if ($id === '' || isset($pending[$id]) || ($message['role'] ?? '') !== 'assistant') throw new InvalidArgumentException('工具调用配对无效');
                $pending[$id] = true;
                if (isset($call['name'])) {
                    $message['tool_calls'][$index] = ['id'=>$id, 'type'=>'function', 'function'=>['name'=>$call['name'], 'arguments'=>json_encode($call['arguments'] ?? new \stdClass(), JSON_THROW_ON_ERROR)]];
                }
            }
        }
        unset($message);
        if ($pending !== []) throw new InvalidArgumentException('工具调用缺少结果，禁止静默丢弃');
        $output = $this->config['max_output_tokens'] ?? null;
        foreach (['max_output_tokens', 'max_input_tokens', 'context_window'] as $field) {
            $value = $this->config[$field] ?? null;
            if ($value !== null && (!is_int($value) || $value < 1)) throw new InvalidArgumentException($field . ' 必须为正整数');
        }
        // 无可靠 tokenizer：按 UTF-8 JSON 每字节一个 token，加每消息 64 和固定 1024 余量。
        // 这是保守准入估算，不是模型精确计数；不截断任何系统、用户或工具消息。
        $textMessages = $messages;
        $images = [];
        foreach ($messages as $mi => $message) {
            if (!is_array($message['content'] ?? null)) continue;
            foreach ($message['content'] as $bi => $block) {
                if (($block['type'] ?? '') === 'text') continue;
                if (($block['type'] ?? '') !== 'private_image' || ($message['role'] ?? '') !== 'user') throw new InvalidArgumentException('只允许可信私有图片引用，禁止供应商展开数组');
                if (!$cap['image_input'] || !in_array($block['mime'] ?? null, $cap['image_mime_types'], true)) throw new InvalidArgumentException('候选模型未声明图片能力或 MIME 不兼容');
                $images[] = [$mi, $bi, $block];
                $textMessages[$mi]['content'][$bi] = ['type'=>'text', 'text'=>''];
            }
        }
        if (count($images) > $cap['max_images']) throw new AiProviderException('budget_exceeded', '候选模型图片数量超限');
        $estimate = strlen(json_encode(['messages'=>$textMessages, 'tools'=>$tools], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) + count($messages) * 64 + 1024 + count($images) * $cap['image_tokens'];
        $inputLimit = $this->config['max_input_tokens'] ?? null;
        $window = $this->config['context_window'] ?? null;
        if (($inputLimit !== null && $estimate > $inputLimit) || ($window !== null && ($output === null || $estimate + $output > $window))) {
            throw new AiProviderException('budget_exceeded', '保守输入估算超过预算，或上下文预算缺少明确输出预留');
        }
        if ($cap['context_window'] !== null && ($output === null || $estimate + $output > $cap['context_window'])) throw new AiProviderException('budget_exceeded', '候选模型上下文预算不匹配');
        $payload = ['model' => $model, 'messages' => $messages, 'stream' => $stream];
        if ($output !== null) $payload[$cap['output_token_parameter']] = $output;
        $effort = $this->config['reasoning_effort'] ?? null;
        if ($effort !== null && $effort !== 'default') $payload['reasoning_effort'] = $effort;
        if ($stream && ($this->config['stream_usage'] ?? false) === true) $payload['stream_options'] = ['include_usage'=>true];
        if ($tools !== []) {
            $payload['tools'] = $tools;
        }
        foreach ($images as [$mi, $bi, $reference]) {
            $resolver = $this->config['_image_resolver'] ?? null;
            if (!is_callable($resolver)) throw new InvalidArgumentException('缺少私有图片鉴权读取器');
            $image = $resolver($reference);
            if (!is_string($image['bytes'] ?? null) || strlen($image['bytes']) > 5242880 || ($image['mime'] ?? null) !== $reference['mime'] || !hash_equals((string) ($reference['sha256'] ?? ''), hash('sha256', $image['bytes']))) throw new InvalidArgumentException('图片内容校验失败');
            $payload['messages'][$mi]['content'][$bi] = ['type'=>'image_url', 'image_url'=>['url'=>'data:' . $image['mime'] . ';base64,' . base64_encode($image['bytes'])]];
        }
        if (strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > AiModelCapabilities::MAX_HTTP_BODY_BYTES) throw new AiProviderException('request_body_exceeded', 'HTTP 请求体超过 20MiB');
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
            if (is_array($decoded['usage'] ?? null)) {
                $output[] = ['type'=>'usage', 'usage'=>$this->normalizeUsage($decoded['usage'])];
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
            $status >= 500 && $status <= 599 => 'provider_unavailable',
            default => 'invalid_request',
        };
    }

    private function isTimeout(Throwable $exception): bool
    {
        if ($exception instanceof \GuzzleHttp\Exception\RequestException || $exception instanceof \GuzzleHttp\Exception\ConnectException) {
            if (($exception->getHandlerContext()['errno'] ?? null) === 28) return true;
        }
        return str_contains(strtolower($exception->getMessage()), 'timed out') || str_contains(strtolower($exception->getMessage()), 'timeout');
    }
}
