<?php

declare(strict_types=1);

namespace app\common\plugin\package;

use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use InvalidArgumentException;
use RuntimeException;

/**
 * 将云端插件包安全流式写入非公开 runtime 临时目录。
 */
final class PluginPackageDownloader
{
    private const MAX_BYTES = 104857600;

    /** @param callable(string, string, array): void $streamDownload */
    public function __construct(
        private readonly string $directory,
        private readonly mixed $streamDownload,
        private readonly ?string $publicKey,
        private readonly string $unsignedPolicy = 'reject_unsigned'
    ) {
        if (!in_array($unsignedPolicy, ['reject_unsigned', 'allow_unsigned', 'require_signature'], true)) {
            throw new InvalidArgumentException('未知的未签名包策略');
        }
        if ($unsignedPolicy === 'require_signature' && trim((string) $publicKey) === '') {
            throw new InvalidArgumentException('签名必需策略必须配置公钥');
        }
    }

    public function download(DownloadDescriptorDto $descriptor): string
    {
        $this->createDirectory();
        $target = $this->directory . DIRECTORY_SEPARATOR . $descriptor->name . '-' . bin2hex(random_bytes(8)) . '.zip';
        try {
            ($this->streamDownload)($descriptor->url, $target, [
                'timeout' => 120,
                'connect_timeout' => 10,
                'max_bytes' => self::MAX_BYTES,
                'max_redirects' => 3,
                'protocols' => ['https', 'http'],
            ]);
            $this->verifyFile($target, $descriptor);
            return $target;
        } catch (\Throwable $exception) {
            if (is_file($target)) {
                @unlink($target);
            }
            if ($exception instanceof RuntimeException || $exception instanceof InvalidArgumentException) {
                throw $exception;
            }
            throw new RuntimeException('插件安装包下载失败：' . $exception->getMessage(), 0, $exception);
        }
    }

    public function delete(string $file): void
    {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function verifyFile(string $file, DownloadDescriptorDto $descriptor): void
    {
        $size = is_file($file) ? filesize($file) : false;
        if ($size === false || $size < 1 || $size > self::MAX_BYTES || $size !== $descriptor->size) {
            throw new RuntimeException('插件安装包大小不匹配或超过 100MB 限制');
        }
        $actualHash = hash_file('sha256', $file);
        if (!is_string($actualHash) || !hash_equals(strtolower($descriptor->sha256), strtolower($actualHash))) {
            throw new RuntimeException('插件安装包 SHA-256 校验失败');
        }
        $this->verifySignature($actualHash, $descriptor);
    }

    private function verifySignature(string $hash, DownloadDescriptorDto $descriptor): void
    {
        if ($descriptor->signature === null) {
            if ($this->unsignedPolicy !== 'allow_unsigned') {
                throw new RuntimeException('当前策略拒绝未签名插件包');
            }
            return;
        }
        if ($this->publicKey === null || trim($this->publicKey) === '') {
            if ($this->unsignedPolicy === 'allow_unsigned') {
                return;
            }
            throw new RuntimeException('插件包包含签名但未配置验证公钥');
        }
        if (strtolower((string) $descriptor->algorithm) !== 'sha256') {
            throw new RuntimeException('不支持的插件包签名算法');
        }
        $signature = base64_decode($descriptor->signature, true);
        if ($signature === false || !function_exists('openssl_verify')) {
            throw new RuntimeException('插件包签名格式无效或 OpenSSL 不可用');
        }
        if (openssl_verify($hash, $signature, $this->publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('插件包签名验证失败');
        }
    }

    private function createDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new RuntimeException('无法创建插件下载临时目录');
        }
    }
}
