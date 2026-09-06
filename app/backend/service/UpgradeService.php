<?php

declare(strict_types=1);

namespace app\backend\service;

use app\common\model\UpgradeManifest;
use app\common\model\UpgradeTask;
use app\common\service\AbstractService;
use app\common\service\MigrationService;
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use RuntimeException;
use think\facade\Db;
use Throwable;

/** 系统升级编排：可信 manifest、全局互斥、备份、部署、migration 与失败恢复。 */
final class UpgradeService extends AbstractService
{
    private const maxBytes = 104857600;
    private const timeout = 120;

    public function __construct(
        private readonly ?string $projectRoot = null,
        private readonly ?string $runtimeRoot = null,
        private readonly mixed $downloader = null,
        private readonly mixed $dnsResolver = null,
        private readonly ?array $hostAllowlist = null,
        private readonly ?UpgradeManifestVerifier $manifestVerifier = null,
        private readonly ?UpgradeDatabaseBackup $databaseBackup = null
    ) {
        parent::__construct();
    }

    public function status(): array
    {
        $tasks = UpgradeTask::order('id', 'desc')->limit(20)->select()->toArray();
        return ['currentVersion' => (string) config('funadmin.version'), 'tasks' => $tasks];
    }

    public function check(): array
    {
        $url = rtrim((string) config('funadmin.api_domain'), '/') . '/api/upgrade/manifest';
        $response = (new Client())->get($url, $this->trustedConnectionOptions($url, 10, 5));
        $body = $response->getBody()->read(1048577);
        if (strlen($body) > 1048576) {
            throw new RuntimeException('升级检查响应超过 1MB 限制');
        }
        $manifest = json_decode($body, true);
        if (!is_array($manifest)) {
            throw new RuntimeException('升级检查响应格式无效');
        }
        $verified = $this->validateRemoteManifest($this->verifier()->verify($manifest));
        $manifestId = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', time() + $this->manifestTtl());
        UpgradeManifest::create([
            'manifest_id' => $manifestId,
            'payload' => $verified,
            'expires_at' => $expiresAt,
            'consumed_at' => null,
        ]);
        return [
            'manifestId' => $manifestId,
            'version' => $verified['version'],
            'changelog' => (string) ($verified['changelog'] ?? ''),
            'expiresAt' => $expiresAt,
        ];
    }

    public function execute(array $request): array
    {
        if (array_diff(array_keys($request), ['manifestId', 'operationToken']) !== []) {
            throw new RuntimeException('在线升级仅接受 manifestId 与 operationToken');
        }
        $manifestId = $this->manifestId((string) ($request['manifestId'] ?? ''));
        $operationToken = $this->operationToken((string) ($request['operationToken'] ?? ''));
        if ($existing = UpgradeTask::where('idempotency_token', $operationToken)->find()) {
            return $existing->toArray();
        }
        return $this->withSystemLock(function () use ($manifestId, $operationToken): array {
            [$task, $claimed, $manifest] = $this->claimOnlineTask($manifestId, $operationToken);
            if (!$claimed) {
                return $task->toArray();
            }
            $archive = $this->runtime() . '/downloads/' . bin2hex(random_bytes(12)) . '.zip';
            $this->createDirectory(dirname($archive));
            try {
                $this->advance($task, 'download', 10);
                $this->download($manifest['packageUrl'], $archive);
                return $this->applyClaimed($task, $archive, $manifest['sha256'], $manifest['version']);
            } catch (Throwable $exception) {
                if ((string) $task->status === 'running') {
                    $task->save([
                        'status' => 'failed', 'stage' => 'download', 'error_message' => $exception->getMessage(),
                        'active_slot' => null, 'lease_expires_at' => null,
                    ]);
                }
                throw $exception;
            } finally {
                if (is_file($archive)) {
                    @unlink($archive);
                }
            }
        });
    }

