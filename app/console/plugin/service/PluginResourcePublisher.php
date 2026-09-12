<?php

declare(strict_types=1);

namespace app\console\plugin\service;

use app\console\plugin\contract\PluginResourceRepository;
use FilesystemIterator;
use app\common\plugin\sdk\Manifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** 将插件公开资源与 Admin Web 源码发布到各自受控目录并登记所有权。 */
final class PluginResourcePublisher
{
    private const ADMIN_WEB_PREFIX = 'admin-web:';
    private const PUBLIC_PREFIX = 'public:';

    /** 可选故障回调仅用于测试进程中断窗口，不属于业务 API。 */
    public function __construct(
        private readonly string $publicRoot,
        private readonly string $adminWebRoot,
        private readonly PluginResourceRepository $repository,
        private readonly string $recoveryRoot,
        private readonly mixed $faultHook = null
    ) {
        if ($this->faultHook !== null && !is_callable($this->faultHook)) {
            throw new RuntimeException('资源发布 fault hook 必须可调用');
        }
    }

    public function publish(Manifest $manifest, bool $rollbackOnFailure = true, ?string $token = null): array
    {
        return $this->apply(
            $this->preparePublish($manifest, $token ?? 'standalone-' . bin2hex(random_bytes(12))),
            $rollbackOnFailure
        );
    }

    public function remove(string $pluginCode, bool $rollbackOnFailure = true, ?string $token = null): array
    {
        return $this->apply(
            $this->prepareRemove($pluginCode, $token ?? 'standalone-' . bin2hex(random_bytes(12))),
            $rollbackOnFailure
        );
    }

    public function preparePublish(Manifest $manifest, string $token): array
    {
        try {
            return $this->materialize($this->planPublish($manifest, $token));
        } catch (Throwable $exception) {
            $this->cleanupFailedPrepare($token, $exception);
        }
    }

