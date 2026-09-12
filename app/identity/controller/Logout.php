<?php

declare(strict_types=1);

namespace app\identity\controller;

use app\common\model\identity\OAuthClient;
use app\common\model\identity\OidcClientSession;
use app\common\service\identity\RedirectUriService;
use app\identity\service\IdTokenHintService;
use app\identity\service\OidcLogoutService;
use think\facade\Session;
use think\Request;
use think\Response;

/** OIDC RP-Initiated Logout；默认结束整个 OP SSO 会话。 */
final class Logout
{
    public function end(Request $request): Response
    {
        $hint = (string) $request->param('id_token_hint', '');
        $claims = (new IdTokenHintService())->verify(1, $hint);
        $redirect = null;
        if ($claims !== null) {
            $client = OAuthClient::forTenant(1)->where('client_id', (string) $claims['aud'])->where('status', 'active')->find();
            $session = $client ? OidcClientSession::forTenant(1)->where('sid', (string) $claims['sid'])->where('client_id', (int) $client->id)->find() : null;
            if ($client && $session) {
                $candidate = trim((string) $request->param('post_logout_redirect_uri', ''));
                if ($candidate !== '' && (new RedirectUriService())->matches(1, (int) $client->id, $candidate, 'post_logout', app()->isDebug())) $redirect = $candidate;
                (new OidcLogoutService())->logout(1, (string) $session->sid, (string) $request->param('logout_scope', 'global') !== 'client');
            }
        }
        Session::delete('identity.session');
        if ($redirect !== null) {
            $state = $request->param('state');
            if ($state !== null) $redirect .= (str_contains($redirect, '?') ? '&' : '?') . http_build_query(['state' => (string) $state], '', '&', PHP_QUERY_RFC3986);
            return redirect($redirect)->header($this->headers());
        }
        return response('已安全退出统一登录', 200, $this->headers("default-src 'none'; frame-ancestors 'none'"));
    }

    private function headers(?string $csp = null): array
    {
        return ['Content-Security-Policy' => $csp ?? "default-src 'none'; frame-ancestors 'none'", 'Cache-Control' => 'no-store', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY'];
    }
}
