<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\model\identity\ApplicationAssignment;
use app\common\model\identity\ApplicationDomain;
use app\common\model\identity\EnterpriseApplication;
use app\common\model\identity\IdentityAdminLink;
use app\common\model\identity\IdentityUserDepartment;
use app\common\service\MigrationService;
use app\common\service\identity\ApplicationAssignmentService;
use app\common\service\identity\ApplicationCatalogService;
use app\common\service\identity\ApplicationDomainService;
use app\common\service\identity\EnterpriseApplicationUrlPolicy;
use app\console\controller\identity\EnterpriseApplication as EnterpriseApplicationController;
use app\console\service\RoleScopeService;
use think\App;
use think\facade\Db;
use think\facade\Session;
use think\Response;

function phase2MysqlExpect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function phase2MysqlQuote(string $identifier): string { phase2MysqlExpect((bool) preg_match('/^[a-z0-9_]+$/', $identifier), '数据库名非法'); return '`' . $identifier . '`'; }
function phase2Launch(EnterpriseApplicationController $controller, App $app, int $applicationId, array $spoofed = []): Response
{
    $app->request->withGet($spoofed)->withRoute([]);
    return $controller->launch($applicationId);
}
function phase2LaunchData(Response $response): array
{
    $payload = $response->getData();
    return is_string($payload) ? json_decode($payload, true, 512, JSON_THROW_ON_ERROR) : (array) $payload;
}

