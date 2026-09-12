<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\marketplace\CloudAccountSession;
use app\common\plugin\marketplace\NativeMarketplaceAdapter;
use app\common\plugin\package\GuzzlePackageStreamDownloader;
use app\common\plugin\package\PluginPackageDownloader;
use app\console\plugin\service\PluginMarketplaceService;
use app\console\plugin\service\PluginService;
use app\common\plugin\sdk\Manifest;
use GuzzleHttp\Psr7\Response;
use app\console\plugin\service\PluginPackagePipeline;
use app\common\plugin\marketplace\PluginMarketplaceGateway;
use app\common\plugin\marketplace\SessionStore;
use app\common\plugin\marketplace\dto\AuthorizationDto;
use app\common\plugin\marketplace\dto\CategoryDto;
use app\common\plugin\marketplace\dto\CloudAccountDto;
use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use app\common\plugin\marketplace\dto\LoginRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceProtocol;
use app\common\plugin\marketplace\dto\MarketplaceSearchResultDto;
use app\common\plugin\marketplace\dto\PluginDetailDto;
use app\common\plugin\marketplace\dto\PluginVersionDto;
use app\common\plugin\marketplace\dto\UpdateCheckDto;

function phase2Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase2Exception(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        phase2Expect(str_contains($exception->getMessage(), $contains), '异常信息不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期抛出异常：' . $contains);
}

phase2Expect(interface_exists(PluginMarketplaceGateway::class), '必须定义 PluginMarketplaceGateway 契约');
$methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(PluginMarketplaceGateway::class))->getMethods());
phase2Expect($methods === ['login', 'refreshToken', 'logout', 'currentAccount', 'categories', 'search', 'detail', 'versions', 'checkUpdates', 'authorize', 'download'], 'Gateway 方法必须明确且完整');

$login = new LoginRequestDto('demo@example.com', 'secret');
phase2Expect($login->account === 'demo@example.com' && $login->password === 'secret', '登录请求 DTO 必须不可变承载凭据');
$search = new MarketplaceSearchRequestDto('cache', 2, 20, 3, '7.1.0');
phase2Expect($search->keyword === 'cache' && $search->page === 2 && $search->limit === 20, '搜索请求 DTO 字段错误');
phase2Exception(static fn () => new MarketplaceSearchRequestDto('', 0, 20, null, '7.1.0'), '页码');

$account = new CloudAccountDto(7, 'demo', '演示', '/avatar.png');
$category = new CategoryDto(3, '工具');
$version = new PluginVersionDto(
    11,
    'demo',
    '1.2.0',
    '更新说明',
    true,
    manifestSchema: 2,
    packageFormat: MarketplaceProtocol::PACKAGE_FORMAT,
    treeHash: str_repeat('b', 64),
    applications: ['app' => true, 'console' => true]
);
$detail = new PluginDetailDto(9, 'demo', '演示插件', '说明', '作者', [$version]);
$result = new MarketplaceSearchResultDto([$detail], 1, 1, 20);
$update = new UpdateCheckDto('demo', '1.0.0', '1.2.0', true, true, true, false, '');
$authorization = new AuthorizationDto('demo', '1.2.0', true, 'authorized');
phase2Expect($account->id === 7 && $category->id === 3 && $result->total === 1, '市场响应 DTO 字段错误');
phase2Expect($update->updateAvailable && $authorization->authorized, '更新与授权 DTO 字段错误');

$descriptor = new DownloadDescriptorDto(
    'https://downloads.example.com/demo.zip',
    'demo',
    '1.2.0',
    str_repeat('a', 64),
    'c2lnbmF0dXJl',
    'ed25519',
    1024,
    2,
    MarketplaceProtocol::PACKAGE_FORMAT,
    str_repeat('b', 64),
    ''
);
phase2Expect($descriptor->size === 1024 && $descriptor->algorithm === 'ed25519', '下载描述 DTO 字段错误');
phase2Exception(static fn () => new DownloadDescriptorDto('file:///tmp/demo.zip', 'demo', '1.2.0', str_repeat('a', 64), 'c2ln', 'ed25519', 1, 2, MarketplaceProtocol::PACKAGE_FORMAT, str_repeat('b', 64), ''), 'http/https');
phase2Exception(static fn () => new DownloadDescriptorDto('https://example.com/demo.zip', 'demo', '1.2.0', 'bad', 'c2ln', 'ed25519', 1, 2, MarketplaceProtocol::PACKAGE_FORMAT, str_repeat('b', 64), ''), 'SHA-256');
phase2Exception(static fn () => new DownloadDescriptorDto('http://127.0.0.1/demo.zip', 'demo', '1.2.0', str_repeat('a', 64), 'c2ln', 'ed25519', 1, 2, MarketplaceProtocol::PACKAGE_FORMAT, str_repeat('b', 64), ''), '禁止');

