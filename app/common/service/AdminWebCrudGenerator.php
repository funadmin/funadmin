<?php

declare(strict_types=1);

namespace app\common\service;

use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use InvalidArgumentException;
use RuntimeException;

/**
 * 旧入口兼容适配器，权威实现位于 app/common/crud。
 *
 * 全局 force 已废弃；写入必须携带 dry-run 返回的确认 token 和逐文件覆盖白名单。
 */
class AdminWebCrudGenerator
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
    }

    public function run(
        string $configPath,
        bool $dryRun = true,
        bool $force = false,
        string $confirmToken = '',
        array $allowOverwrite = [],
        string $operator = 'cli'
    ): array {
        if ($force) {
            throw new InvalidArgumentException('全局 force 已废弃，请使用精确 allowOverwrite');
        }
        $rootPath = $this->rootPath();
        $resolvedConfig = $this->resolveConfigPath($rootPath, $configPath);
        $json = file_get_contents($resolvedConfig);
        if ($json === false) {
            throw new RuntimeException('无法读取 CRUD Definition');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['schemaVersion'])) {
            throw new InvalidArgumentException('旧 Node CRUD 配置已弃用，请迁移为版本化 CRUD Definition');
        }
        $definition = CrudDefinition::fromArray($data);
        $generator = new CrudGenerator($rootPath);
        if ($dryRun) {
            $plan = $generator->plan($definition);
            $token = (string) ($plan['confirmToken'] ?? '');
            unset($plan['confirmToken']);
            $result = [
                'plan' => $plan,
                'sensitive' => ['confirmToken' => $token],
            ];
        } else {
            $generated = $generator->generate($definition, $confirmToken, $allowOverwrite, $operator);
            unset($generated['plan']['confirmToken']);
            $result = $generated;
        }
        return [
            'success' => true,
            'exitCode' => 0,
            'config' => $this->relativePath($rootPath, $resolvedConfig),
            'dryRun' => $dryRun,
            'output' => $this->summary($result['plan']),
            'error' => '',
        ] + $result;
    }

    private function resolveConfigPath(string $rootPath, string $configPath): string
    {
        $configPath = trim($configPath);
        if ($configPath === '' || strtolower(pathinfo($configPath, PATHINFO_EXTENSION)) !== 'json') {
            throw new InvalidArgumentException('必须指定项目目录内的 JSON 配置文件');
        }
        $candidate = $this->isAbsolutePath($configPath) ? $configPath : $rootPath . ltrim($configPath, '/\\');
        $resolved = realpath($candidate);
        $resolvedRoot = realpath($rootPath);
        if ($resolved === false || !is_file($resolved)) {
            throw new InvalidArgumentException('CRUD 配置文件不存在');
        }
        if ($resolvedRoot === false || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $resolvedRoot . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('CRUD 配置文件必须位于项目目录内');
        }
        return $resolved;
    }

    private function rootPath(): string
    {
        $root = $this->projectRoot ?? root_path();
        return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function summary(array $plan): string
    {
        $lines = ['CRUD 生成计划（dry-run 为默认行为）：'];
        foreach ($plan['files'] as $file) {
            $lines[] = sprintf('  [%s] %s %s', $file['status'], $file['path'], $file['hash']);
        }
        return implode(PHP_EOL, $lines);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function relativePath(string $rootPath, string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($rootPath)));
    }
}
