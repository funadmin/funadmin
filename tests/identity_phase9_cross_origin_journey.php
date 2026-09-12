<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/common/functions/plugin.php';

use app\common\service\MigrationService;
use app\common\service\identity\ApplicationCatalogService;
use app\common\service\identity\ClientSecretService;
use app\common\service\identity\IdentityCredentialService;
use app\common\service\identity\OAuthClientService;
use app\common\service\identity\RedirectUriService;
use app\common\service\identity\SigningKeyService;
use app\identity\service\AuthorizationRevocationService;
use think\App;
use think\facade\Db;

function phase9Expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase9Quote(string $identifier): string
{
    phase9Expect(preg_match('/^[a-z0-9_]+$/', $identifier) === 1, '数据库名不安全');
    return '`' . $identifier . '`';
}

function phase9Wait(string $url, $process): void
{
    for ($attempt = 0; $attempt < 80; $attempt++) {
        if (!is_resource($process) || proc_get_status($process)['running'] !== true) throw new RuntimeException('HTTP 服务提前退出：' . $url);
        if (@file_get_contents($url) !== false) return;
        usleep(100000);
    }
    throw new RuntimeException('HTTP 服务未就绪：' . $url);
}

function phase9Stop(&$process): void
{
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    $process = null;
}

function phase9SafeRemoveTree(string $path, string $allowedRoot): void
{
    $realRoot = realpath($allowedRoot);
    $realPath = realpath($path);
    phase9Expect($realRoot !== false && $realPath !== false && ($realPath === $realRoot || str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR)), '拒绝清理 temp root 外路径');
    if (is_link($path)) {
        unlink($path);
        return;
    }
    if (!is_dir($path)) {
        unlink($path);
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        $entryPath = $entry->getPathname();
        $entry->isDir() && !$entry->isLink() ? rmdir($entryPath) : unlink($entryPath);
    }
    rmdir($path);
}

function phase9Start(array $command, string $root, array $environment, string $logPath)
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $logPath, 'a'], 2 => ['file', $logPath, 'a']], $pipes, $root, $environment);
    phase9Expect(is_resource($process), '无法启动 HTTP 服务');
    return $process;
}

function phase9Header(array $headers, string $name): ?string
{
    foreach ($headers as $header) {
        if (str_starts_with(strtolower($header), strtolower($name) . ':')) return trim(substr($header, strlen($name) + 1));
    }
    return null;
}

function phase9Http(array &$jars, string $url, string $method = 'GET', array $form = [], array $headers = []): array
{
    $parts = parse_url($url);
    $origin = strtolower((string) ($parts['scheme'] ?? 'http') . '://' . (string) ($parts['host'] ?? '') . ':' . (string) ($parts['port'] ?? 80));
    $cookies = $jars[$origin] ?? [];
    if ($cookies !== []) $headers[] = 'Cookie: ' . implode('; ', array_map(static fn (string $name, string $value): string => $name . '=' . $value, array_keys($cookies), $cookies));
    $content = $form === [] ? '' : http_build_query($form, '', '&', PHP_QUERY_RFC3986);
    if ($content !== '') $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents($url, false, $context);
    phase9Expect(is_string($body), 'HTTP 请求失败：' . $url);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $cookie) === 1) {
            if ($cookie[2] === '') unset($jars[$origin][$cookie[1]]);
            else $jars[$origin][$cookie[1]] = $cookie[2];
        }
    }
    return [(int) ($match[1] ?? 0), $body, $responseHeaders, $url];
}

function phase9Absolute(string $base, string $location): string
{
    if (preg_match('#^https?://#i', $location) === 1) return $location;
    $parts = parse_url($base);
    $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    return $origin . '/' . ltrim($location, '/');
}

