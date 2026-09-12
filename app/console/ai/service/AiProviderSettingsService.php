<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\common\ai\provider\OpenAiCompatibleGateway;
use GuzzleHttp\Client;

/** Provider 设置读取与临时连接测试服务。 */
final class AiProviderSettingsService
{
    private const FIELDS = [
        'name',
        'base_url',
        'model',
        'connect_timeout',
        'request_timeout',
        'max_retries',
        'api_key',
    ];

    public function __construct(
        private readonly array $serverConfig,
        private readonly mixed $gatewayFactory = null
    ) {
    }

    public function read(array $limits): array
    {
        $provider = $this->serverConfig;
        $apiKey = (string) ($provider['api_key'] ?? '');
        unset($provider['api_key']);
        $provider['configured'] = $apiKey !== '';
        $provider['masked'] = $this->mask($apiKey);

        return ['provider' => $provider, 'limits' => $limits];
    }

    public function test(array $input): array
    {
        $this->validateInput($input);
        $config = $this->serverConfig;
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $config[$field] = $input[$field];
            }
        }

        $gateway = $this->gatewayFactory !== null
            ? ($this->gatewayFactory)($config)
            : new OpenAiCompatibleGateway(new Client(), $config);
        $result = $gateway->chat([['role' => 'user', 'content' => 'Reply OK only.']]);

        return [
            'reachable' => true,
            'model' => (string) ($config['model'] ?? ''),
            'finishReason' => (string) ($result['finishReason'] ?? ''),
        ];
    }

    private function validateInput(array $input): void
    {
        foreach (['name', 'base_url', 'model', 'api_key'] as $field) {
            if (array_key_exists($field, $input) && !is_string($input[$field])) {
                throw new \InvalidArgumentException('Provider 字段类型无效');
            }
        }

        $ranges = [
            'connect_timeout' => [OpenAiCompatibleGateway::MIN_CONNECT_TIMEOUT, OpenAiCompatibleGateway::MAX_CONNECT_TIMEOUT],
            'request_timeout' => [OpenAiCompatibleGateway::MIN_REQUEST_TIMEOUT, OpenAiCompatibleGateway::MAX_REQUEST_TIMEOUT],
            'max_retries' => [OpenAiCompatibleGateway::MIN_RETRIES, OpenAiCompatibleGateway::MAX_RETRIES],
        ];
        foreach ($ranges as $field => [$minimum, $maximum]) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field];
            if (!is_int($value) || $value < $minimum || $value > $maximum) {
                throw new \InvalidArgumentException('Provider 数值字段范围无效');
            }
        }
    }

    private function mask(string $apiKey): string
    {
        if ($apiKey === '') {
            return '';
        }
        return strlen($apiKey) <= 4
            ? str_repeat('•', 8)
            : substr($apiKey, 0, 2) . str_repeat('•', 8);
    }
}
