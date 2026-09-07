<?php

declare(strict_types=1);

namespace app\console\service;

use FilesystemIterator;
use fun\plugins\Manifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** 以目录为独占单元原子发布 Manifest v2 原生应用。 */
final class PluginAppPublicationService
{
    private const RESOURCE_TYPE = 'native_app';
    private const CONSOLE_LAYERS = ['controller', 'model', 'service', 'validate', 'middleware'];

    public function __construct(
        private readonly string $appRoot,
        private readonly string $runtimeRoot,
        private readonly PluginAppPublicationRepository $repository
    ) {
    }

    public function publish(Manifest $manifest, string $token): array
    {
        $this->assertToken($token);
        return $this->locked(function () use ($manifest, $token): array {
            $units = $this->sourceUnits($manifest);
            return $this->execute($manifest->code(), $manifest->version(), $token, $units);
        });
    }

    public function remove(string $pluginCode, string $token): array
    {
        $this->assertPluginCode($pluginCode);
        $this->assertToken($token);
        return $this->locked(function () use ($pluginCode, $token): array {
            $owned = $this->ownedRecords($pluginCode);
            $this->assertCurrentUnmodified($pluginCode, $owned);
            $units = [];
            foreach ($this->recordsByUnit($owned) as $unit => $records) {
                $target = $this->unitTarget($unit, $pluginCode);
                $units[$unit] = ['source' => null, 'target' => $target, 'records' => $records];
            }
            return $this->execute($pluginCode, '', $token, $units, true);
        });
    }

    public function inspect(string $token): array
    {
        $this->assertToken($token);
        $file = $this->journalFile($token);
        if (!is_file($file)) {
            throw new RuntimeException('publication journal 不存在：' . $token);
        }
        try {
            $journal = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('publication journal 损坏：' . $token, 0, $exception);
        }
        if (!is_array($journal) || ($journal['token'] ?? '') !== $token) {
            throw new RuntimeException('publication journal 内容无效：' . $token);
        }
        return $journal;
    }

    public function recover(string $token): void
    {
        $journal = $this->inspect($token);
        if (($journal['state'] ?? '') === 'completed') {
            return;
        }
        $this->rollback($token);
    }

    public function rollback(string $token): void
    {
        $this->assertToken($token);
        $this->locked(function () use ($token): void {
            $journal = $this->inspect($token);
            $this->writeJournal($token, array_replace($journal, ['state' => 'rollback_required']));
            $this->restore($journal);
            $journal = $this->inspect($token);
            $this->cleanupArtifacts($journal);
            $this->writeJournal($token, array_replace($journal, ['state' => 'completed', 'rolled_back' => true]));
        });
    }

    public function complete(string $token): void
    {
        $this->assertToken($token);
        $this->locked(function () use ($token): void {
            $journal = $this->inspect($token);
            if (($journal['state'] ?? '') === 'completed') {
                return;
            }
            if (($journal['state'] ?? '') !== 'registry_committed') {
                throw new RuntimeException('publication 尚未提交 registry，不能完成：' . $token);
            }
            $this->cleanupArtifacts($journal);
            $this->writeJournal($token, array_replace($journal, ['state' => 'completed']));
        });
    }

