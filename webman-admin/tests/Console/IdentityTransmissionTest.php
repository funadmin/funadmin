<?php

declare(strict_types=1);

namespace Tests\Console;

use app\identity\service\BackchannelLogoutDispatcher;
use app\identity\service\BackchannelUrlPolicy;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentityTransmissionTest extends TestCase
{
    public static function unsafeUrls(): array
    {
        return [
            ['http://8.8.8.8/logout'],
            ['https://8.8.8.8/logout#fragment'],
            ['https://user@8.8.8.8/logout'],
            ['https://user:password@8.8.8.8/logout'],
            ['https://127.0.0.1/logout'],
            ['https://[::1]/logout'],
            ['https://10.0.0.1/logout'],
            ['https://8.8.8.8:8443/logout'],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_unsafe_targets_are_rejected_before_any_network_request(string $url): void
    {
        $this->expectException(\DomainException::class);
        (new BackchannelUrlPolicy())->validate($url);
    }

    public function test_dispatcher_sends_only_form_token_and_accepts_success_response(): void
    {
        $handler = new MockHandler([new Response(204)]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);
        $dispatcher = new BackchannelLogoutDispatcher($client);
        self::assertSame(204, $dispatcher->send(['logout_uri' => 'https://8.8.8.8/logout', 'logout_token' => 'test-token']));
        $request = $handler->getLastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('logout_token=test-token', (string) $request->getBody());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        $options = $handler->getLastOptions();
        self::assertFalse($options['allow_redirects']);
        self::assertSame(['8.8.8.8:443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);
    }

    public function test_dispatcher_rejects_redirects_and_oversized_responses(): void
    {
        foreach ([new Response(302, ['Location' => 'https://127.0.0.1/']), new Response(200, [], str_repeat('x', 65537))] as $response) {
            $client = new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]);
            try {
                (new BackchannelLogoutDispatcher($client))->send(['logout_uri' => 'https://8.8.8.8/logout', 'logout_token' => 'test-token']);
                self::fail('重定向或过大响应不得记为投递成功');
            } catch (\RuntimeException $error) {
                self::assertStringStartsWith('rp_http_', $error->getMessage());
            }
        }
    }
}
