<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityCredential;
use app\common\model\identity\IdentityMemberLink;
use app\common\model\identity\IdentityUser;
use app\common\model\identity\IdentityUserDepartment;
use app\common\service\MemberAuthService;
use app\common\service\MigrationService;
use app\common\service\identity\AdminIdentityAdapter;
use app\common\service\identity\IdentityCredentialService;
use app\common\service\identity\IdentityUserService;
use app\common\service\identity\MemberIdentityAdapter;
use think\App;
use think\facade\Db;

function identityMysqlExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function identityMysqlQuote(string $identifier): string
{
    identityMysqlExpect(preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1, '数据库名不合法');
    return '`' . $identifier . '`';
}

function identityMigrationDirectoryThrough094(string $source): string
{
    $target = sys_get_temp_dir() . '/funadmin_identity_migrations_' . bin2hex(random_bytes(6));
    identityMysqlExpect(mkdir($target, 0700), '无法创建隔离 migration 目录');
    foreach (glob($source . '/*.sql') ?: [] as $file) {
        if ((int) substr(basename($file), 0, 3) <= 94) {
            identityMysqlExpect(copy($file, $target . '/' . basename($file)), '无法复制 094 migration 链');
        }
    }
    return $target;
}

function identityRemoveDirectory(string $directory): void
{
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$mysql = (array) $original['connections']['mysql'];
$databaseName = 'funadmin_identity_' . bin2hex(random_bytes(6));
$upgradeDatabaseName = 'funadmin_identity_upgrade_' . bin2hex(random_bytes(6));
$upgradeMigrationDirectory = identityMigrationDirectoryThrough094($root . '/database/migrations');
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);

