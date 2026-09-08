<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\marketplace\CloudAccountSession;
use app\common\plugin\marketplace\NativeMarketplaceAdapter;
use app\common\plugin\marketplace\PluginMarketplaceGateway;
use app\common\plugin\marketplace\SessionStore;
use app\common\plugin\marketplace\dto\CloudAccountDto;
use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use app\common\plugin\marketplace\dto\LoginRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceProtocol;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\PluginVersionDto;
use app\common\plugin\marketplace\dto\UpdateCheckRequestDto;
use app\common\plugin\package\PluginPackageDownloader;

function remainingExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function remainingException(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        remainingExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期异常：' . $contains);
}

final class RemainingMemorySessionStore implements SessionStore
{
    public array $values = [];

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->values[$key]);
    }
}

remainingExpect(MarketplaceProtocol::MANIFEST_SCHEMA === 2, '市场协议 manifest schema 必须硬切为 2');
remainingExpect(MarketplaceProtocol::PACKAGE_FORMAT === 'funadmin-native-app-v1', '市场协议 package format 错误');
remainingExpect(MarketplaceProtocol::SIGNATURE_ALGORITHM === 'ed25519', '市场签名算法必须硬切为 ed25519');

$gatewayCheckUpdates = (new ReflectionMethod(PluginMarketplaceGateway::class, 'checkUpdates'))->getParameters()[0];
remainingExpect((string) $gatewayCheckUpdates->getType() === UpdateCheckRequestDto::class, 'Gateway checkUpdates 必须接收结构化请求 DTO');
$requestDto = new UpdateCheckRequestDto([
    ['code' => 'demo', 'code_version' => '1.0.0', 'db_version' => '003_seed.sql', 'modified' => false],
]);
remainingExpect($requestDto->installed[0]['db_version'] === '003_seed.sql', '更新检查请求必须保留数据库版本');
remainingException(static fn () => new UpdateCheckRequestDto([
    ['code' => 'demo', 'code_version' => '1.0.0', 'db_version' => '003_seed.sql'],
]), 'modified');
remainingException(static fn () => new UpdateCheckRequestDto([
    ['code' => 'demo', 'version' => '1.0.0'],
]), '严格包含');
remainingException(static fn () => new UpdateCheckRequestDto([
    ['code' => 'demo', 'code_version' => '1.0.0', 'db_version' => '', 'modified' => false, 'extra' => true],
]), '严格包含');

$version = new PluginVersionDto(
    1,
    'demo',
    '2.0.0',
    'changes',
    true,
    ['funadmin' => '^7.0'],
    '>=7.0 <8.0',
    '2026-09-05T10:00:00+00:00',
    str_repeat('a', 64),
    'c2ln',
    'ed25519',
    1234,
    2,
    'funadmin-native-app-v1',
    str_repeat('b', 64),
    '003_seed.sql',
    ['app' => true, 'console' => true],
    ''
);
remainingExpect($version->manifestSchema === 2 && $version->treeHash === str_repeat('b', 64), '版本 DTO 必须携带 v3 制品契约');
remainingExpect($version->databaseCapability === '003_seed.sql' && $version->applications['console'], '版本 DTO 必须携带数据库及应用能力');
remainingException(static fn () => new PluginVersionDto(1, 'demo', '2.0.0', compatible: true), '不兼容');
remainingException(static fn () => new PluginVersionDto(
    1,
    'demo',
    '2.0.0',
    compatible: true,
    manifestSchema: 1,
    packageFormat: MarketplaceProtocol::PACKAGE_FORMAT,
    treeHash: str_repeat('b', 64),
    databaseCapability: '',
    applications: ['app' => true, 'console' => false]
), 'manifest schema');

$descriptorArguments = [
    'https://downloads.example.com/demo.zip',
    'demo',
    '2.0.0',
    str_repeat('a', 64),
    'c2ln',
    'ed25519',
    100,
    2,
    'funadmin-native-app-v1',
    str_repeat('b', 64),
    '003_seed.sql',
];
$descriptor = new DownloadDescriptorDto(...$descriptorArguments);
remainingExpect($descriptor->manifestSchema === 2 && $descriptor->packageFormat === MarketplaceProtocol::PACKAGE_FORMAT, '下载描述必须携带 v3 契约');
remainingException(static fn () => new DownloadDescriptorDto(...array_replace($descriptorArguments, [7 => 1])), 'manifest schema');
remainingException(static fn () => new DownloadDescriptorDto(...array_replace($descriptorArguments, [8 => 'zip-v1'])), 'package format');
remainingException(static fn () => new DownloadDescriptorDto(...array_replace($descriptorArguments, [9 => 'bad'])), 'tree hash');
remainingException(static fn () => new DownloadDescriptorDto(...array_replace($descriptorArguments, [5 => 'rsa-sha256'])), 'ed25519');
remainingException(static fn () => new DownloadDescriptorDto(...array_replace($descriptorArguments, [4 => ''])), '签名');
remainingException(static fn () => new DownloadDescriptorDto(...array_replace($descriptorArguments, [10 => '../bad.sql'])), '数据库能力');

