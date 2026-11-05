<?php

declare(strict_types=1);

namespace app\common\plugin\controller;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\AuthorizationService;
use app\common\plugin\service\FrontendHookService;
use app\common\service\authentication\BearerTokenExtractor;
use app\common\service\authentication\TokenService;
use app\common\service\member\MemberAuthService;
use app\common\traits\JsonResponse;
use DomainException;
use support\Container;
use support\Request;
use support\Response;

final class FrontendHooks
{
    use JsonResponse;

    public function index(Request $request): Response
    {
        $audience = $request->get('audience', 'public');
        if (!is_string($audience) || !in_array($audience, ['public', 'member', 'admin'], true)) {
            return $this->fail('Invalid hook audience', code: 400);
        }

        $permissions = [];
        try {
            if ($audience === 'admin') {
                $cookie = (string) $request->cookie(BrowserAuthService::COOKIE, '');
                $user = (new BrowserAuthService())->user($cookie);
                $permissions = (new AuthorizationService())->userInfo($user)['permissions'];
            } elseif ($audience === 'member') {
                $token = (new BearerTokenExtractor())->extract($request);
                $identity = $token === null ? null : (new TokenService())->validateToken($token);
                $id = is_array($identity) ? (int) ($identity['id'] ?? 0) : 0;
                if ($id <= 0 || !(new MemberAuthService())->activeMember($id)) {
                    return $this->fail('Unauthorized', code: 401);
                }
            }

            $descriptors = Container::get(FrontendHookService::class)->descriptors($audience, $permissions);
            return $this->ok('操作成功', $descriptors);
        } catch (DomainException $error) {
            $status = in_array($error->getCode(), [401, 403], true) ? $error->getCode() : 401;
            return $this->fail($error->getMessage(), code: $status);
        }
    }
}
