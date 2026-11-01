<?php

declare(strict_types=1);

namespace app\identity\middleware;

use app\common\service\BearerTokenExtractor;
use app\identity\service\OpaqueTokenService;
use Closure;
use think\Request;
use think\Response;

/**
 * 仅接受 Identity 服务签发的 opaque access token，并注入可信资源身份。
 * token 的 password_version/session_version 与主体状态由 OpaqueTokenService 原子检查。
 */
final class IdentityBearerMiddleware
{
    public function __construct(
        private readonly BearerTokenExtractor $extractor,
        private readonly OpaqueTokenService $tokens
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $opaque = $this->extractor->extract($request);
        if ($opaque === null) return $this->unauthorized('invalid_token');
        $identity = $this->tokens->inspect($opaque);
        if (!($identity['active'] ?? false)) return $this->unauthorized('invalid_token');
        $record = (array) ($identity['_record'] ?? []);
        if (!isset($record['tenant_id'], $record['client_id'], $record['subject_type'])) return $this->unauthorized('invalid_token');
        if (!in_array((string) $record['subject_type'], ['user', 'client'], true)) return $this->unauthorized('invalid_token');
        $request->tenant_id = (int) $record['tenant_id'];
        $request->oauth_client_id = (int) $record['client_id'];
        $request->oauth_scopes = array_values(array_filter(explode(' ', (string) ($identity['scope'] ?? ''))));
        $request->subject_type = (string) $record['subject_type'];
        if ($request->subject_type === 'user') {
            $request->identity_user_id = (int) $record['user_id'];
            if (!empty($identity['member_id'])) $request->member_id = (int) $identity['member_id'];
        }
        return $next($request);
    }

    private function unauthorized(string $error): Response
    {
        return json(['error' => $error], 401)->header([
            'WWW-Authenticate' => 'Bearer realm="identity", error="' . $error . '"',
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    }
}
