<?php

declare(strict_types=1);

namespace app\admin\plugins\oss\service;

use app\common\storage\StorageDriverInterface;
use app\common\storage\StoredFile;
use RuntimeException;
use Throwable;
use Webman\Http\UploadFile;

/**
 * One immutable profile per upload, including later compensation/deletion.
 *
 * Optional factory: fn(string $provider, array $decryptedProfile): object|array.
 * aliyun: OssClient-compatible uploadFile/deleteObject; tencent: Client-compatible
 * putObject/deleteObject. qiniu: ['auth' => Auth-compatible, 'upload' =>
 * UploadManager-compatible, 'bucket' => BucketManager-compatible delete].
 * s3: S3Client-compatible putObject/getObject/deleteObject.
 * The factory runs lazily, once per instance; available() never calls it.
 * Optional limits: ['timeout' => seconds, 'connect_timeout' => seconds] for every SDK request
 * (the connection test uses short ones so the whole probe stays within its budget).
 */
final class CloudDriver implements StorageDriverInterface
{
    private array $profile;
    private mixed $clientFactory;
    private object|array|null $client = null;
    private int $timeout;
    private int $connectTimeout;

    public const PROVIDERS = ['aliyun', 'tencent', 'qiniu', 's3'];

    public function __construct(array $profile, ?callable $clientFactory = null, array $limits = [])
    {
        $this->profile = $profile;
        $this->clientFactory = $clientFactory;
        $this->timeout = max(1, min(120, (int) ($limits['timeout'] ?? 120)));
        $this->connectTimeout = max(1, min(10, (int) ($limits['connect_timeout'] ?? 10)));
    }

