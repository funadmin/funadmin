<?php

declare(strict_types=1);

// 仅在宿主显式运行；不向 HTTP 容器挂载 Docker socket。
$root = dirname(__DIR__, 2);
foreach ([
    '/app/admin/ai/contract/DockerProcessRunner.php',
    '/app/admin/ai/infrastructure/ProcessResult.php',
    '/app/admin/ai/infrastructure/NativeDockerProcessRunner.php',
    '/app/admin/ai/service/AgentSandboxManager.php',
] as $file) require_once $root . $file;

use app\admin\ai\infrastructure\NativeDockerProcessRunner;
use app\admin\ai\service\AgentSandboxManager;

$image = getenv('CONSOLE_SANDBOX_TEST_IMAGE');
if (!is_string($image) || !preg_match('/^[^\s]+@sha256:[a-f0-9]{64}$/D', $image)) {
    fwrite(STDERR, "请设置固定 digest 的 CONSOLE_SANDBOX_TEST_IMAGE\n");
    exit(1);
}
$temporary = sys_get_temp_dir() . '/funadmin-sandbox-smoke-' . bin2hex(random_bytes(8));
mkdir($temporary . '/project', 0700, true);
file_put_contents($temporary . '/project/example.txt', 'snapshot');
file_put_contents($temporary . '/project/.env', 'must-not-copy');
$runner = new NativeDockerProcessRunner();
$manager = new AgentSandboxManager($runner, $temporary . '/project', $temporary . '/private', ['image' => $image]);
$sandbox = null;
$status = 1;
try {
    $sandbox = $manager->create(random_int(100000000, 999999999), 998877);
    $result = $runner->run(['docker', 'start', $sandbox['containerId']], 30);
    if ($result->exitCode !== 0) throw new RuntimeException('测试 sandbox 启动失败');
    $result = $manager->exec($sandbox['containerId'], ['php', '-r', 'if(file_get_contents("/workspace/example.txt")!=="snapshot" || file_exists("/workspace/.env")) exit(1);'], 10);
    if ($result->exitCode !== 0) throw new RuntimeException('快照或敏感文件排除验证失败');
    $inspect = $runner->run(['docker', 'inspect', '--format', '{{json .HostConfig}}', $sandbox['containerId']], 10);
    $host = json_decode($inspect->stdout, true, 512, JSON_THROW_ON_ERROR);
    if ($host['NetworkMode'] !== 'none' || $host['ReadonlyRootfs'] !== true || $host['Privileged'] !== false || !in_array('ALL', $host['CapDrop'], true)) {
        throw new RuntimeException('sandbox 隔离配置不满足要求');
    }
    $manager->cleanup($sandbox['containerId'], $sandbox['workspace'], $sandbox['taskId'], $sandbox['sessionId'], $sandbox['volume']);
    $check = $runner->run(['docker', 'container', 'inspect', $sandbox['containerId']], 10);
    if ($check->exitCode === 0 || is_dir($sandbox['workspace'])) throw new RuntimeException('测试 sandbox 未清理');
    echo "sandbox create/start/snapshot/isolation/cleanup: PASS\n";
    $status = 0;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
} finally {
    if ($sandbox !== null && is_dir($sandbox['workspace'])) {
        try { $manager->cleanup($sandbox['containerId'], $sandbox['workspace'], $sandbox['taskId'], $sandbox['sessionId'], $sandbox['volume']); }
        catch (Throwable $error) { fwrite(STDERR, '测试 sandbox 需要清理：' . $error->getMessage() . PHP_EOL); }
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($temporary);
}
exit($status);
