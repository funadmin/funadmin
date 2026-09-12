<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\PathGuard;
use app\common\crud\ThreeWayMergePlanner;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/** 校验私有导出物，规划并应用通用 AI 代码变更集。 */
final class AiChangeSetService
{
    private const HASH_PATTERN = '/^[a-f0-9]{64}$/';
    private const MAX_BUNDLE_BYTES = 67108864;
    private const MAX_BUNDLE_FILE_BYTES = 16777216;
    private const MAX_BUNDLE_FILES = 100000;
    private const MAX_BUNDLE_LINE_BYTES = 25165824;
    private const FORBIDDEN_SEGMENTS = ['.git', 'runtime', 'node_modules', 'vendor', 'dist', 'build', '.cache', 'coverage'];
    private const SECRET_NAMES = ['.env', '.npmrc', '.pypirc', 'credentials', 'credentials.json', 'id_rsa', 'id_ed25519', 'known_hosts'];

    private readonly Closure $maximumMigration;

    public function __construct(
        private readonly string $projectRoot = '',
        private readonly string $privateRoot = '',
        private readonly ?ConfirmationToken $tokens = null,
        ?callable $maximumMigration = null,
        private readonly int $maxPublicContentBytes = 65536
    ) {
        $this->maximumMigration = Closure::fromCallable($maximumMigration ?? fn (): int => $this->diskMaximumMigration());
    }

