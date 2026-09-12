<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\model\identity\OAuthToken;
use app\common\service\MigrationService;
use app\common\service\identity\ApplicationCatalogService;
use app\common\service\identity\ClientSecretService;
use app\common\service\identity\OAuthClientService;
use app\common\service\identity\RedirectUriService;
use app\common\service\identity\SigningKeyService;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use think\App;
use think\facade\Db;

function phase4HttpExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase4HttpRequest(string $url, string $method = 'GET', array $form = [], array $headers = []): array
{
    $headerLines = $headers;
    $content = '';
    if ($method === 'POST') {
        $content = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $headerLines[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headerLines), 'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents($url, false, $context);
    phase4HttpExpect(is_string($body), 'HTTP 请求失败：' . $url);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $statusMatch);
    $normalized = [];
    foreach (array_slice($responseHeaders, 1) as $line) {
        if (!str_contains($line, ':')) continue;
        [$name, $value] = explode(':', $line, 2);
        $normalized[strtolower(trim($name))][] = trim($value);
    }
    return [(int) ($statusMatch[1] ?? 0), $normalized, $body, $url];
}

function phase4HttpJson(array $response, int $status): array
{
    phase4HttpExpect($response[0] === $status, 'HTTP status 错误，期望 ' . $status . ' 实际 ' . $response[0] . ' body=' . $response[2]);
    phase4HttpExpect(str_starts_with(strtolower($response[1]['content-type'][0] ?? ''), 'application/json'), '响应必须为 JSON，url=' . ($response[3] ?? '') . ' headers=' . json_encode($response[1], JSON_UNESCAPED_UNICODE) . ' body=' . $response[2]);
    phase4HttpExpect(in_array('no-store', $response[1]['cache-control'] ?? [], true) && in_array('no-cache', $response[1]['pragma'] ?? [], true), '协议响应必须禁止缓存');
    $decoded = json_decode($response[2], true);
    phase4HttpExpect(is_array($decoded), '响应 JSON 无效');
    return $decoded;
}

function phase4HttpBasic(string $clientId, string $secret): string
{
    return 'Authorization: Basic ' . base64_encode($clientId . ':' . $secret);
}

function phase4HttpWait(string $url, $process): void
{
    for ($attempt = 0; $attempt < 50; $attempt++) {
        if (!is_resource($process) || proc_get_status($process)['running'] !== true) throw new RuntimeException('PHP HTTP 服务提前退出');
        $response = @file_get_contents($url);
        if ($response !== false) return;
        usleep(100000);
    }
    throw new RuntimeException('PHP HTTP 服务未就绪');
}

