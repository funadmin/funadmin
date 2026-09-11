<?php

declare(strict_types=1);

namespace app\identity\http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use think\Request;
use think\Response;

/**
 * ThinkPHP HTTP 对象与 PSR-7 对象之间的边界适配器。
 */
final class Psr7Adapter
{
    public function __construct(private readonly Psr17Factory $factory = new Psr17Factory())
    {
    }

    /**
     * 将 ThinkPHP 请求转换为 OAuth2 Server 可消费的 PSR-7 请求。
     */
    public function toPsrRequest(Request $request): ServerRequestInterface
    {
        $uri = $this->factory->createUri($request->url(true));
        $body = $this->factory->createStream($request->getInput());
        $psrRequest = $this->factory->createServerRequest($request->method(true), $uri, $request->server())
            ->withBody($body)
            ->withQueryParams($request->get())
            ->withParsedBody($request->post());

        foreach ($request->header() as $name => $value) {
            $psrRequest = $psrRequest->withHeader((string) $name, $value);
        }

        return $psrRequest;
    }

    /**
     * 将 OAuth2 Server 的 PSR-7 响应转换为 ThinkPHP 响应。
     */
    public function toThinkResponse(ResponseInterface $response): Response
    {
        $thinkResponse = Response::create((string) $response->getBody(), 'html', $response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            $thinkResponse->header([$name => implode(', ', $values)]);
        }

        return $thinkResponse;
    }
}