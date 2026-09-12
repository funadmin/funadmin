<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use app\common\service\identity\ApplicationCatalogService;
use app\common\service\identity\IdentityCredentialService;
use app\common\service\identity\OAuthClientService;
use app\common\service\identity\ApplicationAssignmentService;
use app\common\service\identity\RedirectUriService;
use app\identity\service\NativeIdentitySessionResolver;
use think\App;
use think\facade\Db;
use think\facade\Session;
use think\Request;

function phase5HttpExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase5HttpRequest(string $url, string $method = 'GET', array $form = [], array $headers = [], ?string $cookie = null): array
{
    $headerLines = $headers;
    if ($cookie !== null) $headerLines[] = 'Cookie: ' . $cookie;
    $content = '';
    if ($method === 'POST') {
        $content = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $headerLines[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headerLines), 'content' => $content, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents($url, false, $context);
    phase5HttpExpect(is_string($body), 'HTTP 请求失败：' . $url);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $statusMatch);
    $normalized = [];
    foreach (array_slice($responseHeaders, 1) as $line) {
        if (!str_contains($line, ':')) continue;
        [$name, $value] = explode(':', $line, 2);
        $normalized[strtolower(trim($name))][] = trim($value);
    }
    return [(int) ($statusMatch[1] ?? 0), $normalized, $body];
}

function phase5HttpCookie(array $response): string
{
    $pairs = [];
    foreach ($response[1]['set-cookie'] ?? [] as $header) {
        $pair = explode(';', $header, 2)[0];
        [$name] = explode('=', $pair, 2);
        $pairs[trim($name)] = $pair;
    }
    return implode('; ', $pairs);
}

function phase5HttpCsrf(string $html): string
{
    phase5HttpExpect(preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match) === 1, '页面缺少 CSRF token');
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function phase5HttpAuthorize(string $issuer, string $clientId, string $callback, string $state): string
{
    $verifier = str_repeat('v', 43);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    return $issuer . '/authorize?' . http_build_query(['response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => $callback, 'scope' => 'openid profile', 'state' => $state, 'nonce' => 'phase5-nonce', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'], '', '&', PHP_QUERY_RFC3986);
}

function phase5HttpWait(string $url, $process): void
{
    for ($attempt = 0; $attempt < 50; $attempt++) {
        if (!is_resource($process) || proc_get_status($process)['running'] !== true) throw new RuntimeException('PHP HTTP 服务提前退出');
        if (@file_get_contents($url) !== false) return;
        usleep(100000);
    }
    throw new RuntimeException('PHP HTTP 服务未就绪');
}

