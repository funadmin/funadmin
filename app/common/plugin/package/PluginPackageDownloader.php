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
        private readonly string $unsignedPolicy = 'reject_unsigned',
        private readonly bool $allowHttpForInjectedTestStream = false
    ) {
        if (!in_array($unsignedPolicy, ['reject_unsigned', 'allow_unsigned', 'require_signature'], true)) {
            throw new InvalidArgumentException('未知的未签名包策略');
        }
        if ($unsignedPolicy === 'require_signature' && trim((string) $publicKey) === '') {
            throw new InvalidArgumentException('签名必需策略必须配置公钥');
        }
    }

    public function assertCloudInstallationAllowed(): void
    {
        if ($this->unsignedPolicy === 'reject_unsigned' && trim((string) $this->publicKey) === '') {
            throw new RuntimeException('未配置市场公钥，请设置 PLUGIN_MARKETPLACE_PUBLIC_KEY');
        }
    }

    public function download(DownloadDescriptorDto $descriptor): string
    {
        if (strtolower((string) parse_url($descriptor->url, PHP_URL_SCHEME)) !== 'https' && !$this->allowHttpForInjectedTestStream) {
            throw new RuntimeException('生产云下载必须使用 HTTPS');
        }
        $this->createDirectory();
        $target = $this->directory . DIRECTORY_SEPARATOR . $descriptor->code . '-' . bin2hex(random_bytes(8)) . '.zip';
        try {
            ($this->streamDownload)($descriptor->url, $target, [
                'timeout' => 120,
                'connect_timeout' => 10,
                'max_bytes' => self::MAX_BYTES,
                'max_redirects' => 3,
                'protocols' => $this->allowHttpForInjectedTestStream ? ['https', 'http'] : ['https'],
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

    public function verificationMetadata(DownloadDescriptorDto $descriptor): array
    {
        return [
            'signature_algorithm' => $descriptor->algorithm,
            'signature_verified' => $descriptor->signature !== null && trim((string) $this->publicKey) !== '',
        ];
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
        $hasPublicKey = trim((string) $this->publicKey) !== '';
        if ($descriptor->signature === null) {
            if ($hasPublicKey || $this->unsignedPolicy !== 'allow_unsigned') {
                throw new RuntimeException('当前签名策略拒绝未签名插件包');
            }
            return;
        }
        if (strtolower((string) $descriptor->algorithm) !== 'rsa-sha256') {
            throw new RuntimeException('不支持的插件包签名算法，必须为 rsa-sha256');
        }
        if (!$hasPublicKey) {
            if ($this->unsignedPolicy === 'allow_unsigned') {
                return;
            }
            throw new RuntimeException('插件包包含签名但未配置验证公钥');
        }
        $signature = base64_decode($descriptor->signature, true);
        if ($signature === false || !function_exists('openssl_verify')) {
            throw new RuntimeException('插件包签名格式无效或 OpenSSL 不可用');
        }
        $rawDigest = hex2bin($hash);
        if ($rawDigest === false || openssl_verify($rawDigest, $signature, $this->publicKey, OPENSSL_ALGO_SHA256) !== 1) {
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
