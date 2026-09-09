<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/**
 * Base/Local/Remote 只读规划器；读取 Local 后仅返回计划，不签发 token、不写 WAL。
 */
final class ThreeWayMergePlanner
{
    private readonly ContentClassifier $classifier;
    private readonly TextThreeWayMerger $textMerger;

    public function __construct(private readonly string $projectRoot)
    {
        $this->classifier = new ContentClassifier();
        $this->textMerger = new TextThreeWayMerger();
    }

    /** @param list<array<string, mixed>> $inputs */
    public function plan(array $inputs): array
    {
        usort($inputs, static fn (array $left, array $right): int => (string) ($left['path'] ?? '') <=> (string) ($right['path'] ?? ''));
        $files = [];
        foreach ($inputs as $input) {
            $file = $this->planFile($input);
            $artifactType = trim((string) ($input['artifactType'] ?? ''));
            if ($artifactType !== '') {
                $file['artifactType'] = $artifactType;
            }
            $files[] = $file;
        }
        $blocked = array_filter($files, static fn (array $file): bool => in_array($file['status'], [
            'conflict', 'binary-conflict', 'conflict-no-base',
        ], true)) !== [];
        return [
            'dryRun' => true,
            'managed' => true,
            'blocked' => $blocked,
            'files' => $files,
            'planDigest' => hash('sha256', CrudDefinition::canonicalJson($this->digestFiles($files))),
        ];
    }

    private function planFile(array $input): array
    {
        $path = str_replace('\\', '/', (string) ($input['path'] ?? ''));
        $absolute = PathGuard::resolve($this->projectRoot, $path, '项目目录');
        $stat = @lstat($absolute);
        if ($stat !== false && (($stat['mode'] & 0170000) !== 0100000)) {
            throw new InvalidArgumentException('项目目录目标必须是普通文件且禁止符号链接：' . $path);
        }
        $local = $stat === false ? null : file_get_contents($absolute);
        if ($local === false) {
            throw new InvalidArgumentException('无法安全读取 Local：' . $path);
        }

        $localHash = $local === null ? null : hash('sha256', $local);
        $providedBaseHash = is_string($input['baseHash'] ?? null) ? $input['baseHash'] : null;
        $hasBase = (array_key_exists('baseContent', $input) && is_string($input['baseContent'])) || $providedBaseHash !== null;
        $base = is_string($input['baseContent'] ?? null) ? $input['baseContent'] : null;
        if ($base === null && $providedBaseHash !== null && $local !== null && hash_equals($providedBaseHash, $localHash)) {
            $base = $local;
        }
        $remote = array_key_exists('remoteContent', $input) && $input['remoteContent'] !== null
            ? (is_string($input['remoteContent']) ? $input['remoteContent'] : throw new InvalidArgumentException('Remote 内容必须为字符串或 null'))
            : null;
        $baseHash = $base === null ? $providedBaseHash : $this->verifiedHash($base, $providedBaseHash, 'Base');
        $remoteHash = $this->verifiedHash($remote, $input['remoteHash'] ?? null, 'Remote');
        $artifactType = strtolower((string) ($input['artifactType'] ?? ''));
        $declaredKind = isset($input['contentKind']) ? (string) $input['contentKind'] : null;
        $classification = $this->classifyContents($path, [$base, $local, $remote], $declaredKind);
        if ($classification !== null) {
            return $this->binary($path, $base, $local, $remote, $classification['type']);
        }

        if ($artifactType === 'migration') {
            if ($local === null && $remote === null) {
                return $this->result($path, 'keep-local', $baseHash, null, null, null, null);
            }
            return $local === null
                ? $this->result($path, 'create', $baseHash, null, $remoteHash, $remoteHash, $remoteHash, $remote)
                : $this->result($path, 'conflict', $baseHash, $localHash, $remoteHash, null, null, null, $base, $local, $remote);
        }
        if (!$hasBase) {
            return $local === null
                ? $this->result($path, 'create', null, null, $remoteHash, $remoteHash, $remoteHash, $remote)
                : $this->result($path, 'conflict-no-base', null, $localHash, $remoteHash, null, null, null, null, $local, $remote);
        }
        if ($local === null && $remote === null) {
            return $this->result($path, 'keep-local', $baseHash, null, null, null, null);
        }
        if ($base === null) {
            return $this->result($path, 'conflict', $baseHash, $localHash, $remoteHash, null, null, null, null, $local, $remote);
        }

        return $this->text($path, $base, $local, $remote);
    }

