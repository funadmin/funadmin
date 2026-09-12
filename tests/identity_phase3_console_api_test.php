<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

use app\console\controller\base\AdminApiController;
use app\console\controller\identity\EnterpriseApplication;
use app\console\controller\identity\OAuthClient;
use app\console\controller\identity\OidcSigningKey;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

function phase3ApiExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase3AssertController(string $class, string $group, array $routes): void
{
    $controller = new ReflectionClass($class);
    phase3ApiExpect($controller->isSubclassOf(AdminApiController::class), "{$class} 必须继承 AdminApiController");
    phase3ApiExpect($controller->getAttributes(Group::class)[0]->newInstance()->name === $group, "{$class} API Group 不匹配");
    phase3ApiExpect(($controller->getDefaultProperties()['middleware'] ?? []) === [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class], "{$class} 必须具备权限、CSRF、日志中间件");
    foreach ($routes as $method => [$attribute, $rule, $patterns]) {
        phase3ApiExpect($controller->hasMethod($method), "缺少 API 方法：{$method}");
        $attributes = $controller->getMethod($method)->getAttributes($attribute);
        phase3ApiExpect(count($attributes) === 1 && $attributes[0]->newInstance()->rule === $rule, "{$method} 路由不匹配");
        phase3ApiExpect(count($controller->getMethod($method)->getAttributes(Pattern::class)) === $patterns, "{$method} Pattern 数量不匹配");
    }
}

phase3AssertController(OAuthClient::class, 'identity/oauth-clients', [
    'index' => [Get::class, '', 0], 'detail' => [Get::class, ':id', 1], 'save' => [Post::class, '', 0],
    'update' => [Put::class, ':id', 1], 'delete' => [Delete::class, ':id', 1], 'disable' => [Post::class, ':id/disable', 1],
    'secrets' => [Get::class, ':id/secrets', 1], 'createSecret' => [Post::class, ':id/secrets', 1],
    'revokeSecret' => [Delete::class, ':id/secrets/:secretId', 2], 'replaceRedirectUris' => [Put::class, ':id/redirect-uris', 1],
    'replaceScopes' => [Put::class, ':id/scopes', 1], 'replaceGrants' => [Put::class, ':id/grants', 1],
    'revokeTokens' => [Post::class, ':id/revoke-tokens', 1],
]);
phase3AssertController(OidcSigningKey::class, 'identity/signing-keys', [
    'index' => [Get::class, '', 0], 'rotate' => [Post::class, 'rotate', 0],
]);

$runtimeResponseController = new class extends AdminApiController {
    public function __construct() {}

    public function invokeOkWithData(): Response
    {
        return $this->ok(data: ['runtime' => true]);
    }

    protected function responseHeaders(): array
    {
        return [];
    }
};
$runtimeResponse = $runtimeResponseController->invokeOkWithData();
$runtimePayload = json_decode((string) $runtimeResponse->getContent(), true, 512, JSON_THROW_ON_ERROR);
phase3ApiExpect(($runtimePayload['code'] ?? null) === 200, 'AdminJsonResponse::ok 运行时 code 必须为 200');
phase3ApiExpect(($runtimePayload['msg'] ?? null) === '操作成功', 'AdminJsonResponse::ok 运行时 msg 必须保留默认值');
phase3ApiExpect(($runtimePayload['data']['runtime'] ?? null) === true, 'AdminJsonResponse::ok 运行时 data 不得错位');

foreach ([EnterpriseApplication::class, OAuthClient::class, OidcSigningKey::class] as $class) {
    $source = (string) file_get_contents((new ReflectionClass($class))->getFileName());
    phase3ApiExpect(!str_contains($source, 'Db::'), "{$class} Controller 禁止直接访问数据库");
    phase3ApiExpect(str_contains($source, 'AdminIdentityAdapter::TENANT_ID'), "{$class} 必须使用服务端 tenant 上下文");
    phase3ApiExpect(!str_contains($source, "header('X-Tenant-Id'"), "{$class} 不得信任客户端 tenant header");
    phase3ApiExpect(preg_match('/\\$this->ok\\((?!\\s*(?:data|msg|code):)/', $source) !== 1, "{$class} 的 ok 调用必须使用 data/msg/code 命名参数");
    phase3ApiExpect(preg_match('/\\$this->fail\\((?!\\s*(?:data|msg|code):)/', $source) !== 1, "{$class} 的 fail 调用必须使用 data/msg/code 命名参数");
}
$oauthSource = (string) file_get_contents((new ReflectionClass(OAuthClient::class))->getFileName());
phase3ApiExpect(str_contains($oauthSource, "code: 501"), 'P4 前 revoke tokens 必须明确返回 501');

$migration = dirname(__DIR__) . '/database/migrations/098_oauth_client_console.sql';
phase3ApiExpect(is_file($migration), '权限与菜单必须使用下一个空闲 forward migration 098');
$sql = (string) file_get_contents($migration);
$withoutComments = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
phase3ApiExpect(!preg_match('/(?:^|;)\s*(?:DROP|TRUNCATE|RENAME|DELETE|UPDATE)\s/mi', $withoutComments), '098 必须 forward-only');
foreach (['oauth-client', 'signing-key', 'console/identity.oauthclient:', 'console/identity.oidcsigningkey:'] as $marker) {
    phase3ApiExpect(str_contains($sql, $marker), "098 缺少权限或应用中心菜单标记：{$marker}");
}
phase3ApiExpect(str_contains($sql, "href`='\/applications'" ) || str_contains($sql, "href`='/applications'"), 'OAuth Client 菜单必须整合到应用中心');

echo "identity phase3 console API tests passed\n";
