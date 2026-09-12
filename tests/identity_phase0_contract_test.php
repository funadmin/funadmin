<?php

declare(strict_types=1);

use app\identity\http\Psr7Adapter;
use app\identity\service\IssuerService;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CodeChallengeVerifiers\S256Verifier;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use think\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

function identityPhase0Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function identityPhase0Method(string $class, string $method, array $parameters): void
{
    $reflection = new ReflectionMethod($class, $method);
    $actual = [];
    foreach ($reflection->getParameters() as $parameter) {
        $actual[] = ($parameter->getType()?->__toString() ?? '') . ' $' . $parameter->getName();
    }
    identityPhase0Expect($actual === $parameters, $class . '::' . $method . ' 公开签名发生变化');
}

$installed = Composer\InstalledVersions::class;
identityPhase0Expect($installed::getPrettyVersion('league/oauth2-server') === '8.5.5', 'oauth2-server 必须锁定 8.5.5');
identityPhase0Expect($installed::getPrettyVersion('lcobucci/jwt') === '4.3.0', 'lcobucci/jwt 必须解析为 4.3.0');
identityPhase0Expect($installed::getPrettyVersion('nyholm/psr7') === '1.8.2', 'nyholm/psr7 必须解析为 1.8.2');

identityPhase0Method(AuthorizationServer::class, 'validateAuthorizationRequest', [ServerRequestInterface::class . ' $request']);
identityPhase0Method(AuthorizationServer::class, 'completeAuthorizationRequest', ['League\\OAuth2\\Server\\RequestTypes\\AuthorizationRequest $authRequest', ResponseInterface::class . ' $response']);
identityPhase0Method(AuthorizationServer::class, 'respondToAccessTokenRequest', [ServerRequestInterface::class . ' $request', ResponseInterface::class . ' $response']);

$verifier = str_repeat('a', 43);
$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
identityPhase0Expect((new S256Verifier())->getMethod() === 'S256', 'PKCE 方法必须为 S256');
identityPhase0Expect((new S256Verifier())->verifyCodeChallenge($verifier, $challenge), 'PKCE S256 RFC 7636 向量验证失败');
identityPhase0Expect(!(new S256Verifier())->verifyCodeChallenge($verifier, $challenge . 'x'), 'PKCE 必须拒绝错误 challenge');
identityPhase0Expect((new ReflectionClass(AuthCodeGrant::class))->hasMethod('disableRequireCodeChallengeForPublicClients'), 'public client PKCE 默认要求 API 缺失');

$issuer = new IssuerService('https://identity.example.test/');
identityPhase0Expect($issuer->getIssuer() === 'https://identity.example.test', 'issuer 规范化错误');
foreach (['evil.example', 'forwarded.example', '/console', '/api', '/identity'] as $untrustedInput) {
    $_SERVER['HTTP_HOST'] = $untrustedInput;
    $_SERVER['HTTP_X_FORWARDED_HOST'] = $untrustedInput;
    identityPhase0Expect($issuer->getIssuer() === 'https://identity.example.test', '请求信息不得改变 issuer');
}

$adapter = new Psr7Adapter(new Psr17Factory());
identityPhase0Expect($adapter instanceof Psr7Adapter, 'PSR-7 双向 adapter 无法构造');