    private function text(string $path, string $base, ?string $local, ?string $remote): array
    {
        $baseHash = hash('sha256', $base);
        $localHash = $local === null ? null : hash('sha256', $local);
        $remoteHash = $remote === null ? null : hash('sha256', $remote);
        if ($remote === null) {
            return $local !== null && hash_equals($baseHash, $localHash)
                ? $this->result($path, 'delete', $baseHash, $localHash, null, null, null)
                : $this->result($path, 'conflict', $baseHash, $localHash, null, null, null, null, $base, $local);
        }
        if ($local === null) {
            return hash_equals($baseHash, $remoteHash)
                ? $this->result($path, 'keep-local', $baseHash, null, $remoteHash, null, $remoteHash)
                : $this->result($path, 'conflict', $baseHash, null, $remoteHash, null, null, null, $base, null, $remote);
        }
        if (hash_equals($localHash, $remoteHash) || hash_equals($remoteHash, $baseHash)) {
            return $this->result($path, 'keep-local', $baseHash, $localHash, $remoteHash, $localHash, $remoteHash, $local);
        }
        if (hash_equals($localHash, $baseHash)) {
            return $this->result($path, 'update', $baseHash, $localHash, $remoteHash, $remoteHash, $remoteHash, $remote);
        }
        $merge = $this->textMerger->merge($base, $local, $remote);
        if ($merge['status'] === 'conflict') {
            return $this->result($path, 'conflict', $baseHash, $localHash, $remoteHash, null, null, null, $base, $local, $remote);
        }
        $merged = (string) $merge['content'];
        return $this->result($path, 'auto-merged', $baseHash, $localHash, $remoteHash, hash('sha256', $merged), $remoteHash, $merged);
    }

    private function classifyContents(string $path, array $contents, ?string $declaredKind): ?array
    {
        if ($declaredKind === 'binary') {
            return $this->classifier->classify($path, '', 'binary');
        }
        foreach ($contents as $content) {
            if (is_string($content) && $content !== '') {
                $classification = $this->classifier->classify($path, $content, $declaredKind);
                if ($classification['kind'] === 'binary') {
                    return $classification;
                }
            }
        }
        return null;
    }

    private function binary(string $path, ?string $base, ?string $local, ?string $remote, string $type): array
    {
        $hashes = [$base === null ? null : hash('sha256', $base), $local === null ? null : hash('sha256', $local), $remote === null ? null : hash('sha256', $remote)];
        if ($base === null) {
            if ($local === null) {
                return $remote === null
                    ? $this->binaryResult($path, 'keep-local', null, null, null, $type, null, null)
                    : $this->binaryResult($path, 'create', null, null, $remote, $type, $hashes[2], $hashes[2], $remote);
            }
            return $remote !== null && hash_equals($hashes[1], $hashes[2])
                ? $this->binaryResult($path, 'keep-local', null, $local, $remote, $type, $hashes[1], $hashes[2])
                : $this->binaryResult($path, 'binary-conflict', null, $local, $remote, $type, null, null);
        }
        if ($remote === null && $local !== null && hash_equals($hashes[0], $hashes[1])) {
            return $this->binaryResult($path, 'delete', $base, $local, null, $type, null, null);
        }
        if ($local !== null && $remote !== null && hash_equals($hashes[1], $hashes[2])) {
            return $this->binaryResult($path, 'keep-local', $base, $local, $remote, $type, $hashes[1], $hashes[2]);
        }
        if ($local !== null && hash_equals($hashes[0], $hashes[1]) && $remote !== null) {
            return $this->binaryResult($path, 'update', $base, $local, $remote, $type, $hashes[2], $hashes[2], $remote);
        }
        if ($remote !== null && hash_equals($hashes[0], $hashes[2])) {
            return $this->binaryResult($path, 'keep-local', $base, $local, $remote, $type, $hashes[1], $hashes[2]);
        }
        return $this->binaryResult($path, 'binary-conflict', $base, $local, $remote, $type, null, null);
    }

    private function binaryResult(string $path, string $status, ?string $base, ?string $local, ?string $remote, string $type, ?string $mergedHash, ?string $nextBaseHash, ?string $content = null): array
    {
        $result = [
            'path' => $path, 'status' => $status,
            'baseHash' => $base === null ? null : hash('sha256', $base), 'localHash' => $local === null ? null : hash('sha256', $local),
            'remoteHash' => $remote === null ? null : hash('sha256', $remote), 'mergedHash' => $mergedHash,
            'nextBaseHash' => $nextBaseHash, 'baseSize' => $base === null ? null : strlen($base), 'localSize' => $local === null ? null : strlen($local),
            'remoteSize' => $remote === null ? null : strlen($remote), 'contentKind' => 'binary', 'contentType' => $type,
        ];
        return $result;
    }

    private function result(string $path, string $status, ?string $baseHash, ?string $localHash, ?string $remoteHash, ?string $mergedHash, ?string $nextBaseHash, ?string $content = null, ?string $base = null, ?string $local = null, ?string $remote = null): array
    {
        $result = compact('path', 'status', 'baseHash', 'localHash', 'remoteHash', 'mergedHash', 'nextBaseHash');
        $result['contentKind'] = 'text';
        if ($content !== null) $result['content'] = $content;
        if (in_array($status, ['conflict', 'conflict-no-base'], true)) {
            $result['baseContent'] = $base;
            $result['localContent'] = $local;
            $result['remoteContent'] = $remote;
        }
        return $result;
    }

    private function verifiedHash(?string $content, mixed $provided, string $label): ?string
    {
        if ($content === null) return null;
        $actual = hash('sha256', $content);
        if ($provided !== null && (!is_string($provided) || !hash_equals($provided, $actual))) {
            throw new InvalidArgumentException($label . ' hash 与内容不一致');
        }
        return $actual;
    }

    private function digestFiles(array $files): array
    {
        return array_map(static function (array $file): array {
            unset($file['content'], $file['baseContent'], $file['localContent'], $file['remoteContent']);
            return $file;
        }, $files);
    }
}
