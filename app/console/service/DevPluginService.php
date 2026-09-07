<?php

declare(strict_types=1);

namespace app\console\service;

use Closure;
use fun\plugins\Manifest;
use fun\plugins\PluginArchiveService;
use fun\plugins\PluginScaffolder;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** 插件开发工具应用服务，统一编排骨架、Manifest 校验、归档与无密钥审计。 */
final class DevPluginService
{
    /** @var Closure(string): void */
    private readonly Closure $archiveVerifier;

    /** @var Closure(array): int|string */
    private readonly Closure $auditWriter;

    /** @var Closure(string, string, callable): array */
    private readonly Closure $archivePackager;

    public function __construct(
        private readonly string $projectRoot,
        ?callable $archiveVerifier = null,
        ?callable $auditWriter = null,
        ?callable $archivePackager = null
    ) {
        $this->archiveVerifier = Closure::fromCallable($archiveVerifier ?? function (string $archive): void {
            $packages = PluginPackageService::instance();
            $staged = $packages->stage($archive);
            $packages->discard($staged);
        });
        $this->auditWriter = Closure::fromCallable($auditWriter ?? static function (array $audit): string {
            $auditId = bin2hex(random_bytes(12));
            trace('插件开发操作：' . json_encode(['auditId' => $auditId] + $audit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'info');
            return $auditId;
        });
        $pluginsDirectory = $this->pluginsDirectory();
        $this->archivePackager = Closure::fromCallable($archivePackager ?? static function (string $code, string $output, callable $verify) use ($pluginsDirectory): array {
            return (new PluginArchiveService($pluginsDirectory, $verify))->package($code, $output);
        });
    }

    public function previewCreate(array $input): array
    {
        $options = $this->createOptions($input);
        $relativeTarget = 'plugins/' . $options['name'];
        $target = $this->pluginsDirectory() . DIRECTORY_SEPARATOR . $options['name'];
        $conflicts = file_exists($target) || is_link($target) ? [$relativeTarget] : [];
        $files = $conflicts === [] ? $this->previewSkeleton($options) : [];
        $plan = ['operation' => 'create', 'target' => $relativeTarget, 'files' => $files];
        return [
            'auditId' => $this->audit('create-preview', $options['name'], 'planned', $plan),
            'plan' => $plan,
            'conflicts' => $conflicts,
        ];
    }

    public function create(array $input): array
    {
        $options = $this->createOptions($input);
        try {
            $result = $this->scaffolder()->scaffold(
                $options['name'],
                $options['title'],
                $options['application'],
                $options['console'],
                $options['adminWeb']
            );
            $plan = ['operation' => 'create', 'status' => 'created', 'target' => 'plugins/' . $options['name']];
            return [
                'auditId' => $this->audit('create', $options['name'], 'completed', $plan),
                'plan' => $plan,
                'conflicts' => [],
                'plugin' => ['code' => $result['code'], 'directory' => 'plugins/' . $result['code']],
            ];
        } catch (Throwable $exception) {
            $this->audit('create', $options['name'], 'failed', ['error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function validate(string $code): array
    {
        PluginScaffolder::assertValidName($code);
        try {
            $manifest = Manifest::fromDirectory($this->pluginsDirectory() . DIRECTORY_SEPARATOR . $code);
            $data = $manifest->toArray();
            return [
                'auditId' => $this->audit('validate', $code, 'completed', ['version' => $manifest->version()]),
                'valid' => true,
                'manifest' => $data,
                'conflicts' => [],
            ];
        } catch (Throwable $exception) {
            $this->audit('validate', $code, 'failed', ['error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function package(string $code, string $requestedOutput = ''): array
    {
        if (trim($requestedOutput) !== '') {
            throw new InvalidArgumentException('API 不允许指定 package 输出路径');
        }
        PluginScaffolder::assertValidName($code);
        $manifest = Manifest::fromDirectory($this->pluginsDirectory() . DIRECTORY_SEPARATOR . $code);
        $relative = 'runtime/download/plugins/' . $code . '-' . $manifest->version() . '.zip';
        $output = $this->projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        try {
            $result = ($this->archivePackager)($code, $output, $this->archiveVerifier);
            $plan = [
                'operation' => 'package',
                'status' => 'created',
                'output' => $relative,
                'sha256' => $result['sha256'],
                'treeHash' => $result['tree_hash'],
                'files' => $result['files'],
            ];
            return [
                'auditId' => $this->audit('package', $code, 'completed', $plan),
                'plan' => $plan,
                'conflicts' => [],
                'downloadPath' => $relative,
                'sha256' => $result['sha256'],
            ];
        } catch (Throwable $exception) {
            $this->audit('package', $code, 'failed', ['error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function options(): array
    {
        $items = [];
        foreach (glob($this->pluginsDirectory() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (is_link($directory)) {
                continue;
            }
            try {
                $manifest = Manifest::fromDirectory($directory);
            } catch (Throwable) {
                continue;
            }
            $data = $manifest->toArray();
            $application = is_dir($directory . '/app/' . $manifest->code());
            $console = is_dir($directory . '/app/console') && isset($data['adminWeb']);
            $scopes = [];
            if ($application) {
                $scopes[] = 'application';
            }
            if ($console) {
                $scopes[] = 'console';
            }
            if ($application && $console) {
                $scopes[] = 'both';
            }
            if ($scopes === []) {
                continue;
            }
            $items[] = [
                'code' => $manifest->code(),
                'name' => $manifest->name(),
                'manifestVersion' => (int) ($data['schema_version'] ?? 0),
                'version' => $manifest->version(),
                'scopes' => $scopes,
            ];
        }
        usort($items, static fn (array $left, array $right): int => strcmp($left['code'], $right['code']));
        return $items;
    }

    private function previewSkeleton(array $options): array
    {
        $previewRoot = $this->projectRoot() . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'plugin-preview-' . bin2hex(random_bytes(6));
        try {
            $temporaryPlugins = $previewRoot . DIRECTORY_SEPARATOR . 'plugins';
            $result = (new PluginScaffolder($temporaryPlugins))->scaffold(
                $options['name'],
                $options['title'],
                $options['application'],
                $options['console'],
                $options['adminWeb']
            );
            $files = [];
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($result['directory'], \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($result['directory']) + 1));
                $files[] = ['path' => 'plugins/' . $options['name'] . '/' . $relative, 'status' => 'create'];
            }
            usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
            return $files;
        } finally {
            $this->removeDirectory($previewRoot);
        }
    }

    private function createOptions(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        PluginScaffolder::assertValidName($name);
        return [
            'name' => $name,
            'title' => trim((string) ($input['title'] ?? '')),
            'application' => $this->boolean($input, 'application', true),
            'console' => $this->boolean($input, 'console', true),
            'adminWeb' => $this->boolean($input, 'adminWeb', true),
        ];
    }

    private function boolean(array $input, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $input)) {
            return $default;
        }
        if (!is_bool($input[$key])) {
            throw new InvalidArgumentException($key . ' 必须为布尔值');
        }
        return $input[$key];
    }

    private function audit(string $operation, string $code, string $status, array $details): int|string
    {
        return ($this->auditWriter)([
            'operation' => $operation,
            'pluginCode' => $code,
            'status' => $status,
            'details' => $this->sanitize($details),
        ]);
    }

    private function sanitize(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            if (preg_match('/(?:password|secret|token|credential|private[_-]?key|api[_-]?key)/i', (string) $key)) {
                continue;
            }
            $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }
        return $safe;
    }

    private function scaffolder(): PluginScaffolder
    {
        return new PluginScaffolder($this->pluginsDirectory());
    }

    private function pluginsDirectory(): string
    {
        return $this->projectRoot() . DIRECTORY_SEPARATOR . 'plugins';
    }

    private function projectRoot(): string
    {
        return rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
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
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
