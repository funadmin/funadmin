<?php

declare(strict_types=1);

namespace app\console\middleware;

use Closure;
use think\App;
use think\Request;
use think\Response;
use think\response\Json;
use WeakMap;

/** 仅归一化后台明确业务失败，不改变协议错误及响应内容。 */
final class ConsoleResponsePolicy
{
    private static ?WeakMap $transportResponses = null;

    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $this->normalize($request, $next($request));
    }

    public function applies(Request $request): bool
    {
        return $this->app->http->getName() === 'console'
            && !str_contains(strtolower((string) $request->header('accept', '')), 'text/event-stream')
            && !preg_match('~(?:^|/)(?:oauth|oidc|\.well-known)(?:/|$)|/events$~i', $request->pathinfo())
            && !preg_match('/stream|download|export|redirect/i', $request->action());
    }

    /** 内部对象标记，不新增或泄露响应头。 */
    public static function transport(Response $response): Response
    {
        self::$transportResponses ??= new WeakMap();
        self::$transportResponses[$response] = true;
        return $response;
    }

    public function normalize(Request $request, Response $response): Response
    {
        if (!$this->applies($request) || !$response instanceof Json
            || isset(self::$transportResponses[$response])) {
            return $response;
        }
        $headers = array_change_key_case($response->getHeader(), CASE_LOWER);
        if (isset($headers['content-disposition']) || isset($headers['location'])
            || str_contains(strtolower((string) ($headers['content-type'] ?? '')), 'text/event-stream')) {
            return $response;
        }
        if ($response->getCode() >= 500) {
            return json(['code' => $response->getCode(), 'msg' => '服务器内部错误', 'time' => time(), 'data' => null], $response->getCode())
                ->header($response->getHeader());
        }
        if (!in_array($response->getCode(), [400, 422], true)) return $response;
        $body = $response->getData();
        if (is_array($body) && in_array($body['code'] ?? null, [400, 422], true)
            && (is_string($body['msg'] ?? null) || is_string($body['message'] ?? null))
            && !isset($body['error_description']) && !is_string($body['error'] ?? null)) {
            $response->code(200);
        }
        return $response;
    }
}
