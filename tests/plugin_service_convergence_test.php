<?php

declare(strict_types=1);

namespace app\console\plugin\service {
    function root_path(): string { return $GLOBALS['packageTestRoot'] . '/'; }
    function runtime_path(string $path = ''): string { return $GLOBALS['packageTestRoot'] . '/runtime/' . $path; }
    function config(string $key, mixed $default = null): mixed { return $GLOBALS['packageTestPublicKey'] ?? $default; }
}

namespace {
function convergenceExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$serviceDirectory = $root . '/app/console/plugin/service';
$controllerFile = $root . '/app/console/controller/plugin/SystemPlugin.php';

$expectedServices = [
    'PluginService',
    'PluginCenterService',
    'PluginMarketplaceService',
    'PluginPackageService',
    'PluginPackagePipeline',
];
$removedServices = [
    'PluginCenterQueryService',
    'PluginConfigService',
    'PluginMarketplaceFactory',
    'PluginPackageHistoryService',
];

foreach ($expectedServices as $service) {
    convergenceExpect(is_file($serviceDirectory . '/' . $service . '.php'), '缺少目标插件服务：' . $service);
}
foreach ($removedServices as $service) {
    convergenceExpect(!is_file($serviceDirectory . '/' . $service . '.php'), '旧插件服务必须删除：' . $service);
}

$centerSource = (string) file_get_contents($serviceDirectory . '/PluginCenterService.php');
foreach (['discovered', 'installed', 'detail', 'deletePackage', 'enabledModules', 'get', 'save'] as $method) {
    convergenceExpect(
        preg_match('/public function ' . preg_quote($method, '/') . '\s*\(/', $centerSource) === 1,
        'PluginCenterService 缺少职责：' . $method
    );
}
convergenceExpect(substr_count($centerSource, "'/^[a-z][a-z0-9]*$/'") === 1, 'PluginCenterService 必须统一使用唯一插件名校验');
convergenceExpect(!preg_match('/public function (?:versions|operations)\s*\(/', $centerSource), 'Center 不得保留包历史查询职责');

$marketplaceSource = (string) file_get_contents($serviceDirectory . '/PluginMarketplaceService.php');
convergenceExpect(str_contains($marketplaceSource, 'public static function create(PluginService $plugins): self'), 'Marketplace 必须提供静态装配入口');
convergenceExpect(str_contains($marketplaceSource, 'NativeMarketplaceAdapter'), 'Marketplace 必须装配 v3 Native 网关');
convergenceExpect(!preg_match('/public function authorize\s*\(/', $marketplaceSource), 'Marketplace 不得保留零调用 authorize 门面');

$packageSource = (string) file_get_contents($serviceDirectory . '/PluginPackageService.php');
foreach (['versions', 'operations', 'historyPackage', 'recoveryInfo', 'record'] as $method) {
    convergenceExpect(
        preg_match('/(?:public|private) function ' . preg_quote($method, '/') . '\s*\(/', $packageSource) === 1,
        'PluginPackageService 缺少历史职责：' . $method
    );
}
convergenceExpect(str_contains($packageSource, 'serializeRecord'), 'PluginPackageService 必须统一序列化历史记录');

$pipelineSource = (string) file_get_contents($serviceDirectory . '/PluginPackagePipeline.php');
convergenceExpect(str_contains($pipelineSource, 'PluginPackageService::instance()->record'), 'Pipeline 历史回调必须改用 PackageService');
convergenceExpect(!str_contains($pipelineSource, 'discardSafely'), 'Pipeline 必须删除零调用 discardSafely');

$controllerSource = (string) file_get_contents($controllerFile);
foreach ($expectedServices as $service) {
    convergenceExpect(str_contains($controllerSource, $service), 'SystemPlugin 缺少目标服务依赖：' . $service);
}
foreach ($removedServices as $service) {
    convergenceExpect(!str_contains($controllerSource, $service), 'SystemPlugin 仍引用旧服务：' . $service);
}
preg_match_all('/private readonly (?:PluginService|Plugin[A-Za-z]+(?:Service|Pipeline)) \$[A-Za-z]+;/', $controllerSource, $dependencies);
convergenceExpect(count($dependencies[0]) === 5, 'SystemPlugin 插件服务依赖必须收敛为 5 个');

$pluginServiceSource = (string) file_get_contents($serviceDirectory . '/PluginService.php');
convergenceExpect(!str_contains($pluginServiceSource, 'modifyPlugin'), 'PluginService 必须删除零调用 modifyPlugin');
convergenceExpect(preg_match('/public function installPlugin\(string \$code\): bool/', $pluginServiceSource) === 1, 'installPlugin 必须仅接收插件 code 并删除无用 type 参数');

convergenceExpect(!is_file($root . '/app/common/plugin/sdk/LifecycleProgress.php'), 'LifecycleProgress 及仅测试引用必须删除');
convergenceExpect(!is_file($root . '/app/common/plugin/sdk/PluginRuntimeBooter.php'), 'PluginRuntimeBooter 必须随旧 runtime 链删除');
convergenceExpect(!str_contains((string) file_get_contents($root . '/app/common/plugin/sdk/Service.php'), 'getCheckDirs'), 'Service 必须删除零调用 getCheckDirs');
convergenceExpect(!str_contains((string) file_get_contents($root . '/app/common/plugin/marketplace/CloudAccountSession.php'), 'function expiresAt('), 'CloudAccountSession 必须删除零调用 expiresAt');
convergenceExpect(!str_contains((string) file_get_contents($root . '/app/common/plugin/model/Plugin.php'), 'function __construct('), 'Plugin model 必须删除空构造器');

foreach ($expectedServices as $service) {
    $lines = count(file($serviceDirectory . '/' . $service . '.php') ?: []);
    convergenceExpect($lines <= 600, $service . ' 超过 600 行，必须拆分私有 trait/domain 组件');
}

require $root . '/vendor/autoload.php';

// 模型替身只提供安装状态，不连接数据库；路径全部落在独立临时目录。
class PackageTestPluginRecord
{
    public static bool $installed = false;
    public static function where(...$arguments): self { return new self(); }
    public function find(): ?self { return self::$installed ? $this : null; }
}
class_alias(PackageTestPluginRecord::class, 'app\\common\\plugin\\model\\Plugin');
defined('PLUGIN_DIR') || define('PLUGIN_DIR', 'plugins');
$GLOBALS['packageTestRoot'] = realpath(sys_get_temp_dir()) . '/plugin-security-' . bin2hex(random_bytes(8));
mkdir($GLOBALS['packageTestRoot'], 0700, true);
$temporaryRoot = $GLOBALS['packageTestRoot'];
$failures = [];
function packageReject(callable $callback, string $message): void
{
    try { $callback(); } catch (RuntimeException $exception) {
        convergenceExpect(str_contains($exception->getMessage(), $message), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $message);
}
function packageCase(string $name, callable $callback): void
{
    try { $callback(); echo "PASS: {$name}\n"; }
    catch (Throwable $exception) { $GLOBALS['failures'][] = $name . ': ' . $exception->getMessage(); }
}
function packageZip(string $path, array $files): void
{
    $zip = new ZipArchive();
    convergenceExpect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, '创建 ZIP 失败');
    foreach ($files as $name => $contents) {
        str_ends_with($name, '/') ? $zip->addEmptyDir($name) : $zip->addFromString($name, $contents);
    }
    convergenceExpect($zip->close(), '关闭 ZIP 失败');
}
// 独立协议 oracle：固定域前缀 + 按路径字节排序的 JSON 二元组列表。
function packagePayload(array $files): string
{
    unset($files['demo/plugin.sig']);
    ksort($files, SORT_STRING);
    $entries = [];
    foreach ($files as $name => $contents) $entries[] = [$name, str_ends_with($name, '/') ? null : hash('sha256', $contents)];
    return "funadmin-plugin-files-v1\n" . json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
try {
    $service = new \app\console\plugin\service\PluginPackageService();
    $pair = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($pair);
    $GLOBALS['packageTestPublicKey'] = base64_encode(sodium_crypto_sign_publickey($pair));
    $files = ['demo/plugin.json' => '{"code":"demo","version":"1.0.0"}',
        'demo/Plugin.php' => '<?php file_put_contents(' . var_export($temporaryRoot . '/executed', true) . ', "unsafe");',
        'demo/config.php' => '<?php return [];', 'demo/assets/data.bin' => "\0\xfftest"];
    $signed = $files + ['demo/plugin.sig' => base64_encode(sodium_crypto_sign_detached(packagePayload($files), $secret))];
    packageCase('完整文件签名与归档顺序无关', function () use ($service, $temporaryRoot, $signed): void {
        packageZip($temporaryRoot . '/valid.zip', array_reverse($signed, true));
        convergenceExpect($service->inspect($temporaryRoot . '/valid.zip')['code'] === 'demo', '合法签名应通过');
    });
    packageCase('拒绝旧 manifest-only 签名', function () use ($service, $temporaryRoot, $files, $secret): void {
        packageZip($temporaryRoot . '/old.zip', $files + ['demo/plugin.sig' => base64_encode(sodium_crypto_sign_detached($files['demo/plugin.json'], $secret))]);
        packageReject(fn () => $service->inspect($temporaryRoot . '/old.zip'), '签名');
    });
    foreach (['Plugin.php', 'config.php', 'assets/data.bin', 'plugin.json', 'extra.php', 'removed', 'renamed'] as $change) {
        packageCase('拒绝文件变化 ' . $change, function () use ($service, $temporaryRoot, $signed, $change): void {
            $changed = $signed;
            if ($change === 'removed') unset($changed['demo/config.php']);
            elseif ($change === 'renamed') { $changed['demo/renamed.php'] = $changed['demo/config.php']; unset($changed['demo/config.php']); }
            else $changed['demo/' . $change] = ($changed['demo/' . $change] ?? '') . ' ';
            packageZip($temporaryRoot . '/changed.zip', $changed);
            packageReject(fn () => $service->inspect($temporaryRoot . '/changed.zip'), '签名');
        });
    }
    packageCase('拒绝重复 ZIP 路径', function () use ($service, $temporaryRoot, $signed): void {
        $duplicate = $signed + ['demo/copyig.php' => 'duplicate'];
        packageZip($temporaryRoot . '/duplicate.zip', $duplicate);
        // 等长替换中央目录及本地头，避免 ZipArchive 自动覆盖同名条目。
        $bytes = file_get_contents($temporaryRoot . '/duplicate.zip');
        file_put_contents($temporaryRoot . '/duplicate.zip', str_replace('demo/copyig.php', 'demo/config.php', $bytes));
        packageReject(fn () => $service->inspect($temporaryRoot . '/duplicate.zip'), '重复');
    });
    foreach (['demo/./config.php', 'demo//config.php', 'demo/../evil.php', 'demo\\config.php'] as $badPath) {
        packageCase('拒绝歧义路径 ' . $badPath, function () use ($service, $temporaryRoot, $signed, $badPath): void {
            packageZip($temporaryRoot . '/path.zip', $signed + [$badPath => 'bad']);
            packageReject(fn () => $service->inspect($temporaryRoot . '/path.zip'), '路径');
        });
    }
    packageCase('暂存必须在解析插件 PHP 前拒绝旧签名', function () use ($service, $temporaryRoot): void {
        packageReject(fn () => $service->stage($temporaryRoot . '/old.zip'), '签名');
        convergenceExpect(!file_exists($temporaryRoot . '/executed'), '不得执行不可信 PHP');
    });
    packageCase('签包工具复用同一协议', function () use ($service, $temporaryRoot, $files, $secret): void {
        packageZip($temporaryRoot . '/tool.zip', $files);
        $service->signLocalArchive($temporaryRoot . '/tool.zip', base64_encode($secret));
        convergenceExpect($service->inspect($temporaryRoot . '/tool.zip')['code'] === 'demo', '工具产物必须通过验签');
        $zip = new ZipArchive(); $zip->open($temporaryRoot . '/tool.zip');
        convergenceExpect($zip->getFromName('demo/plugin.sig') === base64_encode(sodium_crypto_sign_detached(packagePayload($files), $secret)), '签包协议必须与 oracle 一致');
        $zip->close();
    });
    packageCase('目录记录与根外额外文件同样绑定签名', function () use ($service, $temporaryRoot, $files, $secret): void {
        $withDirectory = $files + ['demo/empty/' => ''];
        $withDirectory['demo/plugin.sig'] = base64_encode(sodium_crypto_sign_detached(packagePayload($withDirectory), $secret));
        packageZip($temporaryRoot . '/directory.zip', $withDirectory);
        $service->inspect($temporaryRoot . '/directory.zip');
        packageZip($temporaryRoot . '/directory.zip', $withDirectory + ['outside.php' => 'bad']);
        packageReject(fn () => $service->inspect($temporaryRoot . '/directory.zip'), '签名');
    });
    packageCase('拒绝 ZIP 符号链接', function () use ($service, $temporaryRoot, $signed): void {
        packageZip($temporaryRoot . '/symlink.zip', $signed);
        $zip = new ZipArchive(); $zip->open($temporaryRoot . '/symlink.zip');
        $zip->setExternalAttributesName('demo/config.php', ZipArchive::OPSYS_UNIX, 0120777 << 16); $zip->close();
        packageReject(fn () => $service->inspect($temporaryRoot . '/symlink.zip'), '符号链接');
    });
    $center = new \app\console\plugin\service\PluginCenterService();
    mkdir($temporaryRoot . '/plugins'); mkdir($temporaryRoot . '/outside');
    file_put_contents($temporaryRoot . '/outside/sentinel', 'keep');
    packageCase('删除拒绝根符号链接', function () use ($center, $temporaryRoot): void {
        symlink($temporaryRoot . '/outside', $temporaryRoot . '/plugins/demo');
        packageReject(fn () => $center->deletePackage('demo'), '符号链接');
        convergenceExpect(file_get_contents($temporaryRoot . '/outside/sentinel') === 'keep', '外部文件不得删除');
    });
    packageCase('删除拒绝父目录符号链接', function () use ($center, $temporaryRoot): void {
        $GLOBALS['packageTestRoot'] = $temporaryRoot . '/parent'; mkdir($GLOBALS['packageTestRoot']);
        symlink($temporaryRoot . '/outside', $GLOBALS['packageTestRoot'] . '/plugins');
        mkdir($temporaryRoot . '/outside/demo'); file_put_contents($temporaryRoot . '/outside/demo/keep', 'keep');
        try { packageReject(fn () => $center->deletePackage('demo'), '符号链接'); }
        finally { $GLOBALS['packageTestRoot'] = $temporaryRoot; }
        convergenceExpect(is_file($temporaryRoot . '/outside/demo/keep'), '父链接目标不得删除');
    });
    packageCase('删除拒绝悬空根链接', function () use ($center, $temporaryRoot): void {
        symlink($temporaryRoot . '/missing', $temporaryRoot . '/plugins/dangling');
        packageReject(fn () => $center->deletePackage('dangling'), '符号链接');
        convergenceExpect(is_link($temporaryRoot . '/plugins/dangling'), '拒绝时不得改变链接');
    });
    packageCase('已安装插件不得删除', function () use ($center, $temporaryRoot): void {
        mkdir($temporaryRoot . '/plugins/installed');
        PackageTestPluginRecord::$installed = true;
        try { packageReject(fn () => $center->deletePackage('installed'), '先卸载'); }
        finally { PackageTestPluginRecord::$installed = false; }
        convergenceExpect(is_dir($temporaryRoot . '/plugins/installed'), '已安装目录必须保留');
    });
    packageCase('正常删除仅解除子链接，不跟随目标', function () use ($center, $temporaryRoot): void {
        mkdir($temporaryRoot . '/plugins/safe'); mkdir($temporaryRoot . '/plugins/safe/nested');
        file_put_contents($temporaryRoot . '/plugins/safe/nested/file', 'ok');
        symlink($temporaryRoot . '/outside', $temporaryRoot . '/plugins/safe/link');
        $center->deletePackage('safe');
        convergenceExpect(!is_dir($temporaryRoot . '/plugins/safe') && is_dir($temporaryRoot . '/outside'), '安全删除必须成功且不跟随链接');
    });
} finally {
    // 只清理本测试随机目录，链接先解除，绝不递归到链接目标。
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporaryRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($temporaryRoot);
}
if ($failures !== []) throw new RuntimeException(implode("\n", $failures));
echo "plugin service convergence and security tests: PASS\n";
}
