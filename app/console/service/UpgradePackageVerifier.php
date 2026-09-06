<?php

declare(strict_types=1);

namespace app\backend\service;

use RuntimeException;
use ZipArchive;

/** 校验系统升级 ZIP 的完整性、版本和文件系统边界。 */
final class UpgradePackageVerifier
{
    private const MAX_UNPACKED_BYTES = 536870912;
    private const ALLOWED_ROOTS = ['admin-web', 'app', 'config', 'database', 'extend', 'public', 'route'];

    public function __construct(
        private readonly int $maxBytes = 104857600,
        private readonly ?UpgradeManifestVerifier $manifestVerifier = null
    ) {
    }

    public function verify(string $archive, string $checksum, string $expectedVersion = ''): array
    {
        if (!is_file($archive) || ($size = filesize($archive)) === false || $size < 1 || $size > $this->maxBytes) {
            throw new RuntimeException('升级包不存在、为空或超过大小限制');
        }
        $actual = hash_file('sha256', $archive);
        if (!is_string($actual)) {
            throw new RuntimeException('无法计算升级包 SHA-256');
        }
        $checksum = strtolower(trim($checksum));
        if ($checksum !== '' && (preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1 || !hash_equals($checksum, strtolower($actual)))) {
            throw new RuntimeException('升级包 SHA-256 校验失败');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('服务器未安装 ZipArchive 扩展');
        }

        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('无法打开升级 ZIP');
        }
        try {
            $files = [];
            $unpacked = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = str_replace('\\', '/', (string) $zip->getNameIndex($index));
                $this->assertEntry($zip, $index, $entry);
                $stat = $zip->statIndex($index);
                $unpacked += (int) ($stat['size'] ?? 0);
                if ($unpacked > self::MAX_UNPACKED_BYTES) {
                    throw new RuntimeException('升级包解压后超过 500MB 限制');
                }
                if (!str_ends_with($entry, '/')) {
                    $files[] = $entry;
                }
            }
            $manifestJson = $zip->getFromName('upgrade.json');
            $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
            if (!is_array($manifest)) {
                throw new RuntimeException('升级包缺少有效 upgrade.json');
            }
            $verifier = $this->manifestVerifier;
            if (!$verifier) {
                throw new RuntimeException('升级可信公钥未配置，禁止执行离线升级');
            }
            $manifest = $verifier->verify($manifest);
            $version = trim((string) ($manifest['version'] ?? ''));
            if (!$this->isVersion($version) || ($expectedVersion !== '' && $version !== $expectedVersion)) {
                throw new RuntimeException('升级包版本与目标版本不一致');
            }
            $payloadFiles = array_values(array_diff($files, ['upgrade.json']));
            $this->verifyFileManifest($zip, $payloadFiles, $manifest);
            return ['version' => $version, 'files' => $payloadFiles, 'manifest' => $manifest, 'sha256' => $actual];
        } finally {
            $zip->close();
        }
    }

    public function extract(string $archive, string $directory, array $files): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建升级暂存目录');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('无法打开升级 ZIP');
        }
        try {
            foreach ($files as $entry) {
                $index = $zip->locateName($entry);
                if ($index === false) {
                    throw new RuntimeException('升级包文件清单不一致');
                }
                $this->assertEntry($zip, $index, $entry);
                $target = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
                $parent = dirname($target);
                if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
                    throw new RuntimeException('无法创建升级暂存子目录');
                }
                $source = $zip->getStream($entry);
                $destination = fopen($target, 'xb');
                if (!is_resource($source) || !is_resource($destination)) {
                    throw new RuntimeException('无法安全解压升级文件');
                }
                stream_copy_to_stream($source, $destination);
                fclose($source);
                fclose($destination);
            }
        } finally {
            $zip->close();
        }
    }

    private function assertEntry(ZipArchive $zip, int $index, string $entry): void
    {
        if ($entry === '' || str_contains($entry, "\0") || str_starts_with($entry, '/') || preg_match('~(^|/)\.\.?(/|$)~', $entry)) {
            throw new RuntimeException('升级 ZIP 包含越界路径');
        }
        if ($entry !== 'upgrade.json' && !in_array(explode('/', $entry)[0], self::ALLOWED_ROOTS, true)) {
            throw new RuntimeException('升级 ZIP 包含不允许覆盖的路径：' . $entry);
        }
        $attributes = 0;
        if ($zip->getExternalAttributesIndex($index, $opsys, $attributes) && (($attributes >> 16) & 0170000) === 0120000) {
            throw new RuntimeException('升级 ZIP 禁止符号链接');
        }
    }

    private function verifyFileManifest(ZipArchive $zip, array $files, array $manifest): void
    {
        $declared = $manifest['files'] ?? null;
        $declaredFiles = is_array($declared) ? array_keys($declared) : [];
        sort($declaredFiles, SORT_STRING);
        sort($files, SORT_STRING);
        if (!is_array($declared) || $declaredFiles !== $files) {
            throw new RuntimeException('升级包文件清单不一致');
        }
        foreach ($declared as $file => $checksum) {
            $content = $zip->getFromName((string) $file);
            if (!is_string($content) || preg_match('/^[a-f0-9]{64}$/', (string) $checksum) !== 1 || !hash_equals((string) $checksum, hash('sha256', $content))) {
                throw new RuntimeException('升级包文件签名清单校验失败：' . $file);
            }
        }
    }

    private function isVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1;
    }
}