    public function upload(string $archive, string $operationToken): array
    {
        $operationToken = $this->operationToken($operationToken);
        if ($existing = UpgradeTask::where('idempotency_token', $operationToken)->find()) {
            return $existing->toArray();
        }
        return $this->withSystemLock(function () use ($archive, $operationToken): array {
            $verified = (new UpgradePackageVerifier(self::maxBytes, $this->verifier()))->verify($archive, '');
            [$task, $claimed] = $this->claimTask($operationToken, $verified['version'], $verified['sha256'], null, 'offline');
            return $claimed
                ? $this->applyClaimed($task, $archive, $verified['sha256'], $verified['version'], $verified)
                : $task->toArray();
        });
    }

    public function restore(int $taskId, string $operationToken): array
    {
        $operationToken = $this->operationToken($operationToken);
        return $this->withSystemLock(function () use ($taskId, $operationToken): array {
            $task = $this->claimRestoreTask($taskId, $operationToken);
            return $this->restoreClaimedTask($task, $operationToken)->toArray();
        });
    }

    public function recoverStale(): array
    {
        return $this->withSystemLock(function (): array {
            $task = $this->claimStaleTask();
            if (!$task) {
                return ['recovered' => false, 'message' => '没有租约已过期的升级任务'];
            }
            if (!$this->requiresRollback((string) $task->stage)) {
                $task->save([
                    'status' => 'failed', 'stage' => 'stale_pre_deploy',
                    'error_message' => '升级进程在部署前终止，陈旧租约已安全释放',
                    'active_slot' => null, 'lease_expires_at' => null,
                ]);
                return ['recovered' => true, 'task' => $task->toArray()];
            }
            return ['recovered' => true, 'task' => $this->restoreClaimedTask($task, 'stale-recovery-' . $task->id)->toArray()];
        });
    }

    private function applyClaimed(UpgradeTask $task, string $archive, string $checksum, string $version, ?array $verified = null): array
    {
        $stage = $this->runtime() . '/stage/' . $task->idempotency_token;
        $backup = $this->runtime() . '/backup/' . $task->id . '-' . date('YmdHis');
        $dbBackup = $backup . '/database.snapshot';
        try {
            $verified ??= (new UpgradePackageVerifier(self::maxBytes, $this->verifier()))->verify($archive, $checksum, $version);
            $this->advance($task, 'extract', 20);
            (new UpgradePackageVerifier(self::maxBytes, $this->verifier()))->extract($archive, $stage, $verified['files']);
            $this->advance($task, 'backup', 35);
            $createdFiles = $this->backupFiles($backup, $verified['files']);
            $metadata = (array) ($task->metadata ?? []);
            $metadata += ['files' => $verified['files'], 'createdFiles' => $createdFiles];
            $backupHash = $this->fileBackupHash($backup, $verified['files'], $createdFiles);
            $task->save(['backup_path' => $backup, 'backup_hash' => $backupHash, 'db_backup_path' => $dbBackup, 'metadata' => $metadata]);
            $this->advance($task, 'database_backup', 45);
            $dbBackupHash = $this->databaseBackup()->backup($dbBackup);
            $task->save(['db_backup_hash' => $dbBackupHash]);
            $this->advance($task, 'deploy', 55);
            $this->deployFiles($stage, $verified['files']);
            $this->advance($task, 'migration', 75);
            $migrationDirectory = $stage . '/database/migrations';
            $migrations = is_dir($migrationDirectory) ? MigrationService::instance()->runDirectory($migrationDirectory) : [];
            $task->save([
                'status' => 'success', 'stage' => 'complete', 'progress' => 100,
                'metadata' => $metadata + ['migrations' => $migrations], 'active_slot' => null, 'lease_expires_at' => null,
            ]);
        } catch (Throwable $exception) {
            $failedStage = (string) $task->stage;
            if ($this->requiresRollback($failedStage)) {
                try {
                    $this->restoreArtifacts($task);
                    $task->save([
                        'status' => 'failed', 'stage' => 'restored_after_failure',
                        'error_message' => $exception->getMessage(), 'active_slot' => null, 'lease_expires_at' => null,
                    ]);
                } catch (Throwable $restoreException) {
                    $message = $exception->getMessage() . '；自动恢复失败：' . $restoreException->getMessage();
                    $task->save([
                        'status' => 'requires_manual_recovery', 'stage' => 'restore_failed',
                        'error_message' => $message, 'active_slot' => 1, 'lease_expires_at' => date('Y-m-d H:i:s'),
                    ]);
                    throw new RuntimeException('系统升级失败且必须人工恢复：' . $message, 0, $exception);
                }
            } else {
                $task->save([
                    'status' => 'failed', 'stage' => $failedStage, 'error_message' => $exception->getMessage(),
                    'active_slot' => null, 'lease_expires_at' => null,
                ]);
            }
            throw new RuntimeException('系统升级失败：' . $exception->getMessage(), 0, $exception);
        } finally {
            $this->removeDirectory($stage);
        }
        return $task->toArray();
    }