$dtoReflection = new ReflectionClass(DownloadDescriptorDto::class);
foreach ($dtoReflection->getProperties() as $property) {
    phase2Expect($property->isReadOnly(), '下载描述 DTO 属性必须 readonly：' . $property->getName());
}

final class MemorySessionStore implements SessionStore
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

$sessionStore = new MemorySessionStore();
$cloudSession = new CloudAccountSession($sessionStore);
$cloudSession->login($account, 'secret-token');
phase2Expect($sessionStore->values['plugin_marketplace']['account'] === $account->toSession(), '会话只能保存必要账号字段');
phase2Expect($sessionStore->values['plugin_marketplace']['access_token'] === 'secret-token', 'token 必须仅保存于 server session');
phase2Expect(!isset($sessionStore->values['plugin_marketplace']['password']), '会话不得保存密码');
phase2Expect($cloudSession->account()?->username === 'demo', '应从会话恢复账号 DTO');
phase2Expect($cloudSession->token() === 'secret-token', '应从会话读取 token');
$cloudSession->logout();
phase2Expect($cloudSession->account() === null && $cloudSession->token() === '', '登出必须完整清除云会话');

$root = dirname(__DIR__);
phase2Expect(!is_file($root . '/app/common/service/AuthCloudService.php'), '无引用 AuthCloudService 旧旁路必须删除');
phase2Expect(!is_file($root . '/app/common/plugin/sdk/command/Config.php'), 'plugins:config 旧命令必须删除');
phase2Expect(!is_file($root . '/app/common/plugin/sdk/config.php'), '插件旧配置副本必须删除');

$productionSources = [
    $root . '/app',
    $root . '/config',
    $root . '/extend',
    $root . '/composer.json',
];
foreach ($productionSources as $productionSource) {
    $files = is_dir($productionSource)
        ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($productionSource, FilesystemIterator::SKIP_DOTS))
        : [$productionSource];
    foreach ($files as $file) {
        $path = (string) $file;
        if (is_dir($path) || (!str_ends_with($path, '.php') && !str_ends_with($path, '.json'))) {
            continue;
        }
        $source = (string) file_get_contents($path);
        phase2Expect(!str_contains($source, 'AuthCloudService'), '生产源码不得引用 AuthCloudService：' . $path);
        phase2Expect(!str_contains($source, 'plugins:config'), '生产源码不得注册 plugins:config：' . $path);
    }
}

phase2Expect(class_exists(NativeMarketplaceAdapter::class), '必须提供 NativeMarketplaceAdapter');
phase2Expect(!is_file($root . '/app/common/plugin/marketplace/LegacyCloudMarketplaceAdapter.php'), '旧云 adapter 必须删除');
$adapterSource = (string) file_get_contents($root . '/app/common/plugin/marketplace/NativeMarketplaceAdapter.php');
phase2Expect(str_contains($adapterSource, '/api/v3/plugins') && !str_contains($adapterSource, '/api/v2'), 'Native adapter 必须硬切 v3 endpoint');