    public static function attributes(array $task, array $artifact, int $createdBy): array
    {
        $baseDigest = (string) ($artifact['baselineDigest'] ?? '');
        $patchDigest = (string) ($artifact['patchSha256'] ?? '');
        $remoteDigest = (string) ($artifact['remoteManifestSha256'] ?? '');
        $manifestDigest = (string) ($artifact['manifestSha256'] ?? $remoteDigest);
        $identity = [
            'conversation_id' => (int) ($task['conversation_id'] ?? 0),
            'task_id' => (int) ($task['id'] ?? 0),
            'base_digest' => $baseDigest,
            'patch_sha256' => $patchDigest,
            'remote_manifest_sha256' => $remoteDigest,
            'manifest_sha256' => $manifestDigest,
        ];
        $digest = hash('sha256', json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return [
            'conversation_id' => $identity['conversation_id'], 'task_id' => $identity['task_id'], 'created_by' => $createdBy,
            'idempotency_key' => 'change-set-' . $digest, 'digest' => $digest, 'base_digest' => $baseDigest,
            'patch_path' => (string) ($artifact['patchPath'] ?? ''), 'patch_sha256' => $patchDigest,
            'base_file_hashes' => $artifact['baselineManifest']['files'] ?? [],
            'manifest' => [
                'baseline_manifest_path' => $artifact['baselineManifestPath'] ?? '',
                'baseline_manifest_sha256' => $artifact['baselineManifestSha256'] ?? '',
                'baseline_bundle_path' => $artifact['baselineBundlePath'] ?? '',
                'baseline_bundle_sha256' => $artifact['baselineBundleSha256'] ?? '',
                'bundle_path' => $artifact['bundlePath'] ?? '', 'bundle_sha256' => $artifact['bundleSha256'] ?? '',
                'remote_manifest_path' => $artifact['manifestPath'] ?? '', 'remote_manifest_sha256' => $remoteDigest,
                'base_digest' => $baseDigest,
                'artifact' => ['admin_id'=>$createdBy, 'conversation_id'=>$identity['conversation_id'], 'task_id'=>$identity['task_id']],
            ],
            'summary' => ['status' => 'exported'], 'status' => 'proposed',
        ];
    }

    /** 返回不包含文件正文的公开计划。 */
    public function preview(array $changeSet, int $adminId, array $selection, bool $canApply, int $conversationId, int $taskId): array
    {
        $this->assertOwned($changeSet, $adminId, $conversationId, $taskId);
        $trusted = $this->trustedArtifacts($changeSet, $selection);
        $plan = $this->buildPlan($trusted, $selection);
        $public = $this->publicPlan($plan);
        $public['changeSetId'] = (int) $changeSet['id'];
        $public['selection'] = $this->selection($selection, array_column($plan['files'], 'path'));
        $public['planDigest'] = $this->boundDigest($changeSet, $adminId, $conversationId, $taskId, $public);
        if (!$public['blocked'] && $canApply) {
            if ($this->tokens === null) throw new RuntimeException('确认 token 服务未配置');
            $public['confirmToken'] = $this->tokens->issue($public['planDigest']);
        }
        return $public;
    }

    /** apply 前重新规划并校验 Local hash、最终审批和一次性 token。 */
    public function apply(array $changeSet, int $adminId, array $selection, string $confirmToken, array $approval, AiChangeSetTransactionService $transaction, int $conversationId, int $taskId): array
    {
        $this->assertOwned($changeSet, $adminId, $conversationId, $taskId);
        $this->assertFinalApproval($approval, $adminId, $conversationId, $taskId);
        if ($this->tokens === null) throw new RuntimeException('确认 token 服务未配置');
        $tokenDigest = $this->tokens->planDigestFromToken($confirmToken);
        $claims = $this->tokens->verify($confirmToken, $tokenDigest);
        $trusted = $this->trustedArtifacts($changeSet, $selection);
        $plan = $this->buildPlan($trusted, $selection);
        $public = $this->publicPlan($plan);
        $public['changeSetId'] = (int) $changeSet['id'];
        $public['selection'] = $this->selection($selection, array_column($plan['files'], 'path'));
        $digest = $this->boundDigest($changeSet, $adminId, $conversationId, $taskId, $public);
        if ($plan['blocked']) throw new RuntimeException('变更集存在 conflict，拒绝 apply', 409);
        if (!hash_equals($digest, $tokenDigest)) throw new RuntimeException('Local hash 已变化，必须重新 preview', 409);
        $this->assertLocalHashes($plan['files']);
        $this->tokens->consume((string) $claims['nonce'], (int) $claims['expiresAt']);
        return $transaction->execute((int) $changeSet['id'], $adminId, $conversationId, $taskId, $plan, $approval);
    }

    public function validatePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        try {
            $absolute = PathGuard::resolve($this->projectRoot, $normalized, 'ChangeSet');
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('ChangeSet 路径禁止逃逸或符号链接：' . $normalized, 0, $exception);
        }
        $segments = explode('/', strtolower($normalized));
        $name = strtolower((string) end($segments));
        if (in_array((string) ($segments[0] ?? ''), self::FORBIDDEN_SEGMENTS, true)
            || in_array($name, self::SECRET_NAMES, true)
            || preg_match('/(?:secret|credential|private[_-]?key|\.pem$|\.key$)/i', $normalized) === 1) {
            throw new InvalidArgumentException('ChangeSet 路径禁止访问凭据、运行时、版本库或构建缓存：' . $normalized);
        }
        if (is_link($absolute)) throw new InvalidArgumentException('ChangeSet 路径禁止符号链接：' . $normalized);
        if (str_starts_with($normalized, 'database/migrations/')) $this->validateMigrationPath($normalized);
        return $absolute;
    }

