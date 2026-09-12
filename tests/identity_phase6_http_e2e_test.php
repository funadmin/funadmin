<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/common/functions/plugin.php';

use app\common\service\MigrationService;
use app\identity\service\OpaqueTokenService;
use think\App;
use think\facade\Db;

function phase6HttpExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase6HttpRequest(string $url, array $headers = []): array
{
    $context = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $body = file_get_contents($url, false, $context);
    phase6HttpExpect(is_string($body), 'HTTP 请求失败：' . $url);
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

function phase6HttpWait(string $url, $process): void
{
    for ($attempt = 0; $attempt < 50; $attempt++) {
        if (!is_resource($process) || proc_get_status($process)['running'] !== true) throw new RuntimeException('PHP HTTP 服务提前退出');
        if (@file_get_contents($url) !== false) return;
        usleep(100000);
    }
    throw new RuntimeException('PHP HTTP 服务未就绪');
}

function phase6HttpJson(array $response, int $status): array
{
    phase6HttpExpect($response[0] === $status, 'HTTP 状态不符：' . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $decoded = json_decode($response[2], true, flags: JSON_THROW_ON_ERROR);
    phase6HttpExpect(is_array($decoded), 'HTTP JSON 响应无效');
    return $decoded;
}

function phase6HttpExpectInvalid(string $issuer, string $token, string $message): void
{
    $response = phase6HttpRequest($issuer . '/resource/identity', ['Authorization: Bearer ' . $token]);
    phase6HttpExpect($response[0] === 401 && str_contains($response[1]['www-authenticate'][0] ?? '', 'invalid_token'), $message);
}

function phase6HttpMigrationDirectory(string $source): string
{
    $target = sys_get_temp_dir() . '/funadmin_phase6_http_migrations_' . bin2hex(random_bytes(5));
    phase6HttpExpect(mkdir($target, 0700), '无法创建 migration 目录');
    foreach (glob($source . '/*.sql') ?: [] as $file) {
        $number = (int) substr(basename($file), 0, 3);
        if ($number <= 99 || in_array($number, [104, 114], true) || basename($file) === '108_identity_oidc_claims.sql') copy($file, $target . '/' . basename($file));
    }
    return $target;
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$database = 'funadmin_identity_phase6_http_' . bin2hex(random_bytes(5));
$migrations = phase6HttpMigrationDirectory($root . '/database/migrations');
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
$port = random_int(21000, 21999);
$issuer = 'http://127.0.0.1:' . $port . '/identity';
$process = null;
$routerPath = sys_get_temp_dir() . '/funadmin-phase6-http-router-' . bin2hex(random_bytes(4)) . '.php';
$environmentPath = $root . '/.env.testing';
$logPath = sys_get_temp_dir() . '/funadmin-phase6-http-' . bin2hex(random_bytes(4)) . '.log';
try {
    $server->execute('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $database;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    (new MigrationService())->runDirectory($migrations, 'core');
    Db::execute("INSERT INTO fun_identity_sso_config (tenant_id,enabled,provider_mode,issuer,external_identity_enabled,backchannel_logout_enabled,created_at,updated_at) VALUES (1,1,'identity_provider',?,0,1,NOW(),NOW())", [$issuer]);
    Db::execute("INSERT INTO fun_identity_user (tenant_id,public_id,realm,source_id,username,display_name,email,mobile,locale,status,created_at,updated_at) VALUES (1,'62000000-0000-4000-8000-000000000001','fixture',962001,'phase6-http','Phase6 HTTP','phase6-http@example.test','13900000006','zh-CN',1,NOW(),NOW())");
    $userId = (int) Db::query("SELECT id FROM fun_identity_user WHERE realm='fixture' AND source_id=962001")[0]['id'];
    Db::execute("INSERT INTO fun_enterprise_application (tenant_id,public_id,code,name,runtime_type,launch_url,status,created_at,updated_at) VALUES (1,'63000000-0000-4000-8000-000000000001','phase6-http','Phase6 HTTP','internal','/resource','published',NOW(),NOW())");
    $applicationId = (int) Db::query("SELECT id FROM fun_enterprise_application WHERE code='phase6-http'")[0]['id'];
    Db::execute("INSERT INTO fun_oauth_client (tenant_id,application_id,client_id,name,client_type,token_endpoint_auth_method,require_pkce,subject_type,sector_identifier,status,created_at,updated_at) VALUES (1,?,'phase6-http-client','HTTP','machine','client_secret_basic',0,'public',NULL,'active',NOW(),NOW()),(1,?,'phase6-http-pair-a','Pair A','confidential','client_secret_basic',0,'pairwise','sector-a.example','active',NOW(),NOW()),(1,?,'phase6-http-pair-b','Pair B','confidential','client_secret_basic',0,'pairwise','sector-b.example','active',NOW(),NOW())", [$applicationId, $applicationId, $applicationId]);
    $clientId = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase6-http-client'")[0]['id'];
    $pairAId = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase6-http-pair-a'")[0]['id'];
    $pairBId = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase6-http-pair-b'")[0]['id'];
    $scopeId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=1 AND name='identity.read'")[0]['id'];
    $openidId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=1 AND name='openid'")[0]['id'];
    $emailId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=1 AND name='email'")[0]['id'];
    Db::execute("INSERT INTO fun_member (username,password,nickname,level_id,status,created_at,updated_at) VALUES ('phase6-http-member','unused','Phase6 HTTP Member',1,1,NOW(),NOW())");
    $memberId = (int) Db::query("SELECT id FROM fun_member WHERE username='phase6-http-member'")[0]['id'];
    Db::execute('INSERT INTO fun_identity_member_link (tenant_id,user_id,member_id,created_at,updated_at) VALUES (1,?,?,NOW(),NOW())', [$userId, $memberId]);
    $tokens = new OpaqueTokenService();
    $userToken = $tokens->issue(1, $clientId, $userId, [$scopeId])['access_token'];
    $clientToken = $tokens->issue(1, $clientId, null, [$scopeId])['access_token'];
    $wrongScopeToken = $tokens->issue(1, $clientId, $userId, [$openidId])['access_token'];
    $openidToken = $tokens->issue(1, $clientId, $userId, [$openidId])['access_token'];
    $emailToken = $tokens->issue(1, $clientId, $userId, [$openidId, $emailId])['access_token'];
    $pairAToken = $tokens->issue(1, $pairAId, $userId, [$openidId])['access_token'];
    $pairARepeatToken = $tokens->issue(1, $pairAId, $userId, [$openidId])['access_token'];
    $pairBToken = $tokens->issue(1, $pairBId, $userId, [$openidId])['access_token'];

    $mysql = $original['connections']['mysql'];
    $environmentFile = implode("\n", [
        'APP_DEBUG = false',
        'APP_ENV = testing',
        'DB_TYPE = ' . $mysql['type'],
        'DB_HOST = ' . $mysql['hostname'],
        'DB_NAME = ' . $database,
        'DB_USER = "' . addcslashes((string) $mysql['username'], "\\\"") . '"',
        'DB_PASS = "' . addcslashes((string) $mysql['password'], "\\\"") . '"',
        'DB_PORT = ' . $mysql['hostport'],
        'DB_CHARSET = ' . $mysql['charset'],
        'DB_PREFIX = ' . $mysql['prefix'],
        'IDENTITY_ISSUER = ' . $issuer,
        'OIDC_SUBJECT_PEPPER = phase6-http-pepper',
    ]) . "\n";
    phase6HttpExpect(file_put_contents($environmentPath, $environmentFile) !== false, '无法创建 HTTP 环境配置');
    $routerContent = '<?php require_once ' . var_export($root . '/app/common/functions/plugin.php', true) . '; $_SERVER["SCRIPT_NAME"] = "/index.php"; $_SERVER["SCRIPT_FILENAME"] = ' . var_export($root . '/public/index.php', true) . '; $_ENV[\'ENV_NAME\'] = \'testing\'; require $_SERVER["SCRIPT_FILENAME"];';
    phase6HttpExpect(file_put_contents($routerPath, $routerContent) !== false, '无法创建 HTTP router');
    $environment = array_merge($_ENV, ['PATH' => '/opt/homebrew/opt/php@8.1/bin:/opt/homebrew/bin:/usr/bin:/bin','PHP_APP_ENV' => 'testing','PHP_DB_NAME' => $database,'PHP_IDENTITY_ISSUER' => $issuer,'PHP_OIDC_SUBJECT_PEPPER' => 'phase6-http-pepper']);
    $process = proc_open(['/opt/homebrew/opt/php@8.1/bin/php','-S','127.0.0.1:' . $port,'-t',$root . '/public',$routerPath], [0 => ['pipe','r'],1 => ['file',$logPath,'a'],2 => ['file',$logPath,'a']], $pipes, $root, $environment);
    phase6HttpExpect(is_resource($process), '无法启动 PHP HTTP 服务');
    phase6HttpWait($issuer . '/status', $process);

    $missing = phase6HttpRequest($issuer . '/resource/identity');
    phase6HttpExpect($missing[0] === 401 && str_contains($missing[1]['www-authenticate'][0] ?? '', 'invalid_token'), '缺少 bearer 必须 401 challenge');
    foreach (['Bearer','Basic abc','Bearer a b','Bearer abc, Bearer def'] as $header) {
        $malformed = phase6HttpRequest($issuer . '/resource/identity', ['Authorization: ' . $header]);
        phase6HttpExpect($malformed[0] === 401, 'malformed bearer 必须拒绝：' . $header);
    }
    // PHP 内置服务器会在应用前覆盖重复同名 header；验证代理合并后应用可见的多值表示。
    $multiple = phase6HttpRequest($issuer . '/resource/identity', [
        'Authorization: Bearer ' . $userToken . ', Bearer ' . $clientToken,
    ]);
    phase6HttpExpect($multiple[0] === 401, '多个 Authorization header 的合并表示必须拒绝');
    $insufficient = phase6HttpRequest($issuer . '/resource/identity', ['Authorization: Bearer ' . $wrongScopeToken]);
    phase6HttpExpect(
        $insufficient[0] === 403
            && str_contains($insufficient[1]['www-authenticate'][0] ?? '', 'insufficient_scope')
            && str_contains($insufficient[1]['www-authenticate'][0] ?? '', 'identity.read'),
        'scope 不足必须 403 challenge：' . json_encode($insufficient, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $userBody = phase6HttpJson(phase6HttpRequest($issuer . '/resource/identity', ['Authorization: Bearer ' . $userToken]), 200);
    phase6HttpExpect($userBody['subject_type'] === 'user' && (int) $userBody['identity_user_id'] === $userId && (int) $userBody['member_id'] === $memberId, 'user token 必须注入用户及 member link 身份');
    $clientBody = phase6HttpJson(phase6HttpRequest($issuer . '/resource/identity', ['Authorization: Bearer ' . $clientToken]), 200);
    phase6HttpExpect($clientBody['subject_type'] === 'client' && $clientBody['identity_user_id'] === null && $clientBody['member_id'] === null, 'client token 不得注入用户身份');

    $openid = phase6HttpJson(phase6HttpRequest($issuer . '/userinfo', ['Authorization: Bearer ' . $openidToken]), 200);
    phase6HttpExpect(array_keys($openid) === ['sub'], 'openid UserInfo 必须仅披露 sub');
    $email = phase6HttpJson(phase6HttpRequest($issuer . '/userinfo', ['Authorization: Bearer ' . $emailToken]), 200);
    phase6HttpExpect(isset($email['sub'], $email['email'], $email['email_verified']) && !isset($email['name'], $email['phone_number']), 'email UserInfo 必须最小披露');
    $pairA = phase6HttpJson(phase6HttpRequest($issuer . '/userinfo', ['Authorization: Bearer ' . $pairAToken]), 200);
    $pairARepeat = phase6HttpJson(phase6HttpRequest($issuer . '/userinfo', ['Authorization: Bearer ' . $pairARepeatToken]), 200);
    $pairB = phase6HttpJson(phase6HttpRequest($issuer . '/userinfo', ['Authorization: Bearer ' . $pairBToken]), 200);
    phase6HttpExpect($pairA['sub'] === $pairARepeat['sub'] && $pairA['sub'] !== $pairB['sub'], 'pairwise sub 必须稳定且按 sector 隔离');

    $expiredToken = $tokens->issue(1, $clientId, $userId, [$scopeId])['access_token'];
    Db::execute('UPDATE fun_oauth_token SET expires_at=DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE token_hash=?', [hash('sha256', $expiredToken)]);
    phase6HttpExpectInvalid($issuer, $expiredToken, '过期 token 必须返回 401');
    $revokedToken = $tokens->issue(1, $clientId, $userId, [$scopeId])['access_token'];
    Db::execute('UPDATE fun_oauth_token SET revoked_at=NOW() WHERE token_hash=?', [hash('sha256', $revokedToken)]);
    phase6HttpExpectInvalid($issuer, $revokedToken, '撤销 token 必须返回 401');
    $versionToken = $tokens->issue(1, $clientId, $userId, [$scopeId])['access_token'];
    Db::execute('UPDATE fun_identity_user SET password_version=password_version+1 WHERE id=?', [$userId]);
    phase6HttpExpectInvalid($issuer, $versionToken, 'password_version 变化必须返回 401');
    Db::execute('UPDATE fun_identity_user SET password_version=password_version-1,session_version=session_version+1 WHERE id=?', [$userId]);
    phase6HttpExpectInvalid($issuer, $versionToken, 'session_version 变化必须返回 401');
    Db::execute('UPDATE fun_identity_user SET session_version=session_version-1,status=0 WHERE id=?', [$userId]);
    phase6HttpExpectInvalid($issuer, $userToken, '禁用用户必须返回 401');
    Db::execute('UPDATE fun_identity_user SET status=1 WHERE id=?', [$userId]);
    Db::execute("UPDATE fun_oauth_client SET status='disabled' WHERE id=?", [$clientId]);
    phase6HttpExpectInvalid($issuer, $userToken, '禁用 client 必须返回 401');
    Db::execute("UPDATE fun_oauth_client SET status='active' WHERE id=?", [$clientId]);
    Db::execute("UPDATE fun_enterprise_application SET status='disabled' WHERE id=?", [$applicationId]);
    phase6HttpExpectInvalid($issuer, $userToken, '禁用 application 必须返回 401');
    Db::execute("UPDATE fun_enterprise_application SET status='published' WHERE id=?", [$applicationId]);
    Db::execute('UPDATE fun_identity_tenant SET status=0 WHERE id=1');
    phase6HttpExpectInvalid($issuer, $userToken, '禁用 tenant 必须返回 401');
    Db::execute('UPDATE fun_identity_tenant SET status=1 WHERE id=1');

    echo "identity phase6 HTTP E2E tests passed\n";
} catch (Throwable $exception) {
    $log = is_file($logPath) ? (string) file_get_contents($logPath) : '';
    throw new RuntimeException($exception->getMessage() . "\nHTTP log:\n" . $log, 0, $exception);
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
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