    public function planPublish(Manifest $manifest, string $token): array
    {
        $this->assertToken($token);
        $roots = $this->roots();
        $existing = $this->repository->all();
        $owned = $this->ownedRecords($manifest->code(), $existing);
        $this->assertSnapshotTargets($owned, $roots);
        $writes = [];
        $artifacts = $this->snapshotArtifacts($owned, $roots, $token);
        $next = [];
        foreach ($this->publicationSources($manifest, $roots) as $resource) {
            $sourceRoot = $this->canonicalSourceDirectory($manifest->directory(), $resource['source']);
            $targetRoot = $this->targetDirectory($resource['root'], $manifest->code(), $resource['target'], $resource['type']);
            foreach ($this->files($sourceRoot) as $source) {
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($source, strlen($sourceRoot) + 1));
                $target = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $rootRelative = str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($resource['root']) + 1));
                $targetPath = $this->registryPath($resource['type'], $rootRelative);
                $this->assertAvailable($target, $targetPath, $manifest->code(), $existing);
                $index = count($writes);
                $this->fault('before_prepare_hash', $index, $source);
                $sha256 = hash_file('sha256', $source);
                if ($sha256 === false) {
                    throw new RuntimeException('无法计算资源 SHA-256：' . $source);
                }
                $relativeSource = 'prepared-files/' . hash('sha256', $targetPath) . '.payload';
                $writes[] = ['encoding' => 'file', 'relative_path' => $relativeSource, 'target_path' => $targetPath, 'sha256' => $sha256];
                $artifacts[] = ['kind' => 'payload', 'source' => $source, 'relative_path' => $relativeSource, 'sha256' => $sha256, 'state' => 'planned'];
                $next[] = [
                    'plugin_code' => $manifest->code(), 'version' => $manifest->version(), 'resource_type' => 'file',
                    'publication_unit' => null, 'operation_token' => $token,
                    'source_path' => str_replace(DIRECTORY_SEPARATOR, '/', substr($source, strlen($manifest->directory()) + 1)),
                    'target_path' => $targetPath, 'sha256' => $sha256,
                    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }
        $nextTargets = array_column($next, 'target_path');
        $deletes = array_values(array_map(
            static fn (array $record): string => (string) $record['target_path'],
            array_filter($owned, static fn (array $record): bool => !in_array($record['target_path'], $nextTargets, true))
        ));
        $snapshot = $this->snapshotFromArtifacts($manifest->code(), $owned, $token, $artifacts);
        $plan = $this->plan($manifest->code(), 'publish', $token, $snapshot, $writes, $deletes, $next);
        $plan['artifacts'] = $artifacts;
        $plan['materialization_state'] = 'planned';
        return $plan;
    }

    public function prepareRemove(string $pluginCode, string $token): array
    {
        try {
            return $this->materialize($this->planRemove($pluginCode, $token));
        } catch (Throwable $exception) {
            $this->cleanupFailedPrepare($token, $exception);
        }
    }

    public function planRemove(string $pluginCode, string $token): array
    {
        $this->assertPluginCode($pluginCode);
        $this->assertToken($token);
        $roots = $this->roots();
        $owned = $this->ownedRecords($pluginCode, $this->repository->all());
        $this->assertSnapshotTargets($owned, $roots);
        $artifacts = $this->snapshotArtifacts($owned, $roots, $token);
        $snapshot = $this->snapshotFromArtifacts($pluginCode, $owned, $token, $artifacts);
        $deletes = array_values(array_map(static fn (array $record): string => (string) $record['target_path'], $owned));
        $plan = $this->plan($pluginCode, 'remove', $token, $snapshot, [], $deletes, []);
        $plan['artifacts'] = $artifacts;
        $plan['materialization_state'] = 'planned';
        return $plan;
    }

    public function materialize(array $plan, ?callable $checkpoint = null): array
    {
        $kindIndexes = ['snapshot' => 0, 'payload' => 0];
        foreach ((array) ($plan['artifacts'] ?? []) as $index => $artifact) {
            $kind = (string) ($artifact['kind'] ?? '');
            $kindIndex = $kindIndexes[$kind] ?? 0;
            $kindIndexes[$kind] = $kindIndex + 1;
            if (($artifact['state'] ?? '') === 'materialized') {
                continue;
            }
            $target = $this->safeRecoveryFile((string) $plan['recovery_token'], (string) $artifact['relative_path']);
            $this->createParent($target);
            $this->fault($kind === 'payload' ? 'before_prepare_copy' : 'before_snapshot_copy', $kindIndex, (string) $artifact['source']);
            if (!copy((string) $artifact['source'], $target)) {
                throw new RuntimeException('无法持久化资源发布文件：' . (string) $artifact['relative_path']);
            }
            $actual = hash_file('sha256', $target);
            if ($actual === false || !hash_equals((string) $artifact['sha256'], $actual)) {
                throw new RuntimeException('资源 materialize SHA-256 校验失败：' . (string) $artifact['relative_path']);
            }
            $plan['artifacts'][$index]['state'] = 'materialized';
            if ($kind === 'snapshot') {
                $this->fault('after_snapshot_file', $kindIndex, (string) ($artifact['target_path'] ?? ''));
            }
            if ($checkpoint !== null) {
                $checkpoint($plan);
            }
        }
        $plan['materialization_state'] = 'completed';
        if ($checkpoint !== null) {
            $checkpoint($plan);
        }
        return $plan;
    }

    public function apply(array $plan, bool $rollbackOnFailure = true): array
    {
        $this->assertPlan($plan);
        $roots = $this->roots();
        try {
            foreach ($plan['writes'] as $index => $write) {
                $source = $this->safeRecoveryFile($plan['recovery_token'], $write['relative_path']);
                $actual = is_file($source) && !is_link($source) ? hash_file('sha256', $source) : false;
                if ($actual === false || !hash_equals($write['sha256'], $actual)) {
                    throw new RuntimeException('资源发布源文件 SHA-256 校验失败：' . $write['target_path']);
                }
                [$root, $relative] = $this->resolveRegistryPath($roots, $write['target_path']);
                $this->copy($source, $this->safeTarget($root, $relative));
                $this->fault('after_write', $index, $write['target_path']);
            }
            foreach ($plan['deletes'] as $index => $targetPath) {
                $this->deleteOwnedFile($roots, $targetPath);
                $this->fault('after_delete', $index, $targetPath);
            }
            $this->fault('before_registry', 0, '');
            $this->repository->replaceForPlugin($plan['plugin_code'], $plan['registry_next']);
            $this->fault('after_registry', 0, '');
            return $plan['snapshot'];
        } catch (Throwable $exception) {
            if ($rollbackOnFailure) {
                try {
                    $this->rollbackPrepared($plan);
                } catch (Throwable $rollbackException) {
                    throw new RuntimeException(
                        $exception->getMessage() . '；资源自动回滚失败：' . $rollbackException->getMessage(),
                        0,
                        $exception
                    );
                }
            }
            throw $exception;
        }
    }

    public function complete(array $plan): void
    {
        $this->assertCleanupPlan($plan);
        $this->cleanupRecoveryArtifacts($plan);
    }

    public function rollbackPrepared(array $plan, bool $cleanup = true): void
    {
        $this->assertPlan($plan);
        $this->restoreSnapshot($plan['snapshot'], $plan['planned_targets']);
        if ($cleanup) {
            $this->cleanupRecoveryArtifacts($plan);
        }
    }

    public function rollback(array $snapshot, bool $cleanup = true): void
    {
        $this->assertSnapshot($snapshot);
        $currentTargets = array_column($this->ownedRecords($snapshot['plugin_code'], $this->repository->all()), 'target_path');
        $this->restoreSnapshot(
            $snapshot,
            array_values(array_unique(array_merge($currentTargets, (array) ($snapshot['touched_targets'] ?? []))))
        );
        if ($cleanup) {
            $this->cleanupRecoveryToken((string) $snapshot['recovery_token']);
        }
    }

    public function cleanupRecoveryArtifacts(array $plan): void
    {
        $this->assertCleanupPlan($plan);
        $this->cleanupRecoveryToken((string) $plan['recovery_token']);
    }

    private function cleanupRecoveryToken(string $token): void
    {
        $this->assertToken($token);
        $this->fault('before_cleanup', 0, '');
        $root = $this->canonicalDirectory($this->recoveryRoot);
        $tokenRoot = $this->safeTarget($root, $token);
        $this->removeTree($tokenRoot . DIRECTORY_SEPARATOR . 'prepared-files');
        $this->removeTree($tokenRoot . DIRECTORY_SEPARATOR . 'resource-files');
        if (is_dir($tokenRoot) && (scandir($tokenRoot) ?: []) === ['.', '..'] && !rmdir($tokenRoot)) {
            throw new RuntimeException('无法清理 token 资源恢复目录');
        }
    }

    private function cleanupFailedPrepare(string $token, Throwable $exception): never
    {
        $tokenRoot = rtrim($this->recoveryRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $token;
        if (!is_dir($tokenRoot)) {
            throw $exception;
        }
        try {
            $this->cleanupRecoveryToken($token);
        } catch (Throwable $cleanupException) {
            throw new RuntimeException(
                $exception->getMessage() . '；prepare 材料清理失败：' . $cleanupException->getMessage()
                    . '；残留路径：' . $tokenRoot,
                0,
                $exception
            );
        }
        throw $exception;
    }

    private function assertSnapshotTargets(array $records, array $roots): void
    {
        foreach ($records as $record) {
            [$root, $relative] = $this->resolveRegistryPath($roots, (string) ($record['target_path'] ?? ''));
            $this->safeTarget($root, $relative);
        }
    }

    private function plan(
        string $pluginCode,
        string $operation,
        string $token,
        array $snapshot,
        array $writes,
        array $deletes,
        array $registryNext
    ): array {
        $plannedTargets = array_values(array_unique(array_merge(array_column($writes, 'target_path'), $deletes)));
        sort($plannedTargets, SORT_STRING);
        return [
            'schema_version' => 1,
            'plugin_code' => $pluginCode,
            'operation' => $operation,
            'recovery_token' => $token,
            'snapshot' => $snapshot,
            'planned_targets' => $plannedTargets,
            'writes' => $writes,
            'deletes' => $deletes,
            'registry_next' => $registryNext,
        ];
    }

    private function assertCleanupPlan(array $plan): void
    {
        foreach (['plugin_code', 'recovery_token'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new RuntimeException('资源发布清理材料结构不完整：' . $key);
            }
        }
        if (array_key_exists('schema_version', $plan)
            && ($plan['schema_version'] !== 1 || !in_array($plan['operation'] ?? null, ['publish', 'remove'], true))) {
            throw new RuntimeException('资源发布计划版本或操作无效');
        }
        $this->assertPluginCode((string) $plan['plugin_code']);
        $this->assertToken((string) $plan['recovery_token']);
    }

    private function assertPlan(array $plan): void
    {
        foreach (['schema_version', 'plugin_code', 'operation', 'recovery_token', 'snapshot', 'planned_targets', 'writes', 'deletes', 'registry_next'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new RuntimeException('资源发布计划结构不完整：' . $key);
            }
        }
        if ($plan['schema_version'] !== 1 || !in_array($plan['operation'], ['publish', 'remove'], true)) {
            throw new RuntimeException('资源发布计划版本或操作无效');
        }
        $this->assertPluginCode((string) $plan['plugin_code']);
        $this->assertToken((string) $plan['recovery_token']);
        $this->assertSnapshot((array) $plan['snapshot'], (string) $plan['plugin_code']);
        $calculated = array_values(array_unique(array_merge(array_column($plan['writes'], 'target_path'), $plan['deletes'])));
        sort($calculated, SORT_STRING);
        $planned = $plan['planned_targets'];
        sort($planned, SORT_STRING);
        if ($planned !== $calculated) {
            throw new RuntimeException('资源发布 planned_targets 与计划不一致');
        }
        $roots = $this->roots();
        foreach ($plan['planned_targets'] as $targetPath) {
            [$root, $relative] = $this->resolveRegistryPath($roots, (string) $targetPath);
            $this->safeTarget($root, $relative);
        }
        foreach ($plan['writes'] as $write) {
            if (!is_array($write) || ($write['encoding'] ?? '') !== 'file'
                || preg_match('/^[a-f0-9]{64}$/', (string) ($write['sha256'] ?? '')) !== 1) {
                throw new RuntimeException('资源发布写入计划无效');
            }
            $source = $this->safeRecoveryFile((string) $plan['recovery_token'], (string) ($write['relative_path'] ?? ''));
            if (!is_file($source) || is_link($source)) {
                throw new RuntimeException('资源发布持久文件不存在或为符号链接');
            }
        }
    }

    private function assertSnapshot(array $snapshot, ?string $expectedPluginCode = null): void
    {
        foreach (['plugin_code', 'recovery_token', 'records', 'files', 'rebuildRequired'] as $key) {
            if (!array_key_exists($key, $snapshot)) {
                throw new RuntimeException('资源恢复 snapshot 结构不完整：' . $key);
            }
        }
        $this->assertPluginCode((string) $snapshot['plugin_code']);
        $this->assertToken((string) $snapshot['recovery_token']);
        if ($expectedPluginCode !== null && $snapshot['plugin_code'] !== $expectedPluginCode) {
            throw new RuntimeException('资源恢复 snapshot 插件标识不一致');
        }
        if (!is_array($snapshot['records']) || !is_array($snapshot['files'])) {
            throw new RuntimeException('资源恢复 snapshot records/files 无效');
        }
    }

    private function restoreSnapshot(array $snapshot, array $touchedTargets): void
    {
        $this->assertSnapshot($snapshot);
        $pluginCode = (string) $snapshot['plugin_code'];
        $roots = $this->roots();
        foreach (array_unique($touchedTargets) as $targetPath) {
            $this->deleteOwnedFile($roots, (string) $targetPath);
        }
        foreach ($snapshot['files'] as $targetPath => $fileSnapshot) {
            [$root, $relative] = $this->resolveRegistryPath($roots, (string) $targetPath);
            $target = $this->safeTarget($root, $relative);
            if (!is_array($fileSnapshot)) {
                throw new RuntimeException('资源恢复 snapshot 禁止内嵌文件内容');
            }
            $this->restoreSnapshotFile($snapshot, $fileSnapshot, $target);
        }
        $this->repository->replaceForPlugin($pluginCode, $snapshot['records']);
    }

    private function ownedRecords(string $pluginCode, array $records): array
    {
        return array_values(array_filter(
            $records,
            static fn (array $row): bool => ($row['plugin_code'] ?? '') === $pluginCode
                && ($row['resource_type'] ?? 'file') === 'file'
        ));
    }

    private function fault(string $point, int $index, string $targetPath): void
    {
        if ($this->faultHook !== null) {
            ($this->faultHook)($point, $index, $targetPath);
        }
    }

    private function snapshotArtifacts(array $records, array $roots, string $token): array
    {
        $artifacts = [];
        foreach ($records as $record) {
            $targetPath = (string) $record['target_path'];
            [$root, $relative] = $this->resolveRegistryPath($roots, $targetPath);
            $source = $this->safeTarget($root, $relative);
            if (!is_file($source)) {
                continue;
            }
            $sha256 = hash_file('sha256', $source);
            if ($sha256 === false) {
                throw new RuntimeException('无法计算资源恢复文件 SHA-256：' . $targetPath);
            }
            $artifacts[] = [
                'kind' => 'snapshot',
                'source' => $source,
                'relative_path' => 'resource-files/' . hash('sha256', $targetPath) . '.snapshot',
                'target_path' => $targetPath,
                'sha256' => $sha256,
                'state' => 'planned',
            ];
        }
        return $artifacts;
    }

    private function snapshotFromArtifacts(string $pluginCode, array $records, string $token, array $artifacts): array
    {
        $files = [];
        foreach ($artifacts as $artifact) {
            if (($artifact['kind'] ?? '') !== 'snapshot') {
                continue;
            }
            $files[(string) $artifact['target_path']] = [
                'encoding' => 'file',
                'relative_path' => (string) $artifact['relative_path'],
                'sha256' => (string) $artifact['sha256'],
            ];
        }
        return [
            'plugin_code' => $pluginCode,
            'recovery_token' => $token,
            'records' => $records,
            'files' => $files,
            'rebuildRequired' => true,
        ];
    }

    private function persistSnapshotFile(string $token, string $targetPath, string $source): array
    {
        $this->assertToken($token);
        $relative = 'resource-files/' . hash('sha256', $targetPath) . '.snapshot';
        $target = $this->safeRecoveryFile($token, $relative);
        $this->createParent($target);
        if (!copy($source, $target)) {
            throw new RuntimeException('无法持久化资源恢复文件：' . $targetPath);
        }
        $sha256 = hash_file('sha256', $target);
        if ($sha256 === false) {
            throw new RuntimeException('无法计算资源恢复文件 SHA-256：' . $targetPath);
        }
        return ['encoding' => 'file', 'relative_path' => $relative, 'sha256' => $sha256];
    }

    private function restoreSnapshotFile(array $snapshot, array $metadata, string $target): void
    {
        if (($metadata['encoding'] ?? '') !== 'file') {
            throw new RuntimeException('资源恢复文件 encoding 无效');
        }
        $source = $this->safeRecoveryFile(
            (string) ($snapshot['recovery_token'] ?? ''),
            (string) ($metadata['relative_path'] ?? '')
        );
        if (!is_file($source) || is_link($source)) {
            throw new RuntimeException('资源恢复文件不存在或为符号链接');
        }
        $actual = hash_file('sha256', $source);
        $expected = (string) ($metadata['sha256'] ?? '');
        if ($actual === false || $expected === '' || !hash_equals($expected, $actual)) {
            throw new RuntimeException('资源恢复文件 SHA-256 校验失败');
        }
        $this->copy($source, $target);
    }

    private function safeRecoveryFile(string $token, string $relative): string
    {
        $this->assertToken($token);
        if (is_link($this->recoveryRoot)) {
            throw new RuntimeException('资源恢复根目录禁止符号链接');
        }
        $root = $this->canonicalDirectory($this->recoveryRoot);
        $tokenRoot = $this->safeTarget($root, $token);
        if (!is_dir($tokenRoot) && !mkdir($tokenRoot, 0755, true) && !is_dir($tokenRoot)) {
            throw new RuntimeException('无法创建 token 资源恢复目录');
        }
        $tokenReal = realpath($tokenRoot);
        if ($tokenReal === false || is_link($tokenRoot)) {
            throw new RuntimeException('资源恢复 token 路径无效');
        }
        return $this->safeTarget($tokenReal, $relative);
    }

    private function assertAvailable(string $target, string $targetPath, string $pluginCode, array $registry): void
    {
        if (is_link($target)) {
            throw new RuntimeException('资源目标路径禁止符号链接：' . $targetPath);
        }
        foreach ($registry as $record) {
            if (($record['target_path'] ?? '') !== $targetPath) {
                continue;
            }
            if (($record['plugin_code'] ?? '') !== $pluginCode) {
                throw new RuntimeException('资源目标已属于其他插件：' . $targetPath);
            }
            return;
        }
        if (is_file($target)) {
            throw new RuntimeException('拒绝覆盖未登记的核心文件：' . $targetPath);
        }
    }

    private function publicationSources(Manifest $manifest, array $roots): array
    {
        $data = $manifest->toArray();
        $publications = [];
        foreach (array_values((array) ($data['resources'] ?? [])) as $resource) {
            $publications[] = $resource + ['root' => $roots['public'], 'type' => 'public'];
        }
        if (is_array($data['adminWeb'] ?? null)) {
            $publications[] = [
                'source' => (string) ($data['adminWeb']['source'] ?? 'admin-web'),
                'target' => 'src/modules/' . $manifest->code(),
                'root' => $roots['admin-web'],
                'type' => 'admin-web',
            ];
        }
        return $publications;
    }

    private function files(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('资源目录禁止符号链接：' . $item->getPathname());
            }
            if (!$item->isFile()) {
                continue;
            }
            $realPath = $item->getRealPath();
            if ($realPath === false || !str_starts_with($realPath, $directory . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('资源源路径越界：' . $item->getPathname());
            }
            $files[] = $realPath;
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function canonicalSourceDirectory(string $pluginRoot, string $relative): string
    {
        $plugin = realpath($pluginRoot);
        $source = realpath($pluginRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($plugin === false || $source === false || !is_dir($source)) {
            throw new RuntimeException('资源源目录不存在：' . $relative);
        }
        if (!str_starts_with($source, $plugin . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('资源源路径越界：' . $relative);
        }
        return rtrim($source, DIRECTORY_SEPARATOR);
    }

    private function targetDirectory(string $root, string $pluginCode, string $relative, string $type): string
    {
        $prefix = $type === 'admin-web' ? 'src/modules/' . $pluginCode : 'plugin-assets/' . $pluginCode;
        if ($relative !== $prefix && !str_starts_with($relative, $prefix . '/')) {
            throw new RuntimeException('资源目标必须位于 ' . $prefix . '/');
        }
        return $this->safeTarget($root, $relative);
    }

    private function registryPath(string $type, string $relative): string
    {
        return ($type === 'admin-web' ? self::ADMIN_WEB_PREFIX : self::PUBLIC_PREFIX) . $relative;
    }

    private function resolveRegistryPath(array $roots, string $targetPath): array
    {
        if (str_starts_with($targetPath, self::ADMIN_WEB_PREFIX)) {
            return [$roots['admin-web'], substr($targetPath, strlen(self::ADMIN_WEB_PREFIX))];
        }
        if (str_starts_with($targetPath, self::PUBLIC_PREFIX)) {
            return [$roots['public'], substr($targetPath, strlen(self::PUBLIC_PREFIX))];
        }
        throw new RuntimeException('资源 registry 目标缺少根前缀：' . $targetPath);
    }

    private function safeTarget(string $root, string $relative): string
    {
        if ($relative === '' || str_contains($relative, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $relative)
            || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
            throw new RuntimeException('资源目标路径越界');
        }
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('资源目标路径越界');
        }
        $this->assertNoTargetSymlink($root, $target);
        return $target;
    }

    private function assertNoTargetSymlink(string $root, string $target): void
    {
        $relative = substr($target, strlen($root) + 1);
        $current = $root;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new RuntimeException('资源目标路径禁止符号链接：' . $relative);
            }
        }
    }

    private function roots(): array
    {
        return [
            'public' => $this->canonicalDirectory($this->publicRoot),
            'admin-web' => $this->canonicalDirectory($this->adminWebRoot),
        ];
    }

    private function canonicalDirectory(string $directory): string
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建资源目录：' . $directory);
        }
        $real = realpath($directory);
        if ($real === false) {
            throw new RuntimeException('无法解析资源目录：' . $directory);
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function copy(string $source, string $target): void
    {
        $this->createParent($target);
        if (!copy($source, $target)) {
            throw new RuntimeException('资源发布失败：' . $target);
        }
    }

    private function write(string $target, string $contents): void
    {
        $this->createParent($target);
        if (file_put_contents($target, $contents) === false) {
            throw new RuntimeException('资源回滚失败：' . $target);
        }
    }

    private function createParent(string $target): void
    {
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建发布目录');
        }
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('资源恢复目录禁止符号链接');
            }
            $removed = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (!$removed) {
                throw new RuntimeException('无法清理资源恢复材料');
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('无法清理资源恢复目录');
        }
    }

    private function deleteOwnedFile(array $roots, string $targetPath): void
    {
        [$root, $relative] = $this->resolveRegistryPath($roots, $targetPath);
        $target = $this->safeTarget($root, $relative);
        if (is_file($target) && !unlink($target)) {
            throw new RuntimeException('无法删除插件资源：' . $targetPath);
        }
        $directory = dirname($target);
        while ($directory !== $root && is_dir($directory) && (scandir($directory) ?: []) === ['.', '..']) {
            if (!rmdir($directory)) {
                throw new RuntimeException('无法清理插件资源目录：' . $targetPath);
            }
            $directory = dirname($directory);
        }
    }

    private function assertPluginCode(string $code): void
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1) {
            throw new RuntimeException('插件标识不合法');
        }
    }

    private function assertToken(string $token): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $token) !== 1) {
            throw new RuntimeException('资源恢复 operation token 不合法');
        }
    }
}
