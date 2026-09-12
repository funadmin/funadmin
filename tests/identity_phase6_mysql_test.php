<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/common/functions/plugin.php';

use app\common\service\MigrationService;
use app\common\service\identity\OidcClaimPolicyService;
use app\identity\service\OidcClaimService;
use app\identity\service\OpaqueTokenService;
use app\identity\service\SubjectService;
use think\App;
use think\facade\Db;

function phase6MysqlExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase6MysqlQuote(string $identifier): string
{
    phase6MysqlExpect(preg_match('/^[a-z0-9_]+$/', $identifier) === 1, '数据库名非法');
    return '`' . $identifier . '`';
}

function phase6MysqlReject(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

function phase6MysqlInvalidGrant(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (DomainException $exception) {
        phase6MysqlExpect($exception->getMessage() === 'invalid_grant', $message . '，实际：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException($message);
}

function phase6MigrationDirectory(string $source): string
{
    $target = sys_get_temp_dir() . '/funadmin_phase6_migrations_' . bin2hex(random_bytes(5));
    phase6MysqlExpect(mkdir($target, 0700), '无法创建隔离 migration 目录');
    foreach (glob($source . '/*.sql') ?: [] as $file) {
        $number = (int) substr(basename($file), 0, 3);
        if ($number <= 99 || $number === 104 || basename($file) === '108_identity_oidc_claims.sql') {
            phase6MysqlExpect(copy($file, $target . '/' . basename($file)), '无法复制 migration：' . basename($file));
        }
    }
    return $target;
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$database = 'funadmin_identity_phase6_' . bin2hex(random_bytes(5));
$migrations = phase6MigrationDirectory($root . '/database/migrations');
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
try {
    $server->execute('CREATE DATABASE ' . phase6MysqlQuote($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $database;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    $migration = new MigrationService();
    $executed = $migration->runDirectory($migrations, 'core');
    phase6MysqlExpect(in_array('108_identity_oidc_claims', $executed, true), 'migration 必须执行 Phase6 108');
    phase6MysqlExpect($migration->runDirectory($migrations, 'core') === [], 'migration 重复执行必须幂等跳过');

    Db::execute("INSERT INTO fun_identity_tenant (id,public_id,code,name,status,created_at,updated_at) VALUES (2,'20000000-0000-4000-8000-000000000002','phase6-tenant','Phase6 Tenant',1,NOW(),NOW())");
    Db::execute("INSERT INTO fun_identity_user (tenant_id,public_id,realm,source_id,username,display_name,email,mobile,locale,status,created_at,updated_at) VALUES (1,'60000000-0000-4000-8000-000000000001','fixture',960001,'phase6-user','Phase6 User','phase6@example.test','13900000000','zh-CN',1,NOW(),NOW()),(2,'60000000-0000-4000-8000-000000000002','fixture',960002,'phase6-other','Other Tenant','other@example.test',NULL,'zh-CN',1,NOW(),NOW())");
    $userId = (int) Db::query("SELECT id FROM fun_identity_user WHERE tenant_id=1 AND realm='fixture' AND source_id=960001")[0]['id'];
    $otherUserId = (int) Db::query("SELECT id FROM fun_identity_user WHERE tenant_id=2 AND realm='fixture' AND source_id=960002")[0]['id'];
    Db::execute("INSERT INTO fun_enterprise_application (tenant_id,public_id,code,name,runtime_type,launch_url,status,claim_policy,created_at,updated_at) VALUES (1,'61000000-0000-4000-8000-000000000001','phase6-app-a','App A','internal','/a','published',?,NOW(),NOW()),(1,'61000000-0000-4000-8000-000000000002','phase6-app-b','App B','internal','/b','published',?,NOW(),NOW())", [json_encode(['allowed_claims' => ['sub','name','preferred_username','picture','locale','email','email_verified','phone_number','phone_number_verified','tenant','departments','organization','roles','permissions']]), json_encode(['allowed_claims' => ['sub','roles','permissions']])]);
    $appA = (int) Db::query("SELECT id FROM fun_enterprise_application WHERE code='phase6-app-a'")[0]['id'];
    $appB = (int) Db::query("SELECT id FROM fun_enterprise_application WHERE code='phase6-app-b'")[0]['id'];
    Db::execute("INSERT INTO fun_oauth_client (tenant_id,application_id,client_id,name,client_type,token_endpoint_auth_method,require_pkce,subject_type,allowed_claims,status,created_at,updated_at) VALUES (1,?,'phase6-public','Public','confidential','client_secret_basic',0,'public',?,'active',NOW(),NOW()),(1,?,'phase6-pair-a','Pair A','confidential','client_secret_basic',0,'pairwise',?,'active',NOW(),NOW()),(1,?,'phase6-pair-b','Pair B','confidential','client_secret_basic',0,'pairwise',?,'active',NOW(),NOW()),(1,?,'phase6-app-b-client','App B','confidential','client_secret_basic',0,'public',?,'active',NOW(),NOW())", [$appA, json_encode(['sub','name','preferred_username','picture','locale','email','email_verified','phone_number','phone_number_verified','tenant','departments','organization','roles','permissions']), $appA, json_encode(['sub']), $appA, json_encode(['sub']), $appB, json_encode(['sub','roles','permissions'])]);
    Db::execute("UPDATE fun_oauth_client SET sector_identifier='sector-a.example' WHERE client_id='phase6-pair-a'");
    Db::execute("UPDATE fun_oauth_client SET sector_identifier='sector-b.example' WHERE client_id='phase6-pair-b'");
    $clientA = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase6-public'")[0]['id'];
    $pairA = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase6-pair-a'")[0]['id'];
    $pairB = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase6-pair-b'")[0]['id'];
    $clientB = (int) Db::query("SELECT id FROM fun_oauth_client WHERE client_id='phase6-app-b-client'")[0]['id'];

    Db::execute("INSERT INTO fun_application_role (tenant_id,application_id,code,name,status,created_at,updated_at) VALUES (1,?,'reader','Reader',1,NOW(),NOW()),(1,?,'global-admin-looking','Must Not Leak',1,NOW(),NOW())", [$appA, $appB]);
    $roleA = (int) Db::query("SELECT id FROM fun_application_role WHERE application_id=? AND code='reader'", [$appA])[0]['id'];
    $roleB = (int) Db::query("SELECT id FROM fun_application_role WHERE application_id=? AND code='global-admin-looking'", [$appB])[0]['id'];
    Db::execute("INSERT INTO fun_application_permission (tenant_id,application_id,code,name,status,created_at,updated_at) VALUES (1,?,'document.read','Read',1,NOW(),NOW()),(1,?,'system.admin','Must Not Leak',1,NOW(),NOW())", [$appA, $appB]);
    $permissionA = (int) Db::query("SELECT id FROM fun_application_permission WHERE application_id=? AND code='document.read'", [$appA])[0]['id'];
    $permissionB = (int) Db::query("SELECT id FROM fun_application_permission WHERE application_id=? AND code='system.admin'", [$appB])[0]['id'];
    Db::execute('INSERT INTO fun_application_user_role (tenant_id,application_id,user_id,role_id,created_at,updated_at) VALUES (1,?,?,?,NOW(),NOW()),(1,?,?,?,NOW(),NOW())', [$appA,$userId,$roleA,$appB,$userId,$roleB]);
    Db::execute('INSERT INTO fun_application_role_permission (tenant_id,application_id,role_id,permission_id,created_at,updated_at) VALUES (1,?,?,?,NOW(),NOW()),(1,?,?,?,NOW(),NOW())', [$appA,$roleA,$permissionA,$appB,$roleB,$permissionB]);

    phase6MysqlReject(fn () => Db::execute('INSERT INTO fun_application_user_role (tenant_id,application_id,user_id,role_id) VALUES (1,?,?,?)', [$appA,$userId,$roleB]), '跨 application 角色绑定必须被 FK 拒绝');
    phase6MysqlReject(fn () => Db::execute('INSERT INTO fun_application_user_role (tenant_id,application_id,user_id,role_id) VALUES (1,?,?,?)', [$appA,$otherUserId,$roleA]), '跨 tenant 用户绑定必须被 FK 拒绝');
    phase6MysqlReject(fn () => Db::execute('INSERT INTO fun_application_role_permission (tenant_id,application_id,role_id,permission_id) VALUES (1,?,?,?)', [$appA,$roleA,$permissionB]), '跨 application 权限绑定必须被 FK 拒绝');

    $policy = new OidcClaimPolicyService();
    phase6MysqlReject(fn () => $policy->saveApplicationPolicy(2, $appA, ['sub']), 'Claim policy 必须拒绝跨 tenant application');
    phase6MysqlReject(fn () => $policy->saveClientPolicy(2, $clientA, ['allowedClaims' => ['sub']]), 'Client claim policy 必须拒绝跨 tenant client');
    phase6MysqlReject(fn () => $policy->saveApplicationPolicy(1, $appA, ['unknown']), '未知 claim 必须默认拒绝');
    $savedPolicy = $policy->saveApplicationPolicy(1, $appA, ['sub','email','email_verified','tenant','departments','roles','permissions']);
    phase6MysqlExpect($savedPolicy['allowed_claims'] === ['sub','email','email_verified','tenant','departments','roles','permissions'], 'Application Claim policy 必须保存白名单');
    $savedClientPolicy = $policy->saveClientPolicy(1, $clientA, ['allowedClaims' => ['sub','email','email_verified','tenant','departments','roles','permissions'], 'subjectType' => 'public']);
    phase6MysqlExpect($savedClientPolicy['allowedClaims'] === ['sub','email','email_verified','tenant','departments','roles','permissions'], 'Client allowed claims 必须保存白名单');

    Db::execute('INSERT INTO fun_identity_user_department (tenant_id,user_id,department_id,is_primary,created_at,updated_at) VALUES (1,?,1,1,NOW(),NOW())', [$userId]);
    $claims = new OidcClaimService(new SubjectService('phase6-mysql-pepper'));
    $openid = $claims->claims(1, $userId, $clientA, ['openid']);
    phase6MysqlExpect(array_keys($openid) === ['sub'], 'openid 必须仅披露 sub');
    $email = $claims->claims(1, $userId, $clientA, ['openid','email']);
    phase6MysqlExpect(isset($email['email'], $email['email_verified']) && !isset($email['name'], $email['phone_number']), 'email scope 必须最小披露');
    $organization = $claims->claims(1, $userId, $clientA, ['openid','organization']);
    phase6MysqlExpect(isset($organization['tenant']['public_id'], $organization['departments'][0]['name']) && !isset($organization['tenant']['id'], $organization['departments'][0]['id']), 'organization claim 不得暴露内部自增 ID');
    $authorized = $claims->claims(1, $userId, $clientA, ['openid','roles','permissions']);
    phase6MysqlExpect($authorized['roles'] === ['reader'] && $authorized['permissions'] === ['document.read'], 'claims 只能包含当前 application 授权');
    $otherApplication = $claims->claims(1, $userId, $clientB, ['openid','roles','permissions']);
    phase6MysqlExpect($otherApplication['roles'] === ['global-admin-looking'] && $otherApplication['permissions'] === ['system.admin'], 'application 授权必须隔离');
    $subA = $claims->claims(1, $userId, $pairA, ['openid'])['sub'];
    $subB = $claims->claims(1, $userId, $pairB, ['openid'])['sub'];
    phase6MysqlExpect($subA !== $subB && $subA === $claims->claims(1, $userId, $pairA, ['openid'])['sub'], 'pairwise subject 必须稳定且按 sector 隔离');

    $scopeId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=1 AND name='openid'")[0]['id'];
    $offlineScopeId = (int) Db::query("SELECT id FROM fun_scope WHERE tenant_id=1 AND name='offline_access'")[0]['id'];
    $refreshIssued = (new OpaqueTokenService())->issue(1, $clientA, $userId, [$scopeId, $offlineScopeId]);
    $refreshToken = $refreshIssued['refresh_token'];
    $refreshFamily = Db::query('SELECT family_id FROM fun_oauth_token WHERE token_hash=?', [hash('sha256', $refreshToken)])[0]['family_id'];
    Db::execute('UPDATE fun_identity_user SET password_version=password_version+1 WHERE id=?', [$userId]);
    phase6MysqlInvalidGrant(fn () => (new OpaqueTokenService())->rotate($refreshToken, $clientA, []), 'password_version 变化后的 refresh 必须 invalid_grant');
    phase6MysqlExpect((int) Db::query('SELECT COUNT(*) AS total FROM fun_oauth_token WHERE family_id=? AND revoked_at IS NULL', [$refreshFamily])[0]['total'] === 0, 'refresh 用户版本失效必须原子撤销整个 family');
    Db::execute('UPDATE fun_identity_user SET password_version=password_version-1 WHERE id=?', [$userId]);

    $sessionRefresh = (new OpaqueTokenService())->issue(1, $clientA, $userId, [$scopeId, $offlineScopeId])['refresh_token'];
    Db::execute('UPDATE fun_identity_user SET session_version=session_version+1 WHERE id=?', [$userId]);
    phase6MysqlInvalidGrant(fn () => (new OpaqueTokenService())->rotate($sessionRefresh, $clientA, []), 'session_version 变化后的 refresh 必须 invalid_grant');
    Db::execute('UPDATE fun_identity_user SET session_version=session_version-1 WHERE id=?', [$userId]);

    $disabledRefresh = (new OpaqueTokenService())->issue(1, $clientA, $userId, [$scopeId, $offlineScopeId])['refresh_token'];
    Db::execute('UPDATE fun_identity_user SET status=0 WHERE id=?', [$userId]);
    phase6MysqlInvalidGrant(fn () => (new OpaqueTokenService())->rotate($disabledRefresh, $clientA, []), 'disabled 用户的 refresh 必须 invalid_grant');
    Db::execute('UPDATE fun_identity_user SET status=1 WHERE id=?', [$userId]);

    $deletedRefresh = (new OpaqueTokenService())->issue(1, $clientA, $userId, [$scopeId, $offlineScopeId])['refresh_token'];
    Db::execute('UPDATE fun_identity_user SET deleted_at=NOW() WHERE id=?', [$userId]);
    phase6MysqlInvalidGrant(fn () => (new OpaqueTokenService())->rotate($deletedRefresh, $clientA, []), 'deleted 用户的 refresh 必须 invalid_grant');
    Db::execute('UPDATE fun_identity_user SET deleted_at=NULL WHERE id=?', [$userId]);

    if (function_exists('pcntl_fork')) {
        $concurrentRefresh = (new OpaqueTokenService())->issue(1, $clientA, $userId, [$scopeId, $offlineScopeId])['refresh_token'];
        $concurrentFamily = Db::query('SELECT family_id FROM fun_oauth_token WHERE token_hash=?', [hash('sha256', $concurrentRefresh)])[0]['family_id'];
        $gate = sys_get_temp_dir() . '/funadmin-phase6-refresh-gate-' . bin2hex(random_bytes(4));
        $results = [$gate . '-a', $gate . '-b'];
        $children = [];
        foreach ($results as $resultPath) {
            $pid = pcntl_fork();
            phase6MysqlExpect($pid >= 0, '无法创建 refresh 并发测试进程');
            if ($pid === 0) {
                while (!is_file($gate)) usleep(1_000);
                try {
                    Db::connect('mysql', true);
                    (new OpaqueTokenService())->rotate($concurrentRefresh, $clientA, []);
                    file_put_contents($resultPath, 'success');
                } catch (DomainException $exception) {
                    file_put_contents($resultPath, $exception->getMessage());
                }
                exit(0);
            }
            $children[] = $pid;
        }
        touch($gate);
        foreach ($children as $pid) pcntl_waitpid($pid, $status);
        Db::connect('mysql', true);
        $outcomes = array_map(static fn (string $path): string => trim((string) file_get_contents($path)), $results);
        sort($outcomes);
        phase6MysqlExpect($outcomes === ['invalid_grant', 'success'], '并发 refresh 必须只有一个消费成功');
        phase6MysqlExpect((int) Db::query('SELECT COUNT(*) AS total FROM fun_oauth_token WHERE family_id=? AND revoked_at IS NULL', [$concurrentFamily])[0]['total'] === 0, '并发 refresh replay 必须撤销整个 family');
        foreach ([$gate, ...$results] as $path) if (is_file($path)) unlink($path);
    }

    $issued = (new OpaqueTokenService())->issue(1, $clientA, $userId, [$scopeId]);
    $tokenRow = Db::query('SELECT password_version,session_version FROM fun_oauth_token WHERE token_hash=?', [hash('sha256', $issued['access_token'])])[0];
    phase6MysqlExpect((int) $tokenRow['password_version'] === 1 && (int) $tokenRow['session_version'] === 1, '用户 token 必须保存签发时版本');
    $tokens = new OpaqueTokenService();
    phase6MysqlExpect($tokens->inspect($issued['access_token'])['active'] === true, '有效 opaque token 必须 active');
    Db::execute('UPDATE fun_identity_user SET password_version=password_version+1 WHERE id=?', [$userId]);
    phase6MysqlExpect($tokens->inspect($issued['access_token'])['active'] === false, 'password_version 变化必须撤销 token');
    Db::execute('UPDATE fun_identity_user SET password_version=1 WHERE id=?', [$userId]);
    Db::execute('UPDATE fun_identity_user SET session_version=session_version+1 WHERE id=?', [$userId]);
    phase6MysqlExpect($tokens->inspect($issued['access_token'])['active'] === false, 'session_version 变化必须撤销 token');
    Db::execute('UPDATE fun_identity_user SET session_version=1 WHERE id=?', [$userId]);
    Db::execute('UPDATE fun_identity_user SET status=0 WHERE id=?', [$userId]);
    phase6MysqlExpect($tokens->inspect($issued['access_token'])['active'] === false, 'disabled user token 必须 inactive');
    Db::execute('UPDATE fun_identity_user SET status=1 WHERE id=?', [$userId]);
    Db::execute("UPDATE fun_oauth_client SET status='disabled' WHERE id=?", [$clientA]);
    phase6MysqlExpect($tokens->inspect($issued['access_token'])['active'] === false, 'disabled client token 必须 inactive');
    Db::execute("UPDATE fun_oauth_client SET status='active' WHERE id=?", [$clientA]);
    Db::execute("UPDATE fun_enterprise_application SET status='disabled' WHERE id=?", [$appA]);
    phase6MysqlExpect($tokens->inspect($issued['access_token'])['active'] === false, 'disabled application token 必须 inactive');
    Db::execute("UPDATE fun_enterprise_application SET status='published' WHERE id=?", [$appA]);
    Db::execute('UPDATE fun_identity_tenant SET status=0 WHERE id=1');
    phase6MysqlExpect($tokens->inspect($issued['access_token'])['active'] === false, 'disabled tenant token 必须 inactive');
    Db::execute('UPDATE fun_identity_tenant SET status=1 WHERE id=1');
    Db::execute('UPDATE fun_oauth_token SET revoked_at=NOW() WHERE token_hash=?', [hash('sha256', $issued['access_token'])]);
    phase6MysqlExpect($tokens->inspect($issued['access_token'])['active'] === false, 'revoked token 必须 inactive');

    Db::execute("INSERT INTO fun_member (username,password,nickname,level_id,status,created_at,updated_at) VALUES ('phase6-member','unused','Phase6 Member',1,1,NOW(),NOW())");
    $memberId = (int) Db::query("SELECT id FROM fun_member WHERE username='phase6-member'")[0]['id'];
    Db::execute('INSERT INTO fun_identity_member_link (tenant_id,user_id,member_id,created_at,updated_at) VALUES (1,?,?,NOW(),NOW())', [$userId,$memberId]);
    $linked = $tokens->issue(1, $clientA, $userId, [$scopeId]);
    phase6MysqlExpect((int) ($tokens->inspect($linked['access_token'])['member_id'] ?? 0) === $memberId, 'member link 必须解析 member_id');
    $clientToken = $tokens->issue(1, $clientA, null, [$scopeId]);
    $clientIdentity = $tokens->inspect($clientToken['access_token']);
    phase6MysqlExpect($clientIdentity['active'] === true && $clientIdentity['_record']['subject_type'] === 'client' && $clientIdentity['_record']['user_id'] === null, 'client token 不得携带用户身份');

    $explain = Db::query("EXPLAIN SELECT * FROM fun_oauth_token WHERE token_hash=? AND token_type='access'", [hash('sha256', $issued['access_token'])])[0];
    phase6MysqlExpect((string) ($explain['key'] ?? '') !== '' && (int) ($explain['rows'] ?? 999999) <= 2, 'opaque token hash 查询必须使用高选择性索引');
    $roleExplain = Db::query('EXPLAIN SELECT role_id FROM fun_application_user_role WHERE tenant_id=1 AND application_id=? AND user_id=?', [$appA,$userId])[0];
    phase6MysqlExpect(str_contains((string) ($roleExplain['key'] ?? ''), 'application_user_role'), 'role claim 查询必须使用 tenant/application/user 索引');
    echo "identity phase6 mysql tests passed; temporary database cleaned\n";
} catch (Throwable $exception) {
    throw new RuntimeException($exception->getMessage() . "\n" . $exception->getTraceAsString(), 0, $exception);
} finally {
    foreach (glob($migrations . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($migrations)) rmdir($migrations);
    $app->config->set($serverConfig, 'database');
    Db::connect('mysql', true)->execute('DROP DATABASE IF EXISTS ' . phase6MysqlQuote($database));
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
