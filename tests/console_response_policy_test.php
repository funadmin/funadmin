<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

use app\ExceptionHandle;
use app\common\service\identity\ApplicationCatalogService;
use app\common\service\identity\EnterpriseApplicationUrlPolicy;
use app\console\middleware\ConsoleResponsePolicy;
use think\App;
use think\Request;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;

function consoleExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$app = new App();
$app->http->name('console');
$request = (new Request())->withHeader(['accept' => 'application/json']);
$handler = new ExceptionHandle($app);
$response = $handler->render($request, new ValidateException('名称不能为空'));
consoleExpect($response->getCode() === 200, 'RED: console 参数验证必须 HTTP 200');
consoleExpect($response->getData()['code'] === 422 && $response->getData()['msg'] === '名称不能为空', '验证失败必须保留明确业务消息');

$middleware = new ConsoleResponsePolicy($app);
$app->bind(\think\exception\Handle::class, ExceptionHandle::class);
$app->middleware->import([ConsoleResponsePolicy::class], 'console-test');
$pipelineResponse = $app->middleware->pipeline('console-test')->send($request)->then(function () {
    throw new ValidateException('管道验证失败');
});
consoleExpect($pipelineResponse->getCode() === 200 && $pipelineResponse->getData()['msg'] === '管道验证失败', '框架管道异常出口必须应用策略');
consoleExpect(in_array(ConsoleResponsePolicy::class, require dirname(__DIR__) . '/app/console/middleware.php', true), '策略必须注册在 console 应用中间件');
foreach ([400, 422] as $status) {
    $body = ['code' => $status, 'msg' => '明确业务失败', 'data' => ['field' => 'name']];
    $original = json($body, $status)->header(['X-CSRF-TOKEN' => 'next-token', 'Cache-Control' => 'no-store']);
    $result = $middleware->handle($request, fn () => $original);
    consoleExpect($result === $original && $result->getCode() === 200 && $result->getData() === $body, '直接 JSON 必须只改变 HTTP 状态');
    consoleExpect($result->getHeader('X-CSRF-TOKEN') === 'next-token', '保留 CSRF 响应头');
    $exceptionResponse = $handler->render($request, new HttpResponseException(json($body, $status)));
    consoleExpect($exceptionResponse->getCode() === 200, '响应异常同样归一化');
}
foreach ([401, 403, 404, 405, 409, 410, 413, 415, 419, 429, 500, 502, 503, 504] as $status) {
    $response = json(['code' => $status, 'msg' => '保留状态'], $status);
    consoleExpect($middleware->handle($request, fn () => $response)->getCode() === $status, '保留状态 ' . $status);
}
$rawFailure = json(['code' => 500, 'msg' => 'secret SQL', 'data' => ['password' => 'secret']], 500);
consoleExpect(!str_contains($middleware->handle($request, fn () => $rawFailure)->getContent(), 'secret'), '局部 catch 生成的 500 也必须脱敏');
foreach ([400, 422, 401, 403, 404, 409, 429, 503] as $status) {
    $response = $middleware->handle($request, fn () => $handler->render($request, new HttpException($status, '协议错误')));
    consoleExpect($response->getCode() === $status, 'HttpException 不得转成普通业务状态');
}
foreach ([new InvalidArgumentException('secret SQL'), new DomainException('secret path'), new RuntimeException('secret token')] as $exception) {
    $response = $handler->render($request, $exception);
    consoleExpect($response->getCode() === 500 && !str_contains($response->getContent(), 'secret'), '未知异常必须为安全 500');
}
$domainService = new \app\common\service\identity\ApplicationDomainService(new EnterpriseApplicationUrlPolicy(static fn () => ['93.184.216.34']));
try {
    $domainService->normalizeCallbacks(['identityCallback' => 'https://one.example/cb', 'logoutCallback' => 'https://two.example/cb']);
    throw new RuntimeException('必须拒绝不同源回调');
} catch (InvalidArgumentException $exception) {
    consoleExpect($handler->render($request, $exception)->getCode() === 200, '回调同源校验必须为明确业务异常');
}
$conflict = new \app\common\service\identity\IdentityResourceException('域名版本冲突', 409);
consoleExpect($handler->render($request, $conflict)->getCode() === 409, '身份资源版本冲突保持 409');
$missing = new \app\common\service\identity\IdentityResourceException('应用不存在', 404);
consoleExpect($handler->render($request, $missing)->getCode() === 404, '身份资源缺失保持 404');
$policy = new EnterpriseApplicationUrlPolicy(static fn () => ['10.0.0.1']);
foreach ([
    fn () => $policy->normalizeLaunchUrl('standalone', 'https://private.example'),
    fn () => (new ApplicationCatalogService($policy))->save(1, ['runtimeType' => 'standalone', 'launchUrl' => 'https://private.example']),
] as $operation) {
    try {
        $operation();
        throw new RuntimeException('必须拒绝私网地址');
    } catch (InvalidArgumentException $exception) {
        $response = $handler->render($request, $exception);
        consoleExpect($response->getCode() === 200 && $response->getData()['code'] === 422, 'URL 明确异常必须作为业务失败');
        consoleExpect(str_contains($response->getData()['msg'], '私网'), '保留 URL 校验具体消息');
    }
}
foreach ([
    json(['error' => 'invalid_request'], 400),
    json(['code' => 200, 'msg' => '非业务失败'], 400),
    response('event: error', 400)->header(['Content-Type' => 'text/event-stream']),
    response('file', 422)->header(['Content-Disposition' => 'attachment; filename=x']),
    redirect('/login'),
] as $original) {
    $status = $original->getCode();
    consoleExpect($middleware->handle($request, fn () => $original)->getCode() === $status, '非业务响应不变');
}
$sse = (new Request())->withHeader(['accept' => 'text/event-stream']);
consoleExpect($middleware->handle($sse, fn () => json(['code' => 400, 'msg' => '流失败'], 400))->getCode() === 400, 'SSE 握手失败不变');
foreach (['api', 'identity'] as $name) {
    $app->http->name($name);
    $original = json(['code' => 422, 'msg' => '公开接口'], 422);
    consoleExpect($middleware->handle($request, fn () => $original)->getCode() === 422, '非 console 隔离');
    consoleExpect($handler->render($request, new HttpResponseException($original)) === $original, '非 console 响应异常不变');
}
echo "console response policy tests: PASS\n";
