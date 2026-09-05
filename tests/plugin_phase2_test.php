<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\marketplace\CloudAccountSession;
use app\common\plugin\marketplace\LegacyCloudMarketplaceAdapter;
use app\common\plugin\package\GuzzlePackageStreamDownloader;
use app\common\plugin\package\PluginPackageDownloader;
use app\backend\service\PluginMarketplaceService;
use GuzzleHttp\Psr7\Response;
use app\backend\service\PluginPackagePipeline;
use app\common\plugin\marketplace\PluginMarketplaceGateway;
use app\common\plugin\marketplace\SessionStore;
use app\common\plugin\marketplace\dto\AuthorizationDto;
use app\common\plugin\marketplace\dto\CategoryDto;
use app\common\plugin\marketplace\dto\CloudAccountDto;
use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use app\common\plugin\marketplace\dto\LoginRequestDto;
use app\common\plugin\marketplace\dto\MarketplaceSearchRequestDto;
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
phase2Expect($methods === ['login', 'logout', 'currentAccount', 'categories', 'search', 'detail', 'versions', 'checkUpdates', 'authorize', 'download'], 'Gateway 方法必须明确且完整');

$login = new LoginRequestDto('demo@example.com', 'secret');
phase2Expect($login->account === 'demo@example.com' && $login->password === 'secret', '登录请求 DTO 必须不可变承载凭据');
$search = new MarketplaceSearchRequestDto('cache', 2, 20, 3, '7.1.0');
phase2Expect($search->keyword === 'cache' && $search->page === 2 && $search->limit === 20, '搜索请求 DTO 字段错误');
phase2Exception(static fn () => new MarketplaceSearchRequestDto('', 0, 20, null, '7.1.0'), '页码');

$account = new CloudAccountDto(7, 'demo', '演示', '/avatar.png');
$category = new CategoryDto(3, '工具');
$version = new PluginVersionDto(11, 'demo', '1.2.0', '更新说明', true);
$detail = new PluginDetailDto(9, 'demo', '演示插件', '说明', '作者', [$version]);
$result = new MarketplaceSearchResultDto([$detail], 1, 1, 20);
$update = new UpdateCheckDto('demo', '1.0.0', '1.2.0', true);
$authorization = new AuthorizationDto('demo', '1.2.0', true, 'authorized');
phase2Expect($account->id === 7 && $category->id === 3 && $result->total === 1, '市场响应 DTO 字段错误');
phase2Expect($update->updateAvailable && $authorization->authorized, '更新与授权 DTO 字段错误');

$descriptor = new DownloadDescriptorDto(
    'https://downloads.example.com/demo.zip',
    'demo',
    '1.2.0',
    str_repeat('a', 64),
    'c2lnbmF0dXJl',
    'sha256',
    1024
);
phase2Expect($descriptor->size === 1024 && $descriptor->algorithm === 'sha256', '下载描述 DTO 字段错误');
phase2Exception(static fn () => new DownloadDescriptorDto('file:///tmp/demo.zip', 'demo', '1.2.0', str_repeat('a', 64), null, null, 1), 'http/https');
phase2Exception(static fn () => new DownloadDescriptorDto('https://example.com/demo.zip', 'demo', '1.2.0', 'bad', null, null, 1), 'SHA-256');
phase2Exception(static fn () => new DownloadDescriptorDto('http://127.0.0.1/demo.zip', 'demo', '1.2.0', str_repeat('a', 64), null, null, 1), '禁止');

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

$authSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/service/AuthCloudService.php');
phase2Expect(!str_contains($authSource, 'serialize(') && !str_contains($authSource, 'unserialize('), '云账号禁止 serialize/unserialize');
phase2Expect(!str_contains($authSource, 'cookie('), '云账号与 token 禁止保存在可读 cookie');