    private function execute(
        string $pluginCode,
        string $version,
        string $token,
        array $sourceUnits,
        bool $removing = false
    ): array {
        $existing = $this->repository->all();
        $owned = $this->ownedRecords($pluginCode, $existing);
        $this->assertCurrentUnmodified($pluginCode, $owned);
        $oldByUnit = $this->recordsByUnit($owned);
        if (!$removing) {
            foreach ($oldByUnit as $unit => $records) {
                if (!isset($sourceUnits[$unit])) {
                    $sourceUnits[$unit] = [
                        'source' => null,
                        'target' => $this->unitTarget($unit, $pluginCode),
                        'records' => $records,
                    ];
                }
            }
        }
        $this->assertExclusive($pluginCode, $sourceUnits, $existing, $oldByUnit);
        $journal = [
            'schema_version' => 1,
            'token' => $token,
            'plugin' => $pluginCode,
            'operation' => $removing ? 'remove' : 'publish',
            'state' => 'prepared',
            'registry_before' => $owned,
            'units' => [],
            'updated_at' => date(DATE_ATOM),
        ];
        $next = [];
        try {
            foreach ($sourceUnits as $unit => $definition) {
                $target = $definition['target'];
                $temp = dirname($target) . DIRECTORY_SEPARATOR . '.publication-' . $token . '-' . $this->unitSlug($unit);
                $backup = $this->backupPath($token, $target);
                $oldHash = is_dir($target) ? $this->treeHash($target) : null;
                $newHash = null;
                $records = [];
                if ($definition['source'] !== null) {
                    $this->copyTree($definition['source'], $temp);
                    $sourceHash = $this->treeHash($definition['source']);
                    $newHash = $this->treeHash($temp);
                    if (!hash_equals($sourceHash, $newHash)) {
                        throw new RuntimeException('publication tree hash 校验失败：' . $unit);
                    }
                    $records = $this->recordsForUnit(
                        $pluginCode,
                        $version,
                        $token,
                        $unit,
                        $definition['source'],
                        $target,
                        $newHash
                    );
                    $next = array_merge($next, $records);
                }
                $journal['units'][] = [
                    'unit' => $unit,
                    'source' => $definition['source'],
                    'target' => $target,
                    'temp' => $temp,
                    'backup' => $backup,
                    'old_tree_hash' => $oldHash,
                    'new_tree_hash' => $newHash,
                    'state' => 'prepared',
                ];
            }
            $this->writeJournal($token, $journal);
            foreach ($journal['units'] as $index => $unit) {
                $this->swap($unit);
                $journal['units'][$index]['state'] = 'swapped';
                $journal['state'] = 'swapped';
                $this->writeJournal($token, $journal);
            }
            $this->repository->replaceAppForPlugin($pluginCode, $next);
            $journal['state'] = 'registry_committed';
            foreach ($journal['units'] as &$unit) {
                $unit['state'] = 'registry_committed';
            }
            unset($unit);
            $this->writeJournal($token, $journal);
            return ['token' => $token, 'plugin' => $pluginCode, 'state' => 'registry_committed'];
        } catch (Throwable $exception) {
            if (is_file($this->journalFile($token))) {
                $current = $this->inspect($token);
                $this->writeJournal($token, array_replace($current, ['state' => 'rollback_required']));
                try {
                    $this->restore($current);
                    $current = $this->inspect($token);
                    $this->cleanupArtifacts($current);
                    $this->writeJournal($token, array_replace($current, ['state' => 'completed', 'rolled_back' => true]));
                } catch (Throwable $rollbackException) {
                    throw new RuntimeException(
                        $exception->getMessage() . '；原生 App 自动回滚失败：' . $rollbackException->getMessage(),
                        0,
                        $exception
                    );
                }
            } else {
                foreach ($sourceUnits as $unit => $definition) {
                    $temp = dirname($definition['target']) . DIRECTORY_SEPARATOR . '.publication-' . $token . '-' . $this->unitSlug($unit);
                    $this->removeTree($temp);
                }
            }
            throw $exception;
        }
    }

    private function sourceUnits(Manifest $manifest): array
    {
        if (($manifest->toArray()['schema_version'] ?? null) !== 2) {
            throw new RuntimeException('原生 App 发布仅支持 Manifest v2');
        }
        $pluginRoot = realpath($manifest->directory());
        $appSource = realpath($manifest->directory() . DIRECTORY_SEPARATOR . 'app');
        if ($pluginRoot === false || $appSource === false || !str_starts_with($appSource, $pluginRoot . DIRECTORY_SEPARATOR)) {
            return [];
        }
        $allowedRoot = [$manifest->code() => true, 'console' => true];
        foreach ($this->directoryEntries($appSource) as $entry) {
            if (is_link($entry)) {
                throw new RuntimeException('原生 App 源目录禁止符号链接：' . $entry);
            }
            if (!is_dir($entry) || !isset($allowedRoot[basename($entry)])) {
                throw new RuntimeException('插件 app 根目录存在未允许目录或文件：' . basename($entry));
            }
        }
        $units = [];
        $application = $appSource . DIRECTORY_SEPARATOR . $manifest->code();
        if (is_dir($application)) {
            $this->assertSafeSourceTree($application, $appSource);
            $units['app:' . $manifest->code()] = [
                'source' => $application,
                'target' => $this->appDirectory() . DIRECTORY_SEPARATOR . $manifest->code(),
            ];
        }
        $console = $appSource . DIRECTORY_SEPARATOR . 'console';
        if (is_dir($console)) {
            foreach ($this->directoryEntries($console) as $entry) {
                if (is_link($entry)) {
                    throw new RuntimeException('原生 App 源目录禁止符号链接：' . $entry);
                }
                $layer = basename($entry);
                if (!is_dir($entry)) {
                    throw new RuntimeException('Console 根目录只允许固定 layer 目录：' . $layer);
                }
                if (!in_array($layer, self::CONSOLE_LAYERS, true)) {
                    throw new RuntimeException('未知 Console layer：' . $layer);
                }
                $this->assertSafeSourceTree($entry, $appSource);
                $units['console:' . $layer . ':' . $manifest->code()] = [
                    'source' => $entry,
                    'target' => $this->appDirectory() . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR
                        . $layer . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . $manifest->code(),
                ];
            }
        }
        ksort($units, SORT_STRING);
        return $units;
    }