function phase9Hidden(string $html, string $name): string
{
    phase9Expect(preg_match('/<input[^>]+name="' . preg_quote($name, '/') . '"[^>]+value="([^"]*)"/i', $html, $match) === 1, '页面缺少字段：' . $name);
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function phase9Step(array &$steps, string $name, array $evidence): void
{
    $steps[$name] = ['passed' => true, 'evidence' => $evidence];
}

function phase9StartAuthorization(array &$jars, string $rpLoginUrl, string $expectedCallback, string $expectedClient): array
{
    [$status, , $headers] = phase9Http($jars, $rpLoginUrl);
    $location = (string) phase9Header($headers, 'Location');
    phase9Expect($status === 302 && $location !== '', 'RP /login 未跳转 authorize');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    phase9Expect(($query['client_id'] ?? '') === $expectedClient && ($query['redirect_uri'] ?? '') === $expectedCallback, 'authorize client/redirect_uri 不匹配');
    phase9Expect(($query['code_challenge_method'] ?? '') === 'S256' && strlen((string) ($query['code_challenge'] ?? '')) >= 43, 'RP 未生成 PKCE S256');
    phase9Expect(strlen((string) ($query['state'] ?? '')) >= 32 && strlen((string) ($query['nonce'] ?? '')) >= 32, 'RP 未生成 state/nonce');
    return [$location, $query];
}

function phase9Interaction(array &$jars, string $authorizeUrl): array
{
    [$status, $body] = phase9Http($jars, $authorizeUrl);
    $payload = json_decode($body, true);
    $location = is_array($payload) ? (string) ($payload['interaction_url'] ?? '') : '';
    phase9Expect($status === 200 && str_contains($location, '/identity/interaction?transaction_id='), 'authorize 未返回 Identity interaction：HTTP ' . $status . ' Body=' . substr(strip_tags($body), 0, 240));
    [$pageStatus, $html] = phase9Http($jars, phase9Absolute($authorizeUrl, $location));
    phase9Expect($pageStatus === 200, 'Identity interaction 页面不可用');
    return [$html, phase9Absolute($authorizeUrl, $location)];
}

function phase9Consent(array &$jars, string $interactionUrl, string $html): string
{
    phase9Expect(str_contains($html, '确认授权'), '预期 consent 页面但未出现');
    [$status, $body, $headers] = phase9Http($jars, phase9Absolute($interactionUrl, '/identity/interaction/consent'), 'POST', ['csrf_token' => phase9Hidden($html, 'csrf_token'), 'transaction_id' => phase9Hidden($html, 'transaction_id'), 'approved' => '1']);
    $location = (string) phase9Header($headers, 'Location');
    $diagnostic = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('#<(style|script)[^>]*>.*?</\\1>#is', '', $body) ?? $body)) ?? '');
    phase9Expect($status === 302 && $location !== '', 'consent 未跳转 callback：HTTP ' . $status . ' Body=' . substr($diagnostic, 0, 800));
    return $location;
}

function phase9CompleteCallback(array &$jars, string $callbackUrl, string $expectedOrigin): string
{
    parse_str((string) parse_url($callbackUrl, PHP_URL_QUERY), $query);
    phase9Expect(array_keys($query) === ['code', 'state'], 'callback URL 必须仅包含 code/state');
    phase9Expect(!str_contains($callbackUrl, 'token'), 'callback URL 泄露 token');
    [$status, , $headers] = phase9Http($jars, $callbackUrl);
    $location = (string) phase9Header($headers, 'Location');
    phase9Expect($status === 302 && $location === '/?stage=authorized', 'RP callback 未完成 code exchange');
    [$pageStatus, $body] = phase9Http($jars, $expectedOrigin . $location);
    phase9Expect($pageStatus === 200 && str_contains($body, 'ID token 验证通过'), 'RP fixture 未完成 nonce/JWKS 验证');
    return $body;
}

