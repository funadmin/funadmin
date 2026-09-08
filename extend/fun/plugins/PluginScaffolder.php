<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;
use Throwable;

/** 原子生成 Manifest v2 插件开发骨架。 */
final class PluginScaffolder
{
    private const RESERVED_NAMES = ['console', 'api', 'index', 'frontend', 'install', 'common'];

    public function __construct(private readonly string $pluginsDirectory)
    {
    }

    public function plan(
        string $name,
        bool $application = true,
        bool $console = true,
        bool $adminWeb = true
    ): array {
        self::assertValidName($name);
        $files = ['plugin.json', 'Plugin.php', 'database/migrations/.gitkeep', 'resources/public/.gitkeep', 'storage/.gitkeep'];
        if ($application) {
            array_push($files, "app/{$name}/controller/Index.php", "app/{$name}/config/app.php", "app/{$name}/route/app.php", "app/{$name}/lang/zh-cn.php", "app/{$name}/event.php", "app/{$name}/provider.php");
            foreach (['model', 'service', 'middleware', 'view'] as $layer) $files[] = "app/{$name}/{$layer}/.gitkeep";
        }
        if ($console) {
            $files[] = 'app/console/controller/Index.php';
            foreach (['model', 'service', 'validate', 'middleware'] as $layer) $files[] = "app/console/{$layer}/.gitkeep";
        }
        if ($adminWeb) array_push($files, 'admin-web/api.ts', 'admin-web/types.ts', 'admin-web/pages/Index.vue', 'admin-web/pages/components/EditDialog.vue');
        sort($files, SORT_STRING);
        return ['code' => $name, 'target' => 'plugins/' . $name, 'files' => $files];
    }

    public function scaffold(
        string $name,
        string $title = '',
        bool $application = true,
        bool $console = true,
        bool $adminWeb = true
    ): array {
        $this->assertName($name);
        $target = rtrim($this->pluginsDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target) || is_link($target)) {
            throw new RuntimeException('插件目标已存在：' . $target);
        }

        $stageRoot = rtrim($this->pluginsDirectory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . '.' . $name . '-stage-' . bin2hex(random_bytes(6));
        $stage = $stageRoot . DIRECTORY_SEPARATOR . $name;
        try {
            $this->createDirectory($stage);
            $this->writeSkeleton($stage, $name, trim($title) !== '' ? trim($title) : $name, $application, $console, $adminWeb);
            Manifest::fromDirectory($stage);
            if (!rename($stage, $target)) {
                throw new RuntimeException('无法原子提交插件目录：' . $target);
            }
            @rmdir($stageRoot);
        } catch (Throwable $exception) {
            $this->removeDirectory($stageRoot);
            throw $exception;
        }

        return ['code' => $name, 'directory' => $target];
    }

