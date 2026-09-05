<?php

declare(strict_types=1);

$baseUrl = rtrim((string) ($argv[1] ?? getenv('API_BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
$testUsername = (string) getenv('API_TEST_USERNAME');
$testPassword = (string) getenv('API_TEST_PASSWORD');

function httpExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @return array{status: int, body: array<string, mixed>}
 */
function apiRequest(string $method, string $url, array $data = [], array $headers = []): array
{
    $headerLines = array_merge(['Accept: application/json'], $headers);
    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ];
    if ($data !== []) {
        $options['http']['header'] .= "\r\nContent-Type: application/json";
        $options['http']['content'] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    $body = file_get_contents($url, false, stream_context_create($options));
    httpExpect($body !== false, "请求失败：{$method} {$url}");
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    return ['status' => (int) ($matches[1] ?? 0), 'body' => $decoded];
}

function expectEnvelope(array $response, int $status): void
{
    httpExpect($response['status'] === $status, "HTTP 状态应为 {$status}，实际为 {$response['status']}");
    httpExpect(($response['body']['code'] ?? null) === $status, "响应 code 应为 {$status}");
    httpExpect(array_key_exists('msg', $response['body']), '响应必须包含 msg');
    httpExpect(array_key_exists('time', $response['body']), '响应必须包含 time');
    httpExpect(array_key_exists('data', $response['body']), '响应必须包含 data');
}

$unauthorized = apiRequest('GET', $baseUrl . '/api/v2/member');
expectEnvelope($unauthorized, 401);

$invalidLogin = apiRequest('POST', $baseUrl . '/api/v2/token', [
    'username' => '',
    'password' => '',
]);
expectEnvelope($invalidLogin, 400);

$wrongLogin = apiRequest('POST', $baseUrl . '/api/v2/token', [
    'username' => '__api_test_missing_user__',
    'password' => 'invalid-password',
]);
expectEnvelope($wrongLogin, 401);

$invalidRefresh = apiRequest('POST', $baseUrl . '/api/v2/token/refresh', [
    'refresh_token' => 'invalid-token',
]);
expectEnvelope($invalidRefresh, 401);

if ($testUsername !== '' && $testPassword !== '') {
    $login = apiRequest('POST', $baseUrl . '/api/v2/token', [
        'username' => $testUsername,
        'password' => $testPassword,
    ]);
    expectEnvelope($login, 200);
    $accessToken = (string) ($login['body']['data']['access_token'] ?? '');
    $refreshToken = (string) ($login['body']['data']['refresh_token'] ?? '');
    httpExpect($accessToken !== '' && $refreshToken !== '', '登录成功必须返回两个 Token');

    $userinfo = apiRequest('GET', $baseUrl . '/api/v2/member', [], [
        'Authorization: Bearer ' . $accessToken,
    ]);
    expectEnvelope($userinfo, 200);
    httpExpect(isset($userinfo['body']['data']['user']['id']), '用户信息必须包含会员 ID');

    $refresh = apiRequest('POST', $baseUrl . '/api/v2/token/refresh', [
        'refresh_token' => $refreshToken,
    ]);
    expectEnvelope($refresh, 200);
    httpExpect(!empty($refresh['body']['data']['access_token']), '刷新接口必须返回 Access Token');
} else {
    echo "api authenticated tests: SKIPPED（未设置 API_TEST_USERNAME/API_TEST_PASSWORD）\n";
}

echo "api http tests: PASS\n";
