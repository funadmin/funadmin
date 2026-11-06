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
 * The factory runs lazily, once per instance; available() never calls it.
 */
final class CloudDriver implements StorageDriverInterface
{
    private array $profile;
    private mixed $clientFactory;
    private object|array|null $client = null;

    public function __construct(array $profile, ?callable $clientFactory = null)
    {
        $this->profile = $profile;
        $this->clientFactory = $clientFactory;
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
        if (!preg_match('/^[a-f0-9]{16}$/D', $p['id']) || !in_array($p['provider'], ['aliyun', 'tencent', 'qiniu'], true)) return false;
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,254}$/D', $p['bucket'])) return false;
        if (!$this->validUrl($p['domain'], ['http', 'https']) || !$this->validDirectory((string) ($p['prefix'] ?? ''))) return false;
        if ($p['provider'] === 'aliyun') {
            if (!$this->validUrl((string) ($p['endpoint'] ?? ''), ['https'])) return false;
            $endpoint = parse_url($p['endpoint']);
            if (!str_ends_with(strtolower($endpoint['host']), '.aliyuncs.com') || !in_array($endpoint['path'] ?? '', ['', '/'], true)) return false;
        }
        if ($p['provider'] === 'tencent' && !preg_match('/^[a-z0-9-]+$/D', (string) ($p['region'] ?? ''))) return false;
        if ($this->clientFactory !== null) return true;
        return match ($p['provider']) {
            'aliyun' => class_exists(\OSS\OssClient::class),
            'tencent' => class_exists(\Qcloud\Cos\Client::class),
            'qiniu' => class_exists(\Qiniu\Auth::class) && class_exists(\Qiniu\Config::class)
                && class_exists(\Qiniu\Storage\UploadManager::class) && class_exists(\Qiniu\Http\RequestOptions::class),
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
                    $stream = fopen($file->getPathname(), 'rb');
                    if ($stream === false) throw new RuntimeException('Cannot read upload');
                    $arguments = ['Bucket' => $this->profile['bucket'], 'Key' => $key, 'Body' => $stream, 'ContentType' => $mime,
                        '@http' => ['connect_timeout' => 10, 'timeout' => 120]];
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
        $prefix = trim((string) ($this->profile['prefix'] ?? ''), '/');
        if ($storageKey === '' || str_starts_with($storageKey, '/') || str_contains($storageKey, '..')
            || !preg_match('#^[a-zA-Z0-9_/-]+\.[a-z0-9]{1,10}$#D', $storageKey)
            || ($prefix !== '' && !str_starts_with($storageKey, $prefix . '/'))) {
            throw new RuntimeException('非法的云存储对象标识');
        }
        try {
            $client = $this->clients();
            switch ($this->profile['provider']) {
                case 'aliyun':
                    $client->deleteObject($this->profile['bucket'], $storageKey);
                    break;
                case 'tencent':
                    $client->deleteObject(['Bucket' => $this->profile['bucket'], 'Key' => $storageKey,
                        '@http' => ['connect_timeout' => 10, 'timeout' => 120]]);
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
                $client->setTimeout(120);
                $client->setConnectTimeout(10);
                $client->setMaxTries(0);
                return $this->client = $client;
            case 'tencent':
                return $this->client = new \Qcloud\Cos\Client([
                    'region' => $p['region'], 'scheme' => 'https', 'timeout' => 120, 'connect_timeout' => 10,
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
                $options = new \Qiniu\Http\RequestOptions(10, null, 120);
                return $this->client = ['auth' => $auth, 'upload' => new \Qiniu\Storage\UploadManager($config, $options),
                    'bucket' => new CloudQiniuBucket($auth, $config, $options)];
        }
        throw new RuntimeException('Unsupported cloud provider');
    }

    private function missingObject(Throwable $error): bool
    {
        $provider = $this->profile['provider'];
        // A generic 404 may mean a missing bucket/endpoint: only NoSuchKey is safe.
        $method = $provider === 'aliyun' ? 'getErrorCode' : 'getCosErrorCode';
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