    public function validateMigrationPath(string $path, bool $modification = false): int
    {
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#^database/migrations/(\d+)_[-a-z0-9_]+\.sql$#', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException('migration 路径或命名不合法');
        }
        $number = (int) $matches[1];
        $maximum = max($this->diskMaximumMigration(), (int) ($this->maximumMigration)());
        $absolute = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if ($modification || is_file($absolute)) throw new InvalidArgumentException('已登记 migration 禁止修改或删除');
        if ($number <= $maximum) throw new InvalidArgumentException('新 migration 编号必须高于已登记最大编号');
        return $number;
    }

    private function trustedArtifacts(array $changeSet, array $selection): array
    {
        $manifest = (array) ($changeSet['manifest'] ?? []);
        $this->assertArtifactBinding($manifest, $changeSet);
        $patch = $this->readPrivateFile((string) ($changeSet['patch_path'] ?? ''), (string) ($changeSet['patch_sha256'] ?? ''), 'patch');
        if ($patch === '') throw new RuntimeException('binary-safe patch 为空');
        $baseManifest = $this->readJsonArtifact($manifest, 'baseline_manifest', 'baseline manifest');
        $remoteManifest = $this->readJsonArtifact($manifest, 'remote_manifest', 'remote manifest');
        $this->assertManifest($baseManifest, (string) ($changeSet['base_digest'] ?? ''), 'baseline');
        $this->assertManifest($remoteManifest, '', 'remote');
        $this->assertPatchFiles($patch, $baseManifest, $remoteManifest);
        $available = array_values(array_unique(array_merge(array_keys($baseManifest['files']), array_keys($remoteManifest['files']))));
        $paths = array_fill_keys($selection === [] ? $available : array_values(array_unique(array_map('strval', $selection))), true);
        return [
            'baseManifest'=>$baseManifest,
            'remoteManifest'=>$remoteManifest,
            'baseBundle'=>$this->readBundleArtifact($manifest, 'baseline_bundle', 'baseline bundle', $paths),
            'remoteBundle'=>$this->readBundleArtifact($manifest, 'bundle', 'remote bundle', $paths),
        ];
    }

    private function buildPlan(array $trusted, array $selection): array
    {
        $paths = array_values(array_unique(array_merge(array_keys($trusted['baseManifest']['files']), array_keys($trusted['remoteManifest']['files']))));
        sort($paths, SORT_STRING);
        $selected = $this->selection($selection, $paths);
        $inputs = [];
        foreach ($selected as $path) {
            $this->validatePath($path);
            $baseMeta = $trusted['baseManifest']['files'][$path] ?? null;
            $remoteMeta = $trusted['remoteManifest']['files'][$path] ?? null;
            $base = $trusted['baseBundle'][$path] ?? null;
            $remote = $trusted['remoteBundle'][$path] ?? null;
            $this->assertContentHash($base, $baseMeta, 'Base', $path);
            $this->assertContentHash($remote, $remoteMeta, 'Remote', $path);
            if (str_starts_with($path, 'database/migrations/')) {
                $this->validateMigrationPath($path, $baseMeta !== null || $remoteMeta === null);
            }
            $inputs[] = ['path'=>$path, 'baseContent'=>$base, 'baseHash'=>$baseMeta['sha256'] ?? null, 'remoteContent'=>$remote,
                'remoteHash'=>$remoteMeta['sha256'] ?? null, 'artifactType'=>str_starts_with($path, 'database/migrations/') ? 'migration' : 'source'];
        }
        return (new ThreeWayMergePlanner($this->projectRoot))->plan($inputs);
    }

    private function publicPlan(array $plan): array
    {
        foreach ($plan['files'] as &$file) {
            $content = $file['content'] ?? null;
            if (($file['contentKind'] ?? 'text') === 'binary' || !is_string($content) || strlen($content) > $this->maxPublicContentBytes) {
                $file['contentOmitted'] = $content !== null;
            }
            unset($file['content'], $file['baseContent'], $file['localContent'], $file['remoteContent']);
        }
        unset($file);
        return $plan;
    }

    private function boundDigest(array $changeSet, int $adminId, int $conversationId, int $taskId, array $public): string
    {
        $files = array_map(static fn (array $file): array => array_intersect_key($file, array_flip([
            'path','status','baseHash','localHash','remoteHash','mergedHash','nextBaseHash','contentKind','contentType','artifactType',
        ])), $public['files']);
        return hash('sha256', CrudDefinition::canonicalJson([
            'adminId'=>$adminId, 'conversationId'=>$conversationId, 'taskId'=>$taskId, 'changeSetId'=>(int)$changeSet['id'],
            'changeSetDigest'=>(string)$changeSet['digest'], 'selection'=>$public['selection'], 'files'=>$files,
        ]));
    }

    private function assertOwned(array $changeSet, int $adminId, int $conversationId, int $taskId): void
    {
        if ((int)($changeSet['created_by'] ?? 0) !== $adminId || (int)($changeSet['conversation_id'] ?? 0) !== $conversationId
            || (int)($changeSet['task_id'] ?? 0) !== $taskId) throw new RuntimeException('资源不存在', 404);
    }

    /** 在 ChangeSet 状态转换前验证最终审批，并由 apply 再次执行深层校验。 */
    public function assertFinalApproval(array $approval, int $adminId, int $conversationId, int $taskId): void
    {
        $expiresAt = strtotime((string) ($approval['expires_at'] ?? ''));
        if (($approval['status'] ?? '') !== 'approved' || ($approval['operation'] ?? '') !== 'apply_workspace'
            || (int) ($approval['requested_by'] ?? 0) !== $adminId || (int) ($approval['decided_by'] ?? 0) !== $adminId
            || (int) ($approval['conversation_id'] ?? 0) !== $conversationId || (int) ($approval['task_id'] ?? 0) !== $taskId
            || $expiresAt === false || $expiresAt <= time()) throw new RuntimeException('最终审批无效或不可绕过', 403);
    }

    private function assertLocalHashes(array $files): void
    {
        foreach ($files as $file) {
            $absolute = $this->validatePath((string) $file['path']);
            $actual = is_file($absolute) ? hash_file('sha256', $absolute) : null;
            if ($actual !== ($file['localHash'] ?? null)) throw new RuntimeException('Local hash 已变化，必须重新 preview', 409);
        }
    }

    private function selection(array $selection, array $available): array
    {
        $selected = $selection === [] ? $available : array_values(array_unique(array_map('strval', $selection)));
        sort($selected, SORT_STRING);
        if (array_diff($selected, $available) !== []) throw new InvalidArgumentException('选择包含变更集之外的文件');
        return $selected;
    }

    private function readJsonArtifact(array $manifest, string $key, string $label): array
    {
        $content = $this->readPrivateFile((string)($manifest[$key . '_path'] ?? ''), (string)($manifest[$key . '_sha256'] ?? ''), $label);
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new RuntimeException($label . ' 必须为对象');
        return $decoded;
    }

    private function readPrivateFile(string $path, string $hash, string $label): string
    {
        [$handle, $expectedHash] = $this->openPrivateFile($path, $hash, $label);
        $content = '';
        $context = hash_init('sha256');
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) throw new RuntimeException('无法读取 ' . $label);
                if ($chunk === '') continue;
                hash_update($context, $chunk);
                $content .= $chunk;
            }
        } finally {
            fclose($handle);
        }
        if (!hash_equals($expectedHash, hash_final($context))) throw new RuntimeException($label . ' sha256 校验失败');
        return $content;
    }

    private function openPrivateFile(string $path, string $hash, string $label): array
    {
        if (preg_match(self::HASH_PATTERN, $hash) !== 1) throw new RuntimeException($label . ' sha256 不合法');
        $root = realpath($this->privateRoot);
        $real = realpath($path);
        if ($root === false || $real === false || is_link($path) || !str_starts_with($real, $root . DIRECTORY_SEPARATOR) || !is_file($real)) {
            throw new RuntimeException($label . ' 必须位于 AI 私有存储');
        }
        $handle = fopen($real, 'rb');
        if ($handle === false) throw new RuntimeException('无法读取 ' . $label);
        return [$handle, $hash];
    }

    private function assertArtifactBinding(array $manifest, array $changeSet): void
    {
        $artifact = $manifest['artifact'] ?? null;
        if (!is_array($artifact)) throw new RuntimeException('artifact 绑定缺失或不合法');
        if ((int) ($artifact['admin_id'] ?? 0) !== (int) ($changeSet['created_by'] ?? 0)
            || (int) ($artifact['conversation_id'] ?? 0) !== (int) ($changeSet['conversation_id'] ?? 0)
            || (int) ($artifact['task_id'] ?? 0) !== (int) ($changeSet['task_id'] ?? 0)) {
            throw new RuntimeException('artifact 归属与 ChangeSet 不一致');
        }
    }

    private function assertPatchFiles(string $patch, array $baseManifest, array $remoteManifest): void
    {
        preg_match_all('/^diff --git a\/(.+) b\/(.+)$/m', $patch, $matches, PREG_SET_ORDER);
        $patchPaths = [];
        foreach ($matches as $match) {
            $patchPaths[] = (string) $match[1];
            $patchPaths[] = (string) $match[2];
        }
        $patchPaths = array_values(array_unique($patchPaths));
        sort($patchPaths, SORT_STRING);
        $changedPaths = [];
        $baseFiles = (array) ($baseManifest['files'] ?? []);
        $remoteFiles = (array) ($remoteManifest['files'] ?? []);
        foreach (array_unique(array_merge(array_keys($baseFiles), array_keys($remoteFiles))) as $path) {
            if (($baseFiles[$path]['sha256'] ?? null) !== ($remoteFiles[$path]['sha256'] ?? null)) $changedPaths[] = (string) $path;
        }
        sort($changedPaths, SORT_STRING);
        if ($patchPaths !== $changedPaths) throw new RuntimeException('binary-safe patch 文件集合与 manifests 不一致');
    }

    private function assertManifest(array $manifest, string $expectedDigest, string $label): void
    {
        if (($manifest['algorithm'] ?? '') !== 'sha256' || !is_array($manifest['files'] ?? null)) throw new RuntimeException($label . ' manifest 不合法');
        $files = $manifest['files'];
        ksort($files, SORT_STRING);
        $digest = hash('sha256', json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        if (!hash_equals((string)($manifest['digest'] ?? ''), $digest) || ($expectedDigest !== '' && !hash_equals($expectedDigest, $digest))) {
            throw new RuntimeException($label . ' manifest digest 校验失败');
        }
        foreach ($files as $path => $meta) {
            if (!is_array($meta) || preg_match(self::HASH_PATTERN, (string)($meta['sha256'] ?? '')) !== 1 || (int)($meta['size'] ?? -1) < 0) {
                throw new RuntimeException($label . ' manifest 文件记录不合法：' . $path);
            }
        }
    }

    private function readBundleArtifact(array $manifest, string $key, string $label, array $paths): array
    {
        [$handle, $expectedHash] = $this->openPrivateFile(
            (string) ($manifest[$key . '_path'] ?? ''),
            (string) ($manifest[$key . '_sha256'] ?? ''),
            $label
        );
        $hash = hash_init('sha256');
        $encodedBytes = 0;
        try {
            $firstLine = $this->readBundleLine($handle, self::MAX_BUNDLE_LINE_BYTES);
            if ($firstLine === null) throw new RuntimeException('bundle 编码不合法');
            $encodedBytes += strlen($firstLine);
            hash_update($hash, $firstLine);
            $header = json_decode($firstLine, true, 512, JSON_THROW_ON_ERROR);
            if (($header['encoding'] ?? '') !== 'base64-ndjson') {
                $legacy = $firstLine;
                while (!feof($handle)) {
                    $chunk = fread($handle, 8192);
                    if ($chunk === false) throw new RuntimeException('无法读取 ' . $label);
                    if ($chunk === '') continue;
                    $encodedBytes += strlen($chunk);
                    if ($encodedBytes > self::MAX_BUNDLE_BYTES * 2) throw new RuntimeException('bundle 超过资源上限');
                    hash_update($hash, $chunk);
                    $legacy .= $chunk;
                }
                $files = $this->decodeBundle(json_decode($legacy, true, 512, JSON_THROW_ON_ERROR), $paths);
            } else {
                if (($header['version'] ?? null) !== 1) throw new RuntimeException('bundle 版本不合法');
                $files = [];
                $seen = [];
                $entryCount = 0;
                $bytes = 0;
                while (($lineHandle = $this->readBundleLineToTemp($handle, self::MAX_BUNDLE_LINE_BYTES)) !== null) {
                    $lineBytes = (int) $lineHandle['bytes'];
                    $encodedBytes += $lineBytes;
                    if ($encodedBytes > self::MAX_BUNDLE_BYTES * 2 || ++$entryCount > self::MAX_BUNDLE_FILES) {
                        fclose($lineHandle['handle']);
                        throw new RuntimeException('bundle 超过资源上限');
                    }
                    rewind($lineHandle['handle']);
                    hash_update_stream($hash, $lineHandle['handle']);
                    $prefix = $this->readBundlePathPrefix($lineHandle['handle']);
                    $relative = $prefix['path'];
                    if (!is_string($relative) || $relative === '' || isset($seen[$relative])) {
                        fclose($lineHandle['handle']);
                        throw new RuntimeException('bundle 文件编码不合法：' . (string) $relative);
                    }
                    $seen[$relative] = true;
                    if (!isset($paths[$relative])) {
                        fclose($lineHandle['handle']);
                        continue;
                    }
                    rewind($lineHandle['handle']);
                    $entry = json_decode((string) stream_get_contents($lineHandle['handle']), true, 512, JSON_THROW_ON_ERROR);
                    fclose($lineHandle['handle']);
                    $content = $entry['content'] ?? null;
                    if (!is_string($content) || strlen($content) > (int) ceil(self::MAX_BUNDLE_FILE_BYTES / 3) * 4
                        || ($value = base64_decode($content, true)) === false) {
                        throw new RuntimeException('bundle 文件编码不合法：' . $relative);
                    }
                    $size = strlen($value);
                    if ($size > self::MAX_BUNDLE_FILE_BYTES || $bytes > self::MAX_BUNDLE_BYTES - $size || count($files) >= self::MAX_BUNDLE_FILES) {
                        throw new RuntimeException('bundle 超过资源上限');
                    }
                    $bytes += $size;
                    $files[$relative] = $value;
                }
            }
            if (!hash_equals($expectedHash, hash_final($hash))) throw new RuntimeException($label . ' sha256 校验失败');
            return $files;
        } finally {
            fclose($handle);
        }
    }

    private function decodeBundle(array $bundle, array $paths): array
    {
        if (($bundle['encoding'] ?? '') !== 'base64' || !is_array($bundle['files'] ?? null) || count($bundle['files']) > self::MAX_BUNDLE_FILES) {
            throw new RuntimeException('bundle 编码不合法');
        }
        $decoded = [];
        $bytes = 0;
        foreach ($bundle['files'] as $path => $content) {
            if (!is_string($content) || strlen($content) > (int) ceil(self::MAX_BUNDLE_FILE_BYTES / 3) * 4
                || ($value = base64_decode($content, true)) === false) throw new RuntimeException('bundle 文件编码不合法：' . $path);
            $size = strlen($value);
            if ($size > self::MAX_BUNDLE_FILE_BYTES || $bytes > self::MAX_BUNDLE_BYTES - $size) throw new RuntimeException('bundle 超过资源上限');
            $bytes += $size;
            if ($paths === [] || isset($paths[(string) $path])) $decoded[(string)$path] = $value;
        }
        return $decoded;
    }

    private function readBundleLine($handle, int $maxBytes): ?string
    {
        $line = fgets($handle, $maxBytes + 1);
        if ($line === false) return null;
        if (!str_ends_with($line, "\n") && !feof($handle)) throw new RuntimeException('bundle 行超过资源上限');
        return $line;
    }

    private function readBundleLineToTemp($handle, int $maxBytes): ?array
    {
        $line = $this->readBundleLine($handle, $maxBytes);
        if ($line === null) return null;
        $temporary = fopen('php://temp/maxmemory:1048576', 'w+b');
        if ($temporary === false) throw new RuntimeException('无法创建 bundle 临时缓冲');
        $bytes = strlen($line);
        if (fwrite($temporary, $line) !== $bytes) {
            fclose($temporary);
            throw new RuntimeException('无法写入 bundle 临时缓冲');
        }
        unset($line);
        return ['handle'=>$temporary, 'bytes'=>$bytes];
    }

    private function readBundlePathPrefix($handle): array
    {
        rewind($handle);
        $prefix = fread($handle, 4096);
        if (!is_string($prefix) || preg_match('/^\{"path":"((?:[^"\\\\]|\\\\.)*)"/', $prefix, $matches) !== 1) {
            throw new RuntimeException('bundle 文件编码不合法');
        }
        $path = json_decode('"' . $matches[1] . '"', true, 512, JSON_THROW_ON_ERROR);
        return ['path'=>is_string($path) ? $path : null];
    }

    private function assertContentHash(?string $content, mixed $meta, string $label, string $path): void
    {
        if ($meta === null && $content === null) return;
        if (!is_array($meta) || $content === null || !hash_equals((string)$meta['sha256'], hash('sha256', $content)) || strlen($content) !== (int)$meta['size']) {
            throw new RuntimeException("{$label} 内容与 manifest 不一致：{$path}");
        }
    }

    private function diskMaximumMigration(): int
    {
        $maximum = 0;
        foreach (glob(rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . '/database/migrations/*.sql') ?: [] as $file) {
            if (preg_match('/^(\d+)_/', basename($file), $matches) === 1) $maximum = max($maximum, (int)$matches[1]);
        }
        return $maximum;
    }
}