$requests = [];
$adapter = new LegacyCloudMarketplaceAdapter(
    static function (string $endpoint, array $params, string $token) use (&$requests): array {
        $requests[] = [$endpoint, $params, $token];
        return match ($endpoint) {
            '/api/v2.plugins/cateList' => ['code' => 200, 'data' => [['id' => 3, 'name' => '工具']]],
            '/api/v2.plugins/getList' => ['code' => 200, 'data' => ['list' => [['id' => 9, 'name' => 'demo', 'title' => '演示']], 'total' => 1]],
            '/api/v2.plugins/down' => ['code' => 200, 'data' => ['file_url' => 'https://example.com/demo.zip', 'version' => '1.2.0', 'sha256' => str_repeat('a', 64), 'size' => 10]],
            default => ['code' => 200, 'data' => ['id' => 7, 'username' => 'demo', 'nickname' => '演示']],
        };
    },
    $cloudSession
);
$cloudSession->login($account, 'token');
phase2Expect($adapter->categories()[0]->name === '工具', '旧云 category 字段必须适配为 DTO');
phase2Expect($adapter->search(new MarketplaceSearchRequestDto('demo'))->items[0]->name === 'demo', '旧云列表字段必须适配为 DTO');
phase2Expect($adapter->download('demo', '1.2.0')->sha256 === str_repeat('a', 64), '旧云下载字段必须适配为 DTO');
phase2Expect($requests[0][2] === 'token', '适配器请求必须显式传递 session token');
$mismatchAdapter = new LegacyCloudMarketplaceAdapter(
    static fn (): array => ['code' => 200, 'data' => ['file_url' => 'https://example.com/demo.zip', 'version' => '9.9.9', 'sha256' => str_repeat('a', 64), 'size' => 10]],
    $cloudSession
);
phase2Exception(static fn () => $mismatchAdapter->download('demo', '1.2.0'), '版本');

$adapterSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/plugin/marketplace/LegacyCloudMarketplaceAdapter.php');
phase2Expect(!str_contains($adapterSource, 'setApiUrl'), '适配器不得复用可变链式云客户端');
phase2Expect(!str_contains($adapterSource, 'loginToken') && !str_contains($adapterSource, 'setLoginToken'), '适配器不得保存实例级可变 token');