$store = new RemainingMemorySessionStore();
$session = new CloudAccountSession($store, static fn (): int => 1_800_000_000);
$requests = [];
$adapter = new NativeMarketplaceAdapter(
    static function (string $endpoint, array $params, string $token) use (&$requests): array {
        $requests[] = [$endpoint, $params, $token];
        $version = [
            'id' => 11,
            'code' => 'demo',
            'code_version' => '2.0.0',
            'changelog' => 'v3',
            'compatible' => true,
            'requires' => ['funadmin' => '^7.0'],
            'compatible_range' => '>=7.0 <8.0',
            'published_at' => '2026-09-05T10:00:00+00:00',
            'sha256' => str_repeat('a', 64),
            'signature' => 'c2ln',
            'signature_algorithm' => 'ed25519',
            'size' => 100,
            'manifest_schema' => 2,
            'package_format' => 'funadmin-native-app-v1',
            'tree_hash' => str_repeat('b', 64),
            'database_capability' => '003_seed.sql',
            'applications' => ['app' => true, 'console' => true],
            'compatible_reason' => '',
        ];
        return match ($endpoint) {
            '/api/v3/auth/login' => ['code' => 200, 'data' => [
                'access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'expires_in' => 60,
                'account' => ['id' => 7, 'username' => 'demo', 'nickname' => 'Demo', 'avatar' => ''],
            ]],
            '/api/v3/auth/refresh' => ['code' => 200, 'data' => ['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => 120]],
            '/api/v3/plugins/categories' => ['code' => 200, 'data' => ['items' => [['id' => 3, 'name' => '工具']]]],
            '/api/v3/plugins' => ['code' => 200, 'data' => ['items' => [[
                'id' => 9, 'code' => 'demo', 'name' => '演示', 'description' => '', 'author' => '', 'versions' => [$version],
            ]], 'total' => 1, 'page' => 1, 'limit' => 20]],
            '/api/v3/plugins/demo' => ['code' => 200, 'data' => [
                'id' => 9, 'code' => 'demo', 'name' => '演示', 'description' => '', 'author' => '', 'versions' => [$version],
            ]],
            '/api/v3/plugins/demo/versions' => ['code' => 200, 'data' => ['items' => [$version]]],
            '/api/v3/plugins/check-updates' => ['code' => 200, 'data' => ['items' => [[
                'code' => 'demo', 'installed_version' => '1.0.0', 'latest_version' => '2.0.0', 'update_available' => true,
                'compatible' => true, 'database_compatible' => true, 'requires_manual_merge' => false, 'reason' => '',
            ]]]],
            '/api/v3/plugins/demo/authorize' => ['code' => 200, 'data' => ['code' => 'demo', 'code_version' => '2.0.0', 'authorized' => true, 'message' => '']],
            '/api/v3/plugins/demo/download' => ['code' => 200, 'data' => $version + ['url' => 'https://downloads.example.com/demo.zip']],
            default => throw new RuntimeException('unexpected endpoint ' . $endpoint),
        };
    },
    $session,
    '7.1.0',
    '8.1.30',
    static fn (): int => 1_800_000_000
);
$adapter->login(new LoginRequestDto('demo', 'secret'));
$adapter->refreshToken();
remainingExpect($requests[0] === ['/api/v3/auth/login', ['account' => 'demo', 'password' => 'secret'], ''], '登录必须使用 v3 endpoint 与严格字段');
remainingExpect($requests[1] === ['/api/v3/auth/refresh', ['refresh_token' => 'refresh-1'], ''], '刷新必须使用 v3 endpoint');
remainingExpect($adapter->categories()[0]->name === '工具', 'v3 分类响应必须严格映射');
remainingExpect($adapter->search(new MarketplaceSearchRequestDto('demo'))->items[0]->code === 'demo', 'v3 搜索响应必须严格映射');
remainingExpect($adapter->detail('demo')->versions[0]->manifestSchema === 2, 'v3 详情版本必须保留 manifest schema');
remainingExpect($adapter->versions('demo')[0]->packageFormat === MarketplaceProtocol::PACKAGE_FORMAT, 'v3 版本响应必须保留 package format');
$updates = $adapter->checkUpdates($requestDto);
remainingExpect($updates[0]->compatible && $updates[0]->databaseCompatible && !$updates[0]->requiresManualMerge, '更新结果必须保留兼容判定');
remainingExpect($requests[6][1]['installed'][0] === $requestDto->installed[0], '更新检查必须原样发送结构化 installed items');
$authorization = $adapter->authorize('demo', '2.0.0');
$download = $adapter->download('demo', '2.0.0');
remainingExpect($authorization->authorized && $download->treeHash === str_repeat('b', 64), '授权和下载必须使用 v3 严格响应');
foreach (array_slice($requests, 2) as [$endpoint, $params]) {
    remainingExpect(($params['manifest_schema'] ?? null) === 2, $endpoint . ' 必须发送 manifest_schema');
    remainingExpect(($params['package_format'] ?? null) === MarketplaceProtocol::PACKAGE_FORMAT, $endpoint . ' 必须发送 package_format');
    remainingExpect(($params['platform_version'] ?? null) === '7.1.0', $endpoint . ' 必须发送 platform_version');
    remainingExpect(($params['php_version'] ?? null) === '8.1.30', $endpoint . ' 必须发送 php_version');
}

if (!function_exists('sodium_crypto_sign_keypair')) {
    throw new RuntimeException('测试环境缺少 Sodium Ed25519');
}
$payload = 'signed-plugin-package';
$keypair = sodium_crypto_sign_keypair();
$secretKey = sodium_crypto_sign_secretkey($keypair);
$publicKey = base64_encode(sodium_crypto_sign_publickey($keypair));
$signingDescriptor = new DownloadDescriptorDto(
    'https://downloads.example.com/demo.zip',
    'demo',
    '2.0.0',
    hash('sha256', $payload),
    'pending',
    'ed25519',
    strlen($payload),
    2,
    'funadmin-native-app-v1',
    str_repeat('b', 64),
    '003_seed.sql'
);
$signature = base64_encode(sodium_crypto_sign_detached(
    PluginPackageDownloader::signaturePayload($signingDescriptor),
    $secretKey
));
$signedDescriptor = new DownloadDescriptorDto(
    $signingDescriptor->url,
    $signingDescriptor->code,
    $signingDescriptor->version,
    $signingDescriptor->sha256,
    $signature,
    $signingDescriptor->algorithm,
    $signingDescriptor->size,
    $signingDescriptor->manifestSchema,
    $signingDescriptor->packageFormat,
    $signingDescriptor->treeHash,
    $signingDescriptor->databaseCapability
);
$downloadRoot = sys_get_temp_dir() . '/funadmin-plugin-v3-' . bin2hex(random_bytes(4));
$downloader = new PluginPackageDownloader(
    $downloadRoot,
    static function (string $url, string $target) use ($payload): void { file_put_contents($target, $payload); },
    $publicKey,
    'require_signature'
);
$file = $downloader->download($signedDescriptor);
remainingExpect(is_file($file), 'Ed25519 规范化 metadata 签名必须验证成功');
$metadata = $downloader->verificationMetadata($signedDescriptor);
remainingExpect(
    ($metadata['manifest_schema'] ?? null) === 2
    && ($metadata['package_format'] ?? '') === MarketplaceProtocol::PACKAGE_FORMAT
    && ($metadata['tree_hash'] ?? '') === str_repeat('b', 64)
    && ($metadata['database_capability'] ?? '') === '003_seed.sql',
    'verification metadata 必须包含完整 v3 契约'
);
$downloader->delete($file);
$tamperedTree = new DownloadDescriptorDto(
    $signedDescriptor->url,
    $signedDescriptor->code,
    $signedDescriptor->version,
    $signedDescriptor->sha256,
    $signedDescriptor->signature,
    $signedDescriptor->algorithm,
    $signedDescriptor->size,
    $signedDescriptor->manifestSchema,
    $signedDescriptor->packageFormat,
    str_repeat('c', 64),
    $signedDescriptor->databaseCapability
);
remainingException(static fn () => $downloader->download($tamperedTree), '签名验证失败');
@rmdir($downloadRoot);

$badAdapter = new NativeMarketplaceAdapter(
    static fn (): array => ['code' => 200, 'data' => ['items' => [[
        'id' => 1,
        'code' => 'demo',
        'code_version' => '2.0.0',
        'changelog' => '',
        'compatible' => true,
        'requires' => [],
        'compatible_range' => '',
        'published_at' => '',
        'sha256' => str_repeat('a', 64),
        'signature' => 'c2ln',
        'signature_algorithm' => 'ed25519',
        'size' => 100,
        'package_format' => 'funadmin-native-app-v1',
        'tree_hash' => str_repeat('b', 64),
        'database_capability' => '',
        'applications' => ['app' => true, 'console' => true],
        'compatible_reason' => '',
    ]]]],
    $session,
    '7.1.0',
    '8.1.30'
);
remainingException(static fn () => $badAdapter->versions('demo'), 'manifest_schema');

$systemPluginSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/system/SystemPlugin.php');
remainingExpect(str_contains($systemPluginSource, 'new UpdateCheckRequestDto($installed)'), 'SystemPlugin 必须在 Controller 边界构造严格更新请求 DTO');

remainingExpect(!is_file(dirname(__DIR__) . '/app/common/plugin/marketplace/LegacyCloudMarketplaceAdapter.php'), 'LegacyCloudMarketplaceAdapter 必须删除');
$production = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/app', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $production .= file_get_contents($file->getPathname());
    }
}
remainingExpect(!str_contains($production, '/api/v2') && !str_contains($production, 'LegacyCloudMarketplaceAdapter'), '生产代码不得保留 v2 endpoint 或 Legacy adapter 引用');

