<?php

declare(strict_types=1);

namespace app\admin\plugins\oss\service;

use Qiniu\Auth;
use Qiniu\Config;
use Qiniu\Http\Client;
use Qiniu\Http\Error;
use Qiniu\Http\RequestOptions;

/**
 * BucketManager's delete protocol with bounded SDK request options.
 *
 * The upstream BucketManager creates private Proxy request options without
 * timeouts. Use its same SDK entry/signing/HTTP primitives instead of altering
 * SDK internals or implementing signature cryptography ourselves.
 */
final class CloudQiniuBucket
{
    private mixed $transport;

    /** Optional HTTP boundary: fn($url, $body, array $headers, RequestOptions): Response. */
    public function __construct(
        private Auth $auth,
        private Config $config,
        private RequestOptions $options,
        ?callable $transport = null
    ) {
        $this->transport = $transport ?? [Client::class, 'post'];
    }

    /** @return array{0: ?array, 1: ?Error} Same return contract as BucketManager::delete. */
    public function delete(string $bucket, string $key): array
    {
        [$host, $error] = $this->config->getRsHostV2($this->auth->getAccessKey(), $bucket, $this->options);
        if ($error !== null) return [null, $error];
        $url = $host . '/delete/' . \Qiniu\entry($bucket, $key);
        $headers = $this->auth->authorizationV2($url, 'POST', null, 'application/x-www-form-urlencoded');
        $response = ($this->transport)($url, null, $headers, $this->options);
        if (!$response->ok()) return [null, new Error($url, $response)];
        return [[], null];
    }
}