    private function assertCurrentUnmodified(string $pluginCode, array $owned): void
    {
        if ($owned === []) {
            return;
        }
        $conflicts = [];
        $byUnit = $this->recordsByUnit($owned);
        foreach ($byUnit as $unit => $records) {
            $targetRoot = $this->unitTarget($unit, $pluginCode);
            $registered = [];
            foreach ($records as $record) {
                $target = $this->registryTarget((string) $record['target_path']);
                $relative = $this->relativePath($targetRoot, $target);
                $registered[$relative] = true;
                if (!is_file($target)) {
                    $conflicts[] = 'deleted:' . $record['target_path'];
                    continue;
                }
                $hash = hash_file('sha256', $target);
                if ($hash === false || !hash_equals((string) $record['sha256'], $hash)) {
                    $conflicts[] = 'modified:' . $record['target_path'];
                }
            }
            if (is_dir($targetRoot)) {
                foreach ($this->files($targetRoot) as $file) {
                    $relative = $this->relativePath($targetRoot, $file);
                    if (!isset($registered[$relative])) {
                        $conflicts[] = 'untracked:' . $this->targetRegistryPath($file);
                    }
                }
            }
        }
        if ($conflicts !== []) {
            sort($conflicts, SORT_STRING);
            throw new RuntimeException('原生 App 存在二次开发冲突：' . implode(', ', $conflicts));
        }
    }

    private function assertExclusive(
        string $pluginCode,
        array $units,
        array $registry,
        array $oldByUnit
    ): void {
        foreach ($units as $unit => $definition) {
            foreach ($registry as $record) {
                if (($record['resource_type'] ?? '') === self::RESOURCE_TYPE
                    && ($record['publication_unit'] ?? '') === $unit
                    && ($record['plugin_code'] ?? '') !== $pluginCode) {
                    throw new RuntimeException('publication unit 已属于其他插件：' . $unit);
                }
            }
            if (file_exists($definition['target']) && !isset($oldByUnit[$unit])) {
                throw new RuntimeException('拒绝覆盖未登记的核心目标：' . $this->targetRegistryPath($definition['target']));
            }
            $this->assertNoTargetSymlink($definition['target']);
        }
    }

    private function swap(array $unit): void
    {
        $target = (string) $unit['target'];
        $backup = (string) $unit['backup'];
        $temp = (string) $unit['temp'];
        if (file_exists($target)) {
            $this->ensureDirectory(dirname($backup));
            if (!rename($target, $backup)) {
                throw new RuntimeException('无法备份原生 App publication unit：' . $target);
            }
        }
        if ($unit['source'] !== null && !rename($temp, $target)) {
            if (is_dir($backup)) {
                @rename($backup, $target);
            }
            throw new RuntimeException('无法原子切换原生 App publication unit：' . $target);
        }
    }

    private function restore(array $journal): void
    {
        $units = array_reverse((array) ($journal['units'] ?? []));
        foreach ($units as $unit) {
            $target = (string) ($unit['target'] ?? '');
            $backup = (string) ($unit['backup'] ?? '');
            $temp = (string) ($unit['temp'] ?? '');
            if ($target !== '') {
                $this->removeTree($target);
            }
            if ($backup !== '' && is_dir($backup)) {
                $this->ensureDirectory(dirname($target));
                if (!rename($backup, $target)) {
                    throw new RuntimeException('无法恢复原生 App publication unit：' . $target);
                }
            }
            $this->removeTree($temp);
        }
        $this->repository->replaceAppForPlugin(
            (string) $journal['plugin'],
            (array) ($journal['registry_before'] ?? [])
        );
    }

