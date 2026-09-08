<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use fun\plugins\Manifest;
use fun\plugins\PluginArchiveService;
use fun\plugins\PluginScaffolder;

function developmentExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function developmentReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        developmentExpect(
            str_contains($exception->getMessage(), $contains),
            '异常信息不匹配：' . $exception->getMessage()
        );
        return;
    }
    throw new RuntimeException('预期抛出异常：' . $contains);
}

function developmentRemove(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

$repository = dirname(__DIR__);
$temp = sys_get_temp_dir() . '/funadmin-plugin-development-' . bin2hex(random_bytes(5));
$plugins = $temp . '/plugins';
mkdir($plugins, 0755, true);

try {
    $scaffolder = new PluginScaffolder($plugins);
    $result = $scaffolder->scaffold('demo', '演示插件');
    $plugin = $plugins . '/demo';

    developmentExpect($result['directory'] === $plugin, 'scaffold 必须返回最终插件目录');
    developmentExpect(Manifest::fromDirectory($plugin)->code() === 'demo', '生成骨架必须立即通过 Manifest v2');
    $manifest = json_decode((string) file_get_contents($plugin . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
    developmentExpect(($manifest['schema_version'] ?? null) === 2, '生成 manifest 必须使用 schema_version=2');
    developmentExpect(!isset($manifest['load'], $manifest['channels']), 'Manifest v2 不得生成 load/channels');
    $pluginConfig = require $plugin . '/config.php';
    developmentExpect(is_array($pluginConfig) && isset($pluginConfig['enabled']['value']), '插件骨架必须生成非空配置 schema');
    developmentExpect(($pluginConfig['enabled']['type'] ?? '') === 'switch', '默认启用配置必须使用 switch 控件');

    foreach ([
        'Plugin.php',
        'config.php',
        'app/demo/controller/Index.php',
        'app/demo/model/.gitkeep',
        'app/demo/service/.gitkeep',
        'app/demo/config/app.php',
        'app/demo/middleware/.gitkeep',
        'app/demo/route/app.php',
        'app/demo/view/.gitkeep',
        'app/demo/lang/zh-cn.php',
        'app/demo/event.php',
        'app/demo/provider.php',
        'app/console/controller/Index.php',
        'app/console/model/.gitkeep',
        'app/console/service/.gitkeep',
        'app/console/validate/.gitkeep',
        'app/console/middleware/.gitkeep',
        'admin-web/api.ts',
        'admin-web/types.ts',
        'admin-web/pages/Index.vue',
        'admin-web/pages/components/EditDialog.vue',
        'database/migrations/.gitkeep',
        'resources/public/.gitkeep',
        'storage/.gitkeep',
    ] as $relative) {
        developmentExpect(is_file($plugin . '/' . $relative), '插件骨架缺少：' . $relative);
    }

    $applicationController = (string) file_get_contents($plugin . '/app/demo/controller/Index.php');
    developmentExpect(str_contains($applicationController, 'namespace app\\demo\\controller;'), '独立应用 namespace 必须使用 app\\demo');
    developmentExpect(str_contains($applicationController, '#[Get('), '独立应用必须生成最小 Attribute controller');
    developmentExpect((require $plugin . '/app/demo/config/app.php') === [], '应用配置文件允许使用无 namespace 的原生返回文件');
    developmentExpect((require $plugin . '/app/demo/event.php')['listen'] === [], 'event.php 必须是可加载的原生配置');
    developmentExpect((require $plugin . '/app/demo/provider.php') === [], 'provider.php 必须是可加载的原生配置');
    $entrySource = (string) file_get_contents($plugin . '/Plugin.php');
    foreach (['beforeUpdate', 'afterUpdate', 'purgeData'] as $hook) {
        developmentExpect(str_contains($entrySource, 'function ' . $hook . '('), 'Plugin.php 必须显式生成生命周期模板：' . $hook);
    }
    developmentExpect(preg_match('/function purgeData\([^)]*\): bool\s*\{\s*return false;\s*\}/s', $entrySource) === 1, 'purge.supported=false 时 purgeData 必须安全返回 false');
    $consoleController = (string) file_get_contents($plugin . '/app/console/controller/Index.php');
    developmentExpect(str_contains($consoleController, 'namespace app\\console\\controller\\plugin\\demo;'), 'Console controller 必须使用原生 layer namespace');
    developmentExpect(str_contains($consoleController, 'extends AdminApiController'), 'Console controller 必须继承 AdminApiController');
    foreach (['CheckAdminApiRole::class', 'CheckAdminApiCsrf::class', 'SystemLog::class'] as $middleware) {
        developmentExpect(str_contains($consoleController, $middleware), 'Console controller 缺少中间件：' . $middleware);
    }
    developmentExpect(str_contains($consoleController, "#[Group('plugin/demo')]"), 'Console Group 必须使用 plugin/name 前缀');
    developmentExpect(($manifest['adminWeb']['components']['Index'] ?? '') === 'pages/Index.vue', 'manifest 组件必须对应页面目录');
    developmentExpect(str_contains((string) file_get_contents($plugin . '/admin-web/pages/Index.vue'), "v-perm=\"'demo:item:list'\""), 'Admin Web 页面必须包含权限使用示例');
    foreach (['example', 'shop'] as $fixtureCode) {
        $fixtureRoot = $repository . '/plugins/' . $fixtureCode;
        $fixtureConfig = require $fixtureRoot . '/config.php';
        developmentExpect(is_array($fixtureConfig) && $fixtureConfig !== [], $fixtureCode . ' 示例插件必须提供可编辑配置');
        developmentExpect(is_dir($fixtureRoot . '/database/migrations'), $fixtureCode . ' 必须使用规范 database/migrations 目录');
        developmentExpect(!is_dir($fixtureRoot . '/migrations'), $fixtureCode . ' 不得保留旧 migrations 双目录');
    }

    developmentReject(static fn () => $scaffolder->scaffold('Demo', '非法'), '格式');
    developmentReject(static fn () => $scaffolder->scaffold('console', '保留名'), '保留');
    developmentReject(static fn () => $scaffolder->scaffold('frontend', '保留名'), '保留');
    developmentReject(static fn () => $scaffolder->scaffold('demo', '重复'), '已存在');

    $optional = $scaffolder->scaffold('minimal', '最小插件', false, false, false);
    developmentExpect(!is_dir($optional['directory'] . '/app'), '--no-application/--no-console 必须关闭 app 骨架');
    developmentExpect(!is_dir($optional['directory'] . '/admin-web'), '--no-admin-web 必须关闭前端骨架');
    developmentExpect(Manifest::fromDirectory($optional['directory'])->code() === 'minimal', '关闭可选骨架后 manifest 仍须有效');
    $applicationOnly = $scaffolder->scaffold('applicationonly', '仅独立应用', true, false, false);
    developmentExpect(is_dir($applicationOnly['directory'] . '/app/applicationonly'), '单独启用 application 必须生成同名应用');
    developmentExpect(!is_dir($applicationOnly['directory'] . '/app/console'), '--no-console 必须允许仅生成独立应用');
    developmentExpect(Manifest::fromDirectory($applicationOnly['directory'])->code() === 'applicationonly', '仅独立应用结构必须有效');
    $consoleOnly = $scaffolder->scaffold('consoleonly', '仅管理应用', false, true, false);
    developmentExpect(!is_dir($consoleOnly['directory'] . '/app/consoleonly'), '--no-application 必须允许仅生成 Console 应用');
    developmentExpect(is_dir($consoleOnly['directory'] . '/app/console'), '单独启用 console 必须生成管理应用');
    developmentExpect(Manifest::fromDirectory($consoleOnly['directory'])->code() === 'consoleonly', '仅 Console 应用结构必须有效');

    $invalid = $scaffolder->scaffold('invalidgroup', '非法分组');
    $controller = $invalid['directory'] . '/app/console/controller/Index.php';
    file_put_contents($controller, str_replace("#[Group('plugin/invalidgroup')]", "#[Group('other')]", (string) file_get_contents($controller)));
    developmentReject(static fn () => Manifest::fromDirectory($invalid['directory']), 'Group');
    $commentBypass = $scaffolder->scaffold('commentbypass', '注释绕过');
    $commentController = $commentBypass['directory'] . '/app/console/controller/Index.php';
    file_put_contents($commentController, "<?php\n// namespace app\\console\\controller\\plugin\\commentbypass;\n// #[Group('plugin/commentbypass')]\nnamespace invalid;\nfinal class Index {}\n");
    developmentReject(static fn () => Manifest::fromDirectory($commentBypass['directory']), 'namespace');
    $entryBypass = $scaffolder->scaffold('entrybypass', '入口绕过');
    file_put_contents($entryBypass['directory'] . '/Plugin.php', "<?php\n// namespace plugins\\entrybypass;\n// class Plugin {}\nnamespace attacker;\nfinal class Other {}\n");
    developmentReject(static fn () => Manifest::fromDirectory($entryBypass['directory']), 'Plugin.php');
    $attributeBypass = $scaffolder->scaffold('attributebypass', '属性绕过');
    $attributeController = $attributeBypass['directory'] . '/app/console/controller/Index.php';
    file_put_contents($attributeController, "<?php\nnamespace app\\console\\controller\\plugin\\attributebypass;\n#[Other(\"\\\\Group('plugin/attributebypass')\")]\nfinal class Index {}\n");
    developmentReject(static fn () => Manifest::fromDirectory($attributeBypass['directory']), 'Group');

    $migrationPlugin = $scaffolder->scaffold('badmigration', '非法迁移');
    file_put_contents($migrationPlugin['directory'] . '/database/migrations/create.sql', 'SELECT 1;');
    developmentReject(static fn () => Manifest::fromDirectory($migrationPlugin['directory']), 'migration');

    $verifiedStages = 0;
    $archiveService = new PluginArchiveService(
        $plugins,
        static function (string $archive) use (&$verifiedStages): void {
            $verifiedStages++;
            developmentExpect(is_file($archive), '打包后必须调用完整 stage 重验');
        }
    );
    file_put_contents($plugin . '/.DS_Store', 'ignored');
    mkdir($plugin . '/runtime/cache', 0755, true);
    file_put_contents($plugin . '/runtime/cache/state.php', 'ignored');
    file_put_contents($plugin . '/config.local.php', 'ignored');
    file_put_contents($plugin . '/.env', 'PLUGIN_SECRET=ignored');
    file_put_contents($plugin . '/.env.example', 'PLUGIN_SECRET=ignored');
    $first = $archiveService->package('demo', $temp . '/demo-a.zip');
    $second = $archiveService->package('demo', $temp . '/demo-b.zip');
    developmentExpect($verifiedStages === 2, '每个产物都必须经过 stage 重验');
    developmentExpect($first['sha256'] === $second['sha256'], '相同源码必须生成确定性 ZIP');
    developmentExpect($first['tree_hash'] === $second['tree_hash'], '相同源码必须生成确定性 tree hash');
    developmentExpect($first['files'] === $second['files'] && $first['files'] === array_values(array_unique($first['files'])), 'files 必须稳定排序且无重复');
    $sortedFiles = $first['files'];
    sort($sortedFiles, SORT_STRING);
    developmentExpect($first['files'] === $sortedFiles, 'files 必须按字节序排序');
    foreach (['.DS_Store', 'runtime/cache/state.php', 'config.local.php', '.env', '.env.example'] as $excluded) {
        developmentExpect(!in_array($excluded, $first['files'], true), '归档必须排除：' . $excluded);
    }
    developmentExpect(!in_array('resources/public/.gitkeep', $first['files'], true), '.gitkeep 不得作为运行资源打包');

    if (function_exists('symlink')) {
        symlink($plugin . '/Plugin.php', $plugin . '/linked.php');
        developmentReject(static fn () => $archiveService->package('demo', $temp . '/linked.zip'), '符号链接');
        unlink($plugin . '/linked.php');
    }

    $console = require $repository . '/config/console.php';
    foreach (['plugin:make', 'plugin:validate', 'plugin:package'] as $command) {
        developmentExpect(isset($console['commands'][$command]), 'console-commands 契约缺少：' . $command);
    }
} finally {
    developmentRemove($temp);
}

echo "plugin development tools tests: PASS\n";