$uploadPath = tempnam(sys_get_temp_dir(), 'identity-phase0-upload-');
identityPhase0Expect(is_string($uploadPath), '无法创建上传文件夹具');
file_put_contents($uploadPath, 'upload-body');
try {
    $thinkRequest = (new Request())
        ->withServer([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/identity/token?scope=openid',
            'HTTP_HOST' => 'identity.example.test',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ])
        ->withHeader(['content-type' => 'application/x-www-form-urlencoded'])
        ->withGet(['scope' => 'openid'])
        ->withPost(['grant_type' => 'authorization_code'])
        ->withCookie(['identity_session' => 'cookie-value'])
        ->withFiles([
            'assertion' => [
                'name' => 'assertion.txt',
                'type' => 'text/plain',
                'tmp_name' => $uploadPath,
                'error' => UPLOAD_ERR_OK,
                'size' => 11,
            ],
        ])
        ->withInput('grant_type=authorization_code');

    $psrRequest = $adapter->toPsrRequest($thinkRequest);
    identityPhase0Expect($psrRequest->getServerParams()['REQUEST_METHOD'] === 'POST', 'server params 必须完整保留');
    identityPhase0Expect($psrRequest->getQueryParams() === ['scope' => 'openid'], 'query params 适配错误');
    identityPhase0Expect($psrRequest->getCookieParams() === ['identity_session' => 'cookie-value'], 'cookie params 适配错误');
    identityPhase0Expect($psrRequest->getParsedBody() === ['grant_type' => 'authorization_code'], 'parsed body 适配错误');
    identityPhase0Expect((string) $psrRequest->getBody() === 'grant_type=authorization_code', '原始 body 适配错误');
    $uploadedFile = $psrRequest->getUploadedFiles()['assertion'] ?? null;
    identityPhase0Expect($uploadedFile instanceof Psr\Http\Message\UploadedFileInterface, 'upload 必须转换为 PSR UploadedFileInterface');
    identityPhase0Expect($uploadedFile->getClientFilename() === 'assertion.txt', 'upload 客户端文件名适配错误');
    identityPhase0Expect($uploadedFile->getClientMediaType() === 'text/plain', 'upload MIME 适配错误');
    identityPhase0Expect((string) $uploadedFile->getStream() === 'upload-body', 'upload 文件流适配错误');
} finally {
    unlink($uploadPath);
}

$psrResponse = (new PsrResponse(401, [], '{"error":"invalid_client"}'))
    ->withHeader('Content-Type', 'application/problem+json')
    ->withAddedHeader('Set-Cookie', 'sid=alpha; Expires=Wed, 21 Oct 2037 07:28:00 GMT; Path=/; HttpOnly; SameSite=Lax')
    ->withAddedHeader('Set-Cookie', 'csrf=beta; Path=/identity; Secure; SameSite=Strict')
    ->withAddedHeader('WWW-Authenticate', 'Basic realm="identity"')
    ->withAddedHeader('WWW-Authenticate', 'Bearer realm="identity", error="invalid_token"');
$thinkResponse = $adapter->toThinkResponse($psrResponse);
identityPhase0Expect($thinkResponse->getCode() === 401, '响应 status 适配错误');
identityPhase0Expect($thinkResponse->getContent() === '{"error":"invalid_client"}', '响应 body 适配错误');
identityPhase0Expect($thinkResponse->getHeader('Content-Type') === 'application/problem+json', '响应 Content-Type 不得被默认 html 类型覆盖');
identityPhase0Expect($thinkResponse->getHeader('Set-Cookie') === [
    'sid=alpha; Expires=Wed, 21 Oct 2037 07:28:00 GMT; Path=/; HttpOnly; SameSite=Lax',
    'csrf=beta; Path=/identity; Secure; SameSite=Strict',
], '多条 Set-Cookie 必须原样保留，禁止逗号合并');
identityPhase0Expect($thinkResponse->getHeader('WWW-Authenticate') === [
    'Basic realm="identity"',
    'Bearer realm="identity", error="invalid_token"',
], '重复响应头必须按 PSR 字段逐条 append');

$root = dirname(__DIR__);
$route = (string) file_get_contents($root . '/app/identity/route/app.php');
$ctrHelper = (string) file_get_contents($root . '/app/common/helper/CtrHelper.php');
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
identityPhase0Expect(!str_contains($ctrHelper, 'Doctrine\\\\Common\\\\Annotations'), 'CtrHelper 不得引用 Doctrine 注解');
identityPhase0Expect(!str_contains($ctrHelper, 'app\\\\common\\\\annotation'), 'CtrHelper 不得引用不存在的 common annotation 类');
identityPhase0Expect(!isset($composer['require']['doctrine/annotations']), 'composer.json 不得声明 doctrine/annotations');
identityPhase0Expect(!str_contains($route, 'Doctrine\\\\Common\\\\Annotations'), 'Attribute 路由运行时不得依赖 Doctrine 注解');
identityPhase0Expect(!str_contains($route, 'app\\\\common\\\\annotation'), 'Attribute 路由运行时不得依赖不存在的 common annotation 类');
identityPhase0Expect(str_contains($route, "Route::get('status'"), 'identity 多应用状态路由缺失');
identityPhase0Expect(str_contains($route, "Route::get('logout'"), 'Phase7 完成后 identity 必须声明 logout');

echo "identity phase0 contract tests passed\n";