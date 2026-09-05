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

    public function plan(CrudDefinition $definition, array $generatedFiles): array
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
            $files[] = [
                'path' => str_replace('\\', '/', $relativePath),
                'status' => $status,
                'hash' => $newHash,
                'previousHash' => $oldHash,
                'content' => $content,
                'diff' => $this->diff($existing === false ? '' : $existing, $content),
            ];
        }
        $tokenSource = [
            'definitionHash' => $definition->hash(),
            'files' => array_map(static fn (array $file): array => [
                'path' => $file['path'],
                'status' => $file['status'],
                'hash' => $file['hash'],
                'previousHash' => $file['previousHash'],
            ], $files),
        ];
        $planDigest = hash('sha256', CrudDefinition::canonicalJson($tokenSource));
        return [
            'dryRun' => true,
            'definitionHash' => $definition->hash(),
            'files' => $files,
            'planDigest' => $planDigest,
            'confirmToken' => $this->tokens->issue($planDigest),
        ];
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
