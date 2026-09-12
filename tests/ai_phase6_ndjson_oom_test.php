<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\ai\contract\DockerProcessRunner;
use app\console\ai\infrastructure\ProcessResult;
use app\console\ai\service\AgentSandboxManager;
use app\console\ai\service\AiChangeSetService;

function phase6NdjsonExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function phase6NdjsonRemove(string $path): void
{
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        foreach (new FilesystemIterator($path) as $item) phase6NdjsonRemove($item->getPathname());
        rmdir($path);
        return;
    }
    unlink($path);
}

function phase6WriteLargeBundle(string $path, string $selectedContent, int $fillerBytes): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) throw new RuntimeException('无法创建大型 bundle fixture');
    fwrite($handle, "{\"encoding\":\"base64-ndjson\",\"version\":1}\n");
    fwrite($handle, '{"path":"large/unchanged.bin","content":"');
    $encodedChunk = base64_encode(str_repeat('x', 3072));
    for ($written = 0; $written < $fillerBytes; $written += 3072) fwrite($handle, $encodedChunk);
    fwrite($handle, '"}' . "\n");
    fwrite($handle, json_encode(['path'=>'src/selected.txt','content'=>base64_encode($selectedContent)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    fclose($handle);
}

function phase6WriteEntryFloodBundle(string $path, string $selectedContent, int $unselectedEntries): void
{
    $handle = fopen($path, 'wb');
    if ($handle === false) throw new RuntimeException('无法创建条目洪泛 bundle fixture');
    fwrite($handle, "{\"encoding\":\"base64-ndjson\",\"version\":1}\n");
    for ($index = 0; $index < $unselectedEntries; $index++) {
        fwrite($handle, '{"path":"unselected/' . $index . '","content":""}' . "\n");
    }
    fwrite($handle, json_encode(['path'=>'src/selected.txt','content'=>base64_encode($selectedContent)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    fclose($handle);
}

final class Phase6DockerRunner implements DockerProcessRunner
{
    public array $calls = [];
    public ?string $failCommand = null;
    public bool $mismatchedLabels = false;

    public function __construct(private readonly string $exportFixture)
    {
    }

    public function run(array $argv, int $timeoutSeconds): ProcessResult
    {
        $this->calls[] = $argv;
        $command = (string) ($argv[1] ?? '');
        if ($this->failCommand === $command) return new ProcessResult(1, '', $command . ' failed');
        if ($command === 'create') return new ProcessResult(0, "phase6-container\n", '');
        if ($command === 'inspect') {
            $labels = $this->mismatchedLabels
                ? ['com.funadmin.ai-agent'=>'true','com.funadmin.ai-task'=>'999','com.funadmin.ai-session'=>'88','com.funadmin.ai-volume'=>'foreign-volume']
                : ['com.funadmin.ai-agent'=>'true','com.funadmin.ai-task'=>'601','com.funadmin.ai-session'=>'88','com.funadmin.ai-volume'=>$this->volumeFromCreate()];
            return new ProcessResult(0, json_encode($labels, JSON_THROW_ON_ERROR), '');
        }
        if ($command === 'volume' && ($argv[2] ?? '') === 'inspect') {
            $labels = $this->mismatchedLabels
                ? ['com.funadmin.ai-agent'=>'true','com.funadmin.ai-task'=>'999','com.funadmin.ai-session'=>'88']
                : ['com.funadmin.ai-agent'=>'true','com.funadmin.ai-task'=>'601','com.funadmin.ai-session'=>'88'];
            return new ProcessResult(0, json_encode($labels, JSON_THROW_ON_ERROR), '');
        }
        if ($command === 'exec' && ($argv[3] ?? '') === 'git' && ($argv[4] ?? '') === 'diff') {
            return new ProcessResult(0, "diff --git a/src/text.txt b/src/text.txt\n", '');
        }
        if ($command === 'cp' && str_contains((string) ($argv[2] ?? ''), ':/workspace/.')) {
            $this->copyFixture((string) $argv[3]);
        }
        return new ProcessResult(0, '', '');
    }

    private function volumeFromCreate(): string
    {
        foreach ($this->calls as $call) {
            if (($call[1] ?? '') !== 'create') continue;
            foreach ($call as $argument) {
                if (is_string($argument) && preg_match('/^type=volume,src=([^,]+),dst=\/workspace$/', $argument, $matches) === 1) return $matches[1];
            }
        }
        return '';
    }

    private function copyFixture(string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->exportFixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($this->exportFixture) + 1);
            $target = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0700, true);
            } else {
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
                copy($item->getPathname(), $target);
            }
        }
    }
}

$root = sys_get_temp_dir() . '/funadmin-ai-p6-' . bin2hex(random_bytes(5));
$project = $root . '/project';
$private = $root . '/private';
$exportFixture = $root . '/export-fixture';
mkdir($project . '/src', 0700, true);
mkdir($exportFixture . '/src', 0700, true);
file_put_contents($project . '/src/text.txt', "before\n");
file_put_contents($exportFixture . '/src/text.txt', "after\n");
$config = ['image'=>'funadmin/ai-agent@sha256:' . str_repeat('a', 64), 'cpu'=>0.5, 'memory_mb'=>256, 'pids'=>64];

try {
    $runner = new Phase6DockerRunner($exportFixture);
    $runner->failCommand = 'cp';
    $manager = new AgentSandboxManager($runner, $project, $private, $config);
    $failed = false;
    try {
        $manager->create(601, 88);
    } catch (RuntimeException) {
        $failed = true;
    }
    $calls = $runner->calls;
    $inspect = array_search(['docker','inspect','--format','{{json .Config.Labels}}','phase6-container'], $calls, true);
    $remove = array_search(['docker','rm','-f','phase6-container'], $calls, true);
    phase6NdjsonExpect($failed, 'docker cp 初始化失败必须向上抛出');
    phase6NdjsonExpect($inspect !== false && $remove !== false && $inspect < $remove, '初始化失败回滚必须先校验标签再删除容器');
    phase6NdjsonExpect((glob($private . '/sandboxes/*') ?: []) === [], '初始化失败不得残留 workspace');
    phase6NdjsonExpect(count(array_filter($calls, static fn (array $call): bool => ($call[1] ?? '') === 'volume' && ($call[2] ?? '') === 'rm')) === 1, '初始化失败必须删除受控 volume');

    foreach (['create', 'run'] as $failedCommand) {
        $failureRunner = new Phase6DockerRunner($exportFixture);
        $failureRunner->failCommand = $failedCommand;
        $failureManager = new AgentSandboxManager($failureRunner, $project, $private, $config);
        try {
            $failureManager->create(601, 88);
            throw new RuntimeException("{$failedCommand} 初始化失败未抛出");
        } catch (RuntimeException) {
        }
        phase6NdjsonExpect((glob($private . '/sandboxes/*') ?: []) === [], "{$failedCommand} 失败不得残留 workspace");
        phase6NdjsonExpect(count(array_filter($failureRunner->calls, static fn (array $call): bool => ($call[1] ?? '') === 'volume' && ($call[2] ?? '') === 'rm')) === 1, "{$failedCommand} 失败必须回滚 volume");
        if ($failedCommand === 'run') {
            phase6NdjsonExpect(in_array(['docker','rm','-f','phase6-container'], $failureRunner->calls, true), 'chown 失败必须回滚容器');
        }
    }

    $mismatchRunner = new Phase6DockerRunner($exportFixture);
    $mismatchRunner->failCommand = 'cp';
    $mismatchRunner->mismatchedLabels = true;
    try {
        (new AgentSandboxManager($mismatchRunner, $project, $private, $config))->create(601, 88);
    } catch (RuntimeException) {
    }
    phase6NdjsonExpect(!in_array(['docker','rm','-f','phase6-container'], $mismatchRunner->calls, true), '标签不匹配时不得删除容器');
    phase6NdjsonExpect(!array_filter($mismatchRunner->calls, static fn (array $call): bool => ($call[1] ?? '') === 'volume' && ($call[2] ?? '') === 'rm'), '标签不匹配时不得删除 volume');

    $largeProject = $root . '/large-project';
    mkdir($largeProject, 0700, true);
    $largeHandle = fopen($largeProject . '/too-large.bin', 'wb');
    ftruncate($largeHandle, 16777217);
    fclose($largeHandle);
    $limitRunner = new Phase6DockerRunner($exportFixture);
    try {
        (new AgentSandboxManager($limitRunner, $largeProject, $private, $config))->create(601, 88);
        throw new RuntimeException('snapshot 超限未拒绝');
    } catch (RuntimeException $exception) {
        phase6NdjsonExpect(str_contains($exception->getMessage(), '资源上限'), 'snapshot 超限必须报告资源上限');
    }
    phase6NdjsonExpect(!array_filter($limitRunner->calls, static fn (array $call): bool => ($call[1] ?? '') === 'create'), 'snapshot/manifest 超限前不得创建 Docker 资源');
    phase6NdjsonExpect((glob($private . '/sandboxes/*') ?: []) === [], 'snapshot/manifest 超限不得残留 workspace');

    foreach (['src/vendor/Library.php'=>'nested vendor', 'app/build/Source.php'=>'nested build', 'src/binary.bin'=>"\x00\xff\x01"] as $relative=>$content) {
        foreach ([$project, $exportFixture] as $fixtureRoot) {
            $path = $fixtureRoot . '/' . $relative;
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
            file_put_contents($path, $content);
        }
    }
    foreach (['vendor/dependency.php', 'build/output.js', 'node_modules/package.js'] as $relative) {
        foreach ([$project, $exportFixture] as $fixtureRoot) {
            $path = $fixtureRoot . '/' . $relative;
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
            file_put_contents($path, 'excluded');
        }
    }
    $exportRunner = new Phase6DockerRunner($exportFixture);
    $exportManager = new AgentSandboxManager($exportRunner, $project, $private, $config);
    $sandbox = $exportManager->create(601, 88);
    $artifact = $exportManager->exportChanges('phase6-container', $sandbox['workspace']);
    foreach (['src/text.txt','src/vendor/Library.php','app/build/Source.php','src/binary.bin'] as $included) {
        phase6NdjsonExpect(isset($artifact['remoteManifest']['files'][$included]), "合法嵌套源码必须保留：{$included}");
    }
    foreach (['vendor/dependency.php','build/output.js','node_modules/package.js'] as $excluded) {
        phase6NdjsonExpect(!isset($artifact['remoteManifest']['files'][$excluded]), "项目根生成/依赖目录必须排除：{$excluded}");
    }
    $bundleEntries = file($artifact['bundlePath'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $decodedBundle = [];
    foreach (array_slice($bundleEntries ?: [], 1) as $line) {
        $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $decodedBundle[$entry['path']] = base64_decode($entry['content'], true);
    }
    phase6NdjsonExpect(($decodedBundle['src/binary.bin'] ?? null) === "\x00\xff\x01", 'FakeDockerRunner 必须实际复制并保真导出二进制 fixture');
    phase6NdjsonExpect(($decodedBundle['src/vendor/Library.php'] ?? null) === 'nested vendor', 'FakeDockerRunner 必须实际复制嵌套合法同名目录');

    $publishedRoot = $artifact['root'];
    $exportRunner->failCommand = 'cp';
    try {
        $exportManager->exportChanges('phase6-container', $sandbox['workspace'] . '-failed');
        throw new RuntimeException('导出失败未抛出');
    } catch (RuntimeException) {
    }
    phase6NdjsonExpect(!is_dir($private . '/exports/' . basename($sandbox['workspace']) . '-failed'), '导出失败不得留下看似有效 artifact');
    phase6NdjsonExpect((glob($private . '/exports/.*.tmp-*') ?: []) === [], '导出失败必须删除同根 temp/partial');
    phase6NdjsonExpect(is_dir($publishedRoot), '另一次导出失败不得破坏已成功原子发布的 artifact');

    $memoryProject = $root . '/memory-project';
    $memoryPrivate = $root . '/memory-private';
    mkdir($memoryProject . '/src', 0700, true);
    mkdir($memoryPrivate . '/exports/one', 0700, true);
    file_put_contents($memoryProject . '/src/selected.txt', "local\n");
    $fillerBytes = 12 * 1024 * 1024;
    $fillerMeta = ['sha256'=>hash('sha256', str_repeat('x', $fillerBytes)), 'size'=>$fillerBytes];
    $baseContent = "base\n";
    $remoteContent = "remote\n";
    $baseFiles = ['large/unchanged.bin'=>$fillerMeta, 'src/selected.txt'=>['sha256'=>hash('sha256', $baseContent),'size'=>strlen($baseContent)]];
    $remoteFiles = ['large/unchanged.bin'=>$fillerMeta, 'src/selected.txt'=>['sha256'=>hash('sha256', $remoteContent),'size'=>strlen($remoteContent)]];
    $baseManifest = ['algorithm'=>'sha256','files'=>$baseFiles,'digest'=>hash('sha256', json_encode($baseFiles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
    $remoteManifest = ['algorithm'=>'sha256','files'=>$remoteFiles,'digest'=>hash('sha256', json_encode($remoteFiles, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
    $artifactRoot = $memoryPrivate . '/exports/one';
    file_put_contents($artifactRoot . '/baseline-manifest.json', json_encode($baseManifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    file_put_contents($artifactRoot . '/manifest.json', json_encode($remoteManifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    phase6WriteLargeBundle($artifactRoot . '/baseline-bundle.json', $baseContent, $fillerBytes);
    phase6WriteLargeBundle($artifactRoot . '/bundle.json', $remoteContent, $fillerBytes);
    $patchPath = $artifactRoot . '/changes.patch';
    file_put_contents($patchPath, "diff --git a/src/selected.txt b/src/selected.txt\n");
    $changeSet = [
        'id'=>1,'conversation_id'=>2,'task_id'=>3,'created_by'=>4,'digest'=>str_repeat('d', 64),
        'base_digest'=>$baseManifest['digest'],'patch_path'=>$patchPath,'patch_sha256'=>hash_file('sha256', $patchPath),
        'manifest'=>[
            'baseline_manifest_path'=>$artifactRoot . '/baseline-manifest.json','baseline_manifest_sha256'=>hash_file('sha256', $artifactRoot . '/baseline-manifest.json'),
            'remote_manifest_path'=>$artifactRoot . '/manifest.json','remote_manifest_sha256'=>hash_file('sha256', $artifactRoot . '/manifest.json'),
            'baseline_bundle_path'=>$artifactRoot . '/baseline-bundle.json','baseline_bundle_sha256'=>hash_file('sha256', $artifactRoot . '/baseline-bundle.json'),
            'bundle_path'=>$artifactRoot . '/bundle.json','bundle_sha256'=>hash_file('sha256', $artifactRoot . '/bundle.json'),
            'artifact'=>['admin_id'=>4,'conversation_id'=>2,'task_id'=>3],
        ],
    ];
    if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
    $memoryBefore = memory_get_usage(true);
    $memoryPreview = (new AiChangeSetService($memoryProject, $memoryPrivate))->preview($changeSet, 4, ['src/selected.txt'], false, 2, 3);
    $memoryGrowth = memory_get_peak_usage(true) - $memoryBefore;
    phase6NdjsonExpect(($memoryPreview['files'][0]['path'] ?? '') === 'src/selected.txt', '大型 bundle 必须只规划 manifest 所需选择路径');
    phase6NdjsonExpect($memoryGrowth < 32 * 1024 * 1024, '读取两个合法 bundle 时不得同时物化无关正文，峰值增长：' . $memoryGrowth);

    phase6WriteEntryFloodBundle($artifactRoot . '/baseline-bundle.json', $baseContent, 100001);
    phase6WriteEntryFloodBundle($artifactRoot . '/bundle.json', $remoteContent, 100001);
    $changeSet['manifest']['baseline_bundle_sha256'] = hash_file('sha256', $artifactRoot . '/baseline-bundle.json');
    $changeSet['manifest']['bundle_sha256'] = hash_file('sha256', $artifactRoot . '/bundle.json');
    try {
        (new AiChangeSetService($memoryProject, $memoryPrivate))->preview($changeSet, 4, ['src/selected.txt'], false, 2, 3);
        throw new RuntimeException('未选中 NDJSON 条目超过文件上限时未拒绝');
    } catch (RuntimeException $exception) {
        phase6NdjsonExpect(str_contains($exception->getMessage(), '资源上限'), '未选中 NDJSON 条目超限必须在解析早期报告资源上限：' . $exception->getMessage());
    }

    $duplicateEntry = json_encode(['path'=>'src/selected.txt','content'=>base64_encode($baseContent)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    file_put_contents($artifactRoot . '/baseline-bundle.json', "{\"encoding\":\"base64-ndjson\",\"version\":1}\n" . $duplicateEntry . $duplicateEntry);
    $changeSet['manifest']['baseline_bundle_sha256'] = hash_file('sha256', $artifactRoot . '/baseline-bundle.json');
    try {
        (new AiChangeSetService($memoryProject, $memoryPrivate))->preview($changeSet, 4, ['src/selected.txt'], false, 2, 3);
        throw new RuntimeException('重复 NDJSON 路径未拒绝');
    } catch (RuntimeException $exception) {
        phase6NdjsonExpect(str_contains($exception->getMessage(), '文件编码不合法'), '重复 NDJSON 路径检测必须保持：' . $exception->getMessage());
    }

    $reflection = new ReflectionClass(AgentSandboxManager::class);
    $writeBundle = $reflection->getMethod('writeBundle');
    $stalePath = $root . '/stale.txt';
    file_put_contents($stalePath, 'old');
    $staleFiles = ['stale.txt'=>['sha256'=>hash('sha256', 'old'),'size'=>3]];
    file_put_contents($stalePath, 'replacement that grew');
    try {
        $writeBundle->invoke($exportManager, $root . '/stale-bundle.json', $root, $staleFiles);
        throw new RuntimeException('文件增长/替换未拒绝');
    } catch (ReflectionException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        phase6NdjsonExpect(str_contains($exception->getMessage(), 'manifest') || str_contains($exception->getMessage(), '资源上限'), '文件增长/替换必须由实际 size/hash 校验拒绝');
    }
} finally {
    phase6NdjsonRemove($root);
}

echo "AI phase 6 NDJSON/OOM tests: PASS\n";
