<?php

declare(strict_types=1);

namespace app\common\plugin\package;

use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use app\common\plugin\marketplace\dto\MarketplaceProtocol;
use InvalidArgumentException;
use JsonException;
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

    public function assertCloudDescriptor(DownloadDescriptorDto $descriptor): void
    {
        if ($descriptor->manifestSchema !== MarketplaceProtocol::MANIFEST_SCHEMA
            || $descriptor->packageFormat !== MarketplaceProtocol::PACKAGE_FORMAT
            || $descriptor->algorithm !== MarketplaceProtocol::SIGNATURE_ALGORITHM
            || trim($descriptor->signature) === ''
            || preg_match('/^[a-f0-9]{64}$/', $descriptor->treeHash) !== 1) {
            throw new RuntimeException('云下载描述不符合 v3 原生包契约');
        }
    }

    public function download(DownloadDescriptorDto $descriptor): string
    {
        $this->assertCloudDescriptor($descriptor);
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
            'signature_verified' => trim((string) $this->publicKey) !== '',
            'manifest_schema' => $descriptor->manifestSchema,
            'package_format' => $descriptor->packageFormat,
            'tree_hash' => $descriptor->treeHash,
            'database_capability' => $descriptor->databaseCapability,
        ];
    }

    public static function signaturePayload(DownloadDescriptorDto $descriptor): string
    {
        try {
            return json_encode([
                'code' => $descriptor->code,
                'code_version' => $descriptor->version,
                'database_capability' => $descriptor->databaseCapability,
                'manifest_schema' => $descriptor->manifestSchema,
                'package_format' => $descriptor->packageFormat,
                'sha256' => $descriptor->sha256,
                'size' => $descriptor->size,
                'tree_hash' => $descriptor->treeHash,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('插件包签名 metadata 无法规范化', 0, $exception);
        }
    }

    private function verifyFile(string $file, DownloadDescriptorDto $descriptor): void
    {
        $size = is_file($file) ? filesize($file) : false;
        if ($size === false || $size < 1 || $size > self::MAX_BYTES || $size !== $descriptor->size) {
            throw new RuntimeException('插件安装包大小不匹配或超过 100MB 限制');
        }
        $actualHash = hash_file('sha256', $file);
        if (!is_string($actualHash) || !hash_equals($descriptor->sha256, strtolower($actualHash))) {
            throw new RuntimeException('插件安装包 SHA-256 校验失败');
        }
        $this->verifySignature($descriptor);
    }

    private function verifySignature(DownloadDescriptorDto $descriptor): void
    {
        $hasPublicKey = trim((string) $this->publicKey) !== '';
        if (!$hasPublicKey) {
            if ($this->unsignedPolicy === 'allow_unsigned') {
                return;
            }
            throw new RuntimeException('插件包包含签名但未配置验证公钥');
        }
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('插件包签名验证需要 Sodium 扩展');
        }
        $signature = base64_decode($descriptor->signature, true);
        $publicKey = base64_decode((string) $this->publicKey, true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || $publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('插件包 Ed25519 签名或公钥格式无效');
        }
        if (!sodium_crypto_sign_verify_detached($signature, self::signaturePayload($descriptor), $publicKey)) {
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