$downloadRoot = sys_get_temp_dir() . '/funadmin-plugin-download-' . bin2hex(random_bytes(4));
$payload = 'zip-content';
$downloadDescriptor = new DownloadDescriptorDto(
    'https://example.com/demo.zip',
    'demo',
    '1.2.0',
    hash('sha256', $payload),
    'dGVzdA==',
    'ed25519',
    strlen($payload),
    2,
    MarketplaceProtocol::PACKAGE_FORMAT,
    str_repeat('b', 64),
    ''
);
$downloader = new PluginPackageDownloader(
    $downloadRoot,
    static function (string $url, string $target, array $options) use ($payload): void {
        phase2Expect($options['max_bytes'] === 104857600, '下载必须限制 100MB');
        phase2Expect($options['max_redirects'] === 3 && $options['protocols'] === ['https'], '重定向必须限制次数且生产下载仅允许 HTTPS');
        file_put_contents($target, $payload);
    },
    null,
    'allow_unsigned'
);
$downloaded = $downloader->download($downloadDescriptor);
phase2Expect(is_file($downloaded) && hash_file('sha256', $downloaded) === $downloadDescriptor->sha256, '必须校验实际 SHA-256');
unlink($downloaded);
phase2Exception(static fn () => (new PluginPackageDownloader($downloadRoot, static function (string $url, string $target): void {
    file_put_contents($target, 'bad-content');
}, null, 'allow_unsigned'))->download($downloadDescriptor), 'SHA-256');
phase2Exception(static fn () => new PluginPackageDownloader($downloadRoot, static function (): void {}, null, 'require_signature'), '公钥');
$missingKeyDownloader = new PluginPackageDownloader($downloadRoot, static function (): void {}, null, 'reject_unsigned');
phase2Exception(static fn () => $missingKeyDownloader->assertCloudInstallationAllowed(), '未配置市场公钥');
$rawPublicKey = random_bytes(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
$encodedPublicKey = base64_encode($rawPublicKey);
putenv('PLUGIN_MARKETPLACE_PUBLIC_KEY=  ' . $encodedPublicKey . '  ');
$pluginConfig = require dirname(__DIR__) . '/config/plugins.php';
phase2Expect(
    ($pluginConfig['marketplace']['public_key'] ?? '') === $encodedPublicKey,
    'PLUGIN_MARKETPLACE_PUBLIC_KEY 必须只 trim Base64 Ed25519 原始公钥'
);
putenv('PLUGIN_MARKETPLACE_PUBLIC_KEY=-----BEGIN PUBLIC KEY-----\\nline-data\\n-----END PUBLIC KEY-----');
$invalidPluginConfig = require dirname(__DIR__) . '/config/plugins.php';
phase2Expect(($invalidPluginConfig['marketplace']['public_key'] ?? 'invalid') === '', 'PEM 公钥与转义换行必须拒绝');
putenv('PLUGIN_MARKETPLACE_PUBLIC_KEY=' . base64_encode('short'));
$invalidPluginConfig = require dirname(__DIR__) . '/config/plugins.php';
phase2Expect(($invalidPluginConfig['marketplace']['public_key'] ?? 'invalid') === '', '非 32 字节 Ed25519 原始公钥必须拒绝');
putenv('PLUGIN_MARKETPLACE_PUBLIC_KEY');
phase2Expect(array_keys($pluginConfig) === ['marketplace'], '插件配置只能保留 marketplace 新配置');
phase2Expect(($pluginConfig['marketplace']['unsigned_policy'] ?? '') === 'reject_unsigned', '市场默认必须拒绝未签名包');
foreach (['request_timeout', 'connect_timeout', 'max_redirects', 'max_package_bytes'] as $key) {
    phase2Expect(isset($pluginConfig['marketplace'][$key]), '市场安全配置缺失：' . $key);
}
foreach (['api_domain', 'api_url', 'cloud_account_key', 'cloud_access_toke_key'] as $legacyKey) {
    phase2Expect(!array_key_exists($legacyKey, $pluginConfig), '插件配置不得保留旧配置键：' . $legacyKey);
    phase2Expect(!array_key_exists($legacyKey, $pluginConfig['marketplace']), '市场配置不得保留旧配置键：' . $legacyKey);
}
$envExample = (string) file_get_contents(dirname(__DIR__) . '/.env.example');
phase2Expect(str_contains($envExample, 'PLUGIN_MARKETPLACE_PUBLIC_KEY ='), '.env.example 必须声明市场 Ed25519 公钥变量');
phase2Expect(str_contains($envExample, 'Base64') && str_contains($envExample, '32 字节'), '.env.example 必须说明公钥编码与长度且不得提供真实密钥');

$downloadSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/plugin/package/PluginPackageDownloader.php');
phase2Expect(!str_contains($downloadSource, 'public_path'), '云下载临时包不得进入 public 中转');
$pipelineSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/plugin/service/PluginPackagePipeline.php');
$fixtureManifest = Manifest::fromDirectory(dirname(__DIR__) . '/tests/fixtures/plugins/example');
$pluginServiceReflection = new ReflectionClass(PluginService::class);
$pluginService = $pluginServiceReflection->newInstanceWithoutConstructor();
$manifestInfoMethod = $pluginServiceReflection->getMethod('manifestInfo');
$manifestInfoMethod->setAccessible(true);
$persistedManifest = $manifestInfoMethod->invoke($pluginService, $fixtureManifest)['manifest'] ?? null;
phase2Expect(is_string($persistedManifest), 'PluginService 写入 fun_plugin.manifest 必须始终为 JSON 字符串');
$persistedManifestData = json_decode($persistedManifest, true, 512, JSON_THROW_ON_ERROR);
phase2Expect(($persistedManifestData['code'] ?? '') === 'example' && ($persistedManifestData['name'] ?? '') !== '', 'fun_plugin.manifest JSON 字符串必须保留 code/name');
phase2Expect(
    strpos($pipelineSource, 'assertCloudInstallationAllowed') < strpos($pipelineSource, '$gateway->authorize'),
    'reject_unsigned 公钥校验必须发生在云市场请求前'
);

final class FakePackageOperations
{
    public array $calls = [];
    public bool $failFinish = false;
    public bool $failDiscard = false;
    public array $stageFailures = [];
    public mixed $observer = null;

    public function inspect(string $archive, string $expectedCode = '', string $expectedVersion = ''): array
    {
        $code = $expectedCode ?: 'demo';
        return [
            'code' => $code,
            'version' => $expectedVersion ?: '1.2.0',
            'manifest' => ['code' => $code, 'name' => '演示插件', 'version' => $expectedVersion ?: '1.2.0'],
        ];
    }

    public function stage(string $archive, string $expectedCode = '', string $expectedVersion = ''): array
    {
        $this->calls[] = ['stage', $archive, $expectedCode, $expectedVersion];
        if (is_callable($this->observer)) {
            ($this->observer)('stage');
        }
        if ($this->stageFailures !== []) {
            throw new RuntimeException((string) array_shift($this->stageFailures));
        }
        return ['stage_directory' => '/stage', 'plugin_directory' => '/stage/demo', 'code' => 'demo', 'version' => '1.2.0'];
    }

    public function deploy(array $staged, string $code): ?string
    {
        $this->calls[] = ['deploy', $code];
        if (is_callable($this->observer)) {
            ($this->observer)('deploy');
        }
        return '/backup';
    }

    public function finish(array $staged, ?string $backup): void
    {
        $this->calls[] = ['finish', $backup];
        if ($this->failFinish) {
            throw new RuntimeException('cleanup failed: /stage, /backup');
        }
    }

    public function rollback(string $code, ?string $backup): void
    {
        $this->calls[] = ['rollback', $code];
    }

    public function discard(array $staged): void
    {
        $this->calls[] = ['discard'];
        if ($this->failDiscard) {
            throw new RuntimeException('discard failed: /stage');
        }
    }
}

$localArchive = tempnam(sys_get_temp_dir(), 'plugin-local-');
file_put_contents($localArchive, 'local-package');
$operations = new FakePackageOperations();
$lifecycleCalls = [];
$pipeline = new PluginPackagePipeline(
    $operations,
    static function (string $operation, string $code, bool $migrate) use (&$lifecycleCalls): bool {
        $lifecycleCalls[] = [$operation, $code, $migrate];
        return true;
    },
    static fn (): bool => true
);
$localResult = $pipeline->installLocal($localArchive);
phase2Expect($localResult['source'] === 'local' && $localResult['package_hash'] !== '', '本地包必须进入统一 pipeline 并记录 hash');
phase2Expect(($localResult['rebuildRequired'] ?? false) === true, '安装插件源码后响应必须要求重新构建 Admin Web');
phase2Expect($lifecycleCalls[0] === ['install', 'demo', true], 'pipeline 必须调用 PluginService install 能力');
$capturedPackageContext = [];
$contextPipeline = new PluginPackagePipeline(
    new FakePackageOperations(),
    static fn (): bool => true,
    static fn (): bool => true,
    null,
    null,
    static function (string $operation, string $code, callable $execute, array $context) use (&$capturedPackageContext): mixed {
        $capturedPackageContext = $context;
        return $execute('', null, $context);
    }
);
$contextPipeline->installLocal($localArchive);
phase2Expect(!array_key_exists('manifest', $capturedPackageContext), 'package context 不得用数组 manifest 覆盖 PluginService JSON manifest');
phase2Expect(($capturedPackageContext['manifest_data']['code'] ?? '') === 'demo', 'package context 必须以 manifest_data 传递原始清单 code');
phase2Expect(array_column($operations->calls, 0) === ['stage', 'deploy', 'finish'], '成功 pipeline 顺序必须为 stage/deploy/lifecycle/finish');

$orderedEvents = [];
$orderedOperations = new FakePackageOperations();
$orderedOperations->observer = static function (string $event) use (&$orderedEvents): void { $orderedEvents[] = $event; };
$orderedPipeline = new PluginPackagePipeline(
    $orderedOperations,
    static function () use (&$orderedEvents): bool {
        $orderedEvents[] = 'lifecycle';
        return true;
    },
    static fn (): bool => true,
    null,
    null,
    static function (string $operation, string $code, callable $execute) use (&$orderedEvents): mixed {
        $orderedEvents[] = 'lock';
        $orderedEvents[] = 'state updating';
        return $execute('1.0.0', static function (string $phase) use (&$orderedEvents): void {
            $orderedEvents[] = $phase . ' audit';
        });
    }
);
$updateResult = $orderedPipeline->updateLocal($localArchive, 'demo');
phase2Expect(($updateResult['rebuildRequired'] ?? false) === true, '升级插件源码后响应必须要求重新构建 Admin Web');
phase2Expect(
    $orderedEvents === ['lock', 'state updating', 'stage', 'validate audit', 'deploy', 'deploy audit', 'lifecycle'],
    '同名包操作必须先持锁并进入 updating，再按真实 stage/validate/deploy 顺序审计'
);

$rejectedOperations = new FakePackageOperations();
$rejectedPipeline = new PluginPackagePipeline(
    $rejectedOperations,
    static fn (): bool => true,
    static fn (): bool => true,
    null,
    null,
    static function (): never { throw new RuntimeException('插件正在执行生命周期操作：demo'); }
);
phase2Exception(static fn () => $rejectedPipeline->updateLocal($localArchive, 'demo'), '正在执行');
phase2Expect($rejectedOperations->calls === [], '同名并发包被锁拒绝后不得 stage 或 deploy');

$preflightCases = [
    '坏 manifest' => ['operation' => 'install', 'message' => 'plugin.json schema 校验失败', 'initial' => ['exists' => false, 'attributes' => []]],
    '坏 channel' => ['operation' => 'update', 'message' => 'channel 路由文件返回值无效', 'initial' => ['exists' => true, 'attributes' => ['code' => 'demo', 'status' => 0, 'lifecycle_state' => 'disabled', 'version' => '1.0.0']]],
];
foreach ($preflightCases as $caseName => $case) {
    $preflightState = $case['initial'];
    $preflightAttempts = 0;
    $preflightOperations = new FakePackageOperations();
    $preflightOperations->stageFailures[] = $case['message'];
    $preflightPipeline = new PluginPackagePipeline(
        $preflightOperations,
        static function () use (&$preflightAttempts, &$preflightState): bool {
            $preflightAttempts++;
            $preflightState['attributes']['lifecycle_state'] = 'disabled';
            return true;
        },
        static fn (): bool => false,
        null,
        null,
        static function (string $operation, string $code, callable $execute, array $context) use (&$preflightState): mixed {
            $context['pre_operation_state'] = $preflightState;
            $preflightState = ['exists' => true, 'attributes' => ['code' => $code, 'status' => 0, 'lifecycle_state' => $operation === 'install' ? 'installing' : 'updating']];
            return $execute((string) ($context['pre_operation_state']['attributes']['version'] ?? ''), null, $context);
        },
        static function (string $code, array $snapshot) use (&$preflightState): void {
            $preflightState = $snapshot;
        }
    );
    $runPreflight = static fn (): array => $case['operation'] === 'install'
        ? $preflightPipeline->installLocal($localArchive)
        : $preflightPipeline->updateLocal($localArchive, 'demo');
    phase2Exception($runPreflight, $case['message']);
    phase2Expect($preflightState === $case['initial'], $caseName . '预检失败必须恢复 beginOperation 前状态');
    phase2Expect(!in_array('deploy', array_column($preflightOperations->calls, 0), true), $caseName . '预检失败不得部署目录');
    $preflightRetry = $runPreflight();
    phase2Expect($preflightRetry['version'] === '1.2.0' && $preflightAttempts === 1, $caseName . '状态恢复后必须可重试');
}

$downgradeOperations = new FakePackageOperations();
$downgradeLifecycleCalled = false;
$downgradeGuardAttempts = 0;
$downgradeState = ['exists' => true, 'attributes' => ['code' => 'demo', 'status' => 0, 'lifecycle_state' => 'disabled', 'version' => '1.3.0']];
$downgradePipeline = new PluginPackagePipeline(
    $downgradeOperations,
    static function () use (&$downgradeLifecycleCalled, &$downgradeState): bool {
        $downgradeLifecycleCalled = true;
        $downgradeState['attributes']['lifecycle_state'] = 'disabled';
        return true;
    },
    static fn (): bool => true,
    null,
    null,
    static function (string $operation, string $code, callable $execute, array $context) use (&$downgradeState): mixed {
        $context['pre_operation_state'] = $downgradeState;
        $downgradeState['attributes']['lifecycle_state'] = 'updating';
        return $execute('1.3.0', null, $context);
    },
    static function (string $code, array $snapshot) use (&$downgradeState): void {
        $downgradeState = $snapshot;
    },
    static function (string $code, string $version) use (&$downgradeGuardAttempts): void {
        $downgradeGuardAttempts++;
        if ($downgradeGuardAttempts === 1) {
            throw new RuntimeException("禁止降级部署：{$code} {$version} 的数据库能力不足");
        }
    }
);
phase2Exception(
    static fn () => $downgradePipeline->redeployHistory($localArchive, 'demo', '1.2.0'),
    '禁止降级部署'
);
phase2Expect(array_column($downgradeOperations->calls, 0) === ['stage', 'discard'], '历史重部署降级门禁必须发生在 deploy 前并清理暂存目录');
phase2Expect(!$downgradeLifecycleCalled, '历史重部署数据库降级被拒绝后不得进入生命周期');
phase2Expect($downgradeState['attributes']['lifecycle_state'] === 'disabled', '降级 guard 失败必须恢复原 disabled 状态');
$downgradeRetry = $downgradePipeline->redeployHistory($localArchive, 'demo', '1.2.0');
phase2Expect($downgradeRetry['version'] === '1.2.0' && $downgradeLifecycleCalled, '降级 guard 状态恢复后必须可重试');

$failedOperations = new FakePackageOperations();
$failedHistoryErrors = [];
$failedPipeline = new PluginPackagePipeline(
    $failedOperations,
    static function (): bool { throw new RuntimeException('migration failed'); },
    static fn (): bool => false,
    static function (): void { throw new RuntimeException('history failed'); },
    static function (string $message) use (&$failedHistoryErrors): void { $failedHistoryErrors[] = $message; }
);
phase2Exception(static fn () => $failedPipeline->updateLocal($localArchive, 'demo'), 'migration failed');
phase2Expect(array_column($failedOperations->calls, 0) === ['stage', 'deploy', 'discard'], '不可回滚失败只能清理 stage，必须保留 backup 供人工恢复');
phase2Expect(str_contains(implode("\n", $failedHistoryErrors), '/backup'), '不可回滚失败必须记录明确的备份恢复路径');
phase2Expect($failedHistoryErrors !== [], '历史写入失败必须独立记录日志');

$restoredSnapshots = [];
$updateAttempts = 0;
$databaseState = [
    'exists' => true,
    'attributes' => [
        'code' => 'demo',
        'version' => '1.0.0',
        'code_version' => '1.0.0',
        'status' => 0,
        'lifecycle_state' => 'disabled',
        'manifest' => '{"version":"1.0.0"}',
        'migration_pending' => 0,
    ],
];
$rollbackOperations = new FakePackageOperations();
$rollbackPipeline = new PluginPackagePipeline(
    $rollbackOperations,
    static function () use (&$updateAttempts, &$databaseState): bool {
        $updateAttempts++;
        $databaseState['attributes'] = array_merge($databaseState['attributes'], [
            'version' => '1.2.0',
            'code_version' => '1.2.0',
            'lifecycle_state' => 'updating',
            'manifest' => '{"version":"1.2.0"}',
            'migration_pending' => 1,
        ]);
        if ($updateAttempts === 1) {
            throw new RuntimeException('migration failed after deploy');
        }
        $databaseState['attributes']['lifecycle_state'] = 'disabled';
        return true;
    },
    static fn (): bool => true,
    null,
    null,
    static function (string $operation, string $code, callable $execute, array $context) use (&$databaseState): mixed {
        $context['pre_operation_state'] = $databaseState;
        $databaseState['attributes']['lifecycle_state'] = 'updating';
        return $execute('1.0.0', null, $context);
    },
    static function (string $code, array $snapshot) use (&$restoredSnapshots, &$databaseState): void {
        $restoredSnapshots[] = [$code, $snapshot];
        $databaseState = $snapshot;
    }
);
phase2Exception(static fn () => $rollbackPipeline->updateLocal($localArchive, 'demo'), 'migration failed after deploy');
phase2Expect(array_column($rollbackOperations->calls, 0) === ['stage', 'deploy', 'rollback', 'discard'], '可回滚更新失败必须恢复目录并清理 stage');
phase2Expect($databaseState['attributes']['lifecycle_state'] === 'disabled', '更新回滚后不得残留 updating');
phase2Expect($databaseState['attributes']['version'] === '1.0.0', '更新回滚后必须恢复原 version');
phase2Expect($databaseState['attributes']['code_version'] === '1.0.0', '更新回滚后必须恢复原 code_version');
phase2Expect($databaseState['attributes']['manifest'] === '{"version":"1.0.0"}', '更新回滚后必须恢复原 manifest');
phase2Expect($databaseState['attributes']['migration_pending'] === 0, '更新回滚后必须恢复原 migration_pending');
$retryResult = $rollbackPipeline->updateLocal($localArchive, 'demo');
phase2Expect($retryResult['version'] === '1.2.0' && $updateAttempts === 2, '更新回滚恢复原态后必须可重试');

$installAttempts = 0;
$installState = ['exists' => false, 'attributes' => []];
$installRollbackOperations = new FakePackageOperations();
$installRollbackPipeline = new PluginPackagePipeline(
    $installRollbackOperations,
    static function () use (&$installAttempts, &$installState): bool {
        $installAttempts++;
        $installState = [
            'exists' => true,
            'attributes' => [
                'code' => 'demo',
                'version' => '1.2.0',
                'lifecycle_state' => 'installing',
                'migration_pending' => 1,
            ],
        ];
        if ($installAttempts === 1) {
            throw new RuntimeException('migration failed after install deploy');
        }
        $installState['attributes']['lifecycle_state'] = 'disabled';
        return true;
    },
    static fn (): bool => true,
    null,
    null,
    static function (string $operation, string $code, callable $execute, array $context) use (&$installState): mixed {
        $context['pre_operation_state'] = $installState;
        $installState = [
            'exists' => true,
            'attributes' => ['code' => $code, 'lifecycle_state' => 'installing'],
        ];
        return $execute('', null, $context);
    },
    static function (string $code, array $snapshot) use (&$installState): void {
        $installState = $snapshot;
    }
);
phase2Exception(static fn () => $installRollbackPipeline->installLocal($localArchive), 'migration failed after install deploy');
phase2Expect($installState === ['exists' => false, 'attributes' => []], '首次安装回滚必须删除占位记录并恢复无记录');
$installRetryResult = $installRollbackPipeline->installLocal($localArchive);
phase2Expect($installRetryResult['version'] === '1.2.0' && $installAttempts === 2, '首次安装回滚恢复无记录后必须可重试');

$historyFailureOperations = new FakePackageOperations();
$historyFailurePipeline = new PluginPackagePipeline(
    $historyFailureOperations,
    static fn (): bool => true,
    static fn (): bool => true,
    static function (): void { throw new RuntimeException('history failed'); },
    static function (string $message) use (&$failedHistoryErrors): void { $failedHistoryErrors[] = $message; }
);
$historyResult = $historyFailurePipeline->installLocal($localArchive);
phase2Expect($historyResult['code'] === 'demo', '部署成功后历史失败不得覆盖主结果');
phase2Expect(array_column($historyFailureOperations->calls, 0) === ['stage', 'deploy', 'finish'], '历史失败不得触发已完成部署回滚或删除插件');
$cleanupFailureOperations = new FakePackageOperations();
$cleanupFailureOperations->failFinish = true;
$cleanupHistory = [];
$cleanupPipeline = new PluginPackagePipeline(
    $cleanupFailureOperations,
    static fn (): bool => true,
    static fn (): bool => false,
    static function (array $data) use (&$cleanupHistory): void { $cleanupHistory[] = $data; },
    static function (): void {}
);
$cleanupResult = $cleanupPipeline->installLocal($localArchive);
phase2Expect(
    ($cleanupResult['warnings'][0] ?? '') === 'cleanup failed: /stage, /backup',
    '主安装成功但 finish 失败时必须返回含可定位路径的 warnings'
);
phase2Expect(
    ($cleanupHistory[0]['status'] ?? '') === 'warning'
    && ($cleanupHistory[0]['error'] ?? '') === 'cleanup failed: /stage, /backup',
    '清理失败必须写入 operation warning/error，不能记录为完全成功'
);
$originalFailureOperations = new FakePackageOperations();
$originalFailureOperations->failFinish = true;
$originalFailureLogs = [];
$originalFailureHistory = [];
$originalFailurePipeline = new PluginPackagePipeline(
    $originalFailureOperations,
    static function (): bool { throw new RuntimeException('original migration failed'); },
    static fn (): bool => false,
    static function (array $row) use (&$originalFailureHistory): void { $originalFailureHistory[] = $row; },
    static function (string $message) use (&$originalFailureLogs): void { $originalFailureLogs[] = $message; }
);
phase2Exception(static fn () => $originalFailurePipeline->updateLocal($localArchive, 'demo'), 'original migration failed');
phase2Expect(
    str_contains(implode('\n', $originalFailureLogs), '/backup')
    && str_contains(implode('\n', $originalFailureLogs), 'original migration failed'),
    '不可回滚失败必须保留 backup 恢复路径且不得覆盖原异常'
);
phase2Expect(($originalFailureHistory[0]['recovery_path'] ?? '') === '/backup', '不可回滚失败历史必须保留 recovery_path');
unlink($localArchive);

$packageSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/plugin/service/PluginPackageService.php');
phase2Expect(!str_contains($packageSource, "'plugin.ini'") && str_contains($packageSource, "'plugin.json'"), 'PluginPackageService 必须只认 plugin.json');
phase2Expect(str_contains($packageSource, "'version' => \$manifest->version()"), 'stage 结果必须携带已校验的 manifest version');
phase2Expect(str_contains($packageSource, 'expectedVersion') && str_contains($packageSource, '版本与请求版本不一致'), 'stage 必须严格校验 expectedVersion');
phase2Expect(!str_contains($packageSource, '@rmdir') && !str_contains($packageSource, '@unlink'), 'stage/backup 清理不得忽略删除结果');

$marketplaceMethods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(PluginMarketplaceService::class))->getMethods(ReflectionMethod::IS_PUBLIC));
foreach (['login', 'refreshToken', 'logout', 'currentAccount', 'categories', 'search', 'detail', 'versions', 'checkUpdates', 'installCloud', 'updateCloud', 'installLocal', 'updateLocal'] as $method) {
    phase2Expect(in_array($method, $marketplaceMethods, true), 'Controller 可调用 service API 缺失：' . $method);
}

