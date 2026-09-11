<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityCredential;
use app\common\model\identity\IdentityMemberLink;
use app\common\model\identity\IdentityUser;
use app\common\model\identity\IdentityUserDepartment;
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

$root = dirname(__DIR__);
$app = new App($root);
$app->initialize();
$original = (array) config('database');
$mysql = (array) $original['connections']['mysql'];
$databaseName = 'funadmin_identity_' . bin2hex(random_bytes(6));
$serverConfig = $original;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);

try {
    $server->execute('CREATE DATABASE ' . identityMysqlQuote($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $databaseName;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);

    $migrationService = new MigrationService();
    $executed = $migrationService->runDirectory($root . '/database/migrations', 'core');
    identityMysqlExpect(in_array('095_identity_foundation', $executed, true), '空库 migration 链必须执行 095');
    identityMysqlExpect($migrationService->runDirectory($root . '/database/migrations', 'core') === [], '重复 migration 必须幂等跳过');
    Db::execute("DELETE FROM fun_system_migration WHERE scope='core' AND version='095_identity_foundation'");
    $upgradeExecuted = $migrationService->runDirectory($root . '/database/migrations', 'core');
    identityMysqlExpect($upgradeExecuted === ['095_identity_foundation'], '094 升级必须只执行 095');

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

    IdentityUser::forTenant(1)->where('id', (int) $memberUser->id)->update(['status' => 0]);
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
    Db::connect('mysql', true)->execute('DROP DATABASE IF EXISTS ' . identityMysqlQuote($databaseName));
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
}