function phase9DispatchBackchannel(int $internalClientId, int $partnerClientId, string $internalUrl, string $partnerUrl, array &$jars): int
{
    $deliveries = Db::query("SELECT id,client_id,logout_token FROM fun_backchannel_logout_delivery WHERE tenant_id=1 AND status='pending' ORDER BY id");
    $delivered = 0;
    foreach ($deliveries as $delivery) {
        $target = (int) $delivery['client_id'] === $internalClientId ? $internalUrl : ((int) $delivery['client_id'] === $partnerClientId ? $partnerUrl : '');
        if ($target === '') continue;
        [$status] = phase9Http($jars, $target . '/backchannel', 'POST', ['logout_token' => (string) $delivery['logout_token']]);
        phase9Expect($status === 204, 'Backchannel RP 拒绝 logout_token');
        Db::execute("UPDATE fun_backchannel_logout_delivery SET status='delivered',attempts=attempts+1,delivered_at=NOW(),response_status=204,updated_at=NOW() WHERE tenant_id=1 AND id=? AND status='pending'", [(int) $delivery['id']]);
        $delivered++;
    }
    return $delivered;
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$database = 'funadmin_identity_phase9_' . bin2hex(random_bytes(6));
[$identityPort, $internalPort, $partnerPort] = [random_int(23000, 23900), random_int(24000, 24900), random_int(25000, 25900)];
$issuer = 'http://identity.localhost:' . $identityPort . '/identity';
$internalOrigin = 'http://127.0.0.1:' . $internalPort;
$partnerOrigin = 'http://localhost:' . $partnerPort;
$tempRoot = sys_get_temp_dir() . '/funadmin-phase9-' . bin2hex(random_bytes(12));
$shadowRoot = $tempRoot . '/http-root';
$environmentPath = $shadowRoot . '/.env.testing';
$sessionDirectory = $shadowRoot . '/runtime/session';
$keyDirectory = $tempRoot . '/keys';
$routerPath = $tempRoot . '/identity-router.php';
$statePath = $tempRoot . '/state.json';
$controlPath = $tempRoot . '/control.json';
$controlResultPath = $tempRoot . '/control-result.json';
$internalLogoutFile = $tempRoot . '/internal-logout.json';
$partnerLogoutFile = $tempRoot . '/partner-logout.json';
$logs = [$tempRoot . '/idp.log', $tempRoot . '/internal.log', $tempRoot . '/partner.log'];
$processes = [null, null, null];
$serve = in_array('--serve', $argv, true);
$stopRequested = false;
$steps = [];
$jars = [];
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use (&$stopRequested): void { $stopRequested = true; });
    pcntl_signal(SIGTERM, static function () use (&$stopRequested): void { $stopRequested = true; });
}
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);

