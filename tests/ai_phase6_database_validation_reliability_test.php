<?php

declare(strict_types=1);

function phase6DatabaseReliabilityExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase6DatabaseReliabilityRun(string $root, string $test, array $overrides): array
{
    $environment = getenv();
    phase6DatabaseReliabilityExpect(is_array($environment), '无法读取当前测试环境变量');
    foreach (['AI_TEST_DB_HOST', 'AI_TEST_DB_PORT', 'AI_TEST_DB_USER', 'AI_TEST_DB_PASS', 'AI_PHASE1_DB_HOST', 'AI_PHASE1_DB_PORT', 'AI_PHASE1_DB_USER', 'AI_PHASE1_DB_PASS', 'AI_PHASE2_DB_HOST', 'AI_PHASE2_DB_PORT', 'AI_PHASE2_DB_USER', 'AI_PHASE2_DB_PASS', 'AI_PHASE4_DB_HOST', 'AI_PHASE4_DB_PORT', 'AI_PHASE4_DB_USER', 'AI_PHASE4_DB_PASS'] as $name) {
        unset($environment[$name]);
    }
    foreach ($overrides as $name => $value) {
        $environment[$name] = $value;
    }

    $pipes = [];
    $process = proc_open([PHP_BINARY, $root . '/tests/' . $test], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
    phase6DatabaseReliabilityExpect(is_resource($process), '无法启动子测试：' . $test);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), (string) $stdout . (string) $stderr];
}

$root = dirname(__DIR__);
$tests = [
    'ai_development_phase1_test.php',
    'ai_phase2_contract_test.php',
    'ai_phase4_migration_reentrancy_test.php',
];
$failures = [];

foreach ($tests as $test) {
    [$exitCode, $output] = phase6DatabaseReliabilityRun($root, $test, []);
    if ($exitCode !== 0 || !str_contains($output, 'SKIP')) {
        $failures[] = "{$test} 未配置数据库时必须明确 SKIP 且退出 0；实际退出码 {$exitCode}";
    }

    [$exitCode, $output] = phase6DatabaseReliabilityRun($root, $test, [
        'AI_TEST_DB_HOST' => '127.0.0.1',
        'AI_TEST_DB_PORT' => '1',
        'AI_TEST_DB_USER' => 'invalid',
        'AI_TEST_DB_PASS' => 'invalid',
    ]);
    if ($exitCode === 0 || str_contains($output, 'SKIP')) {
        $failures[] = "{$test} 已配置数据库但连接失败时必须退出非零且不得 SKIP；实际退出码 {$exitCode}";
    }
}

phase6DatabaseReliabilityExpect($failures === [], implode("\n", $failures));
echo "AI phase 6 database validation reliability tests: PASS\n";