function phase5MigrationDirectory(string $source): string
{
    $target = sys_get_temp_dir() . '/funadmin_phase5_http_migrations_' . bin2hex(random_bytes(5));
    phase5HttpExpect(mkdir($target, 0700), '无法创建 migration 目录');
    foreach (glob($source . '/*.sql') ?: [] as $file) {
        $number = (int) substr(basename($file), 0, 3);
        if ($number <= 99 || in_array($number, [104, 108, 111, 113, 114], true)) copy($file, $target . '/' . basename($file));
    }
    return $target;
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$database = 'funadmin_identity_phase5_http_' . bin2hex(random_bytes(5));
$migrations = phase5MigrationDirectory($root . '/database/migrations');
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
$port = random_int(20000, 20999);
$issuer = 'http://127.0.0.1:' . $port . '/identity';
$process = null;
$routerPath = sys_get_temp_dir() . '/funadmin-phase5-http-router-' . bin2hex(random_bytes(4)) . '.php';
$environmentPath = $root . '/.env.testing';
$logPath = sys_get_temp_dir() . '/funadmin-phase5-http-' . bin2hex(random_bytes(4)) . '.log';
$serve = in_array('--serve', $argv, true);
try {
    $server->execute('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $database;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    (new MigrationService())->runDirectory($migrations, 'core');
    Db::execute("INSERT INTO fun_identity_sso_config (tenant_id,enabled,provider_mode,issuer,external_identity_enabled,backchannel_logout_enabled,created_at,updated_at) VALUES (1,1,'identity_provider',?,0,1,NOW(),NOW())", [$issuer]);
    Db::execute("INSERT INTO fun_identity_user (tenant_id,public_id,realm,source_id,username,display_name,email,locale,status,created_at,updated_at) VALUES (1,'50000000-0000-4000-8000-000000000001','fixture',950001,'phase5-user','Phase5 User','phase5@example.test','zh-CN',1,NOW(),NOW())");
    $userId = (int) Db::query("SELECT id FROM fun_identity_user WHERE realm='fixture' AND source_id=950001")[0]['id'];
    $credentials = new IdentityCredentialService();
    $credentials->syncHash(1, $userId, $credentials->hash('Phase5-Test-Password!'));

    $resolveNative = static function (array $session) use ($userId): ?array {
        Session::set('identity.session', array_merge(['tenant_id' => 1, 'user_id' => $userId, 'session_id' => str_repeat('s', 24), 'auth_time' => time(), 'password_version' => 1, 'session_version' => 1], $session));
        return (new NativeIdentitySessionResolver())->resolve(new Request());
    };
    phase5HttpExpect($resolveNative([]) !== null, '版本匹配的 active Identity 用户会话必须有效');
    phase5HttpExpect($resolveNative(['password_version' => 0]) === null && Session::get('identity.session') === null, '密码版本变化必须销毁 Identity 会话');
    phase5HttpExpect($resolveNative(['session_version' => 0]) === null && Session::get('identity.session') === null, '会话撤销必须销毁 Identity 会话');
    Db::execute('UPDATE fun_identity_user SET status=0 WHERE id=?', [$userId]);
    phase5HttpExpect($resolveNative([]) === null && Session::get('identity.session') === null, '禁用用户必须销毁 Identity 会话');
    Db::execute('UPDATE fun_identity_user SET status=1,deleted_at=NOW() WHERE id=?', [$userId]);
    phase5HttpExpect($resolveNative([]) === null && Session::get('identity.session') === null, '已删除用户必须销毁 Identity 会话');
    Db::execute('UPDATE fun_identity_user SET deleted_at=NULL WHERE id=?', [$userId]);
    phase5HttpExpect($resolveNative(['tenant_id' => 999]) === null && Session::get('identity.session') === null, '租户不匹配必须销毁 Identity 会话');
    Session::delete('identity.session');

    $catalog = new ApplicationCatalogService();
    $internalApp = $catalog->save(1, ['code' => 'phase5-internal', 'name' => 'Phase5 Internal', 'runtimeType' => 'internal', 'launchUrl' => '/phase5-internal']);
    $externalApp = $catalog->save(1, ['code' => 'phase5-external', 'name' => 'Phase5 External', 'runtimeType' => 'standalone', 'launchUrl' => 'https://external.example.test']);
    $deniedInternalApp = $catalog->save(1, ['code' => 'phase5-denied-internal', 'name' => 'Denied Internal', 'runtimeType' => 'internal', 'launchUrl' => '/phase5-denied', 'visibility' => 'private']);
    $deniedExternalApp = $catalog->save(1, ['code' => 'phase5-denied-external', 'name' => 'Denied External', 'runtimeType' => 'standalone', 'launchUrl' => 'https://denied.example.test']);
    foreach ([$internalApp, $externalApp, $deniedInternalApp, $deniedExternalApp] as $application) $catalog->publish(1, (int) $application['id']);
    (new ApplicationAssignmentService())->replace(1, (int) $deniedExternalApp['id'], [['subjectType' => 'user', 'subjectId' => $userId, 'effect' => 'deny']]);
    $maliciousBrand = json_encode(['name' => '<script>window.phase5Xss=1</script>', 'mark' => '<img src=x onerror=window.phase5Xss=2>', 'headline' => '安全 <svg onload=window.phase5Xss=3>', 'description' => '描述 & <b>不可执行</b>'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    Db::execute('UPDATE fun_enterprise_application SET brand_config=? WHERE id IN (?,?)', [$maliciousBrand, $internalApp['id'], $externalApp['id']]);
    $clients = new OAuthClientService();
    $internal = $clients->save(1, (int) $internalApp['id'], ['name' => 'Internal', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid', 'profile']]);
    $external = $clients->save(1, (int) $externalApp['id'], ['name' => 'External', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid', 'profile']]);
    $deniedInternal = $clients->save(1, (int) $deniedInternalApp['id'], ['name' => 'Denied Internal', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid', 'profile']]);
    $deniedExternal = $clients->save(1, (int) $deniedExternalApp['id'], ['name' => 'Denied External', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid', 'profile']]);
    $internalCallback = 'http://127.0.0.1:' . $port . '/callback/internal';
    $externalCallback = 'http://127.0.0.1:' . $port . '/callback/external';
    $deniedInternalCallback = 'http://127.0.0.1:' . $port . '/callback/denied-internal';
    $deniedExternalCallback = 'http://127.0.0.1:' . $port . '/callback/denied-external';
    (new RedirectUriService())->replace(1, (int) $internal['id'], [['uri' => $internalCallback]], true);
    (new RedirectUriService())->replace(1, (int) $external['id'], [['uri' => $externalCallback]], true);
    (new RedirectUriService())->replace(1, (int) $deniedInternal['id'], [['uri' => $deniedInternalCallback]], true);
    (new RedirectUriService())->replace(1, (int) $deniedExternal['id'], [['uri' => $deniedExternalCallback]], true);
    $mysql = $original['connections']['mysql'];
    $environmentLines = ['APP_DEBUG = true', 'APP_ENV = testing', 'DB_TYPE = ' . $mysql['type'], 'DB_HOST = ' . $mysql['hostname'], 'DB_NAME = ' . $database, 'DB_USER = "' . addcslashes((string) $mysql['username'], "\\\"") . '"', 'DB_PASS = "' . addcslashes((string) $mysql['password'], "\\\"") . '"', 'DB_PORT = ' . $mysql['hostport'], 'DB_CHARSET = ' . $mysql['charset'], 'DB_PREFIX = ' . $mysql['prefix'], 'IDENTITY_ISSUER = ' . $issuer];
    $environmentFile = implode("\n", $environmentLines) . "\n";
    phase5HttpExpect(file_put_contents($environmentPath, $environmentFile) !== false, '无法创建独立 HTTP 环境配置');
    $routerContent = '<?php $_SERVER["SCRIPT_NAME"] = "/index.php"; $_SERVER["SCRIPT_FILENAME"] = ' . var_export($root . '/public/index.php', true) . '; $_ENV[\'ENV_NAME\'] = \'testing\'; require $_SERVER["SCRIPT_FILENAME"];';
    phase5HttpExpect(file_put_contents($routerPath, $routerContent) !== false, '无法创建独立 HTTP router');
    $environment = array_merge($_ENV, ['PATH' => '/opt/homebrew/opt/php@8.1/bin:/opt/homebrew/bin:/usr/bin:/bin', 'PHP_APP_ENV' => 'testing', 'PHP_DB_NAME' => $database, 'PHP_IDENTITY_ISSUER' => $issuer]);
    unset($environment['PHP_OAUTH_IDENTITY_SESSION_RESOLVER']);
    $process = proc_open(['/opt/homebrew/opt/php@8.1/bin/php', '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $routerPath], [0 => ['pipe', 'r'], 1 => ['file', $logPath, 'a'], 2 => ['file', $logPath, 'a']], $pipes, $root, $environment);
    phase5HttpExpect(is_resource($process), '无法启动独立 PHP 8.1 服务');
    phase5HttpWait($issuer . '/status', $process);

    $internalAuthorize = phase5HttpAuthorize($issuer, $internal['client_id'], $internalCallback, 'internal-state');
    $externalAuthorize = phase5HttpAuthorize($issuer, $external['client_id'], $externalCallback, 'external-state');
    if ($serve) {
        echo json_encode(['base_url' => 'http://127.0.0.1:' . $port, 'issuer' => $issuer, 'internal_authorize' => $internalAuthorize, 'external_authorize' => $externalAuthorize, 'username' => 'phase5-user', 'password' => 'Phase5-Test-Password!', 'user_id' => $userId], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        while (is_resource($process) && proc_get_status($process)['running'] === true) sleep(1);
        exit(0);
    }

    $browserAuthorize = phase5HttpRequest($internalAuthorize, 'GET', [], ['Accept: text/html']);
    phase5HttpExpect($browserAuthorize[0] === 302 && str_contains($browserAuthorize[1]['location'][0] ?? '', '/identity/interaction?transaction_id='), '浏览器 authorize 必须进入交互页面，status=' . $browserAuthorize[0] . ' headers=' . json_encode($browserAuthorize[1], JSON_UNESCAPED_UNICODE) . ' body=' . $browserAuthorize[2]);
    $login = phase5HttpRequest('http://127.0.0.1:' . $port . ($browserAuthorize[1]['location'][0] ?? ''), 'GET', [], ['Accept: text/html']);
    phase5HttpExpect($login[0] === 200 && str_contains($login[2], '欢迎回来'), '未登录必须显示品牌登录页面');
    phase5HttpExpect(str_contains($login[2], '&lt;script&gt;') && !str_contains($login[2], '<script>window.phase5Xss'), '品牌配置必须 HTML 转义');
    foreach (['content-security-policy', 'cache-control', 'pragma', 'x-content-type-options', 'x-frame-options'] as $header) phase5HttpExpect(isset($login[1][$header]), '登录页面缺少安全响应头：' . $header);
    phase5HttpExpect(in_array('no-store', $login[1]['cache-control'], true), '登录页面必须 no-store');
    $loginCookie = phase5HttpCookie($login);
    phase5HttpExpect($loginCookie !== '', '默认 Native resolver 登录流程必须建立 session Cookie');

    $sessionId = '';
    foreach (explode('; ', $loginCookie) as $cookiePair) {
        if (str_starts_with($cookiePair, 'PHPSESSID=')) $sessionId = substr($cookiePair, strlen('PHPSESSID='));
    }
    $sessionPath = $root . '/runtime/session/sess_' . $sessionId;
    $sessionData = is_file($sessionPath) ? unserialize((string) file_get_contents($sessionPath)) : null;
    phase5HttpExpect(is_array($sessionData), '无法读取真实登录 session');
    $sessionData['captcha'] = ['key' => password_hash('phase5', PASSWORD_BCRYPT, ['cost' => 4])];
    phase5HttpExpect(file_put_contents($sessionPath, serialize($sessionData), LOCK_EX) !== false, '无法准备真实验证码 session');
    parse_str((string) parse_url($browserAuthorize[1]['location'][0] ?? '', PHP_URL_QUERY), $browserQuery);
    $realLogin = phase5HttpRequest($issuer . '/interaction/login', 'POST', ['transaction_id' => (string) ($browserQuery['transaction_id'] ?? ''), 'csrf_token' => phase5HttpCsrf($login[2]), 'username' => 'phase5-user', 'password' => 'Phase5-Test-Password!', 'captcha' => 'phase5'], [], $loginCookie);
    phase5HttpExpect($realLogin[0] === 302 && str_contains($realLogin[1]['location'][0] ?? '', '/identity/interaction?transaction_id='), '默认配置必须完成真实 Identity 登录');
    $authenticatedCookie = phase5HttpCookie($realLogin);
    phase5HttpExpect($authenticatedCookie !== '', '登录成功后必须轮换 session Cookie');
    $renderAfterLogin = phase5HttpRequest('http://127.0.0.1:' . $port . ($realLogin[1]['location'][0] ?? ''), 'GET', [], ['Accept: text/html'], $authenticatedCookie);
    phase5HttpExpect($renderAfterLogin[0] === 302 && str_starts_with($renderAfterLogin[1]['location'][0] ?? '', $internalCallback . '?code='), 'render 必须通过默认 Native resolver 识别 session 并自动签发 code');

    $invalid = phase5HttpRequest(str_replace(rawurlencode($internalCallback), rawurlencode('https://attacker.invalid/callback'), $internalAuthorize), 'GET', [], ['Accept: text/html']);
    phase5HttpExpect($invalid[0] === 400 && !isset($invalid[1]['location']), '非法 redirect_uri 不得重定向');

    $jsonAuth = phase5HttpRequest($externalAuthorize, 'GET', [], ['Accept: application/json']);
    phase5HttpExpect($jsonAuth[0] === 200, 'JSON authorize 必须创建事务');
    $json = json_decode($jsonAuth[2], true, flags: JSON_THROW_ON_ERROR);
    $transactionId = (string) ($json['transaction_id'] ?? '');
    $fixtureHeaders = [];
    $cookie = $authenticatedCookie;

    $deniedInternalAuth = phase5HttpRequest(phase5HttpAuthorize($issuer, $deniedInternal['client_id'], $deniedInternalCallback, 'denied-internal'), 'GET', [], ['Accept: application/json']);
    $deniedInternalJson = json_decode($deniedInternalAuth[2], true, flags: JSON_THROW_ON_ERROR);
    $deniedInternalPage = phase5HttpRequest('http://127.0.0.1:' . $port . $deniedInternalJson['interaction_url'], 'GET', [], $fixtureHeaders, $cookie);
    phase5HttpExpect($deniedInternalPage[0] === 302 && str_contains($deniedInternalPage[1]['location'][0] ?? '', 'error=access_denied') && !str_contains($deniedInternalPage[1]['location'][0] ?? '', 'code='), 'private 且无 assignment 的内部应用必须拒绝且不得签发 code');
    $deniedInternalTransaction = Db::query('SELECT status,interaction_consumed_at FROM fun_oauth_authorization WHERE transaction_hash=?', [hash('sha256', (string) $deniedInternalJson['transaction_id'])])[0];
    phase5HttpExpect($deniedInternalTransaction['status'] === 'denied' && $deniedInternalTransaction['interaction_consumed_at'] !== null, '内部准入拒绝必须结束授权事务');

    $deniedExternalAuth = phase5HttpRequest(phase5HttpAuthorize($issuer, $deniedExternal['client_id'], $deniedExternalCallback, 'denied-external'), 'GET', [], ['Accept: application/json']);
    $deniedExternalJson = json_decode($deniedExternalAuth[2], true, flags: JSON_THROW_ON_ERROR);
    $deniedExternalPage = phase5HttpRequest('http://127.0.0.1:' . $port . $deniedExternalJson['interaction_url'], 'GET', [], $fixtureHeaders, $cookie);
    phase5HttpExpect($deniedExternalPage[0] === 302 && str_contains($deniedExternalPage[1]['location'][0] ?? '', 'error=access_denied') && !str_contains($deniedExternalPage[1]['location'][0] ?? '', 'code='), '外部应用 deny 必须在展示 consent 前拒绝');

    $consent = phase5HttpRequest('http://127.0.0.1:' . $port . $json['interaction_url'], 'GET', [], $fixtureHeaders, $cookie);
    phase5HttpExpect($consent[0] === 200 && str_contains($consent[2], '确认授权'), '外部应用必须显示 consent 页面');
    $cookie = phase5HttpCookie($consent);
    phase5HttpExpect($cookie !== '', 'Identity 页面必须建立 PHP session Cookie');
    $cookieHeaders = implode('; ', $consent[1]['set-cookie'] ?? []);
    phase5HttpExpect(str_contains(strtolower($cookieHeaders), 'httponly'), 'PHP session Cookie 必须 HttpOnly');
    phase5HttpExpect(str_contains(strtolower($cookieHeaders), 'samesite=lax'), 'PHP session Cookie 必须 SameSite=Lax');
    phase5HttpExpect(!str_contains(strtolower($cookieHeaders), 'identity_session='), '不得发送无效的 identity_session 伪 Cookie');

    (new ApplicationAssignmentService())->replace(1, (int) $externalApp['id'], [['subjectType' => 'user', 'subjectId' => $userId, 'effect' => 'deny']]);
    $deniedDecision = phase5HttpRequest($issuer . '/interaction/consent', 'POST', ['transaction_id' => $transactionId, 'csrf_token' => phase5HttpCsrf($consent[2]), 'approved' => '1'], $fixtureHeaders, $cookie);
    phase5HttpExpect($deniedDecision[0] === 302 && str_contains($deniedDecision[1]['location'][0] ?? '', 'error=access_denied') && !str_contains($deniedDecision[1]['location'][0] ?? '', 'code='), 'consent 展示后新增 deny 仍必须在 decision 阶段阻断');
    phase5HttpExpect((int) Db::query('SELECT COUNT(*) AS total FROM fun_oauth_authorization_code c JOIN fun_oauth_authorization a ON a.id=c.authorization_id WHERE a.transaction_hash=?', [hash('sha256', $transactionId)])[0]['total'] === 0, 'decision 准入拒绝不得创建 authorization code');
    (new ApplicationAssignmentService())->replace(1, (int) $externalApp['id'], []);

    $successAuth = phase5HttpRequest($externalAuthorize, 'GET', [], ['Accept: application/json']);
    $successJson = json_decode($successAuth[2], true, flags: JSON_THROW_ON_ERROR);
    $transactionId = (string) $successJson['transaction_id'];
    $consent = phase5HttpRequest('http://127.0.0.1:' . $port . $successJson['interaction_url'], 'GET', [], $fixtureHeaders, $cookie);
    $decision = phase5HttpRequest($issuer . '/interaction/consent', 'POST', ['transaction_id' => $transactionId, 'csrf_token' => phase5HttpCsrf($consent[2]), 'approved' => '1'], $fixtureHeaders, $cookie);
    phase5HttpExpect($decision[0] === 302 && str_starts_with($decision[1]['location'][0] ?? '', $externalCallback . '?code='), 'consent 必须重定向已注册 callback 并签发 code');
    $csrfRefresh = phase5HttpRequest($issuer . '/csrf', 'GET', [], $fixtureHeaders, $cookie);
    phase5HttpExpect($csrfRefresh[0] === 200, 'transaction replay 测试必须能够刷新 CSRF token');
    $replayToken = (string) (json_decode($csrfRefresh[2], true, flags: JSON_THROW_ON_ERROR)['csrf_token'] ?? '');
    $replay = phase5HttpRequest($issuer . '/interaction/consent', 'POST', ['transaction_id' => $transactionId, 'csrf_token' => $replayToken, 'approved' => '1'], $fixtureHeaders, $cookie);
    phase5HttpExpect($replay[0] === 400 && !isset($replay[1]['location']), 'transaction replay 必须在有效 CSRF 下拒绝且不得重定向');

    $invalidResolverAuth = phase5HttpRequest($externalAuthorize, 'GET', [], ['Accept: application/json']);
    $invalidResolverJson = json_decode($invalidResolverAuth[2], true, flags: JSON_THROW_ON_ERROR);
    proc_terminate($process);
    proc_close($process);
    $process = null;
    $invalidResolver = 'Sensitive\\Resolver\\SecretValue';
    phase5HttpExpect(file_put_contents($environmentPath, implode("\n", array_merge($environmentLines, ['APP_DEBUG = false', 'OAUTH_IDENTITY_SESSION_RESOLVER = ' . $invalidResolver])) . "\n") !== false, '无法写入非法 resolver 测试配置');
    $process = proc_open(['/opt/homebrew/opt/php@8.1/bin/php', '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $routerPath], [0 => ['pipe', 'r'], 1 => ['file', $logPath, 'a'], 2 => ['file', $logPath, 'a']], $pipes, $root, $environment);
    phase5HttpExpect(is_resource($process), '无法重启非法 resolver HTTP 服务');
    phase5HttpWait($issuer . '/status', $process);
    $configurationError = phase5HttpRequest('http://127.0.0.1:' . $port . $invalidResolverJson['interaction_url'], 'GET', [], ['Accept: text/html']);
    phase5HttpExpect($configurationError[0] === 500 && !str_contains($configurationError[2], $invalidResolver), 'Identity 非法 resolver class 必须明确返回不泄露配置的 500/local error，status=' . $configurationError[0]);
    $oauthConfigurationError = phase5HttpRequest($issuer . '/decision', 'POST', ['transaction_id' => $invalidResolverJson['transaction_id'], 'approved' => '1']);
    phase5HttpExpect($oauthConfigurationError[0] === 500 && !str_contains($oauthConfigurationError[2], $invalidResolver), 'OAuth 非法 resolver class 必须明确返回不泄露配置的 500/local error');
    echo "identity phase5 HTTP E2E tests passed\n";
} catch (Throwable $exception) {
    $serverLog = is_file($logPath) ? (string) file_get_contents($logPath) : '';
    throw new RuntimeException($exception->getMessage() . "\nPHP HTTP server log:\n" . $serverLog, 0, $exception);
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        proc_close($process);
    }
    foreach (glob($migrations . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($migrations)) rmdir($migrations);
    if (is_file($logPath)) unlink($logPath);
    if (is_file($routerPath)) unlink($routerPath);
    if (is_file($environmentPath)) unlink($environmentPath);
    $app->config->set($serverConfig, 'database');
    Db::connect('mysql', true)->execute('DROP DATABASE IF EXISTS `' . $database . '`');
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