try {
    phase9Expect(mkdir($tempRoot, 0700, true) && mkdir($shadowRoot, 0700) && mkdir($sessionDirectory, 0700, true) && mkdir($keyDirectory, 0700), '无法创建单次随机 temp root');
    $server->execute('CREATE DATABASE ' . phase9Quote($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $database;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    (new MigrationService())->runDirectory($root . '/database/migrations', 'core');

    Db::execute('INSERT INTO fun_identity_sso_config (tenant_id,enabled,provider_mode,issuer,external_identity_enabled,backchannel_logout_enabled,created_at,updated_at) VALUES (1,1,\'identity_provider\',?,0,1,NOW(),NOW())', [$issuer]);
    Db::execute("INSERT INTO fun_identity_user (tenant_id,public_id,realm,source_id,username,display_name,email,locale,status,created_at,updated_at) VALUES (1,'90000000-0000-4000-8000-000000000001','fixture',990001,'phase9-user','Phase9 User','phase9@example.test','zh-CN',1,NOW(),NOW())");
    $userId = (int) Db::query("SELECT id FROM fun_identity_user WHERE realm='fixture' AND source_id=990001")[0]['id'];
    $credentials = new IdentityCredentialService();
    $credentials->syncHash(1, $userId, $credentials->hash('Phase9-Test-Password!'));

    $catalog = new ApplicationCatalogService();
    $internalApp = $catalog->save(1, ['code' => 'phase9-internal', 'name' => 'Internal Workspace', 'runtimeType' => 'internal', 'launchUrl' => $internalOrigin], development: true, sameOriginHost: '127.0.0.1');
    $partnerApp = $catalog->save(1, ['code' => 'phase9-partner', 'name' => 'Partner Standalone Portal', 'runtimeType' => 'standalone', 'launchUrl' => $partnerOrigin], development: true);
    $machineApp = $catalog->save(1, ['code' => 'phase9-machine', 'name' => 'Phase9 Machine', 'runtimeType' => 'internal', 'launchUrl' => '/machine'], development: true, sameOriginHost: '127.0.0.1');
    foreach ([$internalApp, $partnerApp, $machineApp] as $application) $catalog->publish(1, (int) $application['id']);
    Db::execute("UPDATE fun_enterprise_application SET brand_config=? WHERE id IN (?,?)", [json_encode(['name' => 'Northstar Identity', 'mark' => 'NI', 'headline' => '一个身份，连接内部与伙伴应用。', 'description' => 'Phase9 独立 Identity Provider 跨域授权旅程。'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $internalApp['id'], $partnerApp['id']]);

    $clients = new OAuthClientService();
    $internal = $clients->save(1, (int) $internalApp['id'], ['name' => 'Internal First Party RP', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid', 'profile']]);
    $partner = $clients->save(1, (int) $partnerApp['id'], ['name' => 'Partner Standalone RP', 'clientType' => 'confidential', 'grants' => ['authorization_code'], 'scopes' => ['openid', 'profile', 'email']]);
    $machine = $clients->save(1, (int) $machineApp['id'], ['name' => 'Machine Client', 'clientType' => 'machine', 'grants' => ['client_credentials'], 'scopes' => ['identity.read']]);
    $secrets = new ClientSecretService();
    $partnerSecret = $secrets->rotate(1, (int) $partner['id'])['secret'];
    $machineSecret = $secrets->rotate(1, (int) $machine['id'])['secret'];
    $redirects = new RedirectUriService();
    $redirects->replace(1, (int) $internal['id'], [['uri' => $internalOrigin . '/callback'], ['uri' => $internalOrigin . '/post-logout', 'type' => 'post_logout']], true);
    $redirects->replace(1, (int) $partner['id'], [['uri' => $partnerOrigin . '/callback'], ['uri' => $partnerOrigin . '/post-logout', 'type' => 'post_logout']], true);
    Db::execute('UPDATE fun_oauth_client SET backchannel_logout_uri=? WHERE id=?', ['https://example.com/internal-backchannel', $internal['id']]);
    Db::execute('UPDATE fun_oauth_client SET backchannel_logout_uri=? WHERE id=?', ['https://example.com/partner-backchannel', $partner['id']]);
    (new SigningKeyService($keyDirectory))->rotate(1, 3600);

    $mysql = $original['connections']['mysql'];
    $environmentFile = implode("\n", ['APP_DEBUG = true', 'ENV_NAME = testing', 'PHP_APP_ENV = testing', 'APP_ENV = testing', 'DB_TYPE = ' . $mysql['type'], 'DB_HOST = ' . $mysql['hostname'], 'DB_NAME = ' . $database, 'DB_USER = "' . addcslashes((string) $mysql['username'], "\\\"") . '"', 'DB_PASS = "' . addcslashes((string) $mysql['password'], "\\\"") . '"', 'DB_PORT = ' . $mysql['hostport'], 'DB_CHARSET = ' . $mysql['charset'], 'DB_PREFIX = ' . $mysql['prefix'], 'IDENTITY_ISSUER = ' . $issuer, 'OIDC_SUBJECT_PEPPER = phase9-pepper']) . "\n";
    foreach (['app', 'config', 'extend', 'plugins', 'public', 'route', 'vendor'] as $link) phase9Expect(symlink($root . '/' . $link, $shadowRoot . '/' . $link), '无法创建临时目录链接：' . $link);
    phase9Expect(file_put_contents($environmentPath, $environmentFile, LOCK_EX) !== false && chmod($environmentPath, 0600), '无法创建受限临时 env');
    $controlToken = bin2hex(random_bytes(32));
    $router = '<?php $_ENV["ENV_NAME"]=$_SERVER["ENV_NAME"]="testing";$_ENV["PHP_APP_ENV"]=$_SERVER["PHP_APP_ENV"]="testing";$_ENV["APP_ENV"]=$_SERVER["APP_ENV"]="testing";$remote=(string)($_SERVER["REMOTE_ADDR"]??"");if(!in_array($remote,["127.0.0.1","::1"],true)){http_response_code(403);exit("loopback only");}$path=parse_url($_SERVER["REQUEST_URI"]??"/",PHP_URL_PATH);$token=' . var_export($controlToken, true) . ';if($path==="/__phase9/captcha"&&hash_equals($token,(string)($_GET["token"]??""))){$sid=(string)($_COOKIE["PHPSESSID"]??"");$file=' . var_export($sessionDirectory, true) . ' . "/sess_" . preg_replace("/[^a-zA-Z0-9,-]/","",$sid);$data=is_file($file)?unserialize((string)file_get_contents($file),["allowed_classes"=>false]):[];if(!is_array($data))$data=[];$data["captcha"]=["key"=>password_hash("phase9",PASSWORD_BCRYPT,["cost"=>4])];file_put_contents($file,serialize($data),LOCK_EX);header("Content-Type: application/json");echo "{\"status\":\"seeded\"}";return;}if($path==="/__phase9/control"&&hash_equals($token,(string)($_GET["token"]??""))){$action=(string)($_GET["action"]??"status");if(!in_array($action,["status","revoke","dispatch","cleanup"],true)){http_response_code(400);exit("invalid action");}$command=' . var_export($controlPath, true) . ';$result=' . var_export($controlResultPath, true) . ';@unlink($result);file_put_contents($command,json_encode(["action"=>$action,"nonce"=>bin2hex(random_bytes(8))]),LOCK_EX);chmod($command,0600);for($i=0;$i<100&&!is_file($result);$i++)usleep(50000);header("Content-Type: application/json");if(!is_file($result)){http_response_code(504);echo "{\"error\":\"control timeout\"}";}else{echo file_get_contents($result);@unlink($result);}return;}require_once ' . var_export($root . '/vendor/autoload.php', true) . ';require_once ' . var_export($root . '/app/common/functions/plugin.php', true) . ';$_SERVER["SCRIPT_NAME"]="/index.php";$_SERVER["SCRIPT_FILENAME"]=' . var_export($root . '/public/index.php', true) . ';$http=(new \\think\\App(' . var_export($shadowRoot . '/', true) . '))->http;$response=$http->run();$response->send();$http->end($response);';
    phase9Expect(file_put_contents($routerPath, $router, LOCK_EX) !== false && chmod($routerPath, 0600), '无法创建受限 Identity router');
    $baseEnvironment = array_merge($_ENV, ['PATH' => '/opt/homebrew/opt/php@8.1/bin:/opt/homebrew/bin:/usr/bin:/bin', 'ENV_NAME' => 'testing', 'PHP_APP_ENV' => 'testing', 'APP_ENV' => 'testing', 'PHP_DB_NAME' => $database, 'PHP_IDENTITY_ISSUER' => $issuer, 'PHP_OIDC_SUBJECT_PEPPER' => 'phase9-pepper']);
    $php = PHP_BINARY;
    $processes[0] = phase9Start([$php, '-S', '127.0.0.1:' . $identityPort, '-t', $root . '/public', $routerPath], $root, $baseEnvironment, $logs[0]);
    $fixture = $root . '/tests/fixtures/identity-phase9-rp.php';
    $sessionSuffix = strtoupper(bin2hex(random_bytes(4)));
    $internalEnvironment = array_merge($_ENV, ['RP_SESSION_NAME' => 'PHASE9INTERNAL' . $sessionSuffix, 'RP_ISSUER' => $issuer, 'RP_CLIENT_ID' => $internal['client_id'], 'RP_CLIENT_SECRET' => '', 'RP_CALLBACK' => $internalOrigin . '/callback', 'RP_POST_LOGOUT' => $internalOrigin . '/post-logout', 'RP_NAME' => 'Internal Workspace', 'RP_KIND' => 'internal', 'RP_RUNTIME_FILE' => $internalLogoutFile]);
    $partnerEnvironment = array_merge($_ENV, ['RP_SESSION_NAME' => 'PHASE9PARTNER' . $sessionSuffix, 'RP_ISSUER' => $issuer, 'RP_CLIENT_ID' => $partner['client_id'], 'RP_CLIENT_SECRET' => $partnerSecret, 'RP_CALLBACK' => $partnerOrigin . '/callback', 'RP_POST_LOGOUT' => $partnerOrigin . '/post-logout', 'RP_NAME' => 'Partner Standalone Portal', 'RP_KIND' => 'partner', 'RP_RUNTIME_FILE' => $partnerLogoutFile]);
    $processes[1] = phase9Start([$php, '-S', '127.0.0.1:' . $internalPort, $fixture], $root, $internalEnvironment, $logs[1]);
    $processes[2] = phase9Start([$php, '-S', '127.0.0.1:' . $partnerPort, $fixture], $root, $partnerEnvironment, $logs[2]);
    phase9Wait($issuer . '/status', $processes[0]);
    phase9Wait($internalOrigin . '/', $processes[1]);
    phase9Wait($partnerOrigin . '/', $processes[2]);

    [$discoveryStatus, $discoveryBody] = phase9Http($jars, $issuer . '/.well-known/openid-configuration');
    $discovery = json_decode($discoveryBody, true, flags: JSON_THROW_ON_ERROR);
    [$jwksStatus, $jwksBody] = phase9Http($jars, (string) $discovery['jwks_uri']);
    $jwks = json_decode($jwksBody, true, flags: JSON_THROW_ON_ERROR);
    phase9Expect($discoveryStatus === 200 && $jwksStatus === 200 && $discovery['issuer'] === $issuer && count($jwks['keys'] ?? []) === 1, 'Discovery/JWKS 不一致');
    [$machineStatus, $machineBody] = phase9Http($jars, $issuer . '/token', 'POST', ['grant_type' => 'client_credentials', 'scope' => 'identity.read'], ['Authorization: Basic ' . base64_encode($machine['client_id'] . ':' . $machineSecret)]);
    $machineToken = json_decode($machineBody, true, flags: JSON_THROW_ON_ERROR);
    phase9Expect($machineStatus === 200 && isset($machineToken['access_token']), 'client_credentials 失败');
    phase9Step($steps, 'discovery_jwks', ['discovery_status' => 200, 'jwks_status' => 200, 'jwks_keys' => 1, 'client_credentials_status' => 200]);

    $internalCallback = $internalOrigin . '/callback';
    [$authorize, $internalProtocol] = phase9StartAuthorization($jars, $internalOrigin . '/login', $internalCallback, (string) $internal['client_id']);
    [$loginHtml, $interactionUrl] = phase9Interaction($jars, $authorize);
    phase9Expect(str_contains($loginHtml, '欢迎回来'), 'internal 首次请求未显示品牌登录');
    phase9Http($jars, phase9Absolute($interactionUrl, '/__phase9/captcha?token=' . $controlToken));
    [$loginStatus, $loginBody, $loginHeaders] = phase9Http($jars, phase9Absolute($interactionUrl, '/identity/interaction/login'), 'POST', ['csrf_token' => phase9Hidden($loginHtml, 'csrf_token'), 'transaction_id' => phase9Hidden($loginHtml, 'transaction_id'), 'username' => 'phase9-user', 'password' => 'Phase9-Test-Password!', 'captcha' => 'phase9']);
    $afterLogin = (string) phase9Header($loginHeaders, 'Location');
    phase9Expect($loginStatus === 302 && $afterLogin !== '', 'Identity 登录失败：HTTP ' . $loginStatus . ' Body=' . substr(strip_tags($loginBody), 0, 240));
    [$autoStatus, , $autoHeaders] = phase9Http($jars, phase9Absolute($interactionUrl, $afterLogin));
    $internalCallbackUrl = (string) phase9Header($autoHeaders, 'Location');
    phase9Expect($autoStatus === 302, 'internal 未自动授权');
    phase9CompleteCallback($jars, $internalCallbackUrl, $internalOrigin);
    phase9Step($steps, 'internal_login', ['pkce' => 'S256', 'state_length' => strlen($internalProtocol['state']), 'nonce_length' => strlen($internalProtocol['nonce']), 'callback_keys' => ['code', 'state'], 'jwks_verified_by' => 'RP fixture']);

    [$partnerAuthorize] = phase9StartAuthorization($jars, $partnerOrigin . '/login', $partnerOrigin . '/callback', (string) $partner['client_id']);
    [$partnerConsent, $partnerInteraction] = phase9Interaction($jars, $partnerAuthorize);
    $partnerCallbackUrl = phase9Consent($jars, $partnerInteraction, $partnerConsent);
    phase9CompleteCallback($jars, $partnerCallbackUrl, $partnerOrigin);
    phase9Step($steps, 'partner_first_consent', ['op_session_reused' => !str_contains($partnerConsent, '欢迎回来'), 'consent_shown' => true]);

    [$expandedAuthorize] = phase9StartAuthorization($jars, $partnerOrigin . '/login?scope=openid%20profile%20email', $partnerOrigin . '/callback', (string) $partner['client_id']);
    [$expandedConsent, $expandedInteraction] = phase9Interaction($jars, $expandedAuthorize);
    phase9Expect(str_contains($expandedConsent, '邮箱地址'), '扩大 scope 未展示 email consent');
    phase9CompleteCallback($jars, phase9Consent($jars, $expandedInteraction, $expandedConsent), $partnerOrigin);
    phase9Step($steps, 'partner_expanded_consent', ['requested_scope' => 'openid profile email', 'consent_shown' => true]);

    (new AuthorizationRevocationService())->revokeUser(1, $userId);
    phase9Expect((int) Db::query('SELECT COUNT(*) AS aggregate FROM fun_identity_consent WHERE tenant_id=1 AND user_id=? AND revoked_at IS NOT NULL', [$userId])[0]['aggregate'] >= 2, 'consent 撤销未持久化');
    phase9Step($steps, 'consent_revoked', ['revoked' => true]);

    [$reconsentAuthorize] = phase9StartAuthorization($jars, $partnerOrigin . '/login?scope=openid%20profile%20email', $partnerOrigin . '/callback', (string) $partner['client_id']);
    [$reconsentHtml, $reconsentInteraction] = phase9Interaction($jars, $reconsentAuthorize);
    phase9CompleteCallback($jars, phase9Consent($jars, $reconsentInteraction, $reconsentHtml), $partnerOrigin);
    phase9Step($steps, 'partner_reconsent', ['consent_shown_after_revoke' => true]);

    [$logoutStatus, , $logoutHeaders] = phase9Http($jars, $partnerOrigin . '/logout-global');
    $logoutUrl = (string) phase9Header($logoutHeaders, 'Location');
    phase9Expect($logoutStatus === 302 && str_contains($logoutUrl, $issuer . '/logout?'), 'RP 未发起 global logout');
    [$opLogoutStatus, , $opLogoutHeaders] = phase9Http($jars, $logoutUrl);
    $postLogout = (string) phase9Header($opLogoutHeaders, 'Location');
    phase9Expect($opLogoutStatus === 302 && str_starts_with($postLogout, $partnerOrigin . '/post-logout'), 'OP global logout 未回跳 RP');
    [$postStatus, $postBody] = phase9Http($jars, $postLogout);
    phase9Expect($postStatus === 200 && str_contains($postBody, '统一登录已安全退出'), 'RP post logout 未清除本地会话');
    phase9Step($steps, 'global_logout', ['op_session_cleared' => true, 'post_logout_token_leak' => str_contains($postLogout, 'token')]);

    $internalClientId = (int) Db::query('SELECT id FROM fun_oauth_client WHERE client_id=?', [$internal['client_id']])[0]['id'];
    $partnerClientId = (int) Db::query('SELECT id FROM fun_oauth_client WHERE client_id=?', [$partner['client_id']])[0]['id'];
    $delivered = phase9DispatchBackchannel($internalClientId, $partnerClientId, $internalOrigin, $partnerOrigin, $jars);
    phase9Expect($delivered >= 2, '未投递两个 RP 的 backchannel logout');
    phase9Step($steps, 'backchannel_dispatch', ['worker' => 'test dispatcher', 'delivered' => $delivered]);

    [, $internalStatusBody] = phase9Http($jars, $internalOrigin . '/');
    [, $partnerStatusBody] = phase9Http($jars, $partnerOrigin . '/');
    phase9Expect(str_contains($internalStatusBody, '当前没有 RP 本地会话') && str_contains($partnerStatusBody, '当前没有 RP 本地会话'), 'global/backchannel 后 RP session 仍存在');
    phase9Step($steps, 'sessions_cleared', ['internal_authenticated' => false, 'partner_authenticated' => false, 'cookie_origins' => array_keys($jars)]);

    if ($serve) {
        $state = ['issuer' => $issuer, 'identity_url' => $issuer, 'internal_url' => $internalOrigin, 'partner_url' => $partnerOrigin, 'username' => 'phase9-user', 'password' => 'Phase9-Test-Password!', 'captcha_seed_url' => $issuer . '/../__phase9/captcha?token=' . $controlToken, 'revoke_url' => $issuer . '/../__phase9/control?token=' . $controlToken . '&action=revoke', 'dispatch_url' => $issuer . '/../__phase9/control?token=' . $controlToken . '&action=dispatch', 'cleanup_url' => $issuer . '/../__phase9/control?token=' . $controlToken . '&action=cleanup'];
        phase9Expect(file_put_contents($statePath, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) !== false && chmod($statePath, 0600), '无法写入受限旅程状态');
        echo json_encode(['status' => 'READY', 'state_file' => $statePath], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        while (!$stopRequested) {
            foreach ($processes as $process) if (!is_resource($process) || proc_get_status($process)['running'] !== true) throw new RuntimeException('Phase9 服务意外退出');
            if (is_file($controlPath)) {
                $command = json_decode((string) file_get_contents($controlPath), true);
                unlink($controlPath);
                $action = (string) ($command['action'] ?? 'status');
                $result = ['ok' => true, 'action' => $action];
                if ($action === 'revoke') (new AuthorizationRevocationService())->revokeUser(1, $userId);
                elseif ($action === 'dispatch') $result['delivered'] = phase9DispatchBackchannel($internalClientId, $partnerClientId, $internalOrigin, $partnerOrigin, $jars);
                elseif ($action === 'cleanup') $stopRequested = true;
                phase9Expect(file_put_contents($controlResultPath, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) !== false && chmod($controlResultPath, 0600), '无法写入受限控制结果');
            }
            usleep(50000);
        }
    }
} catch (Throwable $exception) {
    $log = '';
    foreach ($logs as $path) if (is_file($path)) $log .= "\n== " . basename($path) . " ==\n" . file_get_contents($path);
    throw new RuntimeException($exception->getMessage() . $log, 0, $exception);
} finally {
    foreach ($processes as &$process) phase9Stop($process);
    unset($process);
    $app->config->set($serverConfig, 'database');
    Db::connect('mysql', true)->execute('DROP DATABASE IF EXISTS ' . phase9Quote($database));
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
    if (is_dir($tempRoot)) phase9SafeRemoveTree($tempRoot, $tempRoot);
}

if (!$serve) {
    phase9Expect(!is_dir($tempRoot), '单次随机 temp root 未清理');
    phase9Step($steps, 'temp_root_cleaned', ['path_existed_after_cleanup' => false]);
    echo json_encode(['status' => 'PASS', 'steps' => $steps], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
}
