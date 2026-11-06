<?php

declare(strict_types=1);

namespace Tests\Oss;

use app\admin\plugins\oss\service\CloudDriver;
use app\admin\plugins\oss\service\CloudQiniuBucket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webman\Http\UploadFile;

spl_autoload_register(static function (string $class): void {
    $prefix = 'app\\admin\\plugins\\oss\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__, 2) . '/plugins/oss/app/admin/plugins/oss/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
    }
}, true, true);

final class CloudDriverTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) if (is_file($path)) unlink($path);
    }

    public static function providers(): array
    {
        return [['aliyun'], ['tencent'], ['qiniu']];
    }

    private function profile(string $provider, array $overrides = []): array
    {
        return array_replace([
            'id' => '0123456789abcdef', 'name' => 'Public uploads', 'provider' => $provider,
            'bucket' => 'assets-123456', 'region' => $provider === 'qiniu' ? 'z0' : 'ap-shanghai',
            'endpoint' => 'https://oss-cn-shanghai.aliyuncs.com', 'domain' => 'https://cdn.example.com/media assets',
            'prefix' => 'tenant/files', 'access_key' => 'test-access-private', 'secret_key' => 'test-secret-private',
        ], $overrides);
    }

    private function driver(string $provider, object|array $clients, array $overrides = []): CloudDriver
    {
        self::assertTrue(class_exists(CloudDriver::class), 'Cloud driver implementation must exist');
        return new CloudDriver($this->profile($provider, $overrides), static fn () => $clients);
    }

    private function upload(string $name = 'original.TXT'): UploadFile
    {
        $path = tempnam(sys_get_temp_dir(), 'oss-upload-');
        file_put_contents($path, "stream data\0\n");
        $this->temporaryFiles[] = $path;
        return new UploadFile($path, $name, 'text/plain', UPLOAD_ERR_OK);
    }

    #[DataProvider('providers')]
    public function test_upload_uses_profile_target_and_keeps_input_file(string $provider): void
    {
        $client = new CloudProtocolDouble();
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        $driver = $this->driver($provider, $clients);
        $file = $this->upload();
        $stored = $driver->store($file, 'articles/2026', 'client-controlled-name');

        self::assertMatchesRegularExpression('#^tenant/files/articles/2026/[a-f0-9]{32}\.txt$#D', $stored->key);
        self::assertSame('https://cdn.example.com/media%20assets/' . $stored->key, $stored->url);
        self::assertSame("stream data\0\n", file_get_contents($file->getPathname()));
        self::assertSame('assets-123456', $client->writtenBucket);
        self::assertSame($stored->key, $client->writtenKey);
        self::assertSame("stream data\0\n", $client->writtenBody);
        self::assertSame('application/octet-stream', $client->writtenMime);
        if ($provider !== 'qiniu') self::assertSame('attachment', $client->writtenDisposition);
        self::assertSame('oss_0123456789abcdef', $driver->name());
        self::assertSame('oss', $driver->source());
        self::assertSame('Public uploads', $driver->label());
        if ($provider === 'qiniu') {
            self::assertSame(['assets-123456', $stored->key, 3600, ['insertOnly' => 1, 'detectMime' => -1], true], $client->tokenArguments);
            self::assertTrue($client->crcChecked);
        }
        if ($provider === 'tencent') self::assertSame(['connect_timeout' => 10, 'timeout' => 120], $client->requestOptions);
    }

    #[DataProvider('providers')]
    public function test_delete_uses_original_profile_bucket_and_key(string $provider): void
    {
        $client = new CloudProtocolDouble();
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        self::assertTrue($this->driver($provider, $clients)->delete('tenant/files/old/file.txt'));
        self::assertSame(['assets-123456', 'tenant/files/old/file.txt'], $client->deleted);
        if ($provider === 'tencent') self::assertSame(['connect_timeout' => 10, 'timeout' => 120], $client->requestOptions);
    }

    public function test_factory_is_lazy_and_reused_only_within_driver(): void
    {
        self::assertTrue(class_exists(CloudDriver::class), 'Cloud driver implementation must exist');
        $calls = [];
        $client = new CloudProtocolDouble();
        $driver = new CloudDriver($this->profile('aliyun'), static function ($provider, $profile) use (&$calls, $client) {
            $calls[] = [$provider, $profile['id']];
            return $client;
        });
        self::assertTrue($driver->available());
        self::assertSame([], $calls);
        $driver->store($this->upload(), '');
        $driver->delete('tenant/files/example.txt');
        self::assertSame([['aliyun', '0123456789abcdef']], $calls);
    }

    public static function invalidProfiles(): array
    {
        return [
            [['id' => '../escape']], [['provider' => 'unknown']], [['bucket' => '']],
            [['access_key' => '']], [['secret_key' => '']], [['domain' => 'javascript:alert(1)']],
            [['domain' => 'https://user:secret@cdn.example.com']], [['domain' => 'https://cdn.example.com/?token=secret']],
            [['prefix' => '../private']], [['endpoint' => 'http://oss.example.com']],
        ];
    }

    #[DataProvider('invalidProfiles')]
    public function test_invalid_profile_is_unavailable_without_invoking_factory(array $changes): void
    {
        self::assertTrue(class_exists(CloudDriver::class), 'Cloud driver implementation must exist');
        $driver = new CloudDriver($this->profile('aliyun', $changes), static function () { self::fail('Invalid configuration must not initialize SDK'); });
        self::assertFalse($driver->available());
        $this->expectException(RuntimeException::class);
        $driver->store($this->upload(), 'safe');
    }

    public static function invalidDirectories(): array
    {
        return [['../escape'], ['a/../b'], ['a\\b'], ['a?b'], ["a\0b"]];
    }

    #[DataProvider('invalidDirectories')]
    public function test_directory_traversal_is_rejected_before_sdk_call(string $directory): void
    {
        $driver = $this->driver('aliyun', new CloudProtocolDouble());
        $this->expectException(RuntimeException::class);
        $driver->store($this->upload(), $directory);
    }

    public function test_empty_extension_and_failed_upload_are_rejected(): void
    {
        $driver = $this->driver('aliyun', new CloudProtocolDouble());
        $this->expectException(RuntimeException::class);
        $driver->store($this->upload('no-extension'), '');
    }

    public function test_failed_php_upload_is_rejected_before_sdk_initialization(): void
    {
        self::assertTrue(class_exists(CloudDriver::class), 'Cloud driver implementation must exist');
        $driver = new CloudDriver($this->profile('aliyun'), static function () { self::fail('Invalid upload must not initialize SDK'); });
        $file = $this->upload();
        $invalid = new UploadFile($file->getPathname(), 'upload.txt', 'text/plain', UPLOAD_ERR_PARTIAL);
        $this->expectException(RuntimeException::class);
        $driver->store($invalid, 'safe');
    }

    public function test_upload_keys_never_reuse_client_rule_or_original_filename(): void
    {
        $driver = $this->driver('aliyun', new CloudProtocolDouble());
        $file = $this->upload('sensitive-original.txt');
        $one = $driver->store($file, 'safe', '../../evil');
        $two = $driver->store($file, 'safe', '../../evil');
        self::assertNotSame($one->key, $two->key);
        self::assertStringNotContainsString('sensitive-original', $one->key);
        self::assertStringNotContainsString('evil', $one->key);
    }

    #[DataProvider('providers')]
    public function test_missing_bucket_is_a_failed_delete(string $provider): void
    {
        $client = new CloudProtocolDouble();
        $client->failure = new CloudProtocolException('NoSuchBucket', 404);
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        $driver = $this->driver($provider, $clients);
        $this->expectException(RuntimeException::class);
        $driver->delete('tenant/files/missing.txt');
    }

    public function test_qiniu_returned_delete_error_is_not_success(): void
    {
        $client = new CloudProtocolDouble();
        $client->qiniuError = new CloudProtocolException('private upstream error', 403);
        $driver = $this->driver('qiniu', ['auth' => $client, 'upload' => $client, 'bucket' => $client]);
        $this->expectException(RuntimeException::class);
        $driver->delete('tenant/files/a.txt');
    }

    public function test_encoded_cdn_base_path_is_encoded_once(): void
    {
        $driver = $this->driver('aliyun', new CloudProtocolDouble(), ['domain' => 'https://cdn.example.com/media%20assets']);
        $result = $driver->store($this->upload(), '');
        self::assertSame('https://cdn.example.com/media%20assets/' . $result->key, $result->url);
    }

    public static function unsafeMimeUploads(): array
    {
        $cases = [];
        foreach (['aliyun', 'tencent', 'qiniu'] as $provider) {
            $cases[] = [$provider, 'renamed.txt', 'text/html', '<!doctype html><script>alert(1)</script>'];
            $cases[] = [$provider, 'renamed.jpg', 'image/jpeg', '<!doctype html><script>alert(1)</script>'];
            $cases[] = [$provider, 'truncated.gif', 'image/gif', 'GIF89a'];
        }
        return $cases;
    }

    #[DataProvider('unsafeMimeUploads')]
    public function test_active_or_unverified_content_is_forced_to_download(string $provider, string $filename, string $claimedMime, string $body): void
    {
        $client = new CloudProtocolDouble();
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        $driver = $this->driver($provider, $clients);
        $file = $this->upload($filename);
        file_put_contents($file->getPathname(), $body);
        $file = new UploadFile($file->getPathname(), $filename, $claimedMime, UPLOAD_ERR_OK);
        $driver->store($file, 'safe');
        self::assertSame('application/octet-stream', $client->writtenMime);
        if ($provider !== 'qiniu') self::assertSame('attachment', $client->writtenDisposition);
        if ($provider === 'qiniu') self::assertSame(['insertOnly' => 1, 'detectMime' => -1], $client->tokenArguments[3]);
    }

    #[DataProvider('providers')]
    public function test_validated_raster_image_ignores_spoofed_multipart_mime(string $provider): void
    {
        $client = new CloudProtocolDouble();
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        $driver = $this->driver($provider, $clients);
        $file = $this->upload('pixel.png');
        file_put_contents($file->getPathname(), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', true));
        $file = new UploadFile($file->getPathname(), 'pixel.png', 'text/html', UPLOAD_ERR_OK);
        $driver->store($file, 'images');
        self::assertSame('image/png', $client->writtenMime);
        self::assertNull($client->writtenDisposition);
    }

    #[DataProvider('providers')]
    public function test_uncertain_upload_logs_only_cleanup_identifiers(string $provider): void
    {
        $client = new CloudProtocolDouble();
        $client->failure = new RuntimeException('Authorization=test-secret-private signed-url=test-access-private');
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        $driver = $this->driver($provider, $clients);
        $logger = \support\Log::channel();
        $handler = new \Monolog\Handler\TestHandler(\Monolog\Level::Debug, false);
        $logger->pushHandler($handler);
        try {
            try {
                $driver->store($this->upload(), 'cleanup');
            } catch (RuntimeException) {
            }
            $records = $handler->getRecords();
            self::assertCount(1, $records);
            self::assertSame('oss.upload.uncertain', $records[0]->message);
            self::assertSame(['provider', 'profile', 'key'], array_keys($records[0]->context));
            self::assertSame($provider, $records[0]->context['provider']);
            self::assertSame('0123456789abcdef', $records[0]->context['profile']);
            self::assertMatchesRegularExpression('#^tenant/files/cleanup/[a-f0-9]{32}\.txt$#D', $records[0]->context['key']);
            self::assertStringNotContainsString('private', json_encode($records[0]->toArray(), JSON_THROW_ON_ERROR));
        } finally {
            $logger->popHandler();
        }
    }

    #[DataProvider('providers')]
    public function test_cloud_upload_failures_have_no_secret_or_previous_exception(string $provider): void
    {
        $client = new CloudProtocolDouble();
        $client->failure = new RuntimeException('request Authorization=test-secret-private token=test-access-private');
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        $driver = $this->driver($provider, $clients);
        $caught = null;
        try {
            $driver->store($this->upload(), 'uploads');
        } catch (RuntimeException $error) {
            $caught = $error;
        }
        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertStringNotContainsString('private', $caught->getMessage());
        self::assertNull($caught->getPrevious());
    }

    public static function missingObjects(): array
    {
        return [['aliyun', 'NoSuchKey', 404], ['tencent', 'NoSuchKey', 404], ['qiniu', '', 612]];
    }

    #[DataProvider('missingObjects')]
    public function test_missing_object_delete_is_idempotent(string $provider, string $code, int $status): void
    {
        $client = new CloudProtocolDouble();
        $client->failure = new CloudProtocolException($code, $status);
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        self::assertTrue($this->driver($provider, $clients)->delete('tenant/files/missing.txt'));
    }

    #[DataProvider('providers')]
    public function test_failed_delete_never_claims_success(string $provider): void
    {
        $client = new CloudProtocolDouble();
        $client->failure = new CloudProtocolException('AccessDenied private credentials', 403);
        $clients = $provider === 'qiniu' ? ['auth' => $client, 'upload' => $client, 'bucket' => $client] : $client;
        $driver = $this->driver($provider, $clients);
        $caught = null;
        try {
            $driver->delete('tenant/files/a.txt');
        } catch (RuntimeException $error) {
            $caught = $error;
        }
        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertStringNotContainsString('private', $caught->getMessage());
        self::assertNull($caught->getPrevious());
    }

    public function test_qiniu_error_return_is_not_mistaken_for_success(): void
    {
        $client = new CloudProtocolDouble();
        $client->qiniuError = new CloudProtocolException('private upstream error', 503);
        $driver = $this->driver('qiniu', ['auth' => $client, 'upload' => $client, 'bucket' => $client]);
        $this->expectException(RuntimeException::class);
        $driver->store($this->upload(), '');
    }

    public function test_qiniu_returned_wrong_key_is_not_mistaken_for_success(): void
    {
        $client = new CloudProtocolDouble();
        $client->qiniuWrongKey = true;
        $driver = $this->driver('qiniu', ['auth' => $client, 'upload' => $client, 'bucket' => $client]);
        $this->expectException(RuntimeException::class);
        $driver->store($this->upload(), '');
    }

    public function test_deletion_cannot_escape_profile_prefix(): void
    {
        $driver = $this->driver('aliyun', new CloudProtocolDouble());
        $this->expectException(RuntimeException::class);
        $driver->delete('other-profile/private.txt');
    }

    public function test_cos_sdk_missing_key_exception_is_idempotent(): void
    {
        require_once dirname(__DIR__, 2) . '/plugins/oss/vendor/autoload.php';
        $client = new CloudProtocolDouble();
        $error = new \Qcloud\Cos\Exception\ServiceResponseException('missing key');
        $error->setExceptionCode('NoSuchKey');
        $error->setResponse(new \GuzzleHttp\Psr7\Response(404));
        $client->failure = $error;
        self::assertTrue($this->driver('tencent', $client)->delete('tenant/files/missing.txt'));
    }

    public function test_tencent_sdk_serializes_safe_download_headers(): void
    {
        require_once dirname(__DIR__, 2) . '/plugins/oss/vendor/autoload.php';
        $client = new class(['region' => 'ap-shanghai', 'scheme' => 'https', 'credentials' => ['secretId' => 'test', 'secretKey' => 'test']]) extends \Qcloud\Cos\Client {
            public array $requests = [];
            public function putObject(array $arguments): array
            {
                $this->requests[] = $this->commandToRequestTransformer($this->getCommand('PutObject', $arguments));
                return ['ETag' => 'example-etag'];
            }
        };
        $driver = $this->driver('tencent', $client);
        $file = $this->upload('renamed.txt');
        file_put_contents($file->getPathname(), '<script>alert(1)</script>');
        $driver->store(new UploadFile($file->getPathname(), 'renamed.txt', 'text/html', UPLOAD_ERR_OK), 'safe');
        self::assertCount(1, $client->requests);
        self::assertSame('application/octet-stream', $client->requests[0]->getHeaderLine('Content-Type'));
        self::assertSame('attachment', $client->requests[0]->getHeaderLine('Content-Disposition'));
    }

    public function test_qiniu_sdk_token_disables_active_mime_redetection(): void
    {
        require_once dirname(__DIR__, 2) . '/plugins/oss/vendor/autoload.php';
        $upload = new class {
            public string $token = '';
            public function putFile($token, $key, ...$arguments): array
            {
                $this->token = $token;
                return [['key' => $key, 'hash' => 'example-hash'], null];
            }
        };
        $driver = $this->driver('qiniu', ['auth' => new \Qiniu\Auth('test', 'test'), 'upload' => $upload, 'bucket' => new CloudProtocolDouble()]);
        $result = $driver->store($this->upload(), 'safe');
        $parts = explode(':', $upload->token);
        $policy = json_decode(\Qiniu\base64_urlSafeDecode($parts[2]), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(-1, $policy['detectMime']);
        self::assertSame(1, $policy['insertOnly']);
        self::assertSame('assets-123456:' . $result->key, $policy['scope']);
    }

    public static function qiniuResponses(): array
    {
        return [[200, null], [612, 612], [403, 403], [503, 503]];
    }

    #[DataProvider('qiniuResponses')]
    public function test_qiniu_delete_uses_sdk_signing_with_bounded_transport(int $status, ?int $expectedError): void
    {
        self::assertTrue(class_exists(CloudQiniuBucket::class), 'Bounded Qiniu delete implementation must exist');
        require_once dirname(__DIR__, 2) . '/plugins/oss/vendor/autoload.php';
        $config = new \Qiniu\Config(\Qiniu\Region::regionHuadong());
        $config->useHTTPS = true;
        $auth = new \Qiniu\Auth('test-access-private', 'test-secret-private');
        $options = new \Qiniu\Http\RequestOptions(10, null, 120);
        $requests = [];
        $transport = static function ($url, $body, $headers, $requestOptions) use (&$requests, $status) {
            $requests[] = [$url, $body, $headers, $requestOptions];
            return new \Qiniu\Http\Response($status, 0.01, ['X-Reqid' => 'test-id'], $status === 200 ? '' : '{"error":"controlled SDK failure"}', null);
        };
        $bucket = new CloudQiniuBucket($auth, $config, $options, $transport);
        [$result, $error] = $bucket->delete('bucket', 'some/path.txt');
        self::assertCount(1, $requests);
        self::assertSame('https://rs-z0.qiniuapi.com/delete/YnVja2V0OnNvbWUvcGF0aC50eHQ=', $requests[0][0]);
        self::assertNull($requests[0][1]);
        self::assertStringStartsWith('Qiniu test-access-private:', $requests[0][2]['Authorization']);
        self::assertSame(10, $requests[0][3]->connection_timeout);
        self::assertSame(120, $requests[0][3]->timeout);
        if ($expectedError === null) {
            self::assertSame([], $result);
            self::assertNull($error);
        } else {
            self::assertNull($result);
            self::assertInstanceOf(\Qiniu\Http\Error::class, $error);
            self::assertSame($expectedError, $error->code());
        }
    }
}

final class CloudProtocolDouble
{
    public ?RuntimeException $failure = null;
    public ?CloudProtocolException $qiniuError = null;
    public bool $qiniuWrongKey = false;
    public string $writtenBucket = '';
    public string $writtenKey = '';
    public string $writtenBody = '';
    public string $writtenMime = '';
    public ?string $writtenDisposition = null;
    public array $deleted = [];
    public array $tokenArguments = [];
    public bool $crcChecked = false;
    public array $requestOptions = [];

    public function uploadFile($bucket, $key, $path, $options = []): array
    {
        if ($this->failure) throw $this->failure;
        $this->writtenBucket = $bucket;
        $this->writtenKey = $key;
        $this->writtenBody = file_get_contents($path);
        $this->writtenMime = $options['headers']['Content-Type'] ?? '';
        $this->writtenDisposition = $options['headers']['Content-Disposition'] ?? null;
        return ['info' => ['http_code' => 200]];
    }

    public function putObject(array $args): array
    {
        if ($this->failure) throw $this->failure;
        $this->writtenBucket = $args['Bucket'];
        $this->writtenKey = $args['Key'];
        $this->writtenBody = stream_get_contents($args['Body']);
        $this->writtenMime = $args['ContentType'];
        $this->writtenDisposition = $args['ContentDisposition'] ?? null;
        $this->requestOptions = $args['@http'] ?? [];
        return ['ETag' => 'example-etag'];
    }

    public function deleteObject(...$args): array
    {
        if ($this->failure) throw $this->failure;
        $this->deleted = is_array($args[0]) ? [$args[0]['Bucket'], $args[0]['Key']] : [$args[0], $args[1]];
        $this->requestOptions = is_array($args[0]) ? ($args[0]['@http'] ?? []) : [];
        return [];
    }

    public function uploadToken(...$args): string
    {
        $this->tokenArguments = $args;
        return 'token-for-single-key';
    }

    public function putFile($token, $key, $path, $params = null, $mime = 'application/octet-stream', $checkCrc = false): array
    {
        if ($this->failure) throw $this->failure;
        $this->writtenBucket = $this->tokenArguments[0];
        $this->writtenKey = $key;
        $this->writtenBody = file_get_contents($path);
        $this->writtenMime = $mime;
        $this->crcChecked = $checkCrc;
        return [['hash' => 'example-hash', 'key' => $this->qiniuWrongKey ? 'wrong-key' : $key], $this->qiniuError];
    }

    public function delete($bucket, $key): array
    {
        if ($this->failure) return [null, $this->failure];
        $this->deleted = [$bucket, $key];
        return [[], $this->qiniuError];
    }
}

final class CloudProtocolException extends RuntimeException
{
    public function __construct(private string $cloudCode, private int $status)
    {
        parent::__construct($cloudCode);
    }

    public function getErrorCode(): string { return $this->cloudCode; }
    public function getCosErrorCode(): string { return $this->cloudCode; }
    public function getStatusCode(): int { return $this->status; }
    public function getHTTPStatus(): int { return $this->status; }
    public function code(): int { return $this->status; }
}
