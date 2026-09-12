<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/common/functions/plugin.php';

use app\common\model\identity\IdentityAuditLog;
use app\common\model\identity\IdentityUser;
use app\common\service\MigrationService;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\IdentityOperationsService;
use app\common\service\identity\MemberIdentityAdapter;
use app\identity\service\AuthorizationRevocationService;
use app\identity\service\AuthorizationTransactionService;
use app\identity\service\BackchannelLogoutDispatcher;
use app\identity\service\IdentitySsoConfigService;
use app\identity\service\OpaqueTokenService;
use think\App;
use think\facade\Db;

function phase8RuntimeExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase8RuntimeQuote(string $value): string
{
    phase8RuntimeExpect(preg_match('/^[a-z0-9_]+$/', $value) === 1, '数据库名非法');
    return '`' . $value . '`';
}

function phase8RuntimeInvalidGrant(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (DomainException $exception) {
        phase8RuntimeExpect($exception->getMessage() === 'invalid_grant', $message);
        return;
    }
    throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$database = 'funadmin_identity_phase8_' . bin2hex(random_bytes(5));
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
try {
    $server->execute('CREATE DATABASE ' . phase8RuntimeQuote($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $database;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    (new MigrationService())->runDirectory($root . '/database/migrations', 'core');

    Db::execute("INSERT INTO fun_identity_tenant (id,public_id,code,name,status,created_at,updated_at) VALUES (2,'80000000-0000-4000-8000-000000000002','phase8-other','Phase8 Other',1,NOW(),NOW())");
    Db::execute("INSERT INTO fun_identity_sso_config (tenant_id,enabled,provider_mode,issuer,external_identity_enabled,backchannel_logout_enabled,created_at,updated_at) VALUES (1,1,'identity_provider','https://identity.example.test',0,1,NOW(),NOW()),(2,1,'identity_provider','https://identity.example.test',0,1,NOW(),NOW())");
    $runtime = new IdentitySsoConfigService();
    $runtimeConfig = $runtime->read(1);
    phase8RuntimeExpect($runtimeConfig !== null && (int) ($runtimeConfig['enabled'] ?? 0) === 1 && (string) ($runtimeConfig['provider_mode'] ?? '') === 'identity_provider' && (int) ($runtimeConfig['backchannel_logout_enabled'] ?? 0) === 1, '有效 identity_provider 配置读取异常：' . json_encode($runtimeConfig, JSON_UNESCAPED_UNICODE));
    Db::execute('UPDATE fun_identity_sso_config SET enabled=0 WHERE tenant_id=1');
    try { $runtime->requireIdentityProvider(1); throw new RuntimeException('配置关闭后必须即时拒绝'); } catch (DomainException $exception) { phase8RuntimeExpect($exception->getMessage() === 'server_error', '关闭配置必须服务端拒绝'); }
    Db::execute("UPDATE fun_identity_sso_config SET enabled=1,provider_mode='external' WHERE tenant_id=1");
    try { $runtime->requireIdentityProvider(1); throw new RuntimeException('external 模式必须拒绝'); } catch (DomainException $exception) { phase8RuntimeExpect($exception->getMessage() === 'server_error', 'external 模式必须服务端拒绝'); }
    Db::execute("UPDATE fun_identity_sso_config SET provider_mode='unknown' WHERE tenant_id=1");
    try { $runtime->requireIdentityProvider(1); throw new RuntimeException('未知模式必须拒绝'); } catch (DomainException $exception) { phase8RuntimeExpect($exception->getMessage() === 'server_error', '未知模式必须服务端拒绝'); }
    try { $runtime->requireIdentityProvider(999); throw new RuntimeException('缺失配置必须拒绝'); } catch (DomainException $exception) { phase8RuntimeExpect($exception->getMessage() === 'server_error', '缺失配置必须 fail closed'); }
    Db::execute("UPDATE fun_identity_sso_config SET provider_mode='identity_provider' WHERE tenant_id=1");

    Db::execute("INSERT INTO fun_identity_user (tenant_id,public_id,realm,source_id,username,display_name,status,created_at,updated_at) VALUES (1,'81000000-0000-4000-8000-000000000001','fixture',981001,'phase8-user','Phase8 User',1,NOW(),NOW()),(2,'81000000-0000-4000-8000-000000000002','fixture',981002,'phase8-other','Phase8 Other',1,NOW(),NOW())");
    $userId = (int) Db::query("SELECT id FROM fun_identity_user WHERE tenant_id=1 AND source_id=981001")[0]['id'];
    $otherUserId = (int) Db::query("SELECT id FROM fun_identity_user WHERE tenant_id=2 AND source_id=981002")[0]['id'];
    Db::execute("INSERT INTO fun_enterprise_application (tenant_id,public_id,code,name,runtime_type,launch_url,status,created_at,updated_at) VALUES (1,'82000000-0000-4000-8000-000000000001','phase8-app','Phase8 App','internal','/phase8','published',NOW(),NOW()),(2,'82000000-0000-4000-8000-000000000002','phase8-other','Phase8 Other','internal','/phase8-other','published',NOW(),NOW())");
    $appId = (int) Db::query("SELECT id FROM fun_enterprise_application WHERE tenant_id=1 AND code='phase8-app'")[0]['id'];
    $otherAppId = (int) Db::query("SELECT id FROM fun_enterprise_application WHERE tenant_id=2 AND code='phase8-other'")[0]['id'];
    Db::execute("INSERT INTO fun_oauth_client (tenant_id,application_id,client_id,name,client_type,token_endpoint_auth_method,require_pkce,status,created_at,updated_at) VALUES (1,?,'phase8-client','Phase8 Client','confidential','client_secret_basic',0,'active',NOW(),NOW()),(2,?,'phase8-other-client','Phase8 Other','confidential','client_secret_basic',0,'active',NOW(),NOW())", [$appId, $otherAppId]);
    $clientId = (int) Db::query("SELECT id FROM fun_oauth_client WHERE tenant_id=1 AND client_id='phase8-client'")[0]['id'];
    $otherClientId = (int) Db::query("SELECT id FROM fun_oauth_client WHERE tenant_id=2 AND client_id='phase8-other-client'")[0]['id'];
    $scopeId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=1 AND name='openid'")[0]['id'];
    $offlineScopeId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=1 AND name='offline_access'")[0]['id'];
    Db::execute("INSERT INTO fun_scope (tenant_id,name,description,is_builtin,status,created_at,updated_at) VALUES (2,'openid','OpenID',1,1,NOW(),NOW()),(2,'offline_access','Offline',1,1,NOW(),NOW())");
    $otherScopeId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=2 AND name='openid'")[0]['id'];
    $otherOfflineScopeId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=2 AND name='offline_access'")[0]['id'];
    $verifier = str_repeat('v', 43);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    $insertAuthorization = static function (int $tenantId, int $clientId, int $userId, string $status, string $challenge): int {
        Db::execute("INSERT INTO fun_oauth_authorization (tenant_id,client_id,user_id,transaction_hash,redirect_uri,redirect_uri_hash,code_challenge,code_challenge_method,status,expires_at,decided_at,created_at,updated_at) VALUES (?,?,?,?,'https://client.example/callback',?,?,'S256',?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW(),NOW(),NOW())", [$tenantId, $clientId, $userId, hash('sha256', random_bytes(16)), hash('sha256', 'https://client.example/callback'), $challenge, $status]);
        return (int) Db::query('SELECT LAST_INSERT_ID() AS id')[0]['id'];
    };
    $completedId = $insertAuthorization(1, $clientId, $userId, 'completed', $challenge);
    $approvedId = $insertAuthorization(1, $clientId, $userId, 'approved', $challenge);
    $pendingTransaction = (new AuthorizationTransactionService())->create(1, $clientId, 'https://client.example/callback', ['state' => null, 'nonce' => null, 'code_challenge' => $challenge], [$scopeId]);
    $otherAuthorizationId = $insertAuthorization(2, $otherClientId, $otherUserId, 'completed', $challenge);
    Db::execute('UPDATE fun_identity_sso_config SET enabled=0,backchannel_logout_enabled=0 WHERE tenant_id=1');
    try { (new AuthorizationTransactionService())->decide($pendingTransaction, ['tenant_id' => 1], true); throw new RuntimeException('配置关闭后 consent 决策必须即时拒绝'); } catch (DomainException $exception) { phase8RuntimeExpect($exception->getMessage() === 'server_error', 'consent 决策必须 fail closed，实际：' . $exception->getMessage()); }
    $deliveryCount = (int) Db::query('SELECT COUNT(*) AS total FROM fun_backchannel_logout_delivery')[0]['total'];
    $notQueued = (new BackchannelLogoutDispatcher())->enqueue(1, 999, ['id' => 999, 'client_id' => $clientId, 'sid' => 'disabled', 'user_id' => $userId], 'phase8-client', 'https://rp.example/logout');
    phase8RuntimeExpect($notQueued === null && (int) Db::query('SELECT COUNT(*) AS total FROM fun_backchannel_logout_delivery')[0]['total'] === $deliveryCount, 'backchannel 关闭时不得创建 delivery');
    Db::execute('UPDATE fun_identity_sso_config SET enabled=1,backchannel_logout_enabled=1 WHERE tenant_id=1');
    $plainCode = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    Db::execute("INSERT INTO fun_oauth_authorization_code (tenant_id,authorization_id,client_id,user_id,code_prefix,code_hash,redirect_uri_hash,code_challenge,subject_type,expires_at,created_at,updated_at) VALUES (1,?,?,?,?,?,?,?,'user',DATE_ADD(NOW(),INTERVAL 5 MINUTE),NOW(),NOW())", [$completedId, $clientId, $userId, substr($plainCode, 0, 12), hash('sha256', $plainCode), hash('sha256', 'https://client.example/callback'), $challenge]);
    Db::execute("INSERT INTO fun_identity_consent (tenant_id,user_id,client_id,granted_scope_hash,scope_names,granted_at,created_at,updated_at) VALUES (1,?,?,?,'openid',NOW(),NOW(),NOW())", [$userId, $clientId, hash('sha256', 'openid')]);
    $issued = (new OpaqueTokenService())->issue(1, $clientId, $userId, [$scopeId, $offlineScopeId], $completedId);
    $otherIssued = (new OpaqueTokenService())->issue(2, $otherClientId, $otherUserId, [$otherScopeId, $otherOfflineScopeId], $otherAuthorizationId);

    (new AuthorizationRevocationService())->revokeUser(1, $userId);
    phase8RuntimeExpect((new OpaqueTokenService())->inspect($issued['access_token'])['active'] === false, 'revoke 后 access token inspect 必须 inactive');
    phase8RuntimeInvalidGrant(fn () => (new OpaqueTokenService())->rotate($issued['refresh_token'], $clientId, []), 'revoke 后 refresh 必须 invalid_grant');
    phase8RuntimeInvalidGrant(fn () => (new AuthorizationTransactionService())->consumeCode($plainCode, $clientId, 'https://client.example/callback', $verifier), 'revoke 后 code 必须 invalid_grant');
    phase8RuntimeExpect((int) Db::query("SELECT COUNT(*) AS total FROM fun_oauth_authorization WHERE tenant_id=1 AND id IN (?,?) AND status='revoked'", [$completedId, $approvedId])[0]['total'] === 2, 'completed 与 approved authorization 都必须 revoked');
    phase8RuntimeExpect((new OpaqueTokenService())->inspect($otherIssued['access_token'])['active'] === true, '跨 tenant token 不得被撤销');
    phase8RuntimeExpect(Db::query('SELECT status FROM fun_oauth_authorization WHERE id=?', [$otherAuthorizationId])[0]['status'] === 'completed', '跨 tenant authorization 不得被撤销');

    Db::execute("INSERT INTO fun_admin (username,password,email,mobile,real_name,dept_id,status,avatar,token,created_at,updated_at) VALUES ('phase8-shadow-admin',?,'admin@example.test','13900000008','Shadow Admin',1,1,'','',NOW(),NOW())", [password_hash('ShadowAdmin!1', PASSWORD_BCRYPT)]);
    $admin = \app\console\authentication\model\Admin::where('username', 'phase8-shadow-admin')->findOrFail();
    $adminIdentity = (new AdminIdentityAdapter())->sync($admin, [1]);
    IdentityUser::forTenant(1)->where('id', (int) $adminIdentity->id)->update(['display_name' => 'stale-admin-name']);
    $auditBefore = IdentityAuditLog::forTenant(1)->where('event_type', 'identity.shadow_read_mismatch')->count();
    $adminDifferences = (new AdminIdentityAdapter())->shadowRead($admin);
    $adminAudit = IdentityAuditLog::forTenant(1)->where('event_type', 'identity.shadow_read_mismatch')->order('id', 'desc')->find();
    $adminContext = is_string($adminAudit?->context) ? json_decode($adminAudit->context, true, 512, JSON_THROW_ON_ERROR) : (array) ($adminAudit?->context ?? []);
    phase8RuntimeExpect(in_array('display_name', $adminDifferences, true), 'admin shadow-read 必须返回真实字段差异');
    phase8RuntimeExpect(IdentityAuditLog::forTenant(1)->where('event_type', 'identity.shadow_read_mismatch')->count() === $auditBefore + 1, 'admin shadow-read mismatch 必须写审计');
    phase8RuntimeExpect(($adminContext['source'] ?? '') === 'admin' && ($adminContext['realm'] ?? '') === 'admin' && in_array('display_name', (array) ($adminContext['fields'] ?? []), true), 'admin shadow-read 审计必须包含来源、realm 和差异字段');
    (new AdminIdentityAdapter())->shadowRead($admin, false);
    phase8RuntimeExpect(IdentityAuditLog::forTenant(1)->where('event_type', 'identity.shadow_read_mismatch')->count() === $auditBefore + 1, 'audit=false 的 shadow-read 不得写审计');

    Db::execute("INSERT INTO fun_member (username,password,email,mobile,nickname,status,level_id,created_at,updated_at) VALUES ('phase8-shadow-member',?,'member@example.test','13900000009','Shadow Member',1,1,NOW(),NOW())", [password_hash('ShadowMember!1', PASSWORD_BCRYPT)]);
    $member = \app\console\model\Member::where('username', 'phase8-shadow-member')->findOrFail();
    $memberIdentity = (new MemberIdentityAdapter())->sync($member);
    IdentityUser::forTenant(1)->where('id', (int) $memberIdentity->id)->update(['email' => 'stale-member@example.test']);
    $memberDifferences = (new MemberIdentityAdapter())->shadowRead($member);
    $memberAudit = IdentityAuditLog::forTenant(1)->where('event_type', 'identity.shadow_read_mismatch')->order('id', 'desc')->find();
    $memberContext = is_string($memberAudit?->context) ? json_decode($memberAudit->context, true, 512, JSON_THROW_ON_ERROR) : (array) ($memberAudit?->context ?? []);
    phase8RuntimeExpect(in_array('email', $memberDifferences, true), 'member shadow-read 必须返回真实字段差异');
    phase8RuntimeExpect(($memberContext['source'] ?? '') === 'member' && ($memberContext['realm'] ?? '') === 'member' && in_array('email', (array) ($memberContext['fields'] ?? []), true), 'member shadow-read 审计必须包含来源、realm 和差异字段');

    Db::execute("UPDATE fun_oauth_authorization_code SET expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE tenant_id=1");
    Db::execute("UPDATE fun_oauth_token SET expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE tenant_id IN (1,2)");
    Db::execute("INSERT INTO fun_oidc_session (tenant_id,user_id,sid,session_token_hash,auth_time,last_seen_at,expires_at,password_version,session_version,status,created_at,updated_at) VALUES (1,?,'phase8-expired-session',?,NOW(),NOW(),DATE_SUB(NOW(),INTERVAL 1 DAY),1,1,'ended',NOW(),NOW()),(2,?,'phase8-other-expired-session',?,NOW(),NOW(),DATE_SUB(NOW(),INTERVAL 1 DAY),1,1,'ended',NOW(),NOW())", [$userId, hash('sha256', 'phase8-expired-session'), $otherUserId, hash('sha256', 'phase8-other-expired-session')]);
    Db::execute("INSERT INTO fun_identity_audit_log (tenant_id,event_type,outcome,context,created_at,updated_at) VALUES (1,'phase8.expired','success',JSON_OBJECT(),DATE_SUB(NOW(),INTERVAL 365 DAY),NOW()),(2,'phase8.other.expired','success',JSON_OBJECT(),DATE_SUB(NOW(),INTERVAL 365 DAY),NOW())");
    $operations = new IdentityOperationsService();
    $beforeDryRun = [
        'tokens' => (int) Db::query('SELECT COUNT(*) AS total FROM fun_oauth_token WHERE tenant_id=1')[0]['total'],
        'sessions' => (int) Db::query('SELECT COUNT(*) AS total FROM fun_oidc_session WHERE tenant_id=1')[0]['total'],
        'audit' => (int) Db::query("SELECT COUNT(*) AS total FROM fun_identity_audit_log WHERE tenant_id=1 AND event_type='phase8.expired'")[0]['total'],
    ];
    $dryRun = $operations->cleanup(1, 1, true, 180);
    phase8RuntimeExpect(($dryRun['dry_run'] ?? false) === true && ($dryRun['matched']['authorization_codes'] ?? 0) === 1 && ($dryRun['matched']['tokens'] ?? 0) === 1 && ($dryRun['matched']['sessions'] ?? 0) === 1 && ($dryRun['matched']['audit'] ?? 0) === 1, 'cleanup dry-run 必须按每类 limit 报告匹配项');
    phase8RuntimeExpect((int) Db::query('SELECT COUNT(*) AS total FROM fun_oauth_token WHERE tenant_id=1')[0]['total'] === $beforeDryRun['tokens'] && (int) Db::query('SELECT COUNT(*) AS total FROM fun_oidc_session WHERE tenant_id=1')[0]['total'] === $beforeDryRun['sessions'] && (int) Db::query("SELECT COUNT(*) AS total FROM fun_identity_audit_log WHERE tenant_id=1 AND event_type='phase8.expired'")[0]['total'] === $beforeDryRun['audit'], 'cleanup dry-run 不得删除数据');
    $cleanup = $operations->cleanup(1, 1, false, 180);
    phase8RuntimeExpect(($cleanup['dry_run'] ?? true) === false && ($cleanup['matched']['tokens'] ?? 0) === 1 && ($cleanup['matched']['sessions'] ?? 0) === 1 && ($cleanup['matched']['audit'] ?? 0) === 1, 'cleanup 必须实际分批删除过期数据');
    phase8RuntimeExpect((int) Db::query("SELECT COUNT(*) AS total FROM fun_oidc_session WHERE tenant_id=2 AND sid='phase8-other-expired-session'")[0]['total'] === 1 && (int) Db::query("SELECT COUNT(*) AS total FROM fun_identity_audit_log WHERE tenant_id=2 AND event_type='phase8.other.expired'")[0]['total'] === 1, 'cleanup 不得删除其他租户数据');

    Db::execute("INSERT INTO fun_identity_audit_log (tenant_id,event_type,outcome,context,created_at,updated_at) VALUES (1,'oauth.refresh_replay','failure',JSON_OBJECT('source','phase8'),NOW(),NOW())");
    $health = $operations->health(1, 30);
    phase8RuntimeExpect(($health['healthy'] ?? true) === false && ($health['alerts']['refresh_replay_24h'] ?? 0) >= 1 && ($health['alerts']['unhealthy_applications'] ?? 0) >= 1, 'health 必须报告 refresh replay 与未配置应用告警');

    $mysql = $original['connections']['mysql'];
    $lockPdo = new PDO('mysql:host=' . $mysql['hostname'] . ';port=' . $mysql['hostport'] . ';dbname=' . $database . ';charset=' . $mysql['charset'], (string) $mysql['username'], (string) $mysql['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    phase8RuntimeExpect((int) $lockPdo->query("SELECT GET_LOCK('identity:1:cleanup',0)")->fetchColumn() === 1, 'fixture 必须先持有 cleanup 运维锁');
    try {
        $operations->withLock(1, 'cleanup', static fn (): bool => true);
        throw new RuntimeException('同租户 cleanup 锁冲突必须拒绝');
    } catch (RuntimeException $exception) {
        phase8RuntimeExpect($exception->getMessage() === '同租户运维任务正在执行', '运维锁冲突必须返回明确错误');
    } finally {
        $lockPdo->query("SELECT RELEASE_LOCK('identity:1:cleanup')");
    }
    phase8RuntimeExpect($operations->withLock(1, 'cleanup', static fn (): string => 'released') === 'released', '运维锁释放后必须可再次执行');

    echo "identity phase8 runtime mysql tests passed; temporary database cleaned\n";
} finally {
    $app->config->set($serverConfig, 'database');
    Db::connect('mysql', true)->execute('DROP DATABASE IF EXISTS ' . phase8RuntimeQuote($database));
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
