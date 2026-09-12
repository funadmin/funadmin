<?php

declare(strict_types=1);

function phase9ContractExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$fixture = $root . '/tests/fixtures/identity-phase9-rp.php';
$journey = $root . '/tests/identity_phase9_cross_origin_journey.php';

phase9ContractExpect(is_file($fixture), '缺少 Phase9 standalone RP fixture');
phase9ContractExpect(is_file($journey), '缺少 Phase9 跨域旅程编排器');

$fixtureSource = (string) file_get_contents($fixture);
foreach (['code_verifier', 'code_challenge', 'state', 'nonce', 'jwks_uri', 'JWT::decode', 'httponly', 'logout_token', '/logout-global', '/backchannel'] as $needle) {
    phase9ContractExpect(str_contains($fixtureSource, $needle), 'RP fixture 缺少安全能力：' . $needle);
}
phase9ContractExpect(!str_contains($fixtureSource, 'localStorage'), 'RP fixture 不得将 token 写入 localStorage');

$transactionSource = (string) file_get_contents($root . '/app/identity/service/AuthorizationTransactionService.php');
phase9ContractExpect(
    str_contains($transactionSource, "where('granted_scope_hash',")
    && str_contains($transactionSource, "whereNull('revoked_at')"),
    'standalone consent 必须按授权唯一键幂等恢复，避免重复提交冲突'
);

$php = PHP_BINARY;
$process = proc_open([$php, $journey], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
phase9ContractExpect(is_resource($process), '无法启动 Phase9 真实 HTTP 旅程');
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
phase9ContractExpect($exitCode === 0, "Phase9 真实 HTTP 旅程失败：\n" . $stdout . $stderr);
$lines = array_values(array_filter(array_map('trim', explode("\n", (string) $stdout))));
$result = json_decode((string) end($lines), true);
phase9ContractExpect(is_array($result) && ($result['status'] ?? '') === 'PASS', 'Phase9 非 serve 模式未返回 PASS 结果');
$expectedSteps = ['discovery_jwks', 'internal_login', 'partner_first_consent', 'partner_expanded_consent', 'consent_revoked', 'partner_reconsent', 'global_logout', 'backchannel_dispatch', 'sessions_cleared', 'temp_root_cleaned'];
phase9ContractExpect(array_keys($result['steps'] ?? []) === $expectedSteps, 'Phase9 结果缺少有序步骤断言');
foreach ($expectedSteps as $step) {
    phase9ContractExpect(($result['steps'][$step]['passed'] ?? false) === true, 'Phase9 步骤未通过：' . $step);
}

echo "identity phase9 cross-origin contract tests passed\n";
