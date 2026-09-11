<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\controller\base\AdminApiController;
use app\console\controller\identity\EnterpriseApplication;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;

function enterpriseApiExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$controller = new ReflectionClass(EnterpriseApplication::class);
enterpriseApiExpect($controller->isSubclassOf(AdminApiController::class), '企业应用 API 必须继承 AdminApiController');
enterpriseApiExpect($controller->getAttributes(Group::class)[0]->newInstance()->name === 'identity/applications', 'API Group 不匹配');
enterpriseApiExpect(($controller->getDefaultProperties()['middleware'] ?? []) === [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class], 'API 必须具备权限、CSRF、日志中间件');
$routes = [
    'index' => [Get::class, ''], 'detail' => [Get::class, ':id'], 'save' => [Post::class, ''], 'update' => [Put::class, ':id'],
    'delete' => [Delete::class, ':id'], 'publish' => [Post::class, ':id/publish'], 'disable' => [Post::class, ':id/disable'], 'launch' => [Get::class, ':id/launch'],
    'assignments' => [Get::class, ':id/assignments'], 'saveAssignments' => [Put::class, ':id/assignments'],
    'domains' => [Get::class, ':id/domains'], 'saveDomains' => [Put::class, ':id/domains'],
    'database' => [Get::class, ':id/database'], 'saveDatabase' => [Put::class, ':id/database'], 'health' => [Post::class, ':id/health'],
];
foreach ($routes as $method => [$attribute, $rule]) {
    enterpriseApiExpect($controller->hasMethod($method), '缺少 API 方法：' . $method);
    $attributes = $controller->getMethod($method)->getAttributes($attribute);
    enterpriseApiExpect(count($attributes) === 1 && $attributes[0]->newInstance()->rule === $rule, $method . ' 路由不匹配');
    if ($method !== 'index' && $method !== 'save') enterpriseApiExpect(count($controller->getMethod($method)->getAttributes(Pattern::class)) === 1, $method . ' 缺少 id Pattern');
}
$source = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/identity/EnterpriseApplication.php');
enterpriseApiExpect(!str_contains($source, 'Db::'), 'Controller 禁止直接访问数据库');
enterpriseApiExpect(str_contains($source, 'private function tenantId(): int'), 'Controller 必须显式解析 tenant');
enterpriseApiExpect(!str_contains($source, "header('X-Tenant-Id'"), 'tenant 禁止由客户端请求头任意覆盖');
enterpriseApiExpect(str_contains($source, 'AdminIdentityAdapter::TENANT_ID'), '当前管理端必须使用服务端固定租户上下文');
$permission = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/096_enterprise_application_center.sql');
foreach (['index','detail','save','update','delete','publish','disable','launch','assignments','saveassignments','domains','savedomains','database','savedatabase','health'] as $action) {
    enterpriseApiExpect(str_contains($permission, "console/identity.enterpriseapplication:{$action}"), '缺少 API 权限：' . $action);
}
enterpriseApiExpect(str_contains($permission, "'/applications'"), '应用中心应保持独立一级菜单，不得覆盖统一业务开发结构');

echo "identity phase2 console API tests passed\n";
