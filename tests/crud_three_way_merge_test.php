<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\crud\ConfirmationToken;
use app\common\crud\ContentClassifier;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\GenerationPlanner;
use app\common\crud\StructuredResourceMerger;
use app\common\crud\TextThreeWayMerger;
use app\common\crud\ThreeWayMergePlanner;

function threeWayExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function threeWayReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        threeWayExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

function threeWayRemove(string $path): void
{
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function threeWayFile(array $plan, string $path): array
{
    foreach ($plan['files'] as $file) {
        if ($file['path'] === $path) return $file;
    }
    throw new RuntimeException('计划缺少文件：' . $path);
}

$root = sys_get_temp_dir() . '/funadmin-three-way-' . bin2hex(random_bytes(5));
mkdir($root . '/managed', 0755, true);
$beforeTree = static fn (): array => array_values(array_filter(scandir($root) ?: [], static fn (string $name): bool => !in_array($name, ['.', '..'], true)));

try {
    $classifier = new ContentClassifier();
    threeWayExpect($classifier->classify('managed/a.php', "<?php\necho '好';\n")['kind'] === 'text', '合法 UTF-8 PHP 应识别为文本');
    threeWayExpect($classifier->classify('managed/blob.dat', "a\0b")['kind'] === 'binary', 'NUL 内容必须识别为二进制');
    threeWayExpect($classifier->classify('managed/photo.png', 'plain')['kind'] === 'binary', '二进制扩展名优先保护');
    threeWayExpect($classifier->classify('managed/invalid.txt', "\xFF")['kind'] === 'binary', '非法 UTF-8 必须识别为二进制');

    $text = new TextThreeWayMerger();
    $nonOverlap = $text->merge("a\nb\nc\nd\n", "a\nLOCAL\nc\nd\n", "a\nb\nc\nREMOTE\n");
    threeWayExpect($nonOverlap['status'] === 'auto-merged' && $nonOverlap['content'] === "a\nLOCAL\nc\nREMOTE\n", '非重叠替换必须自动合并');
    $sameInsert = $text->merge("a\nb\n", "a\nX\nb\n", "a\nX\nb\n");
    threeWayExpect($sameInsert['status'] === 'auto-merged' && substr_count($sameInsert['content'], "X\n") === 1, '同点相同插入必须去重');
    foreach ([
        ["a\nb\n", "a\nL\n", "a\nR\n"],
        ["a\nb\nc\n", "a\nc\n", "a\nB\nc\n"],
        ["a\nb\n", "a\nL\nb\n", "a\nR\nb\n"],
    ] as [$base, $local, $remote]) {
        threeWayExpect($text->merge($base, $local, $remote)['status'] === 'conflict', '重叠、删改或同点不同插入必须冲突');
    }
    $multi = $text->merge("a\nb\nc\nd\ne\n", "A\nb\nc\nd\nE\n", "a\nb\nC\nd\ne\n");
    threeWayExpect($multi['status'] === 'auto-merged' && $multi['content'] === "A\nb\nC\nd\nE\n", '多 hunk 非重叠变更必须稳定合并');
    $crlf = $text->merge("a\r\nb\r\n", "A\r\nb\r\n", "a\r\nb\r\nC");
    threeWayExpect($crlf['status'] === 'auto-merged' && $crlf['content'] === "A\r\nb\r\nC", 'CRLF 与尾换行语义必须保留');
    foreach ([
        ["a\nb\nc\n", "a\nLOCAL\nc\n", "a\nb\nREMOTE\nc\n", "a\nLOCAL\nREMOTE\nc\n"],
        ["a\nb\n", "a\nLOCAL\n", "a\nb\nREMOTE\n", "a\nLOCAL\nREMOTE\n"],
        ["a\n", "", "a\nREMOTE\n", "REMOTE\n"],
    ] as [$base, $local, $remote, $expected]) {
        $boundary = $text->merge($base, $local, $remote);
        threeWayExpect($boundary['status'] === 'auto-merged' && $boundary['content'] === $expected, '半开区间边界插入必须自动合并并保持正确顺序');
    }

    file_put_contents($root . '/managed/auto-merge.txt', "a\nLOCAL\nc\nd\n");
    file_put_contents($root . '/managed/local-base.txt', "base\n");
    file_put_contents($root . '/managed/local-change.txt', "local\n");
    file_put_contents($root . '/managed/same.txt', "same\n");
    file_put_contents($root . '/managed/no-base.txt', "owned\n");
    file_put_contents($root . '/managed/delete-clean.txt', "base\n");
    file_put_contents($root . '/managed/delete-local.txt', "local\n");
    file_put_contents($root . '/managed/blob.bin', "local\0blob");
    file_put_contents($root . '/managed/base-binary.txt', "local\n");
    file_put_contents($root . '/managed/local-binary.txt', "local\xFF");
    file_put_contents($root . '/managed/declared-binary.txt', "local\n");
    file_put_contents($root . '/managed/existing-migration.sql', "old\n");
    file_put_contents($root . '/managed/hash-only.txt', "base\n");
    file_put_contents($root . '/managed/migration.txt', "old\n");
    file_put_contents($root . '/managed/untrusted-kind.txt', "base\n");

    $planner = new ThreeWayMergePlanner($root);
    $plan = $planner->plan([
        ['path' => 'managed/auto-merge.txt', 'baseContent' => "a\nb\nc\nd\n", 'remoteContent' => "a\nb\nc\nREMOTE\n"],
        ['path' => 'managed/create.txt', 'remoteContent' => "new\n"],
        ['path' => 'managed/no-base.txt', 'remoteContent' => "remote\n"],
        ['path' => 'managed/local-base.txt', 'baseContent' => "base\n", 'baseHash' => hash('sha256', "base\n"), 'remoteContent' => "remote\n"],
        ['path' => 'managed/local-change.txt', 'baseContent' => "base\n", 'remoteContent' => "base\n"],
        ['path' => 'managed/same.txt', 'baseContent' => "base\n", 'remoteContent' => "same\n"],
        ['path' => 'managed/delete-clean.txt', 'baseContent' => "base\n", 'remoteContent' => null],
        ['path' => 'managed/delete-local.txt', 'baseContent' => "base\n", 'remoteContent' => null],
        ['path' => 'managed/blob.bin', 'baseContent' => "base\0blob", 'remoteContent' => "remote\0blob", 'contentKind' => 'binary'],
        ['path' => 'managed/existing-migration.sql', 'remoteContent' => "new\n", 'artifactType' => 'migration'],
        ['path' => 'managed/hash-only.txt', 'baseHash' => hash('sha256', "base\n"), 'remoteContent' => "remote\n"],
        ['path' => 'managed/new-migration.sql', 'remoteContent' => "new\n", 'artifactType' => 'migration'],
        ['path' => 'managed/removed-both.txt', 'baseContent' => "base\n", 'remoteContent' => null],
    ]);
    $statuses = array_column($plan['files'], 'status', 'path');
    threeWayExpect($statuses === [
        'managed/auto-merge.txt' => 'auto-merged',
        'managed/blob.bin' => 'binary-conflict',
        'managed/create.txt' => 'create',
        'managed/delete-clean.txt' => 'delete',
        'managed/delete-local.txt' => 'conflict',
        'managed/existing-migration.sql' => 'conflict',
        'managed/hash-only.txt' => 'update',
        'managed/local-base.txt' => 'update',
        'managed/local-change.txt' => 'keep-local',
        'managed/new-migration.sql' => 'create',
        'managed/no-base.txt' => 'conflict-no-base',
        'managed/removed-both.txt' => 'keep-local',
        'managed/same.txt' => 'keep-local',
    ], '文件级状态矩阵不完整或排序不确定');
    threeWayExpect($plan['blocked'] === true && !isset($plan['confirmToken']), '任一冲突必须阻断且不得签发 token');
    threeWayExpect(threeWayFile($plan, 'managed/auto-merge.txt')['content'] === "a\nLOCAL\nc\nREMOTE\n", '文件规划必须输出自动合并内容');
    $updated = threeWayFile($plan, 'managed/local-base.txt');
    foreach (['baseHash', 'localHash', 'remoteHash', 'mergedHash', 'nextBaseHash'] as $hash) {
        threeWayExpect(array_key_exists($hash, $updated), '计划缺少 hash：' . $hash);
    }
    threeWayExpect($updated['nextBaseHash'] === $updated['remoteHash'], '成功后的下一 Base 必须声明为 Remote hash');
    $binary = threeWayFile($plan, 'managed/blob.bin');
    threeWayExpect(!isset($binary['baseContent'], $binary['localContent'], $binary['remoteContent'], $binary['mergedContent'], $binary['content']), '二进制冲突不得泄露内容');
    $binaryCreate = $planner->plan([['path' => 'managed/new.bin', 'remoteContent' => "remote\0binary", 'contentKind' => 'binary']]);
    $binaryCreateFile = threeWayFile($binaryCreate, 'managed/new.bin');
    threeWayExpect($binaryCreateFile['status'] === 'create' && !isset($binaryCreateFile['content']), '非冲突二进制计划也不得泄露原始内容');
    threeWayExpect(isset($binary['baseSize'], $binary['localSize'], $binary['remoteSize'], $binary['contentType']), '二进制冲突必须提供 hash/size/type');
    foreach ([
        ['path' => 'managed/base-binary.txt', 'baseContent' => "base\0binary", 'remoteContent' => "remote\n"],
        ['path' => 'managed/local-binary.txt', 'baseContent' => "base\n", 'remoteContent' => "remote\n"],
        ['path' => 'managed/declared-binary.txt', 'baseContent' => "base\n", 'remoteContent' => "remote\n", 'contentKind' => 'binary'],
    ] as $binaryInput) {
        $binaryPlan = $planner->plan([$binaryInput]);
        $binaryFile = $binaryPlan['files'][0];
        threeWayExpect($binaryFile['status'] === 'binary-conflict' && $binaryPlan['blocked'] === true, 'Base、Local 或声明为 binary 时必须阻断为 binary-conflict');
        threeWayExpect(!isset($binaryFile['baseContent'], $binaryFile['localContent'], $binaryFile['remoteContent'], $binaryFile['mergedContent'], $binaryFile['content']), '任一来源为二进制时不得泄露内容');
    }
    $missingMigration = $planner->plan([
        ['path' => 'managed/missing-migration.sql', 'baseContent' => null, 'remoteContent' => null, 'artifactType' => 'migration'],
        ['path' => 'managed/deleted-migration.sql', 'baseContent' => "base\n", 'remoteContent' => null, 'artifactType' => 'migration'],
    ]);
    threeWayExpect(array_column($missingMigration['files'], 'status', 'path') === [
        'managed/deleted-migration.sql' => 'keep-local',
        'managed/missing-migration.sql' => 'keep-local',
    ], 'migration 双 null 及 Base 后双删除必须是 keep-local no-op');
    threeWayExpect(file_get_contents($root . '/managed/local-base.txt') === "base\n" && !file_exists($root . '/managed/create.txt'), '规划不得写入或删除文件');

    $cleanPlan = $planner->plan([
        ['path' => 'managed/local-base.txt', 'baseContent' => "base\n", 'remoteContent' => "remote\n"],
    ]);
    threeWayExpect($cleanPlan['blocked'] === false && !isset($cleanPlan['confirmToken']), '纯规划即使成功也不得产生 token');

    threeWayReject(static fn () => $planner->plan([['path' => '../escape.txt', 'remoteContent' => 'x']]), '路径');
    $outside = $root . '-outside';
    mkdir($outside, 0755, true);
    file_put_contents($outside . '/secret.txt', 'secret');
    symlink($outside, $root . '/managed/link');
    threeWayReject(static fn () => $planner->plan([['path' => 'managed/link/secret.txt', 'remoteContent' => 'x']]), '符号链接');
    unlink($root . '/managed/link');
    threeWayRemove($outside);

    $structured = new StructuredResourceMerger();
    $baseResources = [
        ['resourceType' => 'permission', 'sourceName' => 'member', 'code' => 'member:list', 'name' => '成员', 'sortOrder' => 10],
        ['resourceType' => 'menu', 'sourceName' => 'member', 'href' => '/member', 'name' => '成员', 'icon' => 'old'],
    ];
    $localResources = $baseResources;
    $localResources[0]['name'] = '本地成员';
    $remoteResources = $baseResources;
    $remoteResources[0]['sortOrder'] = 20;
    $remoteResources[1]['icon'] = 'new';
    $structuredResult = $structured->merge($baseResources, $localResources, $remoteResources);
    threeWayExpect($structuredResult['status'] === 'auto-merged', '结构化非重叠字段必须自动合并');
    $byKey = array_column($structuredResult['resources'], null, 'resourceKey');
    threeWayExpect($byKey['permission|member|member:list']['name'] === '本地成员' && $byKey['permission|member|member:list']['sortOrder'] === 20, '结构化字段三方规则错误');
    $conflictingResources = $remoteResources;
    $conflictingResources[0]['name'] = '远程成员';
    threeWayExpect($structured->merge($baseResources, $localResources, $conflictingResources)['status'] === 'conflict', '结构化同字段冲突必须阻断');
    $ordered = $structured->merge([], [['resourceType' => 'menu', 'sourceName' => 'z', 'href' => '/z', 'id' => 99]], [['resourceType' => 'menu', 'sourceName' => 'a', 'href' => '/a', 'id' => 1]]);
    threeWayExpect(array_column($ordered['resources'], 'sourceName') === ['a', 'z'] && !isset($ordered['resources'][0]['id']), '结构化集合必须不依赖 ID 且确定排序');

    $artifactTypes = ['migration', 'model', 'validate', 'service', 'controller', 'permissionMigration', 'api', 'view', 'form', 'detail', 'phpTest', 'vitestTest'];
    $targets = [];
    $templates = [];
    foreach ($artifactTypes as $artifactType) {
        $targets[$artifactType] = $artifactType === 'model' ? 'managed/Sample.php' : 'managed/' . $artifactType . '.txt';
        $templates[$artifactType] = 'tests/fixture.tpl';
    }
    $definition = CrudDefinition::fromArray([
        'schemaVersion' => '1.0', 'entity' => 'sample', 'table' => 'fun_sample', 'title' => '示例',
        'apiPrefix' => '/sample', 'permissionPrefix' => 'sample:item',
        'fields' => [['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true]],
        'relations' => [], 'optionsSource' => [],
        'features' => ['batchDelete' => false, 'status' => false, 'detail' => false, 'import' => false, 'export' => false, 'upload' => false, 'dictionary' => false, 'referenceProtection' => false, 'formMode' => 'dialog', 'importLimit' => 100, 'exportLimit' => 100],
        'dataScope' => ['enabled' => false, 'field' => ''],
        'generationTargets' => $targets, 'templates' => $templates,
    ]);
    $templateRoot = $root . '/templates';
    mkdir($templateRoot . '/tests', 0755, true);
    file_put_contents($templateRoot . '/tests/fixture.tpl', "remote\n");
    $generator = new CrudGenerator($root, $templateRoot, new ConfirmationToken($root, 'three-way-test-secret'));
    $ordinary = $generator->plan($definition);
    threeWayExpect(isset($ordinary['confirmToken']) && !isset($ordinary['blocked']), '普通独立 CRUD 必须保持原计划行为');
    $managed = $generator->planManaged($definition, [
        ['path' => 'managed/Sample.php', 'baseContent' => "remote\n"],
        ['path' => 'managed/migration.txt', 'artifactType' => 'model'],
        ['path' => 'managed/untrusted-kind.txt', 'baseContent' => "base\n", 'artifactType' => 'migration'],
    ]);
    threeWayExpect($managed['managed'] === true && threeWayFile($managed, 'managed/Sample.php')['status'] === 'keep-local' && !isset($managed['confirmToken']), 'business managed 显式 baseline 才启用三方计划');
    threeWayExpect(threeWayFile($managed, 'managed/migration.txt')['status'] === 'conflict', 'Definition migration 目标必须覆盖调用方伪装的 artifactType');
    threeWayExpect(threeWayFile($managed, 'managed/untrusted-kind.txt')['status'] === 'delete', 'Definition 目标外 baseline 不得信任调用方声明为 migration');
    $directManaged = (new GenerationPlanner($root, new ConfirmationToken($root, 'direct-managed-secret')))->planManaged(
        $definition,
        ['managed/migration.txt' => "remote\n"],
        [
            ['path' => 'managed/migration.txt', 'artifactType' => 'model'],
            ['path' => 'managed/untrusted-kind.txt', 'baseContent' => "base\n", 'artifactType' => 'migration'],
        ]
    );
    threeWayExpect(threeWayFile($directManaged, 'managed/migration.txt')['status'] === 'conflict', 'GenerationPlanner 必须独立从 Definition 派生 migration 类型');
    threeWayExpect(threeWayFile($directManaged, 'managed/untrusted-kind.txt')['status'] === 'delete', 'GenerationPlanner 不得信任目标外 migration 声明');
    threeWayExpect($directManaged['planDigest'] === (new GenerationPlanner($root, new ConfirmationToken($root, 'other-secret')))->planManaged(
        $definition,
        ['managed/migration.txt' => "remote\n"],
        [
            ['path' => 'managed/migration.txt', 'artifactType' => 'model'],
            ['path' => 'managed/untrusted-kind.txt', 'baseContent' => "base\n", 'artifactType' => 'migration'],
        ]
    )['planDigest'], '相同冲突计划的 digest 必须稳定且不受 token secret 影响');

    threeWayExpect($beforeTree() === ['managed', 'templates'], '测试期间除显式夹具外不得产生 WAL、token 或临时写入');
    threeWayExpect(!is_dir($root . '/runtime') && glob($root . '/.crud-write-*') === [], '三方规划必须零运行时副作用');
    threeWayExpect(!str_contains((string) file_get_contents(dirname(__DIR__) . '/app/common/crud/TextThreeWayMerger.php'), 'proc_open'), '文本合并不得调用外部 shell');

    echo "CRUD three-way merge tests: PASS\n";
} finally {
    threeWayRemove($root);
}
