<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\contract\DockerProcessRunner;
use app\admin\ai\infrastructure\ProcessResult;

final class SandboxProcessFixture implements DockerProcessRunner
{
    public array $calls = [];
    public string $container = 'console-sandbox';
    public string $volume = '';
    public bool $failExport = false;
    public bool $failCleanup = false;
    public string $tree = '';
    private array $labels = [];

    public function __construct(private readonly string $root) {}

    public function run(array $argv, int $timeoutSeconds): ProcessResult
    {
        $this->calls[] = $argv;
        if (($argv[1] ?? '') === 'rm' && $this->failCleanup) return new ProcessResult(1, '', 'cleanup failed');
        $stdout = '';
        if (($argv[1] ?? '') === 'volume' && ($argv[2] ?? '') === 'create') {
            $this->volume = end($argv);
            foreach ($argv as $index => $arg) if ($arg === '--label') { [$key, $value] = explode('=', $argv[$index + 1], 2); $this->labels[$key] = $value; }
        } elseif (($argv[1] ?? '') === 'create') {
            $this->labels['com.funadmin.ai-volume'] = $this->volume;
            $stdout = $this->container;
        } elseif (($argv[1] ?? '') === 'cp') {
            if (str_starts_with($argv[2], $this->container . ':')) {
                if ($this->failExport) return new ProcessResult(1, '', 'export failed');
                $this->copyTree($this->tree, $argv[3]);
            } else {
                $this->tree = $this->root . '/container-tree';
                $this->copyTree(rtrim($argv[2], '/.'), $this->tree);
            }
        } elseif (($argv[1] ?? '') === 'exec') {
            $command = array_slice($argv, 3);
            if (($command[0] ?? '') === 'cat') $stdout = file_get_contents($this->tree . '/' . substr($command[2], strlen('/workspace/')));
            if (($command[0] ?? '') === 'php' && isset($command[3], $command[4])) {
                file_put_contents($this->tree . '/' . substr($command[3], strlen('/workspace/')), $command[4]);
            }
            if (array_slice($command, 0, 2) === ['git', 'diff']) $stdout = 'diff --git a/example.txt b/example.txt';
        } elseif (($argv[1] ?? '') === 'inspect' || (($argv[1] ?? '') === 'volume' && ($argv[2] ?? '') === 'inspect')) {
            $stdout = json_encode($this->labels, JSON_THROW_ON_ERROR);
        }
        return new ProcessResult(0, $stdout, '');
    }

    private function copyTree(string $source, string $target): void
    {
        if (!is_dir($target)) mkdir($target, 0700, true);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $file) {
            $path = $target . '/' . substr($file->getPathname(), strlen($source) + 1);
            $file->isDir() ? mkdir($path, 0700, true) : copy($file->getPathname(), $path);
        }
    }
}