    /** S3 bucket naming rules (lowercase, digits, dots, hyphens; 3-63 chars; not an IP address). */
    public static function validS3Bucket(string $bucket): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/D', $bucket) === 1 && !str_contains($bucket, '..')
            && filter_var($bucket, FILTER_VALIDATE_IP) === false;
    }

    /**
     * S3-compatible endpoint: empty means the AWS default for the region. Otherwise HTTPS only (same as the
     * aliyun endpoint rule), no credentials/query/fragment/path, and never a blocked IP literal (see
     * blockedEndpointIp), nor an obfuscated numeric host or well-known metadata host.
     * Private-network IPs and hostnames stay allowed for self-hosted MinIO. Hostnames are not resolved here;
     * SDK redirects are disabled so a response cannot bounce the request elsewhere.
     */
    public static function validS3Endpoint(string $endpoint): bool
    {
        if ($endpoint === '') return true;
        if (strlen($endpoint) > 255 || preg_match('/[\x00-\x20\x7f\\\\]/', $endpoint)) return false;
        $parts = parse_url($endpoint);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true) || str_ends_with($endpoint, '?') || str_ends_with($endpoint, '#')) return false;
        $host = strtolower(trim($parts['host'], '[]'));
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) return !self::blockedEndpointIp($host);
        if (preg_match('/^(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*\.?$/D', $host)) return false;
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D', $host)) return false;
        return $host !== 'localhost' && !str_ends_with($host, '.localhost')
            && !in_array($host, ['metadata', 'metadata.google.internal', 'metadata.goog', 'instance-data', 'instance-data.ec2.internal'], true);
    }

    /**
     * IP literals an S3 endpoint must never use: loopback, link-local (169.254.169.254 and friends), unspecified and
     * other reserved ranges, IPv4-mapped IPv6, cloud metadata addresses that live outside those ranges
     * (Alibaba Cloud ECS 100.100.100.200, AWS IMDS IPv6 fd00:ec2::254), and IPv6 addresses whose embedded IPv4 is
     * itself blocked: IPv4-compatible ::/96, SIIT ::ffff:0:0:0/96, NAT64 64:ff9b::/96 and 64:ff9b:1::/48, 6to4 2002::/16
     * (a translator or relay would deliver them to that IPv4).
     */
    private static function blockedEndpointIp(string $ip): bool
    {
        $binary = @inet_pton($ip);
        if ($binary === false) return true;
        if (strlen($binary) === 4) {
            return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false || $binary === inet_pton('100.100.100.200');
        }
        $zero = str_repeat("\0", 16);
        if (str_starts_with($binary, substr($zero, 0, 10) . "\xff\xff") || $binary === $zero || $binary === substr($zero, 0, 15) . "\x01") return true;
        if ($binary === inet_pton('fd00:ec2::254')) return true;
        // IPv6 forms that carry an IPv4 address a translator or relay would reach: judge the embedded IPv4 the same way.
        $embedded = match (true) {
            str_starts_with($binary, substr($zero, 0, 12)) => substr($binary, 12, 4),                                   // ::/96 IPv4-compatible
            str_starts_with($binary, substr($zero, 0, 8) . "\xff\xff\0\0") => substr($binary, 12, 4),                    // ::ffff:0:0:0/96 SIIT
            str_starts_with($binary, substr((string) inet_pton('64:ff9b::'), 0, 12)) => substr($binary, 12, 4),         // 64:ff9b::/96 NAT64
            str_starts_with($binary, substr((string) inet_pton('64:ff9b:1::'), 0, 6)) => substr($binary, 12, 4),        // 64:ff9b:1::/48 local NAT64 (RFC 8215)
            str_starts_with($binary, "\x20\x02") => substr($binary, 2, 4),                                              // 2002::/16 6to4
            default => null,
        };
        if ($embedded !== null) return self::blockedEndpointIp((string) inet_ntop($embedded));
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public function name(): string
    {
        return 'oss_' . ($this->profile['id'] ?? '');
    }

    public function label(): string
    {
        return (string) ($this->profile['name'] ?? 'OSS');
    }

    public function source(): string
    {
        return 'oss';
    }

    public function available(): bool
    {
        $p = $this->profile;
        foreach (['id', 'provider', 'bucket', 'domain', 'access_key', 'secret_key'] as $field) {
            if (!is_string($p[$field] ?? null) || trim($p[$field]) === '') return false;
        }
        if (!preg_match('/^[a-f0-9]{16}$/D', $p['id']) || !in_array($p['provider'], self::PROVIDERS, true)) return false;
        if ($p['provider'] === 's3' ? !self::validS3Bucket($p['bucket']) : !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,254}$/D', $p['bucket'])) return false;
        if (!$this->validUrl($p['domain'], ['http', 'https']) || !$this->validDirectory((string) ($p['prefix'] ?? ''))) return false;
        if ($p['provider'] === 'aliyun') {
            if (!$this->validUrl((string) ($p['endpoint'] ?? ''), ['https'])) return false;
            $endpoint = parse_url($p['endpoint']);
            if (!str_ends_with(strtolower($endpoint['host']), '.aliyuncs.com') || !in_array($endpoint['path'] ?? '', ['', '/'], true)) return false;
        }
        if (in_array($p['provider'], ['tencent', 's3'], true) && !preg_match('/^[a-z0-9-]+$/D', (string) ($p['region'] ?? ''))) return false;
        if ($p['provider'] === 's3' && !self::validS3Endpoint((string) ($p['endpoint'] ?? ''))) return false;
        if ($this->clientFactory !== null) return true;
        return match ($p['provider']) {
            'aliyun' => class_exists(\OSS\OssClient::class),
            'tencent' => class_exists(\Qcloud\Cos\Client::class),
            'qiniu' => class_exists(\Qiniu\Auth::class) && class_exists(\Qiniu\Config::class)
                && class_exists(\Qiniu\Storage\UploadManager::class) && class_exists(\Qiniu\Http\RequestOptions::class),
            's3' => class_exists(\Aws\S3\S3Client::class),
        };
    }

    public function store(UploadFile $file, string $directory, string $rule = ''): StoredFile
    {
        $this->requireAvailable();
        if (!$this->validDirectory($directory)) throw new RuntimeException('非法的保存目录');
        $extension = strtolower($file->getUploadExtension());
        if (!$file->isValid() || !preg_match('/^[a-z0-9]{1,10}$/D', $extension) || !is_file($file->getPathname()) || !is_readable($file->getPathname())) {
            throw new RuntimeException('上传文件无效');
        }
        $prefix = trim((string) ($this->profile['prefix'] ?? ''), '/');
        $parts = array_filter([$prefix, trim($directory, '/'), bin2hex(random_bytes(16)) . '.' . $extension], static fn (string $part): bool => $part !== '');
        $key = implode('/', $parts);
        $stream = null;
        try {
            $client = $this->clients();
            $mime = $this->contentType($file->getPathname());
            $headers = ['Content-Type' => $mime];
            if ($mime === 'application/octet-stream') $headers['Content-Disposition'] = 'attachment';
            switch ($this->profile['provider']) {
                case 'aliyun':
                    $client->uploadFile($this->profile['bucket'], $key, $file->getPathname(), ['headers' => $headers]);
                    break;
                case 'tencent':
                case 's3':
                    $stream = fopen($file->getPathname(), 'rb');
                    if ($stream === false) throw new RuntimeException('Cannot read upload');
                    $arguments = ['Bucket' => $this->profile['bucket'], 'Key' => $key, 'Body' => $stream, 'ContentType' => $mime];
                    if ($this->profile['provider'] === 'tencent') $arguments['@http'] = $this->httpLimits();
                    if (isset($headers['Content-Disposition'])) $arguments['ContentDisposition'] = $headers['Content-Disposition'];
                    $client->putObject($arguments);
                    break;
                case 'qiniu':
                    // detectMime=0 (the default) re-sniffs octet-stream uploads and
                    // may restore active HTML MIME. -1 preserves our inspected type.
                    $token = $client['auth']->uploadToken($this->profile['bucket'], $key, 3600, ['insertOnly' => 1, 'detectMime' => -1], true);
                    $result = $client['upload']->putFile($token, $key, $file->getPathname(), null, $mime, true);
                    if (!is_array($result) || count($result) < 2 || $result[1] !== null || !is_array($result[0]) || ($result[0]['key'] ?? null) !== $key) {
                        throw new RuntimeException('Cloud upload returned an error');
                    }
                    break;
            }
        } catch (Throwable) {
            $this->logFailure('oss.upload.uncertain', $key);
            // Never chain SDK exceptions: their messages/traces may contain signed URLs.
            throw new RuntimeException('云存储上传失败，请检查配置或稍后重试');
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
        return new StoredFile($key, $this->publicUrl($key));
    }

    public function delete(string $storageKey): bool
    {
        $this->requireAvailable();
        $this->assertKey($storageKey);
        try {
            $client = $this->clients();
            switch ($this->profile['provider']) {
                case 'aliyun':
                    $client->deleteObject($this->profile['bucket'], $storageKey);
                    break;
                case 'tencent':
                    $client->deleteObject(['Bucket' => $this->profile['bucket'], 'Key' => $storageKey, '@http' => $this->httpLimits()]);
                    break;
                case 's3':
                    $client->deleteObject(['Bucket' => $this->profile['bucket'], 'Key' => $storageKey]);
                    break;
                case 'qiniu':
                    $result = $client['bucket']->delete($this->profile['bucket'], $storageKey);
                    if (!is_array($result) || count($result) < 2) throw new RuntimeException('Invalid cloud result');
                    if ($result[1] !== null) {
                        if (is_object($result[1]) && method_exists($result[1], 'code') && (int) $result[1]->code() === 612) return true;
                        throw new RuntimeException('Cloud delete returned an error');
                    }
                    break;
            }
            return true;
        } catch (Throwable $error) {
            if ($this->missingObject($error)) return true;
            $this->logFailure('oss.delete.failed', $storageKey);
            throw new RuntimeException('云存储删除失败，请检查配置或稍后重试');
        }
    }

    /**
     * Read back an object this driver wrote and compare it with the expected bytes (connection test).
     * Reads at most strlen($expected) + 1 bytes. Qiniu has no signed object read through its management API,
     * so it compares the stat size and Qiniu etag instead of fetching the public domain.
     */
    public function verify(string $storageKey, string $expected): bool
    {
        $this->requireAvailable();
        $this->assertKey($storageKey);
        $limit = strlen($expected) + 1;
        try {
            $client = $this->clients();
            $bucket = $this->profile['bucket'];
            switch ($this->profile['provider']) {
                case 'aliyun':
                    $body = (string) $client->getObject($bucket, $storageKey);
                    break;
                case 'tencent':
                    $body = $this->readBody($client->getObject(['Bucket' => $bucket, 'Key' => $storageKey, '@http' => $this->httpLimits()])['Body'] ?? '', $limit);
                    break;
                case 's3':
                    $body = $this->readBody($client->getObject(['Bucket' => $bucket, 'Key' => $storageKey])['Body'] ?? '', $limit);
                    break;
                case 'qiniu':
                    $result = $client['bucket']->stat($bucket, $storageKey);
                    if (!is_array($result) || count($result) < 2 || $result[1] !== null || !is_array($result[0])) throw new RuntimeException('Cloud stat returned an error');
                    $etag = strtr(base64_encode("\x16" . sha1($expected, true)), '+/', '-_');
                    return (int) ($result[0]['fsize'] ?? -1) === strlen($expected) && hash_equals($etag, (string) ($result[0]['hash'] ?? ''));
                default:
                    throw new RuntimeException('Unsupported cloud provider');
            }
            return hash_equals($expected, $body);
        } catch (Throwable) {
            $this->logFailure('oss.read.failed', $storageKey);
            throw new RuntimeException('云存储读取失败，请检查配置或稍后重试');
        }
    }

    private function readBody(mixed $body, int $limit): string
    {
        if (is_string($body)) return substr($body, 0, $limit);
        if ($body instanceof \Psr\Http\Message\StreamInterface) {
            $data = '';
            while (strlen($data) < $limit && !$body->eof()) {
                $chunk = $body->read($limit - strlen($data));
                if ($chunk === '') break;
                $data .= $chunk;
            }
            return $data;
        }
        throw new RuntimeException('Unexpected cloud response body');
    }

    private function assertKey(string $storageKey): void
    {
        $prefix = trim((string) ($this->profile['prefix'] ?? ''), '/');
        if ($storageKey === '' || str_starts_with($storageKey, '/') || str_contains($storageKey, '..')
            || !preg_match('#^[a-zA-Z0-9_/-]+\.[a-z0-9]{1,10}$#D', $storageKey)
            || ($prefix !== '' && !str_starts_with($storageKey, $prefix . '/'))) {
            throw new RuntimeException('非法的云存储对象标识');
        }
    }

    /** @return array{connect_timeout: int, timeout: int} */
    private function httpLimits(): array
    {
        return ['connect_timeout' => $this->connectTimeout, 'timeout' => $this->timeout];
    }

    private function requireAvailable(): void
    {
        if (!$this->available()) throw new RuntimeException('云存储配置无效或 SDK 不可用');
    }

    private function clients(): object|array
    {
        if ($this->client !== null) return $this->client;
        if ($this->clientFactory !== null) {
            return $this->client = ($this->clientFactory)($this->profile['provider'], $this->profile);
        }
        $p = $this->profile;
        switch ($p['provider']) {
            case 'aliyun':
                $client = new \OSS\OssClient($p['access_key'], $p['secret_key'], $p['endpoint']);
                $client->setTimeout($this->timeout);
                $client->setConnectTimeout($this->connectTimeout);
                $client->setMaxTries(0);
                return $this->client = $client;
            case 'tencent':
                return $this->client = new \Qcloud\Cos\Client([
                    'region' => $p['region'], 'scheme' => 'https', 'timeout' => $this->timeout, 'connect_timeout' => $this->connectTimeout,
                    'retry' => 0, 'allow_redirects' => false,
                    'credentials' => ['secretId' => $p['access_key'], 'secretKey' => $p['secret_key']],
                ]);
            case 'qiniu':
                $auth = new \Qiniu\Auth($p['access_key'], $p['secret_key']);
                $regions = ['z0' => 'regionHuadong', 'z1' => 'regionHuabei', 'z2' => 'regionHuanan',
                    'na0' => 'regionNorthAmerica', 'as0' => 'regionSingapore', 'cn-east-2' => 'regionHuadong2'];
                $method = $regions[$p['region'] ?? ''] ?? null;
                $config = new \Qiniu\Config($method === null ? null : \Qiniu\Region::$method());
                $config->useHTTPS = true;
                $config->backupUcHostsRetryTimes = 0;
                $options = new \Qiniu\Http\RequestOptions($this->connectTimeout, null, $this->timeout);
                return $this->client = ['auth' => $auth, 'upload' => new \Qiniu\Storage\UploadManager($config, $options),
                    'bucket' => new CloudQiniuBucket($auth, $config, $options)];
            case 's3':
                return $this->client = new \Aws\S3\S3Client(self::s3Config($p, $this->timeout, $this->connectTimeout));
        }
        throw new RuntimeException('Unsupported cloud provider');
    }

    /** S3Client configuration for a decrypted profile (public for SDK-level tests with a mock handler). */
    public static function s3Config(array $p, int $timeout = 120, int $connectTimeout = 10): array
    {
        $config = [
            'version' => '2006-03-01', 'region' => (string) $p['region'],
            // Static credentials only: never fall back to env, shared files or the instance metadata service.
            'credentials' => ['key' => (string) $p['access_key'], 'secret' => (string) $p['secret_key']],
            'use_aws_shared_config_files' => false,
            'use_path_style_endpoint' => !empty($p['path_style']),
            'retries' => 0,
            'http' => ['connect_timeout' => $connectTimeout, 'timeout' => $timeout, 'allow_redirects' => false],
            // Many S3-compatible services (MinIO, R2 and others) reject the newer default flexible checksums.
            'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
        ];
        if (($p['endpoint'] ?? '') !== '') $config['endpoint'] = (string) $p['endpoint'];
        return $config;
    }

    private function missingObject(Throwable $error): bool
    {
        $provider = $this->profile['provider'];
        // A generic 404 may mean a missing bucket/endpoint: only NoSuchKey is safe.
        $method = match ($provider) { 'aliyun' => 'getErrorCode', 's3' => 'getAwsErrorCode', default => 'getCosErrorCode' };
        return $provider !== 'qiniu' && method_exists($error, $method) && $error->$method() === 'NoSuchKey';
    }

    private function contentType(string $path): string
    {
        // Multipart MIME and filename are supplied by the client. Only matching
        // server-side MIME and raster image headers qualify for inline display.
        $types = ['image/jpeg' => 'image/jpeg', 'image/png' => 'image/png', 'image/gif' => 'image/gif',
            'image/bmp' => 'image/bmp', 'image/x-ms-bmp' => 'image/bmp', 'image/webp' => 'image/webp'];
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $mime = $types[$detected] ?? null;
        if ($mime !== null) {
            $image = @getimagesize($path);
            if ($image !== false && ($image[0] ?? 0) > 0 && ($image[1] ?? 0) > 0 && ($image['mime'] ?? '') === $mime) return $mime;
        }
        return 'application/octet-stream';
    }

    private function validDirectory(string $directory): bool
    {
        return !str_contains($directory, '..') && preg_match('#^[A-Za-z0-9_/-]*$#D', $directory) === 1;
    }

    private function validUrl(string $url, array $schemes): bool
    {
        if (preg_match('/[\x00-\x1f\x7f\\\\]/', $url)) return false;
        $parts = parse_url($url);
        return is_array($parts) && in_array($parts['scheme'] ?? '', $schemes, true) && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment']);
    }

    private function publicUrl(string $key): string
    {
        $p = parse_url($this->profile['domain']);
        $base = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $path = trim($p['path'] ?? '', '/');
        $path = $path === '' ? '' : '/' . implode('/', array_map(static fn (string $part): string => rawurlencode(rawurldecode($part)), explode('/', $path)));
        return $base . $path . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    private function logFailure(string $event, string $key): void
    {
        try {
            \support\Log::warning($event, ['provider' => $this->profile['provider'], 'profile' => $this->profile['id'], 'key' => $key]);
        } catch (Throwable) {
            // A logging failure must not replace the sanitized storage error.
        }
    }
}
