<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

session_name((string) getenv('RP_SESSION_NAME'));
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

$issuer = rtrim((string) getenv('RP_ISSUER'), '/');
$clientId = (string) getenv('RP_CLIENT_ID');
$clientSecret = (string) getenv('RP_CLIENT_SECRET');
$callback = (string) getenv('RP_CALLBACK');
$name = (string) getenv('RP_NAME');
$kind = (string) getenv('RP_KIND');
$runtimeFile = (string) getenv('RP_RUNTIME_FILE');

function phase9Base64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function phase9Http(string $url, string $method = 'GET', array $form = [], array $headers = []): array
{
    $content = $form === [] ? '' : http_build_query($form, '', '&', PHP_QUERY_RFC3986);
    if ($content !== '') $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
    return [(int) ($match[1] ?? 0), is_string($body) ? $body : '', $responseHeaders];
}

function phase9Json(string $url): array
{
    [$status, $body] = phase9Http($url, headers: ['Accept: application/json']);
    if ($status !== 200) throw new RuntimeException('HTTP ' . $status . ': ' . $url);
    return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
}

function phase9Escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function phase9Page(string $title, string $body, string $kind): never
{
    $accent = $kind === 'internal' ? '#2563eb' : '#7c3aed';
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . phase9Escape($title) . '</title><style>'
        . ':root{color-scheme:light}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(145deg,#eef2ff,#f8fafc 50%,#ecfeff);font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033;padding:30px}.card{width:min(820px,100%);background:#fff;border:1px solid #e5e7eb;border-radius:26px;padding:42px;box-shadow:0 24px 70px rgba(15,23,42,.13)}.badge{display:inline-flex;padding:7px 12px;border-radius:999px;background:' . $accent . '18;color:' . $accent . ';font-weight:750;font-size:13px}.hero{display:flex;justify-content:space-between;gap:24px;align-items:start}h1{font-size:38px;margin:18px 0 10px}.muted{color:#64748b;line-height:1.7}.status{margin:28px 0;padding:20px;border-radius:18px;background:#f8fafc;border:1px solid #e5e7eb}.ok{color:#047857;font-weight:750}.actions{display:flex;gap:12px;flex-wrap:wrap}a,button{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:0;border-radius:12px;padding:13px 18px;font:inherit;font-weight:750;cursor:pointer;background:' . $accent . ';color:#fff}.secondary{background:#eef2f7;color:#334155}.danger{background:#dc2626}code{font-size:12px;word-break:break-all}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.metric{padding:14px;border:1px solid #e5e7eb;border-radius:14px}.metric b{display:block;margin-bottom:5px}@media(max-width:640px){.card{padding:26px}.grid{grid-template-columns:1fr}h1{font-size:30px}}</style></head><body><main class="card">' . $body . '</main></body></html>';
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/login') {
    $scope = trim((string) ($_GET['scope'] ?? 'openid profile'));
    $state = phase9Base64Url(random_bytes(24));
    $nonce = phase9Base64Url(random_bytes(24));
    $verifier = phase9Base64Url(random_bytes(48));
    $_SESSION['oidc_pending'] = ['state' => $state, 'nonce' => $nonce, 'code_verifier' => $verifier, 'scope' => $scope];
    $query = ['response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => $callback, 'scope' => $scope, 'state' => $state, 'nonce' => $nonce, 'code_challenge' => phase9Base64Url(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256'];
    header('Location: ' . $issuer . '/authorize?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

if ($path === '/callback') {
    $query = $_GET;
    $pending = $_SESSION['oidc_pending'] ?? null;
    if (!is_array($pending) || array_diff(array_keys($query), ['code', 'state']) !== [] || !isset($query['code'], $query['state']) || !hash_equals((string) $pending['state'], (string) $query['state'])) {
        http_response_code(400);
        phase9Page('Callback rejected', '<span class="badge">安全校验</span><h1>Callback 已拒绝</h1><p class="muted">仅接受匹配的 code/state。</p>', $kind);
    }
    $headers = ['Accept: application/json'];
    $form = ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => (string) $query['code'], 'redirect_uri' => $callback, 'code_verifier' => (string) $pending['code_verifier']];
    if ($clientSecret !== '') $headers[] = 'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret);
    [$status, $tokenBody] = phase9Http($issuer . '/token', 'POST', $form, $headers);
    $tokens = json_decode($tokenBody, true, flags: JSON_THROW_ON_ERROR);
    if ($status !== 200 || empty($tokens['id_token'])) throw new RuntimeException('code exchange 失败：' . $tokenBody);
    $discovery = phase9Json($issuer . '/.well-known/openid-configuration');
    $jwks = phase9Json((string) $discovery['jwks_uri']);
    $claims = (array) JWT::decode((string) $tokens['id_token'], JWK::parseKeySet($jwks));
    if (($claims['iss'] ?? null) !== $issuer || ($claims['aud'] ?? null) !== $clientId || ($claims['nonce'] ?? null) !== $pending['nonce']) throw new RuntimeException('ID token iss/aud/nonce 校验失败');
    session_regenerate_id(true);
    unset($_SESSION['oidc_pending']);
    $_SESSION['rp_user'] = ['sub' => (string) $claims['sub'], 'name' => (string) ($claims['name'] ?? $claims['preferred_username'] ?? $claims['sub']), 'sid' => (string) $claims['sid'], 'scope' => (string) ($tokens['scope'] ?? $pending['scope']), 'id_token' => (string) $tokens['id_token'], 'verified' => true];
    header('Location: /?stage=authorized', true, 302);
    exit;
}

if ($path === '/logout-rp') {
    $_SESSION = [];
    session_regenerate_id(true);
    phase9Page($name . ' 已退出', '<span class="badge">RP logout</span><h1>本地会话已退出</h1><p class="muted">统一身份会话仍由 Identity Provider 管理。</p><div class="actions"><a href="/">返回应用</a></div>', $kind);
}

if ($path === '/logout-global') {
    $user = $_SESSION['rp_user'] ?? [];
    if (!is_array($user) || empty($user['id_token'])) { header('Location: /', true, 302); exit; }
    $query = ['id_token_hint' => $user['id_token'], 'post_logout_redirect_uri' => (string) getenv('RP_POST_LOGOUT'), 'state' => phase9Base64Url(random_bytes(18)), 'logout_scope' => 'global'];
    header('Location: ' . $issuer . '/logout?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986), true, 302);
    exit;
}

if ($path === '/backchannel' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = (string) ($_POST['logout_token'] ?? '');
    try {
        $claims = (array) JWT::decode($token, JWK::parseKeySet(phase9Json($issuer . '/.well-known/jwks.json')));
        $events = (array) ($claims['events'] ?? []);
        if (($claims['iss'] ?? '') !== $issuer || ($claims['aud'] ?? '') !== $clientId || !isset($events['http://schemas.openid.net/event/backchannel-logout'])) throw new RuntimeException('logout token claims 无效');
        $record = ['received_at' => date(DATE_ATOM), 'sid' => (string) ($claims['sid'] ?? ''), 'jti' => (string) ($claims['jti'] ?? '')];
        file_put_contents($runtimeFile, json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
        http_response_code(204);
    } catch (Throwable) {
        http_response_code(400);
    }
    exit;
}

if ($path === '/post-logout') {
    $_SESSION = [];
    session_regenerate_id(true);
    phase9Page($name . ' 全局退出', '<span class="badge">Global logout</span><h1>统一登录已安全退出</h1><p class="muted">RP 本地会话已清除，OP 会话已结束。</p><div class="actions"><a href="/">返回应用</a></div>', $kind);
}

$user = $_SESSION['rp_user'] ?? null;
$backchannel = is_file($runtimeFile) ? json_decode((string) file_get_contents($runtimeFile), true) : null;
if (is_array($user) && is_array($backchannel) && hash_equals((string) ($user['sid'] ?? ''), (string) ($backchannel['sid'] ?? ''))) {
    $_SESSION = [];
    session_regenerate_id(true);
    $user = null;
}

$origin = ($_SERVER['HTTP_HOST'] ?? 'unknown');
if (!is_array($user)) {
    phase9Page($name, '<div class="hero"><div><span class="badge">' . phase9Escape($kind === 'internal' ? 'First-party / Internal RP' : 'Partner / Standalone RP') . '</span><h1>' . phase9Escape($name) . '</h1><p class="muted">独立 origin：<code>' . phase9Escape($origin) . '</code><br>当前没有 RP 本地会话。</p></div></div><div class="status"><b>未登录</b><p class="muted">将使用 Authorization Code + PKCE 跳转到 Identity Provider。</p></div><div class="actions"><a href="/login">使用统一身份登录</a></div>', $kind);
}

$scope = (string) ($user['scope'] ?? '');
$expanded = str_contains(' ' . $scope . ' ', ' email ');
$body = '<span class="badge">OIDC session verified</span><h1>欢迎，' . phase9Escape($user['name']) . '</h1><p class="muted">已通过 JWKS 验证 ID token 的 signature、iss、aud 与 nonce，并建立本地 HttpOnly session。</p><div class="grid"><div class="metric"><b>Origin</b><code>' . phase9Escape($origin) . '</code></div><div class="metric"><b>Scopes</b><code>' . phase9Escape($scope) . '</code></div><div class="metric"><b>Token storage</b><span class="ok">仅服务端 session</span></div><div class="metric"><b>Callback</b><code>code + state only</code></div></div><div class="status ok">授权完成 · ID token 验证通过</div><div class="actions">';
if ($kind === 'partner' && !$expanded) $body .= '<a href="/login?scope=openid%20profile%20email">扩展 email scope</a>';
$body .= '<a class="secondary" href="/logout-rp">仅退出当前 RP</a><a class="danger" href="/logout-global">全局退出</a></div>';
phase9Page($name . ' 已授权', $body, $kind);
