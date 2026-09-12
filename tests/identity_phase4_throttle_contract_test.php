<?php

declare(strict_types=1);

function phase4ThrottleExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$route = (string) file_get_contents(dirname(__DIR__) . '/app/identity/route/app.php');
phase4ThrottleExpect(str_contains($route, 'use think\\middleware\\Throttle;'), 'Identity 协议路由必须声明 Throttle 中间件');
phase4ThrottleExpect(str_contains($route, "'client_id'") && str_contains($route, "'__IP__'"), 'Identity 限流键必须按 client 与 IP 隔离');

$contracts = [
    'authorize' => ['get', 'GET', '20/m'],
    'decision' => ['post', 'POST', '10/m'],
    'token' => ['post', 'POST', '30/m'],
    'revoke' => ['post', 'POST', '30/m'],
    'introspect' => ['post', 'POST', '60/m'],
    'userinfo' => ['get', 'GET', '60/m'],
];
foreach ($contracts as $endpoint => [$routeMethod, $httpMethod, $rate]) {
    $pattern = sprintf(
        '/Route::%s\\(\'%s\',\\s*\'OAuth\\/%s\'\\)->middleware\\(Throttle::class,\\s*\\[\\s*\'visit_method\'\\s*=>\\s*\\[\'%s\'\\],\\s*\'visit_rate\'\\s*=>\\s*\'%s\',\\s*\'key\'\\s*=>\\s*\\$oauthThrottleKey,?\\s*\\]\\);/s',
        $routeMethod,
        $endpoint,
        $endpoint,
        $httpMethod,
        preg_quote($rate, '/'),
    );
    phase4ThrottleExpect(preg_match($pattern, $route) === 1, $endpoint . ' 必须声明分级 Throttle 限流 ' . $rate);
}

echo "identity phase4 throttle contract tests passed\n";