$openApiFile = dirname(__DIR__) . '/docs/openapi/plugin-marketplace-v3.yaml';
remainingExpect(is_file($openApiFile), '必须提供插件市场服务端 v3 OpenAPI 契约');
$openApi = (string) file_get_contents($openApiFile);
foreach ([
    '/api/v3/auth/login:',
    '/api/v3/auth/refresh:',
    '/api/v3/plugins/categories:',
    '/api/v3/plugins:',
    '/api/v3/plugins/{code}:',
    '/api/v3/plugins/{code}/versions:',
    '/api/v3/plugins/check-updates:',
    '/api/v3/plugins/{code}/authorize:',
    '/api/v3/plugins/{code}/download:',
] as $path) {
    remainingExpect(str_contains($openApi, $path), 'OpenAPI 缺少路径 ' . $path);
}
foreach ([
    'MarketplaceContext:',
    'UpdateCheckRequest:',
    'PluginVersion:',
    'DownloadDescriptor:',
    'manifest_schema:',
    'package_format:',
    'tree_hash:',
    'database_capability:',
    'signature_algorithm:',
] as $contract) {
    remainingExpect(str_contains($openApi, $contract), 'OpenAPI 缺少契约 ' . $contract);
}
remainingExpect(str_contains($openApi, 'openapi: 3.1.0'), 'OpenAPI 契约版本必须为 3.1.0');
remainingExpect(str_contains($openApi, 'const: 2'), 'OpenAPI 必须固定 manifest_schema=2');
remainingExpect(str_contains($openApi, 'const: funadmin-native-app-v1'), 'OpenAPI 必须固定原生 package_format');
remainingExpect(str_contains($openApi, 'const: ed25519'), 'OpenAPI 必须固定 Ed25519 签名算法');
remainingExpect(substr_count($openApi, 'unevaluatedProperties: false') >= 4, 'OpenAPI 复合对象必须在最终 schema 禁止未评估字段');
remainingExpect(!preg_match('/UpdateCheckRequest:\s+[\s\S]*?additionalProperties: false[\s\S]*?required: \[installed\]/', $openApi), 'UpdateCheckRequest 不得在 allOf 子 schema 提前封闭字段');
remainingExpect(!preg_match('/VersionOperationRequest:\s+[\s\S]*?additionalProperties: false[\s\S]*?required: \[code, code_version, db_version\]/', $openApi), 'VersionOperationRequest 不得在 allOf 子 schema 提前封闭字段');
remainingExpect(str_contains($openApi, "pattern: '^(?:|\\d+[A-Za-z0-9._-]*\\.sql)$'"), 'OpenAPI 数据库能力必须与客户端数字前缀规则一致');

echo "plugin marketplace remaining tests: PASS\n";