    private function claimOnlineTask(string $manifestId, string $operationToken): array
    {
        try {
            return Db::transaction(function () use ($manifestId, $operationToken): array {
                $now = date('Y-m-d H:i:s');
                $record = UpgradeManifest::where('manifest_id', $manifestId)->lock(true)->find();
                if (!$record || $record->consumed_at !== null || strtotime((string) $record->expires_at) <= time()
                    || !is_array($record->payload)) {
                    throw new RuntimeException('升级 manifest 不存在、已过期或已使用', 409);
                }
                $manifest = $this->validateRemoteManifest($this->verifier()->verify($record->payload));
                [$task, $claimed] = $this->claimTask($operationToken, $manifest['version'], $manifest['sha256'], $manifestId, 'online');
                if (!$claimed) {
                    return [$task, false, $manifest];
                }
                $updated = UpgradeManifest::where('id', $record->id)->whereNull('consumed_at')
                    ->where('expires_at', '>', $now)->update(['consumed_at' => $now]);
                if ($updated !== 1) {
                    throw new RuntimeException('升级 manifest 并发消费冲突', 409);
                }
                return [$task, true, $manifest];
            });
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException && $exception->getCode() === 409) {
                throw $exception;
            }
            $this->throwConflict($exception);
        }
    }

    private function claimTask(string $operationToken, string $version, string $checksum, ?string $manifestId, string $source): array
    {
        try {
            $task = UpgradeTask::create([
                'idempotency_token' => $operationToken,
                'manifest_id' => $manifestId,
                'from_version' => (string) config('funadmin.version'),
                'to_version' => $version,
                'status' => 'running',
                'stage' => 'verify',
                'progress' => 5,
                'package_hash' => $checksum,
                'active_slot' => 1,
                'lease_expires_at' => $this->leaseExpiry(),
                'metadata' => ['source' => $source],
            ]);
            return [$task, true];
        } catch (Throwable $exception) {
            if ($existing = UpgradeTask::where('idempotency_token', $operationToken)->find()) {
                return [$existing, false];
            }
            $this->throwConflict($exception);
        }
    }

    private function claimRestoreTask(int $taskId, string $operationToken): UpgradeTask
    {
        return Db::transaction(function () use ($taskId, $operationToken): UpgradeTask {
            $task = UpgradeTask::where('id', $taskId)->lock(true)->find();
            if (!$task || !$this->hasRecoveryArtifacts($task)) {
                throw new RuntimeException('升级恢复备份不存在');
            }
            $lease = (string) ($task->lease_expires_at ?? '');
            if ((int) ($task->active_slot ?? 0) === 1 && $lease !== '' && strtotime($lease) > time()) {
                throw new RuntimeException('该任务仍在执行，禁止并发恢复', 409);
            }
            $metadata = (array) ($task->metadata ?? []);
            if (($metadata['restoreToken'] ?? '') === $operationToken && (string) $task->status === 'restored') {
                return $task;
            }
            $task->save([
                'status' => 'running', 'stage' => 'restore_database', 'active_slot' => 1,
                'lease_expires_at' => $this->leaseExpiry(),
                'metadata' => $metadata + ['restoreToken' => $operationToken],
            ]);
            return $task;
        });
    }

    private function claimStaleTask(): ?UpgradeTask
    {
        return Db::transaction(function (): ?UpgradeTask {
            $now = date('Y-m-d H:i:s');
            $task = UpgradeTask::where('active_slot', 1)
                ->where('lease_expires_at', '<=', $now)->lock(true)->find();
            if (!$task) {
                return null;
            }
            $updated = UpgradeTask::where('id', $task->id)->where('active_slot', 1)
                ->where('lease_expires_at', '<=', $now)->update([
                    'status' => 'recovering', 'lease_expires_at' => $this->leaseExpiry(),
                ]);
            if ($updated !== 1) {
                throw new RuntimeException('陈旧升级任务已被其他恢复进程接管', 409);
            }
            $task->status = 'recovering';
            $task->lease_expires_at = $this->leaseExpiry();
            return $task;
        });
    }

    private function throwConflict(Throwable $previous): never
    {
        $active = UpgradeTask::where('active_slot', 1)->find();
        if ($active) {
            $lease = (string) ($active->lease_expires_at ?? '');
            $message = $lease !== '' && strtotime($lease) <= time()
                ? '检测到租约已过期的升级任务，必须人工确认并恢复，禁止静默并发'
                : '已有系统升级或恢复任务正在执行';
            throw new RuntimeException($message, 409, $previous);
        }
        throw new RuntimeException('系统升级任务原子占用失败', 409, $previous);
    }

    private function restoreClaimedTask(UpgradeTask $task, string $operationToken): UpgradeTask
    {
        try {
            $this->restoreArtifacts($task);
            $metadata = (array) ($task->metadata ?? []);
            $metadata['restoreToken'] = $operationToken;
            $task->save([
                'status' => 'restored', 'stage' => 'restore_complete', 'progress' => 100,
                'error_message' => '', 'metadata' => $metadata, 'active_slot' => null, 'lease_expires_at' => null,
            ]);
        } catch (Throwable $exception) {
            $task->save([
                'status' => 'requires_manual_recovery', 'stage' => 'restore_failed',
                'error_message' => $exception->getMessage(), 'active_slot' => 1,
                'lease_expires_at' => date('Y-m-d H:i:s'),
            ]);
            throw $exception;
        }
        return $task;
    }

    private function restoreArtifacts(UpgradeTask $task): void
    {
        if (!$this->hasRecoveryArtifacts($task)) {
            throw new RuntimeException('升级恢复备份不完整');
        }
        $metadata = (array) ($task->metadata ?? []);
        $this->advance($task, 'restore_database', 82);
        $restoreReport = $this->databaseBackup()->restore((string) $task->db_backup_path, (string) $task->db_backup_hash);
        UpgradeTask::where('id', $task->id)->update([
            'backup_path' => $task->backup_path, 'backup_hash' => $task->backup_hash,
            'db_backup_path' => $task->db_backup_path, 'db_backup_hash' => $task->db_backup_hash,
            'db_backup_schema' => (string) ($restoreReport['backup_schema'] ?? ''),
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'active_slot' => 1, 'lease_expires_at' => $this->leaseExpiry(),
        ]);
        $this->advance($task, 'restore_files', 90);
        $this->assertFileBackupHash($task, $metadata);
        $this->restoreFiles((string) $task->backup_path, (array) ($metadata['files'] ?? []), (array) ($metadata['createdFiles'] ?? []));
    }

    private function hasRecoveryArtifacts(UpgradeTask $task): bool
    {
        return $this->isRuntimeBackupPath((string) $task->backup_path)
            && $this->isRuntimeBackupPath((string) $task->db_backup_path)
            && is_dir((string) $task->backup_path)
            && is_file((string) $task->db_backup_path)
            && preg_match('/^[a-f0-9]{64}$/', (string) $task->backup_hash) === 1
            && preg_match('/^[a-f0-9]{64}$/', (string) $task->db_backup_hash) === 1;
    }

    private function requiresRollback(string $stage): bool
    {
        return in_array($stage, ['deploy', 'migration', 'restore_database', 'restore_files', 'restore_failed'], true);
    }

    private function isRuntimeBackupPath(string $path): bool
    {
        $root = $this->runtime() . '/backup';
        if ($path === '' || !str_starts_with($path, $root . '/') || is_link($path)) {
            return false;
        }
        for ($current = dirname($path); str_starts_with($current, $root); $current = dirname($current)) {
            if (is_link($current)) {
                return false;
            }
            if ($current === $root) {
                return true;
            }
        }
        return false;
    }

    private function advance(UpgradeTask $task, string $stage, int $progress): void
    {
        $this->renewLease($task, ['stage' => $stage, 'progress' => $progress]);
        $task->stage = $stage;
        $task->progress = $progress;
    }

    private function renewLease(UpgradeTask $task, array $state = []): void
    {
        $updated = UpgradeTask::where('id', $task->id)->where('active_slot', 1)->update($state + ['lease_expires_at' => $this->leaseExpiry()]);
        if ($updated !== 1) {
            throw new RuntimeException('系统升级任务租约已丢失', 409);
        }
    }

    private function withSystemLock(callable $operation): array
    {
        $directory = $this->runtime();
        $this->createDirectory($directory);
        $handle = fopen($directory . '/system-upgrade.lock', 'c');
        if (!is_resource($handle) || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('已有系统升级或恢复任务正在执行', 409);
        }
        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function validateRemoteManifest(array $manifest): array
    {
        $version = $this->version((string) ($manifest['version'] ?? ''));
        $packageUrl = trim((string) ($manifest['packageUrl'] ?? ''));
        $checksum = strtolower(trim((string) ($manifest['sha256'] ?? '')));
        $size = filter_var($manifest['size'] ?? null, FILTER_VALIDATE_INT);
        $this->assertTrustedHttpsUrl($packageUrl);
        if (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1) {
            throw new RuntimeException('升级 manifest SHA-256 格式无效');
        }
        if ($size === false || $size < 1 || $size > self::maxBytes) {
            throw new RuntimeException('升级 manifest 包大小无效或超过 100MB 限制');
        }
        return array_replace($manifest, ['version' => $version, 'packageUrl' => $packageUrl, 'sha256' => $checksum, 'size' => $size]);
    }

    private function download(string $url, string $target): void
    {
        if (is_callable($this->downloader)) {
            ($this->downloader)($url, $target, ['timeout' => self::timeout, 'maxBytes' => self::maxBytes, 'allowlist' => $this->allowedHosts()]);
            return;
        }
        $client = new Client();
        for ($redirects = 0; $redirects <= 2; $redirects++) {
            $response = $client->get($url, $this->trustedConnectionOptions($url, self::timeout, 10) + [
                'stream' => true, 'http_errors' => false,
                'on_stats' => static function (TransferStats $stats): void {},
            ]);
            $status = $response->getStatusCode();
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = trim($response->getHeaderLine('Location'));
                if ($location === '' || $redirects === 2) {
                    throw new RuntimeException('升级下载重定向无效或次数过多');
                }
                $url = (string) \GuzzleHttp\Psr7\UriResolver::resolve(new \GuzzleHttp\Psr7\Uri($url), new \GuzzleHttp\Psr7\Uri($location));
                $this->assertTrustedHttpsUrl($url);
                continue;
            }
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('升级包下载失败，HTTP 状态：' . $status);
            }
            $output = fopen($target, 'xb');
            if ($output === false) {
                throw new RuntimeException('无法创建升级包临时文件');
            }
            $received = 0;
            try {
                $body = $response->getBody();
                while (!$body->eof()) {
                    $chunk = $body->read(1048576);
                    $received += strlen($chunk);
                    if ($received > self::maxBytes) {
                        throw new RuntimeException('升级包超过 100MB 限制');
                    }
                    if ($chunk !== '' && fwrite($output, $chunk) !== strlen($chunk)) {
                        throw new RuntimeException('写入升级包临时文件失败');
                    }
                }
            } finally {
                fclose($output);
            }
            return;
        }
        throw new RuntimeException('升级包下载失败');
    }

    private function assertTrustedHttpsUrl(string $url): void
    {
        $this->trustedAddresses($url);
    }

    private function trustedConnectionOptions(string $url, int $timeout = self::timeout, int $connectTimeout = 10): array
    {
        [$host, $port, $addresses] = $this->trustedAddresses($url);
        $resolve = array_map(
            static fn (string $address): string => $host . ':' . $port . ':' . (str_contains($address, ':') ? '[' . $address . ']' : $address),
            $addresses
        );
        return ['timeout' => $timeout, 'connect_timeout' => $connectTimeout, 'verify' => true, 'allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => $resolve]];
    }

    private function trustedAddresses(string $url): array
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);
        if ($scheme !== 'https' || $host === '' || $port !== 443 || !in_array($host, $this->allowedHosts(), true)) {
            throw new RuntimeException('升级地址必须使用 HTTPS 且位于 host allowlist');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveAddresses($host);
        if ($addresses === []) {
            throw new RuntimeException('升级地址 DNS 解析失败');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('升级地址不得解析到私有或保留网络');
            }
        }
        return [$host, $port, $addresses];
    }

    private function resolveAddresses(string $host): array
    {
        if (is_callable($this->dnsResolver)) {
            return array_values(array_unique(array_filter(array_map('strval', (array) ($this->dnsResolver)($host)))));
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!is_array($records)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(
            static fn (array $record): string => (string) ($record['ip'] ?? $record['ipv6'] ?? ''),
            $records
        ))));
    }

    private function allowedHosts(): array
    {
        $configured = $this->hostAllowlist ?? (array) config('funadmin.upgrade_hosts', []);
        if ($this->hostAllowlist === null) {
            $configured[] = strtolower((string) parse_url((string) config('funadmin.api_domain'), PHP_URL_HOST));
        }
        return array_values(array_unique(array_filter(array_map(static fn (mixed $host): string => strtolower(trim((string) $host)), $configured))));
    }

    private function fileBackupHash(string $backup, array $files, array $createdFiles): string
    {
        $created = array_fill_keys($createdFiles, true);
        $hashes = [];
        foreach ($files as $relative) {
            if (isset($created[$relative])) {
                $hashes[$relative] = null;
                continue;
            }
            $hash = hash_file('sha256', $backup . '/' . $relative);
            if (!is_string($hash)) {
                throw new RuntimeException('无法计算文件备份哈希：' . $relative);
            }
            $hashes[$relative] = $hash;
        }
        ksort($hashes);
        return hash('sha256', json_encode($hashes, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function assertFileBackupHash(UpgradeTask $task, array $metadata): void
    {
        $actual = $this->fileBackupHash(
            (string) $task->backup_path,
            (array) ($metadata['files'] ?? []),
            (array) ($metadata['createdFiles'] ?? [])
        );
        if (!hash_equals((string) $task->backup_hash, $actual)) {
            throw new RuntimeException('文件备份 SHA-256 校验失败');
        }
    }

    private function databaseBackup(): UpgradeDatabaseBackup
    {
        return $this->databaseBackup ?? new UpgradeDatabaseBackup();
    }

    private function backupFiles(string $backup, array $files): array
    {
        $this->createDirectory($backup);
        $createdFiles = [];
        foreach ($files as $relative) {
            $source = $this->project() . '/' . $relative;
            $this->assertSafeProjectTarget($source);
            if (!is_file($source)) {
                $createdFiles[] = $relative;
                continue;
            }
            $target = $backup . '/' . $relative;
            $this->createDirectory(dirname($target));
            if (!copy($source, $target)) {
                throw new RuntimeException('备份项目文件失败：' . $relative);
            }
        }
        return $createdFiles;
    }

    private function deployFiles(string $stage, array $files): void
    {
        foreach ($files as $relative) {
            $source = $stage . '/' . $relative;
            $target = $this->project() . '/' . $relative;
            $this->assertSafeProjectTarget($target);
            $this->createDirectory(dirname($target));
            $temporary = $target . '.upgrade-' . bin2hex(random_bytes(4));
            if (!copy($source, $temporary) || !rename($temporary, $target)) {
                @unlink($temporary);
                throw new RuntimeException('部署升级文件失败：' . $relative);
            }
        }
    }

    private function restoreFiles(string $backup, array $files, array $createdFiles = []): void
    {
        $created = array_fill_keys($createdFiles, true);
        foreach ($files as $relative) {
            $target = $this->project() . '/' . $relative;
            $this->assertSafeProjectTarget($target);
            if (isset($created[$relative])) {
                if (is_file($target) && !unlink($target)) {
                    throw new RuntimeException('删除升级新增文件失败：' . $relative);
                }
                continue;
            }
            $source = $backup . '/' . $relative;
            if (!is_file($source)) {
                throw new RuntimeException('升级备份文件缺失：' . $relative);
            }
            $this->createDirectory(dirname($target));
            if (!copy($source, $target)) {
                throw new RuntimeException('恢复项目文件失败：' . $relative);
            }
        }
    }

    private function assertSafeProjectTarget(string $target): void
    {
        $root = $this->project();
        if (!str_starts_with($target, $root . '/') || is_link($target)) {
            throw new RuntimeException('项目目标路径越界或包含符号链接');
        }
        $current = dirname($target);
        while ($current !== $root && str_starts_with($current, $root . '/')) {
            if (is_link($current)) {
                throw new RuntimeException('项目目标目录禁止符号链接');
            }
            $current = dirname($current);
        }
    }

    private function verifier(): UpgradeManifestVerifier
    {
        return $this->manifestVerifier ?? new UpgradeManifestVerifier(
            trim((string) config('funadmin.upgrade_public_key', '')),
            strtolower(trim((string) config('funadmin.upgrade_signature_algorithm', 'ed25519')))
        );
    }

    private function version(string $version): string
    {
        $version = trim($version);
        if (preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new RuntimeException('目标版本必须是语义化版本');
        }
        return $version;
    }

    private function manifestId(string $manifestId): string
    {
        $manifestId = trim($manifestId);
        if (preg_match('/^[a-f0-9]{48}$/', $manifestId) !== 1 && preg_match('/^manifest-[A-Za-z0-9_-]{8,55}$/', $manifestId) !== 1) {
            throw new RuntimeException('manifestId 格式无效');
        }
        return $manifestId;
    }

    private function operationToken(string $operationToken): string
    {
        $operationToken = trim($operationToken);
        if (preg_match('/^[A-Za-z0-9_-]{12,64}$/', $operationToken) !== 1) {
            throw new RuntimeException('operationToken 格式无效');
        }
        return $operationToken;
    }

    private function manifestTtl(): int
    {
        return max(60, min(900, (int) config('funadmin.upgrade_manifest_ttl', 300)));
    }

    private function leaseExpiry(): string
    {
        $seconds = max(300, min(7200, (int) config('funadmin.upgrade_lease_seconds', 1800)));
        return date('Y-m-d H:i:s', time() + $seconds);
    }

    private function project(): string
    {
        return rtrim($this->projectRoot ?? root_path(), DIRECTORY_SEPARATOR);
    }

    private function runtime(): string
    {
        return rtrim($this->runtimeRoot ?? runtime_path('upgrade'), DIRECTORY_SEPARATOR);
    }

    private function createDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建升级工作目录');
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }
        foreach (new \FilesystemIterator($directory) as $item) {
            $item->isDir() && !$item->isLink() ? $this->removeDirectory($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
