<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/common/functions/plugin.php';

use app\common\service\MigrationService;
use app\identity\oauth\PkceService;
use app\identity\service\AuthorizationTransactionService;
use app\identity\service\BackchannelLogoutWorker;
use app\identity\service\BackchannelUrlPolicy;
use app\identity\service\OidcLogoutService;
use app\identity\service\OidcSessionService;
use think\App;
use think\facade\Db;

function phase7MysqlExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase7MysqlQuote(string $value): string
{
    phase7MysqlExpect(preg_match('/^[a-z0-9_]+$/', $value) === 1, '数据库名非法');
    return '`' . $value . '`';
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$database = 'funadmin_identity_phase7_' . bin2hex(random_bytes(5));
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
try {
    $server->execute('CREATE DATABASE ' . phase7MysqlQuote($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $database;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    $executed = (new MigrationService())->runDirectory($root . '/database/migrations', 'core');
    phase7MysqlExpect(in_array('111_oidc_session_logout', $executed, true), 'Phase7 migration 未执行');
    phase7MysqlExpect(in_array('113_oidc_logout_reliability', $executed, true), 'Phase7 forward migration 未执行');

    Db::execute("INSERT INTO fun_identity_user (tenant_id,public_id,realm,source_id,username,display_name,status,created_at,updated_at) VALUES (1,'71000000-0000-4000-8000-000000000001','fixture',971001,'phase7-user','Phase7 User',1,NOW(),NOW())");
    $userId = (int) Db::query("SELECT id FROM fun_identity_user WHERE source_id=971001")[0]['id'];
    Db::execute("INSERT INTO fun_enterprise_application (tenant_id,public_id,code,name,runtime_type,launch_url,status,created_at,updated_at) VALUES (1,'72000000-0000-4000-8000-000000000001','phase7-app','Phase7 App','internal','/app','published',NOW(),NOW())");
    $appId = (int) Db::query("SELECT id FROM fun_enterprise_application WHERE code='phase7-app'")[0]['id'];
    Db::execute("INSERT INTO fun_oauth_client (tenant_id,application_id,client_id,name,client_type,token_endpoint_auth_method,require_pkce,status,created_at,updated_at) VALUES (1,?,'phase7-a','Phase7 A','confidential','client_secret_basic',0,'active',NOW(),NOW()),(1,?,'phase7-b','Phase7 B','confidential','client_secret_basic',0,'active',NOW(),NOW())", [$appId, $appId]);
    $clientA = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase7-a'")[0]['id'];
    $clientB = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase7-b'")[0]['id'];

    $sessions = new OidcSessionService();
    $op = $sessions->createOrReuse(1, $userId);
    phase7MysqlExpect(strlen($op['session_token']) > 30 && Db::query('SELECT session_token_hash FROM fun_oidc_session WHERE id=?', [$op['session_id']])[0]['session_token_hash'] === hash('sha256', $op['session_token']), 'OP session 只能保存 token hash');
    phase7MysqlExpect($sessions->resolve(1, $op['session_token']) !== null, '有效 OP session 必须可解析');
    $a = $sessions->bindClient(1, (int) $op['session_id'], $userId, $clientA);
    $b = $sessions->bindClient(1, (int) $op['session_id'], $userId, $clientB);
    phase7MysqlExpect($a['sid'] !== $b['sid'], '两个 RP 必须使用不同 sid');

    $single = (new OidcLogoutService())->logout(1, (string) $a['sid'], false);
    phase7MysqlExpect($single !== [] && Db::query('SELECT status FROM fun_oidc_client_session WHERE id=?', [$a['id']])[0]['status'] === 'ended', '单 RP 退出必须结束目标 client session');
    phase7MysqlExpect(Db::query('SELECT status FROM fun_oidc_client_session WHERE id=?', [$b['id']])[0]['status'] === 'active', '单 RP 退出不得结束其他 RP');
    $rebound = $sessions->bindClient(1, (int) $op['session_id'], $userId, $clientA);
    phase7MysqlExpect($rebound['id'] !== $a['id'] && $rebound['sid'] !== $a['sid'], '退出后的 client session 不得复用，重新授权必须生成新 sid');

    $verifier = str_repeat('v', 43);
    $challenge = PkceService::challenge($verifier);
    Db::execute("INSERT INTO fun_oauth_authorization (tenant_id,client_id,user_id,transaction_hash,redirect_uri,redirect_uri_hash,state,nonce,code_challenge,code_challenge_method,status,auth_time,session_id,oidc_session_id,client_session_id,expires_at,decided_at,created_at,updated_at) VALUES (1,?,?,?,'https://client.example/callback',?,NULL,NULL,?,'S256','approved',NOW(),?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW(),NOW(),NOW())", [$clientA, $userId, hash('sha256', random_bytes(16)), hash('sha256', 'https://client.example/callback'), $challenge, $rebound['sid'], $op['session_id'], $rebound['id']]);
    $authorizationId = (int) Db::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
    $plainCode = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    Db::execute("INSERT INTO fun_oauth_authorization_code (tenant_id,authorization_id,client_id,user_id,code_prefix,code_hash,redirect_uri_hash,code_challenge,subject_type,expires_at,created_at,updated_at) VALUES (1,?,?,?,?,?,?,?,'user',DATE_ADD(NOW(),INTERVAL 5 MINUTE),NOW(),NOW())", [$authorizationId, $clientA, $userId, substr($plainCode, 0, 12), hash('sha256', $plainCode), hash('sha256', 'https://client.example/callback'), $challenge]);
    (new OidcLogoutService())->logout(1, (string) $b['sid'], true);
    phase7MysqlExpect(Db::query('SELECT status FROM fun_oidc_session WHERE id=?', [$op['session_id']])[0]['status'] === 'ended', '全局退出必须结束 OP session');
    phase7MysqlExpect(Db::query('SELECT status FROM fun_oauth_authorization WHERE id=?', [$authorizationId])[0]['status'] === 'revoked', '退出必须按 client_session_id 撤销 authorization');
    try {
        (new AuthorizationTransactionService())->consumeCode($plainCode, $clientA, 'https://client.example/callback', $verifier);
        throw new RuntimeException('退出后未兑换 code 不得继续兑换');
    } catch (DomainException $exception) {
        phase7MysqlExpect($exception->getMessage() === 'invalid_grant', '退出后兑换 code 必须返回 invalid_grant');
    }

    Db::execute("INSERT INTO fun_backchannel_logout_delivery (tenant_id,oidc_session_id,client_session_id,client_id,jti,logout_uri,logout_token,payload_hash,status,attempts,next_attempt_at,created_at,updated_at) VALUES (1,?,?,?,?, 'https://rp.example/logout','token',?,'pending',0,NOW(),NOW(),NOW())", [$op['session_id'], $rebound['id'], $clientA, '73000000-0000-4000-8000-000000000001', hash('sha256', 'token')]);
    $deliveryId = (int) Db::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
    $workerA = new BackchannelLogoutWorker('worker-a', 30);
    $workerB = new BackchannelLogoutWorker('worker-b', 30);
    phase7MysqlExpect(count($workerA->claim('worker-a', 1)) === 1, '第一个 worker 必须 claim 到期 pending');
    phase7MysqlExpect($workerB->claim('worker-b', 1) === [], '并发第二个 worker 不得重复 claim');
    Db::execute("UPDATE fun_backchannel_logout_delivery SET lease_expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?", [$deliveryId]);
    phase7MysqlExpect(count($workerB->claim('worker-b', 1)) === 1, 'processing 租约过期后必须可恢复 claim');

    $op2 = $sessions->createOrReuse(1, $userId);
    Db::execute('UPDATE fun_identity_user SET session_version=session_version+1 WHERE id=?', [$userId]);
    phase7MysqlExpect($sessions->resolve(1, $op2['session_token']) === null, 'session_version 变化必须使 OP session 失效');

    foreach (['https://127.0.0.1/logout', 'http://example.com/logout', 'https://localhost/logout'] as $unsafe) {
        try { (new BackchannelUrlPolicy())->validate($unsafe); throw new RuntimeException('SSRF URL 未拒绝：' . $unsafe); } catch (DomainException) {}
    }
    echo "identity phase7 mysql tests passed; temporary database cleaned\n";
} finally {
    $app->config->set($serverConfig, 'database');
    Db::connect('mysql', true)->execute('DROP DATABASE IF EXISTS ' . phase7MysqlQuote($database));
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
