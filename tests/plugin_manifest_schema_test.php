<?php

declare(strict_types=1);

// Admin Web 插件仅发布源码，并由 Vite 在构建时发现已声明组件。
require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\sdk\Manifest;
use think\App;

function schemaExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function schemaReject(string $directory, array $manifest, string $message): void
{
    file_put_contents($directory . '/plugin.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    try {
        Manifest::fromDirectory($directory);
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$schemaFile = $root . '/app/common/plugin/sdk/schema/plugin.schema.json';
schemaExpect(is_file($schemaFile), '必须存在权威 plugin.schema.json');
$schema = json_decode((string) file_get_contents($schemaFile), true, 512, JSON_THROW_ON_ERROR);
schemaExpect(($schema['additionalProperties'] ?? true) === false, '根对象必须拒绝未知字段');
schemaExpect(($schema['properties']['schema_version']['const'] ?? null) === 2, 'schema 必须固定为 Manifest v2');
schemaExpect(!isset($schema['properties']['load'], $schema['properties']['channels']), 'Manifest v2 必须删除 load/channels');

$temp = sys_get_temp_dir() . '/funadmin-manifest-schema-' . bin2hex(random_bytes(5));
$plugin = $temp . '/demo';
foreach (['app/demo/controller', 'app/console/controller', 'resources/public', 'admin-web', 'database/migrations', 'storage'] as $directory) {
    mkdir($plugin . '/' . $directory, 0755, true);
}
file_put_contents($plugin . '/Plugin.php', '<?php namespace plugins\\demo; final class Plugin extends \\app\\common\\plugin\\sdk\\Plugin { protected function initialize(): void {} public function install(): bool { return true; } public function uninstall(): bool { return true; } public function enabled(): bool { return true; } public function disabled(): bool { return true; } public function purgeData(): bool { return true; } }');
file_put_contents($plugin . '/app/demo/controller/Index.php', '<?php namespace app\\demo\\controller; final class Index {}');
file_put_contents($plugin . '/app/console/controller/Index.php', '<?php namespace app\\console\\controller\\plugin\\demo; use think\\annotation\\route\\Group; #[Group("plugin/demo")] final class Index {}');
file_put_contents($plugin . '/resources/public/app.css', 'body{}');
file_put_contents($plugin . '/admin-web/Index.vue', '<template>demo</template>');
file_put_contents($plugin . '/database/migrations/001_initial.sql', 'SELECT 1;');

$valid = [
    'schema_version' => 2,
    'code' => 'demo',
    'name' => '演示插件',
    'version' => '1.0.0',
    'requires' => ['php' => '>=8.1', 'funadmin' => '>=1.0.0', 'plugins' => []],
    'entry' => ['class' => 'plugins\\demo\\Plugin', 'file' => 'Plugin.php'],
    'adminWeb' => [
        'source' => 'admin-web',
        'components' => ['Index' => 'Index.vue'],
        'minFrontendVersion' => '1.0.0',
        'permissions' => [['code' => 'demo:dashboard:view', 'name' => '查看演示']],
        'menu' => [
            ['name' => '演示', 'path' => '/plugin/demo/index', 'permission' => 'demo:dashboard:view'],
            ['name' => '插件列表', 'path' => '/plugin/demo/plugins', 'permission' => 'system:plugin:list'],
        ],
        'routes' => [['path' => '/plugin/demo/index', 'name' => 'Plugin_demo_Index', 'component' => 'Index', 'meta' => ['permission' => 'demo:dashboard:view']]],
    ],
    'resources' => ['public' => ['source' => 'resources/public', 'target' => 'plugin-assets/demo/public']],
    'migrations' => ['path' => 'database/migrations'],
    'storage' => ['path' => 'storage'],
    'purge' => ['supported' => true],
];
file_put_contents($plugin . '/plugin.json', json_encode($valid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
schemaExpect(Manifest::fromDirectory($plugin)->code() === 'demo', '完整合法 manifest 必须通过同一 schema validator');

$cases = [];
$case = $valid; $case['surprise'] = true; $cases[] = [$case, '未知根字段必须拒绝'];
$case = $valid; unset($case['code']); $cases[] = [$case, '缺少 code 必须拒绝'];
$case = $valid; $case['code'] = 'Demo'; $cases[] = [$case, 'code 必须符合小写标识正则'];
$case = $valid; $case['title'] = '旧标题'; $cases[] = [$case, '旧根 title 必须拒绝'];
$case = $valid; unset($case['requires']); $cases[] = [$case, '缺少 requires 必须拒绝'];
$case = $valid; $case['adminWeb']['permissions'] = 'demo:view'; $cases[] = [$case, 'adminWeb.permissions 错误类型必须拒绝'];
$case = $valid; $case['entry']['file'] = 'app/demo/controller/Index.php'; $cases[] = [$case, '入口文件必须固定为根 Plugin.php'];
$case = $valid; $case['load'] = []; $cases[] = [$case, 'Manifest v2 必须拒绝 load'];
$case = $valid; $case['channels'] = []; $cases[] = [$case, 'Manifest v2 必须拒绝 channels'];
$case = $valid; $case['resources']['public']['target'] = '../core'; $cases[] = [$case, '资源目标路径越界必须拒绝'];
$case = $valid; $case['entry']['class'] = 'plugins\\other\\Plugin'; $cases[] = [$case, 'entry namespace 与 code 不一致必须拒绝'];
$case = $valid; $case['adminWeb']['permissions'][0]['code'] = 'other:dashboard:view'; $cases[] = [$case, '插件不得声明其他插件命名空间权限'];
$case = $valid; $case['adminWeb']['permissions'][0]['code'] = 'demo:view'; $cases[] = [$case, '插件权限必须使用三段格式'];
$case = $valid; $case['adminWeb']['menu'][0]['permission'] = 'other:dashboard:view'; $cases[] = [$case, '菜单不得引用其他插件权限'];
$case = $valid; $case['adminWeb']['menu'][0]['permission'] = 'demo:settings:view'; $cases[] = [$case, '菜单不得引用未声明权限'];
$case = $valid; $case['adminWeb']['menu'][1]['permission'] = 'system:plugin:delete'; $cases[] = [$case, '菜单不得引用核心写权限'];
$case = $valid; $case['adminWeb']['routes'][0]['meta']['permission'] = 'other:dashboard:view'; $cases[] = [$case, '路由不得引用其他插件权限'];
$case = $valid; unset($case['adminWeb']); $case['admin_web'] = ['entry' => 'entry.js']; $cases[] = [$case, '旧 admin_web 必须拒绝'];
$case = $valid; $case['adminWeb']['rebuildRequired'] = true; $cases[] = [$case, 'rebuildRequired 不得写入 plugin.json'];
$case = $valid; $case['adminWeb']['components'] = []; $cases[] = [$case, 'components 不得为空'];
$case = $valid; $case['adminWeb']['components']['Index'] = 'missing.vue'; $cases[] = [$case, '组件文件必须存在'];
$case = $valid; $case['adminWeb']['routes'][0]['component'] = 'Other'; $cases[] = [$case, '路由组件必须已声明'];
foreach ($cases as [$manifest, $message]) schemaReject($plugin, $manifest, $message);
file_put_contents($plugin . '/plugin.json', json_encode($valid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

Manifest::fromDirectory($plugin);
file_put_contents($plugin . '/app/console/controller/Index.php', '<?php namespace app\\console\\controller\\plugin\\demo; use think\\annotation\\route\\Group; #[Group("other")] final class Index {}');
schemaReject($plugin, $valid, 'Console Group 必须限制在 plugin/code 前缀');
file_put_contents($plugin . '/app/console/controller/Index.php', '<?php namespace app\\console\\controller\\plugin\\demo; use think\\annotation\\route\\Group; #[Group("plugin/demo")] final class Index {}');
file_put_contents($plugin . '/database/migrations/bad.sql', 'SELECT 1;');
schemaReject($plugin, $valid, 'migration 文件名必须被校验');
unlink($plugin . '/database/migrations/bad.sql');
$case = $valid; $case['storage']['path'] = 'storage/missing'; schemaReject($plugin, $case, 'storage.path 必须存在');
file_put_contents($plugin . '/Plugin.php', '<?php namespace plugins\\demo; final class Plugin extends \\app\\common\\plugin\\sdk\\Plugin { protected function initialize(): void {} public function install(): bool { return true; } public function uninstall(): bool { return true; } public function enabled(): bool { return true; } public function disabled(): bool { return true; } }');
schemaReject($plugin, $valid, 'purge.supported=true 必须 override purgeData');
file_put_contents($plugin . '/Plugin.php', '<?php namespace plugins\\other; final class Plugin {}');
schemaReject($plugin, $valid, 'Plugin.php namespace 必须与 manifest 一致');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
rmdir($temp);
echo "plugin manifest schema tests: PASS\n";