    private function recordsForUnit(
        string $pluginCode,
        string $version,
        string $token,
        string $unit,
        string $source,
        string $target,
        string $treeHash
    ): array {
        $records = [];
        $now = date('Y-m-d H:i:s');
        foreach ($this->files($source) as $file) {
            $relative = $this->relativePath($source, $file);
            $sha256 = hash_file('sha256', $file);
            if ($sha256 === false) {
                throw new RuntimeException('无法计算原生 App SHA-256：' . $file);
            }
            $records[] = [
                'plugin_code' => $pluginCode,
                'version' => $version,
                'resource_type' => self::RESOURCE_TYPE,
                'publication_unit' => $unit,
                'operation_token' => $token,
                'source_path' => str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen(dirname(dirname($source))) + 1)),
                'target_path' => $this->targetRegistryPath($target . DIRECTORY_SEPARATOR . $relative),
                'sha256' => $sha256,
                'tree_hash' => $treeHash,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        return $records;
    }

    private function copyTree(string $source, string $target): void
    {
        $this->removeTree($target);
        $this->ensureDirectory($target);
        foreach ($this->files($source) as $file) {
            $relative = $this->relativePath($source, $file);
            $destination = $target . DIRECTORY_SEPARATOR . $relative;
            $this->ensureDirectory(dirname($destination));
            if (!copy($file, $destination)) {
                throw new RuntimeException('原生 App 文件复制失败：' . $relative);
            }
        }
    }

    private function treeHash(string $directory): string
    {
        $hashes = [];
        foreach ($this->files($directory) as $file) {
            $hash = hash_file('sha256', $file);
            if ($hash === false) {
                throw new RuntimeException('无法计算 publication tree hash：' . $file);
            }
            $hashes[] = $this->relativePath($directory, $file) . "\0" . $hash;
        }
        return hash('sha256', implode("\n", $hashes));
    }

