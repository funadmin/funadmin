<?php

namespace app\admin\middleware;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\http\AdminResponse;
use DomainException;
use Webman\Http\Request;
use support\Response;
use Webman\MiddlewareInterface;

/**
 * Admin Web API CSRF 校验，失败时始终返回 JSON。
 */
class CheckAdminApiCsrf implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler($request);
        }

        try {
            (new BrowserAuthService())->assertCsrf(
                (string) $request->cookie(BrowserAuthService::COOKIE, ''),
                (string) $request->header('x-csrf-token', '')
            );
        } catch (DomainException $error) {
            return AdminResponse::create($error->getMessage(), null, 403);
        }

        return $handler($request);
    }
}