$downloadRoot = sys_get_temp_dir() . '/funadmin-plugin-download-' . bin2hex(random_bytes(4));
$payload = 'zip-content';
$downloadDescriptor = new DownloadDescriptorDto('https://example.com/demo.zip', 'demo', '1.2.0', hash('sha256', $payload), null, null, strlen($payload));
$downloader = new PluginPackageDownloader(
    $downloadRoot,
    static function (string $url, string $target, array $options) use ($payload): void {
        phase2Expect($options['max_bytes'] === 104857600, '下载必须限制 100MB');
        phase2Expect($options['max_redirects'] === 3 && $options['protocols'] === ['https', 'http'], '重定向必须限制次数和协议');
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
putenv('PLUGIN_MARKETPLACE_PUBLIC_KEY=-----BEGIN PUBLIC KEY-----\\nline-data\\n-----END PUBLIC KEY-----');
$pluginConfig = require dirname(__DIR__) . '/config/plugins.php';
phase2Expect(
    ($pluginConfig['marketplace']['public_key'] ?? '') === "-----BEGIN PUBLIC KEY-----\nline-data\n-----END PUBLIC KEY-----",
    'PLUGIN_MARKETPLACE_PUBLIC_KEY 必须显式读取并还原 PEM 换行'
);
putenv('PLUGIN_MARKETPLACE_PUBLIC_KEY');

$downloadSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/plugin/package/PluginPackageDownloader.php');
phase2Expect(!str_contains($downloadSource, 'public_path'), '云下载临时包不得进入 public 中转');
$pipelineSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/service/PluginPackagePipeline.php');
phase2Expect(
    strpos($pipelineSource, 'assertCloudInstallationAllowed') < strpos($pipelineSource, '$gateway->authorize'),
    'reject_unsigned 公钥校验必须发生在云市场请求前'
);

final class FakePackageOperations
{
    public array $calls = [];
    public bool $failFinish = false;
    public bool $failDiscard = false;

    public function stage(string $archive, string $expectedName = '', string $expectedVersion = ''): array
    {
        $this->calls[] = ['stage', $archive, $expectedName, $expectedVersion];
        return ['stage_directory' => '/stage', 'plugin_directory' => '/stage/demo', 'name' => 'demo', 'version' => '1.2.0'];
    }

    public function deploy(array $staged, string $name): ?string
    {
        $this->calls[] = ['deploy', $name];
        return '/backup';
    }

    public function finish(array $staged, ?string $backup): void
    {
        $this->calls[] = ['finish', $backup];
        if ($this->failFinish) {
            throw new RuntimeException('cleanup failed: /stage, /backup');
        }
    }

    public function rollback(string $name, ?string $backup): void
    {
        $this->calls[] = ['rollback', $name];
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
    static function (string $operation, string $name, bool $migrate) use (&$lifecycleCalls): bool {
        $lifecycleCalls[] = [$operation, $name, $migrate];
        return true;
    },
    static fn (): bool => true
);
$localResult = $pipeline->installLocal($localArchive);
phase2Expect($localResult['source'] === 'local' && $localResult['package_hash'] !== '', '本地包必须进入统一 pipeline 并记录 hash');
phase2Expect($lifecycleCalls[0] === ['install', 'demo', true], 'pipeline 必须调用 PluginService install 能力');
phase2Expect(array_column($operations->calls, 0) === ['stage', 'deploy', 'finish'], '成功 pipeline 顺序必须为 stage/deploy/lifecycle/finish');

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
phase2Expect(array_column($failedOperations->calls, 0) === ['stage', 'deploy', 'finish'], 'migration 后不可回滚时必须清理 stage 与 backup，不得泄漏备份');
phase2Expect($failedHistoryErrors !== [], '历史写入失败必须独立记录日志');

$historyFailureOperations = new FakePackageOperations();
$historyFailurePipeline = new PluginPackagePipeline(
    $historyFailureOperations,
    static fn (): bool => true,
    static fn (): bool => true,
    static function (): void { throw new RuntimeException('history failed'); },
    static function (string $message) use (&$failedHistoryErrors): void { $failedHistoryErrors[] = $message; }
);
$historyResult = $historyFailurePipeline->installLocal($localArchive);
phase2Expect($historyResult['name'] === 'demo', '部署成功后历史失败不得覆盖主结果');
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
$originalFailurePipeline = new PluginPackagePipeline(
    $originalFailureOperations,
    static function (): bool { throw new RuntimeException('original migration failed'); },
    static fn (): bool => false,
    null,
    static function (string $message) use (&$originalFailureLogs): void { $originalFailureLogs[] = $message; }
);
phase2Exception(static fn () => $originalFailurePipeline->updateLocal($localArchive, 'demo'), 'original migration failed');
phase2Expect(
    str_contains(implode('\n', $originalFailureLogs), '/stage')
    && str_contains(implode('\n', $originalFailureLogs), 'original migration failed'),
    '失败清理异常必须保留定位路径且不得覆盖原异常'
);
unlink($localArchive);

$packageSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/service/PluginPackageService.php');
phase2Expect(!str_contains($packageSource, "'plugin.ini'") && str_contains($packageSource, "'plugin.json'"), 'PluginPackageService 必须只认 plugin.json');
phase2Expect(str_contains($packageSource, "'version' => \$manifest->version()"), 'stage 结果必须携带已校验的 manifest version');
phase2Expect(str_contains($packageSource, 'expectedVersion') && str_contains($packageSource, '版本与请求版本不一致'), 'stage 必须严格校验 expectedVersion');
phase2Expect(!str_contains($packageSource, '@rmdir') && !str_contains($packageSource, '@unlink'), 'stage/backup 清理不得忽略删除结果');

$marketplaceMethods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(PluginMarketplaceService::class))->getMethods(ReflectionMethod::IS_PUBLIC));
foreach (['login', 'logout', 'currentAccount', 'categories', 'search', 'detail', 'versions', 'checkUpdates', 'authorize', 'installCloud', 'updateCloud', 'installLocal', 'updateLocal'] as $method) {
    phase2Expect(in_array($method, $marketplaceMethods, true), 'Controller 可调用 service API 缺失：' . $method);
}

$migrationFile = dirname(__DIR__) . '/database/migrations/009_plugin_package_history.sql';
phase2Expect(is_file($migrationFile), '阶段二必须使用新的 009 migration，不得覆盖现有 008');
$migration = (string) file_get_contents($migrationFile);
phase2Expect(str_contains($migration, 'plugin_version_history') && str_contains($migration, 'plugin_operation'), '必须建立版本与操作历史表');
$historySource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/service/PluginPackageHistoryService.php');
phase2Expect(str_contains($historySource, 'Db::transaction'), '历史两表必须在同一事务内保存');
foreach (['from_version', 'signature_algorithm', 'signature_verified', 'source', 'package_hash'] as $historyField) {
    phase2Expect(str_contains($historySource, $historyField), '历史缺少字段：' . $historyField);
}
$controllerSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/controller/Plugin.php');
foreach (['AuthCloudService', 'setApiUrl', 'downloadCloudArchive', 'doInstall', 'getCloudData', 'public_path'] as $forbidden) {
    phase2Expect(!str_contains($controllerSource, $forbidden), 'Plugin Controller 仍存在旁路：' . $forbidden);
}
phase2Expect(str_contains($controllerSource, 'PluginMarketplaceService'), 'Plugin Controller 必须统一调用市场应用服务');
phase2Expect(str_contains($controllerSource, "file('file')"), 'localinstall 必须直接接收 multipart file');
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
