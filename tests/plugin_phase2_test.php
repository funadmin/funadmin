<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\marketplace\CloudAccountSession;
use app\common\plugin\marketplace\LegacyCloudMarketplaceAdapter;
use app\common\plugin\package\PluginPackageDownloader;
use app\backend\service\PluginMarketplaceService;
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
            '/api/v2.plugins/down' => ['code' => 200, 'data' => ['file_url' => 'https://example.com/demo.zip', 'sha256' => str_repeat('a', 64), 'size' => 10]],
            default => ['code' => 200, 'data' => ['id' => 7, 'username' => 'demo', 'nickname' => '演示']],
        };
    },
    $cloudSession
);
$adapter->setLoginToken('token');
phase2Expect($adapter->categories()[0]->name === '工具', '旧云 category 字段必须适配为 DTO');
phase2Expect($adapter->search(new MarketplaceSearchRequestDto('demo'))->items[0]->name === 'demo', '旧云列表字段必须适配为 DTO');
phase2Expect($adapter->download('demo', '1.2.0')->sha256 === str_repeat('a', 64), '旧云下载字段必须适配为 DTO');
phase2Expect($requests[0][2] === 'token', '适配器请求必须显式传递 session token');

$adapterSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/plugin/marketplace/LegacyCloudMarketplaceAdapter.php');
phase2Expect(!str_contains($adapterSource, 'setApiUrl'), '适配器不得复用可变链式云客户端');

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

$downloadSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/plugin/package/PluginPackageDownloader.php');
phase2Expect(!str_contains($downloadSource, 'public_path'), '云下载临时包不得进入 public 中转');

final class FakePackageOperations
{
    public array $calls = [];

    public function stage(string $archive, string $expectedName = ''): array
    {
        $this->calls[] = ['stage', $archive, $expectedName];
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
    }

    public function rollback(string $name, ?string $backup): void
    {
        $this->calls[] = ['rollback', $name];
    }

    public function discard(array $staged): void
    {
        $this->calls[] = ['discard'];
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
$failedPipeline = new PluginPackagePipeline(
    $failedOperations,
    static function (): bool { throw new RuntimeException('migration failed'); },
    static fn (): bool => false
);
phase2Exception(static fn () => $failedPipeline->updateLocal($localArchive, 'demo'), 'migration failed');
phase2Expect(array_column($failedOperations->calls, 0) === ['stage', 'deploy', 'discard'], 'migration 后不可回滚时只能清理 stage，不得回滚部署');
unlink($localArchive);

$packageSource = (string) file_get_contents(dirname(__DIR__) . '/app/backend/service/PluginPackageService.php');
phase2Expect(!str_contains($packageSource, "'plugin.ini'") && str_contains($packageSource, "'plugin.json'"), 'PluginPackageService 必须只认 plugin.json');
phase2Expect(str_contains($packageSource, "'version' => \$manifest->version()"), 'stage 结果必须携带已校验的 manifest version');

$marketplaceMethods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(PluginMarketplaceService::class))->getMethods(ReflectionMethod::IS_PUBLIC));
foreach (['login', 'logout', 'currentAccount', 'categories', 'search', 'detail', 'versions', 'checkUpdates', 'authorize', 'installCloud', 'updateCloud', 'installLocal', 'updateLocal'] as $method) {
    phase2Expect(in_array($method, $marketplaceMethods, true), 'Controller 可调用 service API 缺失：' . $method);
}

$migrationFile = dirname(__DIR__) . '/database/migrations/009_plugin_package_history.sql';
phase2Expect(is_file($migrationFile), '阶段二必须使用新的 009 migration，不得覆盖现有 008');
$migration = (string) file_get_contents($migrationFile);
phase2Expect(str_contains($migration, 'plugin_version_history') && str_contains($migration, 'plugin_operation'), '必须建立版本与操作历史表');
phase2Expect(str_contains($migration, 'package_hash') && str_contains($migration, 'source'), '历史必须记录包 hash 与来源');
phase2Expect(!preg_match('/\bDROP\b|\bTRUNCATE\b/i', $migration), '阶段二 migration 必须只向前且不可破坏历史');

echo "plugin phase2 all focused assertions: ok\n";
