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
