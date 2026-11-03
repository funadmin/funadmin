<?php

declare(strict_types=1);

namespace app\identity\http;

use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Webman\Http\UploadFile as UploadedFile;
use support\Request;
use support\Response;

/**
 * ThinkPHP HTTP 对象与 PSR-7 对象之间的边界适配器。
 */
final class Psr7Adapter
{
    public function __construct(private readonly HttpFactory $factory = new HttpFactory())
    {
    }

    /**
     * 将 ThinkPHP 请求转换为 OAuth2 Server 可消费的 PSR-7 请求。
     */
    public function toPsrRequest(Request $request): ServerRequestInterface
    {
        $uri = $this->factory->createUri('http://' . $request->host() . $request->uri());
        $body = $this->factory->createStream($request->rawBody());
        $psrRequest = $this->factory->createServerRequest($request->method(), $uri)
            ->withBody($body)
            ->withQueryParams($request->get())
            ->withCookieParams($request->cookie())
            ->withUploadedFiles($this->convertUploadedFiles($request->file() ?? []))
            ->withParsedBody($request->post());

        foreach ($request->header() as $name => $value) {
            $psrRequest = $psrRequest->withHeader((string) $name, $value);
        }

        return $psrRequest;
    }

    /**
     * 将 OAuth2 Server 的 PSR-7 响应转换为 ThinkPHP 响应。
     */
    public function toWebmanResponse(ResponseInterface $response): Response
    {
        return new Response($response->getStatusCode(), $response->getHeaders(), (string) $response->getBody());
    }

    /**
     * 将 ThinkPHP 上传文件树递归转换为 PSR-7 上传文件树。
     */
    private function convertUploadedFiles(array $files): array
    {
        foreach ($files as $name => $file) {
            if (is_array($file)) {
                $files[$name] = $this->convertUploadedFiles($file);
                continue;
            }

            if ($file instanceof UploadedFile) {
                $files[$name] = $this->convertUploadedFile($file);
            }
        }

        return $files;
    }

    /**
     * 转换单个 ThinkPHP 上传文件且保留客户端元数据。
     */
    private function convertUploadedFile(UploadedFile $file): UploadedFileInterface
    {
        $stream = $this->factory->createStreamFromFile($file->getPathname());

        return $this->factory->createUploadedFile(
            $stream,
            $file->getSize(),
            UPLOAD_ERR_OK,
            $file->getUploadName(),
            $file->getUploadMimeType()
        );
    }
}