function phase4HttpMigrationDirectory(string $source): string
{
    $target = sys_get_temp_dir() . '/funadmin_phase4_http_migrations_' . bin2hex(random_bytes(5));
    phase4HttpExpect(mkdir($target, 0700), '无法创建 migration 目录');
    foreach (glob($source . '/*.sql') ?: [] as $file) {
        $number = (int) substr(basename($file), 0, 3);
        if ($number <= 99 || $number === 114) copy($file, $target . '/' . basename($file));
    }
    return $target;
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$database = 'funadmin_identity_phase4_http_' . bin2hex(random_bytes(5));
$migrations = phase4HttpMigrationDirectory($root . '/database/migrations');
$keyDirectory = sys_get_temp_dir() . '/funadmin-phase4-http-keys-' . bin2hex(random_bytes(4));
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
$port = random_int(19000, 19999);
$issuer = 'http://127.0.0.1:' . $port . '/identity';
$process = null;
$routerPath = sys_get_temp_dir() . '/funadmin-phase4-http-router-' . bin2hex(random_bytes(4)) . '.php';
$environmentPath = $root . '/.env.testing';
$logPath = sys_get_temp_dir() . '/funadmin-phase4-http-' . bin2hex(random_bytes(4)) . '.log';
try {
    $server->execute('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $database;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    (new MigrationService())->runDirectory($migrations, 'core');
    Db::execute("INSERT INTO fun_identity_sso_config (tenant_id,enabled,provider_mode,issuer,external_identity_enabled,backchannel_logout_enabled,created_at,updated_at) VALUES (1,1,'identity_provider',?,0,1,NOW(),NOW())", [$issuer]);
    Db::execute("INSERT INTO fun_identity_user (tenant_id,public_id,realm,source_id,username,display_name,email,mobile,locale,status,created_at,updated_at) VALUES (1,'10000000-0000-4000-8000-000000000001','fixture',900001,'phase4-user','Phase4 User','phase4@example.test','13800000000','zh-CN',1,NOW(),NOW())");
    $userId = (int) Db::query("SELECT id FROM fun_identity_user WHERE realm='fixture' AND source_id=900001")[0]['id'];
    $catalog = new ApplicationCatalogService();
    $webApp = $catalog->save(1, ['code' => 'phase4-web', 'name' => 'Phase4 Web', 'runtimeType' => 'internal', 'launchUrl' => '/phase4-web']);
    $machineApp = $catalog->save(1, ['code' => 'phase4-machine', 'name' => 'Phase4 Machine', 'runtimeType' => 'internal', 'launchUrl' => '/phase4-machine']);
    $catalog->publish(1, (int) $webApp['id']);
    $catalog->publish(1, (int) $machineApp['id']);
    Db::execute("INSERT INTO fun_scope (tenant_id,name,description,is_builtin,status,created_at,updated_at) VALUES (1,'machine.read','Machine read',0,1,NOW(),NOW())");
    $clients = new OAuthClientService();
    $web = $clients->save(1, (int) $webApp['id'], ['name' => 'Web', 'clientType' => 'confidential', 'grants' => ['authorization_code', 'refresh_token'], 'scopes' => ['openid', 'profile', 'email', 'offline_access']]);
    $public = $clients->save(1, (int) $webApp['id'], ['name' => 'Public', 'clientType' => 'public', 'grants' => ['authorization_code', 'refresh_token'], 'scopes' => ['openid', 'profile']]);
    $machine = $clients->save(1, (int) $machineApp['id'], ['name' => 'Machine', 'clientType' => 'machine', 'grants' => ['client_credentials'], 'scopes' => ['machine.read']]);
    $callback = 'http://127.0.0.1:' . $port . '/callback';
    (new RedirectUriService())->replace(1, (int) $web['id'], [['uri' => $callback]], true);
    (new RedirectUriService())->replace(1, (int) $public['id'], [['uri' => $callback . '/public']], true);
    $secrets = new ClientSecretService();
    $webSecret = $secrets->rotate(1, (int) $web['id'])['secret'];
    $machineSecret = $secrets->rotate(1, (int) $machine['id'])['secret'];
    (new SigningKeyService($keyDirectory))->rotate(1, 3600);
    $environment = array_merge($_ENV, ['PATH' => '/opt/homebrew/opt/php@8.1/bin:/opt/homebrew/bin:/usr/bin:/bin', 'PHP_APP_ENV' => 'testing', 'PHP_DB_NAME' => $database, 'PHP_IDENTITY_ISSUER' => $issuer, 'PHP_OAUTH_IDENTITY_SESSION_RESOLVER' => 'app\\identity\\service\\FixtureIdentitySessionResolver', 'PHP_OIDC_SUBJECT_PEPPER' => 'phase4-http-pepper']);
    $mysql = $original['connections']['mysql'];
    $environmentFile = implode("\n", [
        'APP_DEBUG = true',
        'APP_ENV = testing',
        'DB_TYPE = ' . $mysql['type'],
        'DB_HOST = ' . $mysql['hostname'],
        'DB_NAME = ' . $database,
        'DB_USER = ' . $mysql['username'],
        'DB_PASS = "' . addcslashes((string) $mysql['password'], "\\\"") . '"',
        'DB_PORT = ' . $mysql['hostport'],
        'DB_CHARSET = ' . $mysql['charset'],
        'DB_PREFIX = ' . $mysql['prefix'],
        'IDENTITY_ISSUER = ' . $issuer,
        'OAUTH_IDENTITY_SESSION_RESOLVER = app\\identity\\service\\FixtureIdentitySessionResolver',
        'OIDC_SUBJECT_PEPPER = phase4-http-pepper',
    ]) . "\n";
    phase4HttpExpect(file_put_contents($environmentPath, $environmentFile) !== false, '无法创建独立 HTTP 环境配置');
    $routerContent = '<?php $_SERVER["SCRIPT_NAME"] = "/index.php"; $_SERVER["SCRIPT_FILENAME"] = ' . var_export($root . '/public/index.php', true) . '; $_ENV[\'ENV_NAME\'] = \'testing\'; require $_SERVER["SCRIPT_FILENAME"];';
    phase4HttpExpect(file_put_contents($routerPath, $routerContent) !== false, '无法创建独立 HTTP router');
    $command = ['/opt/homebrew/opt/php@8.1/bin/php', '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $routerPath];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $logPath, 'a'], 2 => ['file', $logPath, 'a']], $pipes, $root, $environment);
    phase4HttpExpect(is_resource($process), '无法启动独立 PHP 8.1 服务');
    phase4HttpWait($issuer . '/.well-known/openid-configuration', $process);

    $discovery = phase4HttpJson(phase4HttpRequest($issuer . '/.well-known/openid-configuration', 'GET', [], ['Host: attacker.invalid']), 200);
    phase4HttpExpect($discovery['issuer'] === $issuer && $discovery['end_session_endpoint'] === $issuer . '/logout' && $discovery['backchannel_logout_supported'] === true, 'Discovery issuer 必须固定且声明已实现 logout');
    $jwks = phase4HttpJson(phase4HttpRequest((string) $discovery['jwks_uri']), 200);
    phase4HttpExpect(count($jwks['keys'] ?? []) === 1 && $jwks['keys'][0]['alg'] === 'RS256', 'JWKS 必须发布实际 RS256 key');

    $verifier = str_repeat('v', 43);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $state = 'phase4-state';
    $authorizeUrl = $issuer . '/authorize?' . http_build_query(['response_type' => 'code', 'client_id' => $web['client_id'], 'redirect_uri' => $callback, 'scope' => 'openid profile email offline_access', 'state' => $state, 'nonce' => 'phase4-nonce', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'], '', '&', PHP_QUERY_RFC3986);
    $authorize = phase4HttpJson(phase4HttpRequest($authorizeUrl), 200);
    phase4HttpExpect(isset($authorize['transaction_id']), 'authorize 必须创建事务');
    $decision = phase4HttpRequest($issuer . '/decision', 'POST', ['transaction_id' => $authorize['transaction_id'], 'approved' => '1'], ['X-Test-Identity-Tenant: 1', 'X-Test-Identity-User: ' . $userId, 'X-Test-Identity-Session: phase4-session-0001']);
    phase4HttpExpect($decision[0] === 302 && isset($decision[1]['location'][0]), 'decision 必须重定向 callback，status=' . $decision[0] . ' headers=' . json_encode($decision[1], JSON_UNESCAPED_UNICODE) . ' body=' . $decision[2]);
    parse_str((string) parse_url($decision[1]['location'][0], PHP_URL_QUERY), $decisionQuery);
    phase4HttpExpect(($decisionQuery['state'] ?? '') === $state && isset($decisionQuery['code']), 'decision 必须保留 state 并签发 code');
    $tokenForm = ['grant_type' => 'authorization_code', 'code' => $decisionQuery['code'], 'redirect_uri' => $callback, 'code_verifier' => $verifier];
    $tokens = phase4HttpJson(phase4HttpRequest($issuer . '/token', 'POST', $tokenForm, [phase4HttpBasic($web['client_id'], $webSecret)]), 200);
    phase4HttpExpect(isset($tokens['access_token'], $tokens['refresh_token'], $tokens['id_token']), 'code exchange 必须签发 access/refresh/ID token');
    $claims = JWT::decode($tokens['id_token'], JWK::parseKeySet($jwks));
    phase4HttpExpect($claims->iss === $issuer && $claims->aud === $web['client_id'] && $claims->nonce === 'phase4-nonce', 'ID Token claims 或 JWKS RS256 验证失败');
    $replayCode = phase4HttpJson(phase4HttpRequest($issuer . '/token', 'POST', $tokenForm, [phase4HttpBasic($web['client_id'], $webSecret)]), 400);
    phase4HttpExpect(($replayCode['error'] ?? '') === 'invalid_grant', 'authorization code 必须原子单次消费');
    $userinfo = phase4HttpJson(phase4HttpRequest($issuer . '/userinfo', 'GET', [], ['Authorization: Bearer ' . $tokens['access_token']]), 200);
    phase4HttpExpect(preg_match('/^[0-9a-f-]{36}$/', (string) $userinfo['sub']) === 1 && $userinfo['sub'] !== '10000000-0000-4000-8000-000000000001' && $userinfo['email'] === 'phase4@example.test', 'userinfo sub 必须稳定派生且不得暴露 user public_id');
    phase4HttpExpect($claims->sub === $userinfo['sub'], 'ID Token 与 UserInfo 必须使用一致的 sub');
    $refreshed = phase4HttpJson(phase4HttpRequest($issuer . '/token', 'POST', ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']], [phase4HttpBasic($web['client_id'], $webSecret)]), 200);
    phase4HttpExpect($refreshed['refresh_token'] !== $tokens['refresh_token'], 'refresh 必须 rotation');
    $refreshReplay = phase4HttpJson(phase4HttpRequest($issuer . '/token', 'POST', ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']], [phase4HttpBasic($web['client_id'], $webSecret)]), 400);
    phase4HttpExpect(($refreshReplay['error'] ?? '') === 'invalid_grant', 'refresh replay 必须拒绝并吊销 family');
    $familyIntrospection = phase4HttpJson(phase4HttpRequest($issuer . '/introspect', 'POST', ['token' => $refreshed['access_token']], [phase4HttpBasic($web['client_id'], $webSecret)]), 200);
    phase4HttpExpect(($familyIntrospection['active'] ?? true) === false, 'refresh replay 必须吊销新一代 access token');

    $machineToken = phase4HttpJson(phase4HttpRequest($issuer . '/token', 'POST', ['grant_type' => 'client_credentials', 'scope' => 'machine.read'], [phase4HttpBasic($machine['client_id'], $machineSecret)]), 200);
    phase4HttpExpect($machineToken['scope'] === 'machine.read' && !isset($machineToken['refresh_token']), 'client credentials 必须仅签发 machine access token');
    $introspection = phase4HttpJson(phase4HttpRequest($issuer . '/introspect', 'POST', ['token' => $machineToken['access_token']], [phase4HttpBasic($machine['client_id'], $machineSecret)]), 200);
    phase4HttpExpect(($introspection['active'] ?? false) === true && $introspection['client_id'] === $machine['client_id'], 'introspection client_id 必须为公开标识');
    phase4HttpJson(phase4HttpRequest($issuer . '/revoke', 'POST', ['token' => $machineToken['access_token']], [phase4HttpBasic($machine['client_id'], $machineSecret)]), 200);
    $revoked = phase4HttpJson(phase4HttpRequest($issuer . '/introspect', 'POST', ['token' => $machineToken['access_token']], [phase4HttpBasic($machine['client_id'], $machineSecret)]), 200);
    phase4HttpExpect(($revoked['active'] ?? true) === false, 'revoke 后 introspection 必须 inactive');

    $publicAuth = phase4HttpJson(phase4HttpRequest($issuer . '/authorize?' . http_build_query(['response_type' => 'code', 'client_id' => $public['client_id'], 'redirect_uri' => $callback . '/public', 'scope' => 'openid profile', 'nonce' => 'public-nonce', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'])), 200);
    $publicDecision = phase4HttpRequest($issuer . '/decision', 'POST', ['transaction_id' => $publicAuth['transaction_id'], 'approved' => '1'], ['X-Test-Identity-Tenant: 1', 'X-Test-Identity-User: ' . $userId, 'X-Test-Identity-Session: phase4-session-0002']);
    parse_str((string) parse_url($publicDecision[1]['location'][0] ?? '', PHP_URL_QUERY), $publicQuery);
    $publicToken = phase4HttpJson(phase4HttpRequest($issuer . '/token', 'POST', ['grant_type' => 'authorization_code', 'client_id' => $public['client_id'], 'code' => $publicQuery['code'] ?? '', 'redirect_uri' => $callback . '/public', 'code_verifier' => $verifier]), 200);
    phase4HttpExpect(isset($publicToken['access_token']), 'public client 必须使用 none + PKCE 换 token');
    phase4HttpExpect((int) OAuthToken::where('token_hash', hash('sha256', $publicToken['access_token']))->count() === 1 && (int) OAuthToken::where('token_hash', $publicToken['access_token'])->count() === 0, 'opaque token 只能哈希存储');

    $invalidClientResponse = phase4HttpRequest($issuer . '/token', 'POST', ['grant_type' => 'client_credentials'], [phase4HttpBasic($machine['client_id'], 'wrong-secret')]);
    $invalidClient = phase4HttpJson($invalidClientResponse, 401);
    phase4HttpExpect(($invalidClient['error'] ?? '') === 'invalid_client' && str_starts_with($invalidClientResponse[1]['www-authenticate'][0] ?? '', 'Basic '), 'invalid_client status/body/challenge 错误');

    $bruteForceStatus = 0;
    for ($attempt = 0; $attempt < 31; $attempt++) {
        $bruteForce = phase4HttpRequest($issuer . '/token', 'POST', ['grant_type' => 'client_credentials'], [phase4HttpBasic('phase4-brute-force-client', 'wrong-secret')]);
        $bruteForceStatus = $bruteForce[0];
        if ($attempt < 30) phase4HttpExpect($bruteForceStatus === 401, 'token 限流不得提前阻断第 ' . ($attempt + 1) . ' 次请求');
    }
    phase4HttpExpect($bruteForceStatus === 429, 'token 连续爆破请求必须在超过 30/m 后返回 429');
    $invalidTokenResponse = phase4HttpRequest($issuer . '/userinfo', 'GET', [], ['Authorization: Bearer invalid']);
    $invalidToken = phase4HttpJson($invalidTokenResponse, 401);
    phase4HttpExpect(($invalidToken['error'] ?? '') === 'invalid_token' && str_contains($invalidTokenResponse[1]['www-authenticate'][0] ?? '', 'invalid_token'), 'invalid_token status/body/challenge 错误');
    echo "identity phase4 HTTP E2E tests passed\n";
} catch (Throwable $exception) {
    $serverLog = is_file($logPath) ? (string) file_get_contents($logPath) : '';
    throw new RuntimeException($exception->getMessage() . "\nPHP HTTP server log:\n" . $serverLog, 0, $exception);
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    foreach (glob($keyDirectory . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($keyDirectory)) rmdir($keyDirectory);
    foreach (glob($migrations . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($migrations)) rmdir($migrations);
    if (is_file($logPath)) unlink($logPath);
    if (is_file($routerPath)) unlink($routerPath);
    if (is_file($environmentPath)) unlink($environmentPath);
    $app->config->set($serverConfig, 'database');
    $cleanup = Db::connect('mysql', true);
    $cleanup->execute('DROP DATABASE IF EXISTS `' . $database . '`');
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