    private function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('原生 App 目录禁止符号链接：' . $item->getPathname());
            }
            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function assertSafeSourceTree(string $directory, string $root): void
    {
        $realRoot = realpath($root);
        $realDirectory = realpath($directory);
        if ($realRoot === false || $realDirectory === false || !str_starts_with($realDirectory, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('原生 App 源路径越界：' . $directory);
        }
        $this->files($realDirectory);
    }

    private function unitTarget(string $unit, string $pluginCode): string
    {
        if ($unit === 'app:' . $pluginCode) {
            return $this->appDirectory() . DIRECTORY_SEPARATOR . $pluginCode;
        }
        if (preg_match('/^console:([a-z]+):' . preg_quote($pluginCode, '/') . '$/', $unit, $match) === 1
            && in_array($match[1], self::CONSOLE_LAYERS, true)) {
            return $this->appDirectory() . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . $match[1]
                . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . $pluginCode;
        }
        throw new RuntimeException('registry publication unit 非法：' . $unit);
    }

    private function ownedRecords(string $pluginCode, ?array $records = null): array
    {
        return array_values(array_filter(
            $records ?? $this->repository->all(),
            static fn (array $row): bool => ($row['plugin_code'] ?? '') === $pluginCode
                && ($row['resource_type'] ?? '') === self::RESOURCE_TYPE
        ));
    }

    private function recordsByUnit(array $records): array
    {
        $grouped = [];
        foreach ($records as $record) {
            $unit = (string) ($record['publication_unit'] ?? '');
            if ($unit === '') {
                throw new RuntimeException('原生 App registry 缺少 publication_unit');
            }
            $grouped[$unit][] = $record;
        }
        ksort($grouped, SORT_STRING);
        return $grouped;
    }

    private function registryTarget(string $targetPath): string
    {
        if (!str_starts_with($targetPath, 'app/')) {
            throw new RuntimeException('原生 App registry 目标越界：' . $targetPath);
        }
        $target = $this->rootDirectory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetPath);
        $this->assertNoTargetSymlink($target);
        return $target;
    }

    private function targetRegistryPath(string $target): string
    {
        $root = $this->rootDirectory();
        if ($target !== $root && !str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('原生 App 目标路径越界：' . $target);
        }
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($root) + 1));
    }

    private function relativePath(string $root, string $path): string
    {
        if (!str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('publication 文件路径越界：' . $path);
        }
        return substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
    }

    private function assertNoTargetSymlink(string $target): void
    {
        $root = $this->rootDirectory();
        if ($target !== $root && !str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('原生 App 目标路径越界');
        }
        $current = $root;
        $relative = substr($target, strlen($root) + 1);
        foreach (array_filter(explode(DIRECTORY_SEPARATOR, $relative), 'strlen') as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new RuntimeException('原生 App 目标路径禁止符号链接：' . $this->targetRegistryPath($current));
            }
        }
    }

    private function backupPath(string $token, string $target): string
    {
        return $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-backups' . DIRECTORY_SEPARATOR . $token
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->targetRegistryPath($target));
    }

    private function journalFile(string $token): string
    {
        return $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-journals' . DIRECTORY_SEPARATOR
            . $token . DIRECTORY_SEPARATOR . 'journal.json';
    }

    private function writeJournal(string $token, array $journal): void
    {
        $journal['updated_at'] = date(DATE_ATOM);
        $file = $this->journalFile($token);
        $this->ensureDirectory(dirname($file));
        $temporary = tempnam(dirname($file), 'journal.tmp.');
        if ($temporary === false) {
            throw new RuntimeException('无法创建 publication journal 临时文件');
        }
        try {
            $json = json_encode($journal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !rename($temporary, $file)) {
                throw new RuntimeException('publication journal 原子写入失败');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function cleanupArtifacts(array $journal): void
    {
        foreach ((array) ($journal['units'] ?? []) as $unit) {
            $this->removeTree((string) ($unit['temp'] ?? ''));
            $this->removeTree((string) ($unit['backup'] ?? ''));
        }
        $backupRoot = $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-backups'
            . DIRECTORY_SEPARATOR . (string) $journal['token'];
        $this->removeEmptyParents($backupRoot, $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-backups');
    }

    private function locked(callable $operation): mixed
    {
        $lockFile = $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication.lock';
        $lock = fopen($lockFile, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('无法获取插件全局发布锁');
        }
        try {
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function directoryEntries(string $directory): array
    {
        $entries = glob($directory . DIRECTORY_SEPARATOR . '*') ?: [];
        sort($entries, SORT_STRING);
        return $entries;
    }

    private function removeTree(string $directory): void
    {
        if ($directory === '' || (!is_dir($directory) && !is_link($directory))) {
            return;
        }
        if (is_link($directory)) {
            throw new RuntimeException('拒绝删除符号链接 publication 路径：' . $directory);
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('拒绝删除包含符号链接的 publication unit：' . $item->getPathname());
            }
            $ok = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (!$ok) {
                throw new RuntimeException('无法清理 publication 路径：' . $item->getPathname());
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('无法清理 publication 目录：' . $directory);
        }
    }

    private function removeEmptyParents(string $directory, string $stop): void
    {
        while ($directory !== $stop && is_dir($directory) && (scandir($directory) ?: []) === ['.', '..']) {
            if (!rmdir($directory)) {
                return;
            }
            $directory = dirname($directory);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建 publication 目录：' . $directory);
        }
    }

    private function rootDirectory(): string
    {
        $root = dirname($this->appDirectory());
        $real = realpath($root);
        if ($real === false) {
            throw new RuntimeException('无法解析项目根目录');
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function appDirectory(): string
    {
        $this->ensureDirectory($this->appRoot);
        $real = realpath($this->appRoot);
        if ($real === false) {
            throw new RuntimeException('无法解析 App 根目录');
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function runtimeDirectory(): string
    {
        $this->ensureDirectory($this->runtimeRoot);
        $real = realpath($this->runtimeRoot);
        if ($real === false) {
            throw new RuntimeException('无法解析 publication runtime 目录');
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function unitSlug(string $unit): string
    {
        return str_replace(':', '-', $unit);
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
            throw new RuntimeException('publication operation token 不合法');
        }
    }
}
