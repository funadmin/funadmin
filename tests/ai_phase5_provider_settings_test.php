<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\ai\provider\AiProviderException;
use app\console\ai\service\AiProviderSettingsService;
use app\common\service\AdminLogService;

function phase5ProviderExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

phase5ProviderExpect(class_exists(AiProviderSettingsService::class), '阶段五缺少 Provider settings 服务');

$adminLogServiceSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/service/AdminLogService.php');
phase5ProviderExpect(str_contains($adminLogServiceSource, 'use app\\console\\model\\AdminLog;'), 'AdminLogService 必须引用实际存在的 AdminLog 模型');
phase5ProviderExpect(class_exists(app\console\model\AdminLog::class), '日志保存使用的 AdminLog 模型必须可解析');

$serverConfig = [
    'name' => 'server-provider',
    'base_url' => 'https://server.example.com/v1',
    'model' => 'server-model',
    'connect_timeout' => 5,
    'request_timeout' => 60,
    'max_retries' => 2,
    'api_key' => 'sk-server-secret',
];
$captured = [];
$service = new AiProviderSettingsService($serverConfig, static function (array $config) use (&$captured): object {
    $captured[] = $config;
    return new class {
        public function chat(array $messages): array
        {
            return ['finishReason' => 'stop'];
        }
    };
});

$public = $service->read(['max_rounds' => 8]);
phase5ProviderExpect(!array_key_exists('api_key', $public['provider']), 'settingsRead 不得泄漏 api_key');
phase5ProviderExpect($public['provider']['configured'] === true, 'settingsRead 必须返回 configured');
phase5ProviderExpect(is_string($public['provider']['masked']) && $public['provider']['masked'] !== '', 'settingsRead 必须返回非空 masked');
phase5ProviderExpect(!str_contains(json_encode($public, JSON_THROW_ON_ERROR), 'sk-server-secret'), 'settingsRead 的 masked 不得包含 secret');
phase5ProviderExpect($public['limits']['max_rounds'] === 8, 'settingsRead 必须保留 limits');

$temporary = [
    'name' => 'temporary-provider',
    'base_url' => 'https://temporary.example.com/v1',
    'model' => 'temporary-model',
    'connect_timeout' => 7,
    'request_timeout' => 19,
    'max_retries' => 1,
    'api_key' => 'sk-temporary-secret',
];
$result = $service->test($temporary);
phase5ProviderExpect($captured[0] === $temporary, 'settingsTest 必须使用本次请求体中的全部临时 Provider 字段');
phase5ProviderExpect($result === ['reachable' => true, 'model' => 'temporary-model', 'finishReason' => 'stop'], 'settingsTest 必须返回临时 model 与连接结果');

$service->test(['model' => '', 'api_key' => '']);
phase5ProviderExpect($captured[1]['model'] === '' && $captured[1]['api_key'] === '', 'settingsTest 仅在字段缺省时回退，显式空值不得回退');
phase5ProviderExpect($captured[1]['name'] === 'server-provider' && $captured[1]['base_url'] === 'https://server.example.com/v1', 'settingsTest 缺省字段必须回退服务端配置');
phase5ProviderExpect($serverConfig['api_key'] === 'sk-server-secret', 'settingsTest 不得持久化临时 api_key');

$invalidInputs = [
    ['name' => null],
    ['base_url' => []],
    ['model' => 123],
    ['api_key' => ['sk-validation-secret']],
    ['connect_timeout' => null],
    ['connect_timeout' => []],
    ['connect_timeout' => 1.5],
    ['connect_timeout' => '5'],
    ['connect_timeout' => -1],
    ['connect_timeout' => 31],
    ['request_timeout' => null],
    ['request_timeout' => []],
    ['request_timeout' => 1.5],
    ['request_timeout' => '60'],
    ['request_timeout' => -1],
    ['request_timeout' => 301],
    ['max_retries' => null],
    ['max_retries' => []],
    ['max_retries' => 1.5],
    ['max_retries' => '2'],
    ['max_retries' => -1],
    ['max_retries' => 4],
];
foreach ($invalidInputs as $invalidInput) {
    try {
        $service->test($invalidInput + ['api_key' => 'sk-validation-secret']);
        throw new RuntimeException('settingsTest 必须拒绝非法字段类型或越界数值：' . json_encode(array_keys($invalidInput), JSON_THROW_ON_ERROR));
    } catch (InvalidArgumentException $exception) {
        phase5ProviderExpect(!str_contains($exception->getMessage(), 'sk-validation-secret'), 'settingsTest 校验错误不得包含 secret');
    }
}
$service->test(['connect_timeout' => 1, 'request_timeout' => 1, 'max_retries' => 0]);
$service->test(['connect_timeout' => 30, 'request_timeout' => 300, 'max_retries' => 3]);
phase5ProviderExpect($captured[2]['connect_timeout'] === 1 && $captured[2]['request_timeout'] === 1 && $captured[2]['max_retries'] === 0, 'settingsTest 必须接受安全范围下界');
phase5ProviderExpect($captured[3]['connect_timeout'] === 30 && $captured[3]['request_timeout'] === 300 && $captured[3]['max_retries'] === 3, 'settingsTest 必须接受安全范围上界');

$unsafeService = new AiProviderSettingsService($serverConfig);
foreach (['http://api.example.com/v1', 'https://127.0.0.1/v1'] as $unsafeUrl) {
    try {
        $unsafeService->test(['base_url' => $unsafeUrl, 'api_key' => 'sk-never-log-this']);
        throw new RuntimeException('settingsTest 必须复用网关 HTTPS/SSRF 校验');
    } catch (InvalidArgumentException $exception) {
        phase5ProviderExpect(!str_contains($exception->getMessage(), 'sk-never-log-this'), '校验错误不得包含临时 secret');
    }
}

$errorService = new AiProviderSettingsService($serverConfig, static fn (array $config): object => new class {
    public function chat(array $messages): array
    {
        throw new AiProviderException('authentication', 'Provider 请求失败');
    }
});
try {
    $errorService->test(['api_key' => 'sk-error-secret']);
    throw new RuntimeException('Provider 错误必须向上传递');
} catch (AiProviderException $exception) {
    phase5ProviderExpect(!str_contains($exception->getMessage(), 'sk-error-secret'), 'Provider 错误不得包含临时 secret');
}

$sanitize = new ReflectionMethod(AdminLogService::class, 'sanitize');
$sanitize->setAccessible(true);
$sanitized = $sanitize->invoke(AdminLogService::instance(), ['api_key' => 'sk-audit-secret', 'nested' => ['apiKey' => 'sk-camel-secret']]);
phase5ProviderExpect($sanitized['api_key'] === '[REDACTED]' && $sanitized['nested']['apiKey'] === '[REDACTED]', '操作日志必须脱敏临时 api_key/apiKey');
phase5ProviderExpect(!str_contains(json_encode($sanitized, JSON_THROW_ON_ERROR), 'sk-audit-secret'), '操作日志不得记录临时 key');

$controller = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/ai/Ai.php');
phase5ProviderExpect(str_contains($controller, 'AiProviderSettingsService'), 'Provider 控制器必须委托 settings 服务');
phase5ProviderExpect(str_contains($controller, '$this->input()'), 'settingsTest 必须读取本次请求体');

echo "AI phase 5 provider settings tests: PASS\n";
