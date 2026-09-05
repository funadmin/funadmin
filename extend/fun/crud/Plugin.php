<?php

declare(strict_types=1);

namespace fun\crud;

use app\backend\service\PluginService;
use fun\helper\FileHelper;
use fun\helper\ZipHelper;
use RuntimeException;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/**
 * 生成符合 plugin.json 契约的新式插件骨架，并代理插件生命周期命令。
 */
final class Plugin extends Command
{
    private array $options = [];

    protected function configure(): void
    {
        $this->setName('plugin')
            ->setDescription('生成 plugin.json 插件骨架或执行插件生命周期操作')
            ->addOption('name', null, Option::VALUE_REQUIRED, '插件名，小写字母开头且仅含小写字母和数字')
            ->addOption('title', null, Option::VALUE_OPTIONAL, '插件标题')
            ->addOption('description', null, Option::VALUE_OPTIONAL, '插件描述')
            ->addOption('author', null, Option::VALUE_OPTIONAL, '插件作者', 'FunAdmin')
            ->addOption('version', null, Option::VALUE_OPTIONAL, '插件语义化版本', '1.0.0')
            ->addOption('funadmin-version', null, Option::VALUE_OPTIONAL, '所需 FunAdmin 版本约束', '>=1.0.0')
            ->addOption('force', 'f', Option::VALUE_NONE, '覆盖已存在的骨架文件')
            ->addOption('package', null, Option::VALUE_NONE, '将插件目录打包到 public')
            ->addOption('install', null, Option::VALUE_NONE, '安装插件')
            ->addOption('uninstall', null, Option::VALUE_NONE, '卸载插件')
            ->addOption('enable', null, Option::VALUE_NONE, '启用插件')
            ->addOption('disable', null, Option::VALUE_NONE, '禁用插件');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $this->options = $this->readOptions($input);
            $this->assertOptions();

            $action = $this->lifecycleAction();
            if ($action !== null) {
                return $this->executeLifecycle($action, $output);
            }
            if ($this->options['package']) {
                return $this->package($output);
            }

            $this->generate();
            $output->info('插件骨架生成成功：' . $this->pluginDirectory());
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }

    private function readOptions(Input $input): array
    {
        $name = trim((string) $input->getOption('name'));
        return [
            'name' => $name,
            'title' => trim((string) ($input->getOption('title') ?: $name)),
            'description' => trim((string) ($input->getOption('description') ?: $name . ' 插件')),
            'author' => trim((string) $input->getOption('author')),
            'version' => trim((string) $input->getOption('version')),
            'funadmin_version' => trim((string) $input->getOption('funadmin-version')),
            'force' => (bool) $input->getOption('force'),
            'package' => (bool) $input->getOption('package'),
            'install' => (bool) $input->getOption('install'),
            'uninstall' => (bool) $input->getOption('uninstall'),
            'enable' => (bool) $input->getOption('enable'),
            'disable' => (bool) $input->getOption('disable'),
        ];
    }

    private function assertOptions(): void
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $this->options['name'])) {
            throw new RuntimeException('插件名仅允许小写字母和数字，且必须以字母开头');
        }
        if (in_array($this->options['name'], ['backend', 'common', 'frontend', 'api', 'install'], true)) {
            throw new RuntimeException('插件名不能使用系统应用名称');
        }
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $this->options['version'])) {
            throw new RuntimeException('插件版本必须是语义化版本');
        }
        $actions = array_filter([
            'package' => $this->options['package'],
            'install' => $this->options['install'],
            'uninstall' => $this->options['uninstall'],
            'enable' => $this->options['enable'],
            'disable' => $this->options['disable'],
        ]);
        if (count($actions) > 1) {
            throw new RuntimeException('一次只能执行一个打包或生命周期操作');
        }
    }

    private function lifecycleAction(): ?string
    {
        foreach (['install', 'uninstall', 'enable', 'disable'] as $action) {
            if ($this->options[$action]) {
                return $action;
            }
        }
        return null;
    }

    private function executeLifecycle(string $action, Output $output): int
    {
        if (!is_dir($this->pluginDirectory())) {
            throw new RuntimeException('插件目录不存在');
        }
        $service = app(PluginService::class);
        match ($action) {
            'install' => $service->installPlugin($this->options['name'], 'install'),
            'uninstall' => $service->uninstallPlugin($this->options['name']),
            'enable' => $service->enablePlugin($this->options['name']),
            'disable' => $service->disablePlugin($this->options['name']),
        };
        $output->info(match ($action) {
            'install' => '安装成功',
            'uninstall' => '卸载成功',
            'enable' => '启用成功',
            'disable' => '禁用成功',
        });
        return 0;
    }

    private function generate(): void
    {
        $files = [
            'plugin.json' => 'json.tpl',
            'Plugin.php' => 'plugin.tpl',
            'config/services.php' => 'services.tpl',
            'config/events.php' => 'events.tpl',
            'routes/plugin.php' => 'routes.tpl',
            'migrations/001_initial.sql' => 'migration.tpl',
            'resources/admin/entry.js' => 'admin-entry.tpl',
        ];
        foreach ($files as $relativePath => $template) {
            $this->writeFile($relativePath, $this->render($template));
        }
        $this->writeFile('resources/public/.gitkeep', '');
    }

    private function render(string $template): string
    {
        $source = file_get_contents($this->templateDirectory() . DIRECTORY_SEPARATOR . $template);
        if ($source === false) {
            throw new RuntimeException('插件模板不存在：' . $template);
        }
        return str_replace(
            ['{%plugin%}', '{%plugin_dir%}', '{%title%}', '{%description%}', '{%author%}', '{%version%}', '{%funadmin_version%}'],
            [$this->options['name'], PLUGIN_NAMESPACE, $this->options['title'], $this->options['description'], $this->options['author'], $this->options['version'], $this->options['funadmin_version']],
            $source
        );
    }

    private function writeFile(string $relativePath, string $content): void
    {
        $file = $this->pluginDirectory() . $relativePath;
        if (is_file($file) && !$this->options['force']) {
            throw new RuntimeException('文件已存在，使用 --force 覆盖：' . $relativePath);
        }
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建目录：' . $directory);
        }
        if (file_put_contents($file, $content) === false) {
            throw new RuntimeException('无法写入文件：' . $file);
        }
    }

    private function package(Output $output): int
    {
        $source = $this->pluginDirectory();
        if (!is_dir($source)) {
            throw new RuntimeException('插件目录不存在');
        }
        $temporary = root_path('runtime/plugin-package-' . $this->options['name'] . '-' . bin2hex(random_bytes(4)));
        $archive = public_path($this->options['name'] . '-' . $this->options['version'] . '.zip');
        try {
            FileHelper::copyDir($source, $temporary);
            ZipHelper::zip($archive, $temporary);
            if (!is_file($archive) || filesize($archive) === 0) {
                throw new RuntimeException('插件打包失败');
            }
        } finally {
            if (is_dir($temporary)) {
                FileHelper::delDir($temporary);
            }
        }
        $output->info('打包成功：' . $archive);
        return 0;
    }

    private function pluginDirectory(): string
    {
        return rtrim(root_path(PLUGIN_DIR . '/' . $this->options['name']), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function templateDirectory(): string
    {
        return rtrim(root_path('extend/fun/crud/tpl/plugin'), DIRECTORY_SEPARATOR);
    }
}