$migrationFile = dirname(__DIR__) . '/database/migrations/009_plugin_package_history.sql';
phase2Expect(is_file($migrationFile), '阶段二必须使用新的 009 migration，不得覆盖现有 008');
$migration = (string) file_get_contents($migrationFile);
phase2Expect(str_contains($migration, 'plugin_version_history') && str_contains($migration, 'plugin_operation'), '必须建立版本与操作历史表');
$historySource = (string) file_get_contents(dirname(__DIR__) . '/app/console/plugin/service/PluginPackageService.php');
phase2Expect(str_contains($historySource, 'Db::transaction'), '历史两表必须在同一事务内保存');
foreach (['from_version', 'signature_algorithm', 'signature_verified', 'source', 'package_hash'] as $historyField) {
    phase2Expect(str_contains($historySource, $historyField), '历史缺少字段：' . $historyField);
}
$controllerSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/plugin/SystemPlugin.php');
foreach (['AuthCloudService', 'setApiUrl', 'downloadCloudArchive', 'doInstall', 'getCloudData', 'public_path'] as $forbidden) {
    phase2Expect(!str_contains($controllerSource, $forbidden), 'SystemPlugin Controller 仍存在旁路：' . $forbidden);
}
phase2Expect(str_contains($controllerSource, 'PluginMarketplaceService'), 'SystemPlugin Controller 必须统一调用市场应用服务');
phase2Expect(str_contains($controllerSource, "file('file')"), 'installLocal 必须直接接收 multipart file');
$streamSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/plugin/package/GuzzlePackageStreamDownloader.php');
foreach (['FILTER_FLAG_NO_PRIV_RANGE', 'FILTER_FLAG_NO_RES_RANGE', 'Location'] as $ssrfBoundary) {
    phase2Expect(str_contains($streamSource, $ssrfBoundary), '下载器缺少 SSRF/重定向边界：' . $ssrfBoundary);
}
$resolvedHosts = [];
$secureRequests = [];
$secureDownloader = new GuzzlePackageStreamDownloader(
    null,
    static function (string $host) use (&$resolvedHosts): array {
        $resolvedHosts[] = $host;
        return match ($host) {
            'downloads.example.com' => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'],
            'cdn.example.com' => ['93.184.216.35'],
            default => [],
        };
    },
    static function (string $url, array $requestOptions) use (&$secureRequests): Response {
        $secureRequests[] = [$url, $requestOptions];
        return count($secureRequests) === 1
            ? new Response(302, ['Location' => 'https://cdn.example.com/demo.zip'])
            : new Response(200, ['Content-Length' => '10']);
    }
);
$secureDownloader('https://downloads.example.com/demo.zip', $downloadRoot . '/secure.zip', [
    'timeout' => 120,
    'connect_timeout' => 10,
    'max_bytes' => 104857600,
    'max_redirects' => 3,
]);
phase2Expect($resolvedHosts === ['downloads.example.com', 'cdn.example.com'], '每次重定向必须重新解析 A 与 AAAA');
phase2Expect(
    ($secureRequests[0][1]['curl'][CURLOPT_RESOLVE][0] ?? '') === 'downloads.example.com:443:93.184.216.34'
    && ($secureRequests[1][1]['curl'][CURLOPT_RESOLVE][0] ?? '') === 'cdn.example.com:443:93.184.216.35',
    '实际连接必须逐跳固定到已验证地址并保留原 URL 主机'
);
phase2Exception(static fn () => (new GuzzlePackageStreamDownloader(
    null,
    static fn (): array => ['93.184.216.34', 'fc00::1'],
    static fn (): Response => new Response(200)
))('https://downloads.example.com/demo.zip', $downloadRoot . '/blocked.zip', [
    'timeout' => 120,
    'connect_timeout' => 10,
    'max_bytes' => 104857600,
    'max_redirects' => 3,
]), '禁止');
phase2Exception(static fn () => (new GuzzlePackageStreamDownloader(null, static fn (): array => ['93.184.216.34']))(
    'https://downloads.example.com/demo.zip',
    $downloadRoot . '/unsupported.zip',
    ['timeout' => 120, 'connect_timeout' => 10, 'max_bytes' => 104857600, 'max_redirects' => 3]
), '不支持安全地址绑定');
phase2Expect(str_contains($migration, 'package_hash') && str_contains($migration, 'source'), '历史必须记录包 hash 与来源');
phase2Expect(!preg_match('/\bDROP\b|\bTRUNCATE\b/i', $migration), '阶段二 migration 必须只向前且不可破坏历史');

echo "plugin phase2 all focused assertions: ok\n";