$root = dirname(__DIR__); $app = new App($root); $app->initialize();
$original = (array) config('database'); $database = 'funadmin_identity_phase2_' . bin2hex(random_bytes(5));
$serverConfig = $original; $serverConfig['connections']['mysql']['database'] = ''; $app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);
try {
    $server->execute('CREATE DATABASE ' . phase2MysqlQuote($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original; $isolated['connections']['mysql']['database'] = $database; $app->config->set($isolated, 'database'); Db::connect('mysql', true);
    $migration = new MigrationService(); $executed = $migration->runDirectory($root . '/database/migrations', 'core');
    phase2MysqlExpect(in_array('096_enterprise_application_center', $executed, true), '隔离 MySQL 必须执行 096');
    phase2MysqlExpect($migration->runDirectory($root . '/database/migrations', 'core') === [], '096 重复执行必须幂等跳过');
    Db::execute("INSERT INTO fun_identity_tenant (public_id,code,name,status,created_at,updated_at) VALUES (UUID(),'other','Other',1,NOW(),NOW())");
    $tenant2 = (int) Db::query("SELECT id FROM fun_identity_tenant WHERE code='other'")[0]['id'];
    $policy = new EnterpriseApplicationUrlPolicy(static fn (string $host): array => ['93.184.216.34']);
    $catalog = new ApplicationCatalogService($policy);
    $app1 = $catalog->save(1, ['code' => 'crm', 'name' => 'CRM', 'runtimeType' => 'internal', 'launchUrl' => '/crm'], null, false, 'localhost');
    phase2MysqlExpect((int) EnterpriseApplication::forTenant(1)->where('id', $app1['id'])->count() === 1, '应用必须绑定 tenant');
    phase2MysqlExpect((int) EnterpriseApplication::forTenant($tenant2)->where('id', $app1['id'])->count() === 0, '跨 tenant 查询必须拒绝');
    try { $catalog->detail($tenant2, (int) $app1['id']); throw new RuntimeException('跨 tenant 详情必须拒绝'); } catch (DomainException) {}
    (new ApplicationAssignmentService())->replace(1, (int) $app1['id'], [['subjectType' => 'all', 'effect' => 'allow'], ['subjectType' => 'user', 'subjectId' => 7, 'effect' => 'deny']]);
    $rows = ApplicationAssignment::forTenant(1)->where('application_id', $app1['id'])->select()->toArray();
    phase2MysqlExpect(!ApplicationAssignmentService::decide($rows, 7, [], []), 'MySQL assignment deny 必须优先');
    try { Db::execute("INSERT INTO fun_application_assignment (tenant_id,application_id,subject_type,subject_id,effect,status) VALUES (1,?,'all',9,'allow',1)", [$app1['id']]); throw new RuntimeException('数据库约束必须拒绝非法 subject'); } catch (Throwable) {}

    $adminId = 1;
    $identityUserId = (int) IdentityAdminLink::forTenant(1)->where('admin_id', $adminId)->value('user_id');
    phase2MysqlExpect($identityUserId > 0, '真实 Controller 测试需要后台管理员 identity link');
    Session::set('admin', ['id' => $adminId, 'tenant_id' => $tenant2, 'identity_user_id' => 999999, 'role_ids' => [999999], 'department_ids' => [999999]]);
    $roleIds = (new RoleScopeService())->adminRoleIds($adminId);
    phase2MysqlExpect($roleIds !== [], '真实 Controller 测试需要从 RoleScopeService 解析角色');
    $departmentId = (int) IdentityUserDepartment::forTenant(1)->where('user_id', $identityUserId)->value('department_id');
    if ($departmentId <= 0) {
        $departmentId = (int) Db::query('SELECT id FROM fun_department ORDER BY id LIMIT 1')[0]['id'];
        IdentityUserDepartment::create(['tenant_id' => 1, 'user_id' => $identityUserId, 'department_id' => $departmentId, 'is_primary' => 1]);
    }

    $assignmentService = new ApplicationAssignmentService();
    $launchCases = [
        ['user', 'internal', '/apps/user', $identityUserId],
        ['department', 'plugin', '/plugin/shop/index', $departmentId],
        ['role', 'standalone', 'https://app.example.com/role', $roleIds[0]],
        ['all', 'internal', '/apps/all', null],
    ];
    $controller = new EnterpriseApplicationController($app);
    foreach ($launchCases as [$subjectType, $runtimeType, $launchUrl, $subjectId]) {
        $application = $catalog->save(1, ['code' => 'allow-' . $subjectType, 'name' => 'Allow ' . $subjectType, 'runtimeType' => $runtimeType, 'launchUrl' => $launchUrl], null, false, 'localhost');
        $catalog->publish(1, (int) $application['id']);
        $assignment = ['subjectType' => $subjectType, 'effect' => 'allow'];
        if ($subjectId !== null) $assignment['subjectId'] = $subjectId;
        $assignmentService->replace(1, (int) $application['id'], [$assignment]);
        $response = phase2Launch($controller, $app, (int) $application['id'], ['userId' => 999999, 'roleIds' => [999999], 'departmentIds' => [999999], 'tenant_id' => $tenant2]);
        phase2MysqlExpect($response->getCode() === 200, "{$runtimeType} 的 {$subjectType} allow 必须放行");
        phase2MysqlExpect((phase2LaunchData($response)['data']['launchUrl'] ?? '') === $launchUrl, "{$runtimeType} 启动结果错误");
    }

    $deniedApplication = $catalog->save(1, ['code' => 'deny-wins', 'name' => 'Deny wins', 'runtimeType' => 'standalone', 'launchUrl' => 'https://app.example.com/denied'], null, false, 'localhost');
    $catalog->publish(1, (int) $deniedApplication['id']);
    $assignmentService->replace(1, (int) $deniedApplication['id'], [
        ['subjectType' => 'all', 'effect' => 'allow'],
        ['subjectType' => 'user', 'subjectId' => $identityUserId, 'effect' => 'deny'],
    ]);
    phase2MysqlExpect(phase2Launch($controller, $app, (int) $deniedApplication['id'])->getCode() === 403, '显式 deny 必须覆盖 all allow，超级管理员也不得绕过');

    $tenantApplication = $catalog->save(1, ['code' => 'tenant-default', 'name' => 'Tenant default', 'runtimeType' => 'internal', 'launchUrl' => '/apps/tenant', 'visibility' => 'tenant'], null, false, 'localhost');
    $catalog->publish(1, (int) $tenantApplication['id']);
    phase2MysqlExpect(phase2Launch($controller, $app, (int) $tenantApplication['id'])->getCode() === 200, 'tenant 应默认允许同租户 active identity，无需 allow assignment');

    $publicApplication = $catalog->save(1, ['code' => 'public-default', 'name' => 'Public default', 'runtimeType' => 'internal', 'launchUrl' => '/apps/public', 'visibility' => 'public'], null, false, 'localhost');
    $catalog->publish(1, (int) $publicApplication['id']);
    phase2MysqlExpect(phase2Launch($controller, $app, (int) $publicApplication['id'])->getCode() === 200, 'public 应允许同租户后台登录 identity，无需 allow assignment');

    $privateOwner = $catalog->save(1, ['code' => 'private-owner', 'name' => 'Private owner', 'runtimeType' => 'internal', 'launchUrl' => '/apps/private-owner', 'visibility' => 'private', 'ownerIdentityUserId' => $identityUserId], null, false, 'localhost');
    $catalog->publish(1, (int) $privateOwner['id']);
    phase2MysqlExpect(phase2Launch($controller, $app, (int) $privateOwner['id'])->getCode() === 200, 'private owner 必须允许进入');

    $privateUserAllow = $catalog->save(1, ['code' => 'private-user', 'name' => 'Private user', 'runtimeType' => 'internal', 'launchUrl' => '/apps/private-user', 'visibility' => 'private'], null, false, 'localhost');
    $catalog->publish(1, (int) $privateUserAllow['id']);
    $assignmentService->replace(1, (int) $privateUserAllow['id'], [['subjectType' => 'user', 'subjectId' => $identityUserId, 'effect' => 'allow']]);
    phase2MysqlExpect(phase2Launch($controller, $app, (int) $privateUserAllow['id'])->getCode() === 200, 'private 显式 user allow 必须允许进入');

    foreach (['all', 'department', 'role'] as $subjectType) {
        $private = $catalog->save(1, ['code' => 'private-' . $subjectType, 'name' => 'Private ' . $subjectType, 'runtimeType' => 'internal', 'launchUrl' => '/apps/private-' . $subjectType, 'visibility' => 'private'], null, false, 'localhost');
        $catalog->publish(1, (int) $private['id']);
        $assignment = ['subjectType' => $subjectType, 'effect' => 'allow'];
        if ($subjectType === 'department') $assignment['subjectId'] = $departmentId;
        if ($subjectType === 'role') $assignment['subjectId'] = $roleIds[0];
        $assignmentService->replace(1, (int) $private['id'], [$assignment]);
        phase2MysqlExpect(phase2Launch($controller, $app, (int) $private['id'])->getCode() === 403, "private 不得被 {$subjectType} allow 越权放行");
    }

    $privateWithoutOwner = $catalog->save(1, ['code' => 'private-none', 'name' => 'Private none', 'runtimeType' => 'internal', 'launchUrl' => '/apps/private-none', 'visibility' => 'private'], null, false, 'localhost');
    $catalog->publish(1, (int) $privateWithoutOwner['id']);
    phase2MysqlExpect(phase2Launch($controller, $app, (int) $privateWithoutOwner['id'])->getCode() === 403, '无 owner 且无显式 user allow 的 private 必须拒绝');

    foreach (['private' => $privateOwner, 'tenant' => $tenantApplication, 'public' => $publicApplication] as $visibility => $application) {
        $assignmentService->replace(1, (int) $application['id'], [['subjectType' => 'user', 'subjectId' => $identityUserId, 'effect' => 'deny']]);
        phase2MysqlExpect(phase2Launch($controller, $app, (int) $application['id'])->getCode() === 403, "{$visibility} 的显式 deny 必须优先");
    }

    Db::execute('UPDATE fun_identity_user SET status=0 WHERE tenant_id=1 AND id=?', [$identityUserId]);
    phase2MysqlExpect(phase2Launch($controller, $app, (int) $publicApplication['id'])->getCode() === 403, '未启用 identity 不得按 tenant/public 默认准入');
    Db::execute('UPDATE fun_identity_user SET status=1 WHERE tenant_id=1 AND id=?', [$identityUserId]);

    $tenant2Application = $catalog->save($tenant2, ['code' => 'other-tenant', 'name' => 'Other tenant', 'runtimeType' => 'internal', 'launchUrl' => '/apps/other'], null, false, 'localhost');
    $catalog->publish($tenant2, (int) $tenant2Application['id']);
    $assignmentService->replace($tenant2, (int) $tenant2Application['id'], [['subjectType' => 'all', 'effect' => 'allow']]);
    phase2MysqlExpect(phase2Launch($controller, $app, (int) $tenant2Application['id'], ['tenant_id' => $tenant2])->getCode() === 403, '客户端伪造 tenant 不得跨租户启动应用');

    $domains = new ApplicationDomainService($policy);
    $savedDomains = $domains->replace(1, (int) $app1['id'], [[
        'identityCallback' => 'https://app.example.com/identity',
        'logoutCallback' => 'https://app.example.com/logout',
    ]], false, []);
    $domainId = (int) $savedDomains[0]['id'];
    phase2MysqlExpect($domains->replace(1, (int) $app1['id'], [], false, [$domainId]) === [], '匹配快照的空域名必须显式删除');
    $concurrent = ApplicationDomain::create([
        'tenant_id' => 1, 'application_id' => $app1['id'], 'domain_type' => 'web', 'scheme' => 'https',
        'host' => 'app.example.com', 'port' => 443, 'path' => '/concurrent', 'identity_callback_path' => '/concurrent',
        'logout_callback_path' => '/logout', 'is_primary' => 1, 'status' => 1,
    ]);
    try {
        $domains->replace(1, (int) $app1['id'], [], false, []);
        throw new RuntimeException('陈旧空快照必须检测并发冲突');
    } catch (DomainException) {
        phase2MysqlExpect((int) ApplicationDomain::forTenant(1)->where('id', (int) $concurrent->id)->count() === 1, '并发新增域名不得被误删');
    }
    phase2MysqlExpect((int) Db::query("SELECT COUNT(*) aggregate FROM fun_admin_menu WHERE source_name='enterprise_application_center'")[0]['aggregate'] === 2, '应用中心菜单必须幂等且独立');
    echo "identity phase2 mysql tests passed; temporary database cleaned\n";
} finally {
    $app->config->set($serverConfig, 'database'); $cleanup = Db::connect('mysql', true); $cleanup->execute('DROP DATABASE IF EXISTS ' . phase2MysqlQuote($database));
    $app->config->set($original, 'database'); Db::connect('mysql', true);
}