try {
    $server->execute('CREATE DATABASE ' . identityMysqlQuote($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->execute('CREATE DATABASE ' . identityMysqlQuote($upgradeDatabaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $databaseName;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);

    $migrationService = new MigrationService();
    $executed = $migrationService->runDirectory($root . '/database/migrations', 'core');
    identityMysqlExpect(in_array('095_identity_foundation', $executed, true), '空库 migration 链必须执行 095');
    identityMysqlExpect($migrationService->runDirectory($root . '/database/migrations', 'core') === [], '重复 migration 必须幂等跳过');

    $upgrade = $original;
    $upgrade['connections']['mysql']['database'] = $upgradeDatabaseName;
    $app->config->set($upgrade, 'database');
    Db::connect('mysql', true);
    $through094 = $migrationService->runDirectory($upgradeMigrationDirectory, 'core');
    identityMysqlExpect(end($through094) === '094_ai_agent_runtime', '升级库必须先完整执行到 094');
    Db::execute("INSERT INTO fun_admin (username,password,email,mobile,real_name,dept_id,status,avatar,token,created_at,updated_at) VALUES ('upgrade-conflict',?,'upgrade@example.test','13900000003','Upgrade Admin',1,1,'','',NOW(),NOW())", [password_hash('UpgradeAdmin!1', PASSWORD_BCRYPT)]);
    $upgradeAdminId = (int) Db::query("SELECT id FROM fun_admin WHERE username='upgrade-conflict'")[0]['id'];
    Db::execute("INSERT INTO fun_member (username,password,email,mobile,nickname,status,level_id,created_at,updated_at) VALUES ('upgrade-conflict',?,'upgrade@example.test','13900000003','Upgrade Member',1,1,NOW(),NOW())", [password_hash('UpgradeMember!1', PASSWORD_BCRYPT)]);
    $upgradeMemberId = (int) Db::query("SELECT id FROM fun_member WHERE username='upgrade-conflict'")[0]['id'];
    $upgradeExecuted = $migrationService->runDirectory($root . '/database/migrations', 'core');
    identityMysqlExpect($upgradeExecuted === ['095_identity_foundation'], '094 升级必须只执行 095');
    $upgradeAdminUserId = (int) IdentityAdminLink::forTenant(1)->where('admin_id', $upgradeAdminId)->value('user_id');
    $upgradeMemberUserId = (int) IdentityMemberLink::forTenant(1)->where('member_id', $upgradeMemberId)->value('user_id');
    identityMysqlExpect($upgradeAdminUserId > 0 && $upgradeMemberUserId > 0 && $upgradeAdminUserId !== $upgradeMemberUserId, '095 初始回填必须分别创建 admin/member identity');
    identityMysqlExpect((int) Db::query("SELECT COUNT(*) AS aggregate FROM fun_identity_migration_audit WHERE admin_id={$upgradeAdminId} AND member_id={$upgradeMemberId}")[0]['aggregate'] === 3, '095 必须报告 username/email/mobile 冲突');

    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    Db::execute("INSERT INTO fun_admin (username,password,email,mobile,real_name,dept_id,status,avatar,token,created_at,updated_at) VALUES ('same-user',?,'','13900000001','Admin Same',1,1,'','',NOW(),NOW())", [password_hash('AdminPass!1', PASSWORD_BCRYPT)]);
    $adminId = (int) Db::query("SELECT id FROM fun_admin WHERE username='same-user'")[0]['id'];
    Db::execute("INSERT INTO fun_member (username,password,email,mobile,nickname,status,level_id,created_at,updated_at) VALUES ('same-user',?,NULL,'13900000002','Member Same',1,1,NOW(),NOW())", [password_hash('MemberPass!1', PASSWORD_BCRYPT)]);
    $memberId = (int) Db::query("SELECT id FROM fun_member WHERE username='same-user'")[0]['id'];
    $admin = \app\console\model\Admin::find($adminId);
    $member = \app\console\model\Member::find($memberId);
    $adminUser = (new AdminIdentityAdapter())->sync($admin, [1]);
    $memberUser = (new MemberIdentityAdapter())->sync($member);
    identityMysqlExpect((int) $adminUser->id !== (int) $memberUser->id, 'admin/member 同 ID 或同用户名绝不能合并');
    identityMysqlExpect((string) $adminUser->public_id !== (string) $memberUser->public_id, 'public_id 必须独立 UUID');
    identityMysqlExpect(IdentityAdminLink::forTenant(1)->where('admin_id', $adminId)->count() === 1, '管理员关联缺失');
    identityMysqlExpect(IdentityMemberLink::forTenant(1)->where('member_id', $memberId)->count() === 1, '会员关联缺失');
    identityMysqlExpect($memberUser->email === null, '空邮箱必须规范为 NULL');
    identityMysqlExpect((string) $memberUser->avatar === '', '缺失头像必须保持可空语义');
    identityMysqlExpect((string) $memberUser->locale === 'zh-CN', 'identity 必须使用默认 locale');
    identityMysqlExpect((int) $memberUser->password_version === 1, 'identity 必须初始化密码版本');
    identityMysqlExpect((int) $memberUser->session_version === 1, 'identity 必须初始化会话版本');
    identityMysqlExpect(IdentityUserDepartment::forTenant(1)->where('user_id', (int) $adminUser->id)->where('department_id', 1)->count() === 1, '管理员部门未回填');
    $legacyLogin = (new MemberAuthService())->authenticate('same-user', 'MemberPass!1');
    identityMysqlExpect(($legacyLogin['id'] ?? 0) === $memberId, '会员旧登录行为必须保持可用');

    $credentials = new IdentityCredentialService();
    $legacyHash = password_hash('upgrade-me', PASSWORD_BCRYPT, ['cost' => 4]);
    $credentials->syncHash(1, (int) $memberUser->id, $legacyHash);
    $passwordVersion = (int) IdentityUser::forTenant(1)->where('id', (int) $memberUser->id)->value('password_version');
    identityMysqlExpect($credentials->verifyAndUpgrade(1, (int) $memberUser->id, 'upgrade-me'), '旧密码哈希验证失败');
    $upgraded = IdentityCredential::forTenant(1)->where('user_id', (int) $memberUser->id)->find();
    identityMysqlExpect((string) $upgraded->secret_hash !== $legacyHash, '密码验证后必须升级弱哈希');
    identityMysqlExpect((int) IdentityUser::forTenant(1)->where('id', (int) $memberUser->id)->value('password_version') === $passwordVersion + 1, '密码哈希升级必须递增 password_version');
    try {
        $credentials->syncHash(1, (int) $memberUser->id, 'plain-text');
        throw new RuntimeException('identity credential 不得接受明文密码');
    } catch (InvalidArgumentException) {
    }

    $sessionVersion = (int) IdentityUser::forTenant(1)->where('id', (int) $memberUser->id)->value('session_version');
    $member->status = 0;
    (new MemberIdentityAdapter())->sync($member);
    $disabledUser = IdentityUser::forTenant(1)->where('id', (int) $memberUser->id)->findOrFail();
    identityMysqlExpect((int) $disabledUser->session_version === $sessionVersion + 1, 'identity 状态变化必须递增 session_version');
    try {
        (new IdentityUserService())->assertCanCreateOidcSession(1, (int) $memberUser->id);
        throw new RuntimeException('禁用 identity 不得创建 OIDC session');
    } catch (DomainException) {
    }

    $before = (string) $admin->email;
    try {
        Db::transaction(function () use ($admin): void {
            $admin->save(['email' => 'rollback@example.test']);
            (new AdminIdentityAdapter())->sync($admin, [999999999]);
        });
    } catch (Throwable) {
    }
    identityMysqlExpect((string) \app\console\model\Admin::find($adminId)->email === $before, 'identity dual-write 失败必须回滚 legacy');

    echo "identity phase1 mysql tests passed; temporary database cleaned\n";
} finally {
    $app->config->set($serverConfig, 'database');
    $cleanup = Db::connect('mysql', true);
    $cleanup->execute('DROP DATABASE IF EXISTS ' . identityMysqlQuote($databaseName));
    $cleanup->execute('DROP DATABASE IF EXISTS ' . identityMysqlQuote($upgradeDatabaseName));
    identityRemoveDirectory($upgradeMigrationDirectory);
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