    public static function assertValidName(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $name) !== 1) {
            throw new RuntimeException('插件名称格式必须匹配 ^[a-z][a-z0-9]*$');
        }
        if (in_array($name, self::RESERVED_NAMES, true)) {
            throw new RuntimeException('插件名称属于保留应用名：' . $name);
        }
    }

    private function assertName(string $name): void
    {
        self::assertValidName($name);
        if (!is_dir($this->pluginsDirectory)) {
            $this->createDirectory($this->pluginsDirectory);
        }
    }

    private function writeSkeleton(
        string $directory,
        string $name,
        string $title,
        bool $application,
        bool $console,
        bool $adminWeb
    ): void {
        $manifest = [
            'schema_version' => 2,
            'code' => $name,
            'name' => $title,
            'description' => '',
            'author' => '',
            'version' => '1.0.0',
            'requires' => ['php' => '>=8.1', 'funadmin' => '>=1.0.0', 'plugins' => (object) []],
            'entry' => ['class' => 'plugins\\' . $name . '\\Plugin', 'file' => 'Plugin.php'],
            'resources' => ['public' => ['source' => 'resources/public', 'target' => 'plugin-assets/' . $name . '/public']],
            'migrations' => ['path' => 'database/migrations'],
            'storage' => ['path' => 'storage'],
            'purge' => ['supported' => false],
        ];
        if ($adminWeb) {
            $manifest['adminWeb'] = [
                'source' => 'admin-web',
                'components' => ['Index' => 'pages/Index.vue'],
                'minFrontendVersion' => '1.0.0',
                'permissions' => [[
                    'code' => $name . ':item:list',
                    'name' => '查看' . $title,
                ]],
                'menu' => [[
                    'name' => $title,
                    'path' => '/plugin/' . $name . '/items',
                    'permission' => $name . ':item:list',
                ]],
                'routes' => [[
                    'path' => '/plugin/' . $name . '/items',
                    'name' => ucfirst($name) . 'Index',
                    'component' => 'Index',
                    'meta' => ['permission' => $name . ':item:list'],
                ]],
            ];
        }

        $this->write($directory . '/plugin.json', json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . "\n");
        $this->write($directory . '/Plugin.php', $this->entrySource($name));
        $this->keep($directory . '/database/migrations');
        $this->keep($directory . '/resources/public');
        $this->keep($directory . '/storage');

        if ($application) {
            $applicationRoot = $directory . '/app/' . $name;
            $this->write($applicationRoot . '/controller/Index.php', $this->applicationControllerSource($name));
            foreach (['model', 'service', 'middleware', 'view'] as $layer) {
                $this->keep($applicationRoot . '/' . $layer);
            }
            $this->write($applicationRoot . '/config/app.php', $this->returnArraySource());
            $this->write($applicationRoot . '/route/app.php', $this->returnArraySource());
            $this->write($applicationRoot . '/lang/zh-cn.php', $this->returnArraySource());
            $this->write($applicationRoot . '/event.php', $this->eventSource());
            $this->write($applicationRoot . '/provider.php', $this->returnArraySource());
        }
        if ($console) {
            $this->write($directory . '/app/console/controller/Index.php', $this->consoleControllerSource($name));
            foreach (['model', 'service', 'validate', 'middleware'] as $layer) {
                $this->keep($directory . '/app/console/' . $layer);
            }
        }
        if ($adminWeb) {
            $adminWebRoot = $directory . '/admin-web';
            $this->write($adminWebRoot . '/api.ts', $this->adminWebApiSource($name));
            $this->write($adminWebRoot . '/types.ts', $this->adminWebTypesSource());
            $this->write($adminWebRoot . '/pages/Index.vue', $this->adminWebSource($name, $title));
            $this->write($adminWebRoot . '/pages/components/EditDialog.vue', $this->adminWebDialogSource());
        }
    }

    private function entrySource(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace plugins\\{$name};

use fun\\Plugins;

final class Plugin extends Plugins
{
    public function install(): bool { return true; }
    public function uninstall(): bool { return true; }
    public function enabled(): bool { return true; }
    public function disabled(): bool { return true; }
    public function beforeUpdate(string \$fromVersion, string \$toVersion, bool \$migrate): bool { return true; }
    public function afterUpdate(string \$fromVersion, string \$toVersion, bool \$migrate): bool { return true; }
    public function purgeData(): bool { return false; }
}
PHP;
    }

    private function applicationControllerSource(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace app\\{$name}\\controller;

use think\\annotation\\route\\Get;
use think\\Response;

final class Index
{
    #[Get('index')]
    public function index(): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}
PHP;
    }

    private function consoleControllerSource(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace app\\console\\controller\\plugin\\{$name};

use app\\console\\controller\\base\\AdminApiController;
use app\\console\\middleware\\CheckAdminApiCsrf;
use app\\console\\middleware\\CheckAdminApiRole;
use app\\console\\middleware\\SystemLog;
use think\\annotation\\route\\Get;
use think\\annotation\\route\\Group;
use think\\Response;

#[Group('plugin/{$name}')]
final class Index extends AdminApiController
{
    protected array \$middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('index')]
    public function index(): Response
    {
        return json(['code' => 200, 'msg' => 'success', 'data' => []]);
    }
}
PHP;
    }

    private function returnArraySource(): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n";
    }

    private function eventSource(): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nreturn ['bind' => [], 'listen' => [], 'subscribe' => []];\n";
    }

    private function adminWebApiSource(string $name): string
    {
        return "import http from '@/utils/http';\nimport type { Item } from './types';\n\nexport const listItems = () => http.get<Item[]>('/plugin/{$name}/index');\n";
    }

    private function adminWebTypesSource(): string
    {
        return "export interface Item {\n  id: number;\n  name: string;\n}\n";
    }

    private function adminWebSource(string $name, string $title): string
    {
        $escaped = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return <<<VUE
<template>
  <section>
    <h1>{$escaped}</h1>
    <el-button v-perm="'{$name}:item:list'" @click="load">刷新</el-button>
  </section>
</template>

<script setup lang="ts">
import { listItems } from '../api';

const load = async () => { await listItems(); };
</script>
VUE;
    }

    private function adminWebDialogSource(): string
    {
        return "<template>\n  <el-dialog title=\"编辑\" />\n</template>\n";
    }

    private function keep(string $directory): void
    {
        $this->write(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.gitkeep', '');
    }

    private function write(string $file, string $content): void
    {
        $this->createDirectory(dirname($file));
        if (file_put_contents($file, $content, LOCK_EX) === false) {
            throw new RuntimeException('无法写入插件骨架文件：' . $file);
        }
    }

    private function createDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建目录：' . $directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $item->isDir() && !$item->isLink() ? rmdir($path) : unlink($path);
        }
        rmdir($directory);
    }
}
