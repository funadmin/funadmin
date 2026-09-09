<?php

declare(strict_types=1);

namespace app\common\crud;

/**
 * 纯内存生成计划，默认不产生任何项目文件变更。
 */
final class GenerationPlanner
{
    private readonly ConfirmationToken $tokens;

    public function __construct(private readonly string $projectRoot, ?ConfirmationToken $tokens = null)
    {
        $this->tokens = $tokens ?? new ConfirmationToken($projectRoot);
    }

    public function plan(CrudDefinition $definition, array $generatedFiles, array $preconditions = [], array $operations = []): array
    {
        ksort($generatedFiles, SORT_STRING);
        $files = [];
        foreach ($generatedFiles as $relativePath => $content) {
            if (!is_string($relativePath) || !is_string($content)) {
                throw new \InvalidArgumentException('生成文件路径和内容必须为字符串');
            }
            $absolutePath = PathGuard::resolve($this->projectRoot, $relativePath, '项目目录');
            $blocked = file_exists($absolutePath) && !is_file($absolutePath);
            $existing = is_file($absolutePath) ? file_get_contents($absolutePath) : false;
            $oldHash = $existing === false ? null : hash('sha256', $existing);
            $newHash = hash('sha256', $content);
            $status = $blocked
                ? 'blocked'
                : ($existing === false ? 'create' : (hash_equals($oldHash, $newHash) ? 'unchanged' : 'conflict'));
            $operation = $operations[$relativePath] ?? null;
            if (is_array($operation) && ($operation['type'] ?? '') === 'manifest-merge-cas') {
                $operation['previousHash'] = $oldHash;
                $operation['merged'] = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            }
            $files[] = [
                'path' => str_replace('\\', '/', $relativePath),
                'status' => $status,
                'hash' => $newHash,
                'previousHash' => $oldHash,
                'content' => $content,
                'diff' => $this->diff($existing === false ? '' : $existing, $content),
                'operation' => $operation,
            ];
        }
        $tokenSource = [
            'definitionHash' => $definition->hash(),
            'preconditions' => $preconditions,
            'files' => array_map(static fn (array $file): array => [
                'path' => $file['path'],
                'status' => $file['status'],
                'hash' => $file['hash'],
                'previousHash' => $file['previousHash'],
                'operation' => $file['operation'],
            ], $files),
        ];
        $planDigest = hash('sha256', CrudDefinition::canonicalJson($tokenSource));
        return [
            'dryRun' => true,
            'definitionHash' => $definition->hash(),
            'preconditions' => $preconditions,
            'files' => $files,
            'planDigest' => $planDigest,
            'confirmToken' => $this->tokens->issue($planDigest),
        ];
    }

    /**
     * business managed 正式生成专用只读入口；baseline 由调用方显式提供。
     */
    public function planManaged(CrudDefinition $definition, array $generatedFiles, array $baselines): array
    {
        ksort($generatedFiles, SORT_STRING);
        $artifactByPath = [];
        foreach ((array) $definition->get('generationTargets', []) as $artifactType => $path) {
            if (is_string($path)) {
                $artifactByPath[str_replace('\\', '/', $path)] = (string) $artifactType;
            }
        }
        $baselineByPath = [];
        foreach ($baselines as $baseline) {
            if (!is_array($baseline) || !is_string($baseline['path'] ?? null)) {
                throw new \InvalidArgumentException('生成 baseline 必须包含字符串 path');
            }
            $path = str_replace('\\', '/', $baseline['path']);
            if (isset($baselineByPath[$path])) {
                throw new \InvalidArgumentException('生成 baseline 路径重复：' . $path);
            }
            if (isset($artifactByPath[$path])) {
                $baseline['artifactType'] = $artifactByPath[$path];
            } elseif (($baseline['artifactType'] ?? '') === 'migration') {
                unset($baseline['artifactType']);
            }
            $baselineByPath[$path] = $baseline;
        }
        $inputs = [];
        foreach ($generatedFiles as $path => $content) {
            if (!is_string($path) || !is_string($content)) {
                throw new \InvalidArgumentException('生成文件路径和内容必须为字符串');
            }
            $normalizedPath = str_replace('\\', '/', $path);
            $baseline = $baselineByPath[$normalizedPath] ?? ['path' => $normalizedPath];
            $baseline['path'] = $normalizedPath;
            if (isset($artifactByPath[$normalizedPath])) {
                $baseline['artifactType'] = $artifactByPath[$normalizedPath];
            }
            $baseline['remoteContent'] = $content;
            $baseline['remoteHash'] = hash('sha256', $content);
            $inputs[] = $baseline;
            unset($baselineByPath[$normalizedPath]);
        }
        foreach ($baselineByPath as $baseline) {
            $baseline['remoteContent'] = null;
            $inputs[] = $baseline;
        }
        $plan = (new ThreeWayMergePlanner($this->projectRoot))->plan($inputs);
        $plan['definitionHash'] = $definition->hash();
        $plan['planDigest'] = hash('sha256', CrudDefinition::canonicalJson([
            'definitionHash' => $definition->hash(),
            'files' => array_map(static function (array $file): array {
                unset($file['content'], $file['baseContent'], $file['localContent'], $file['remoteContent']);
                return $file;
            }, $plan['files']),
        ]));
        return $plan;
    }

    private function diff(string $old, string $new): string
    {
        if ($old === $new) {
            return '';
        }
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);
        $lines = ['--- current', '+++ generated'];
        foreach (array_slice($oldLines, 0, 80) as $line) {
            $lines[] = '-' . $line;
        }
        foreach (array_slice($newLines, 0, 80) as $line) {
            $lines[] = '+' . $line;
        }
        return implode("\n", $lines);
    }
}
