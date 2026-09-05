<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
define('FUNADMIN_CRUD_HELPER_TESTING', true);

use app\common\crud\AtomicWriter;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\DefinitionValidator;
use app\common\crud\FieldInference;
use app\common\crud\GenerationManifest;
use app\common\crud\GenerationPlanner;
use app\common\crud\SafeCommit;
use app\common\crud\SchemaInspector;
use app\common\crud\TemplateRenderer;
use app\common\service\AdminWebCrudGenerator;

function crudExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function crudReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        crudExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

function crudRemoveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function validDefinition(array $overrides = []): array
{
    return array_replace_recursive([
        'schemaVersion' => '1.0',
        'name' => 'audit-log',
        'table' => 'fun_audit_log',
        'title' => '审计日志',
        'paths' => [
            'backend' => 'app/backend/controller/system/AuditLog.php',
            'frontend' => 'admin-web/src/views/system/audit-log/index.vue',
            'database' => 'database/generated/audit_log.sql',
        ],
        'apiPrefix' => '/system/audit-log',
        'permissionPrefix' => 'system:audit-log',
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true],
            ['name' => 'status', 'dbType' => 'tinyint(1)', 'nullable' => false, 'comment' => '状态:0=禁用,1=启用'],
        ],
        'relations' => [],
        'templates' => [
            'backend' => 'backend/controller.php.tpl',
            'frontend' => 'frontend/index.vue.tpl',
            'database' => 'database/migration.sql.tpl',
        ],
    ], $overrides);
}

$root = sys_get_temp_dir() . '/funadmin-crud-core-' . bin2hex(random_bytes(5));
mkdir($root, 0755, true);

try {
    $definition = CrudDefinition::fromArray(validDefinition());
    (new DefinitionValidator())->validate($definition, $root);
    crudExpect($definition->schemaVersion() === '1.0', 'Definition 必须保留 schemaVersion');
    crudExpect($definition->fields()[0]['name'] === 'id', 'Definition 必须保留字段');

    foreach ([
        ['data' => ['schemaVersion' => '9.9'], 'message' => 'schemaVersion'],
        ['data' => ['name' => '../escape'], 'message' => 'name'],
        ['data' => ['paths' => ['backend' => '../outside.php']], 'message' => '项目目录'],
        ['data' => ['apiPrefix' => '/system/x;rm'], 'message' => 'API'],
        ['data' => ['permissionPrefix' => 'system:x $(id)'], 'message' => '权限'],
        ['data' => ['command' => 'rm -rf /'], 'message' => '未知字段'],
        ['data' => ['templates' => ['backend' => '/tmp/evil.tpl']], 'message' => '模板'],
        ['data' => ['title' => "坏标题\0"], 'message' => '文本'],
        ['data' => ['fields' => [['name' => 'missing', 'dbType' => 'varchar(20)', 'nullable' => 'no']]], 'message' => 'nullable'],
        ['data' => ['fields' => [['name' => 'id', 'dbType' => 'bigint', 'nullable' => false, 'script' => 'evil']]], 'message' => '字段包含未知属性'],
        ['data' => ['relations' => [['name' => 'owner', 'type' => 'belongsTo']]], 'message' => '关系'],
        ['data' => ['relations' => [['name' => 'owner', 'type' => 'belongsTo', 'field' => 'owner_id', 'target' => 'Owner', 'targetField' => 'id', 'command' => 'evil']]], 'message' => '关系包含未知属性'],
    ] as $case) {
        crudReject(
            static fn () => (new DefinitionValidator())->validate(
                CrudDefinition::fromArray(validDefinition($case['data'])),
                $root
            ),
            $case['message']
        );
    }

    $queries = [];
    $inspector = new SchemaInspector(static function (string $sql, array $bindings) use (&$queries): array {
        $queries[] = [$sql, $bindings];
        return match (true) {
            str_contains($sql, 'information_schema.TABLES') => [['TABLE_NAME' => 'fun_role_user', 'TABLE_COMMENT' => '角色用户']],
            str_contains($sql, 'information_schema.COLUMNS') => [
                ['COLUMN_NAME' => 'role_id', 'COLUMN_TYPE' => 'bigint unsigned', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLUMN_COMMENT' => '角色ID', 'ORDINAL_POSITION' => 1],
                ['COLUMN_NAME' => 'user_id', 'COLUMN_TYPE' => 'bigint unsigned', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLUMN_COMMENT' => '用户ID', 'ORDINAL_POSITION' => 2],
            ],
            str_contains($sql, 'information_schema.STATISTICS') => [
                ['INDEX_NAME' => 'PRIMARY', 'NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'role_id'],
                ['INDEX_NAME' => 'PRIMARY', 'NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 2, 'COLUMN_NAME' => 'user_id'],
                ['INDEX_NAME' => 'uk_role_user', 'NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'role_id'],
                ['INDEX_NAME' => 'uk_role_user', 'NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 2, 'COLUMN_NAME' => 'user_id'],
            ],
            str_contains($sql, 'information_schema.KEY_COLUMN_USAGE') => [
                ['CONSTRAINT_NAME' => 'fk_role', 'COLUMN_NAME' => 'role_id', 'REFERENCED_TABLE_NAME' => 'fun_role', 'REFERENCED_COLUMN_NAME' => 'id'],
            ],
            default => throw new RuntimeException('出现非预期 SQL'),
        };
    });
    $schema = $inspector->inspect('fun_role_user');
    crudExpect(count($queries) === 4, 'SchemaInspector 必须读取表、字段、索引和外键');
    crudExpect(array_reduce($queries, static fn (bool $carry, array $query): bool => $carry && str_contains($query[0], 'information_schema.'), true), 'SchemaInspector 只能读取 information_schema');
    crudExpect($schema['primaryKey'] === ['role_id', 'user_id'] && $schema['pivot'] === true, '复合主键 pivot 必须被识别');
    crudExpect($schema['uniqueIndexes'][0]['columns'] === ['role_id', 'user_id'], '必须返回唯一索引');
    crudExpect($schema['foreignKeys'][0]['referencedTable'] === 'fun_role', '必须返回外键');
    crudReject(static fn () => $inspector->inspect('fun_role_user;DROP'), '表名');

    $inference = new FieldInference();
    $fields = $inference->infer([
        'primaryKey' => ['id'],
        'pivot' => false,
        'columns' => [
            ['name' => 'id', 'type' => 'bigint unsigned', 'nullable' => false, 'comment' => '主键'],
            ['name' => 'status', 'type' => 'tinyint(1)', 'nullable' => false, 'comment' => '状态:0=关闭,1=开启'],
            ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true, 'comment' => '创建时间'],
            ['name' => 'category_id', 'type' => 'bigint unsigned', 'nullable' => false, 'comment' => '分类'],
            ['name' => 'price', 'type' => 'decimal(10,2)', 'nullable' => false, 'comment' => '价格'],
        ],
        'foreignKeys' => [['column' => 'category_id', 'referencedTable' => 'fun_category', 'referencedColumn' => 'id']],
    ]);
    $byName = array_column($fields, null, 'name');
    crudExpect($byName['status']['component'] === 'radio' && $byName['status']['options'][1]['label'] === '开启', '注释枚举优先于命名与类型');
    crudExpect($byName['created_at']['managed'] === true && $byName['created_at']['writable'] === false, 'Laravel 公共字段必须使用终态规则');
    crudExpect($byName['category_id']['relation'] === 'category' && $byName['category_id']['component'] === 'select', '外键约束优先于命名');
    crudExpect($byName['price']['valueType'] === 'decimal', '类型兜底必须稳定');

    $renderer = new TemplateRenderer($root . '/templates');
    mkdir($root . '/templates/backend', 0755, true);
    file_put_contents($root . '/templates/backend/fixture.tpl', "{{title}}|{{phpClass}}\n");
    crudExpect($renderer->render('backend/fixture.tpl', ['title' => '<日志>', 'phpClass' => 'AuditLog']) === '&lt;日志&gt;|AuditLog' . "\n", '模板文本必须按上下文转义');
    crudReject(static fn () => $renderer->render('../secret', []), '模板');

    $now = 1_800_000_000;
    $tokens = new ConfirmationToken($root, 'm3-test-confirm-secret', 60, static function () use (&$now): int {
        return $now;
    });
    $planner = new GenerationPlanner($root, $tokens);
    $writerFor = static fn (?callable $afterWrite = null, ?callable $beforeReplace = null): AtomicWriter => new AtomicWriter(
        $root,
        $afterWrite,
        $tokens,
        $beforeReplace
    );
    $files = ['generated/a.txt' => "alpha\n", 'generated/b.txt' => "beta\n"];
    $planA = $planner->plan($definition, $files);
    $planB = $planner->plan($definition, array_reverse($files, true));
    crudExpect($planA['confirmToken'] !== $planB['confirmToken'], '确认 token 必须包含随机 nonce');
    crudExpect($planA['planDigest'] === $planB['planDigest'], '相同输入必须产生稳定 planDigest');
    $tokenParts = explode('.', $planA['confirmToken']);
    $tokenPayload = json_decode(base64_decode(strtr($tokenParts[0], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
    foreach (['planDigest', 'issuedAt', 'expiresAt', 'nonce'] as $claim) {
        crudExpect(isset($tokenPayload[$claim]), '确认 token payload 缺少：' . $claim);
    }
    crudExpect(!str_contains(json_encode($planA, JSON_THROW_ON_ERROR), 'm3-test-confirm-secret'), '计划和 token 不得泄露签名密钥');
    $runtimeRoot = $root . '-runtime-secret';
    mkdir($runtimeRoot, 0755, true);
    $runtimeTokenA = new ConfirmationToken($runtimeRoot);
    $runtimeTokenB = new ConfirmationToken($runtimeRoot);
    $runtimePlanDigest = hash('sha256', 'runtime-plan');
    $runtimeToken = $runtimeTokenA->issue($runtimePlanDigest);
    crudExpect($runtimeTokenB->verify($runtimeToken, $runtimePlanDigest)['planDigest'] === $runtimePlanDigest, '运行时密钥必须跨实例和进程稳定');
    $runtimeSecretPath = $runtimeRoot . '/runtime/cache/crud-confirm.secret';
    chmod($runtimeSecretPath, 0666);
    new ConfirmationToken($runtimeRoot);
    clearstatcache(true, $runtimeSecretPath);
    crudExpect((fileperms($runtimeSecretPath) & 0777) === 0600, '已有运行时密钥必须强制收紧为 0600');
    $symlinkSecretRoot = $root . '-runtime-secret-link';
    mkdir($symlinkSecretRoot . '/runtime/cache', 0700, true);
    symlink($runtimeSecretPath, $symlinkSecretRoot . '/runtime/cache/crud-confirm.secret');
    crudReject(static fn () => new ConfirmationToken($symlinkSecretRoot), '普通文件');
    crudRemoveTree($symlinkSecretRoot);
    crudRemoveTree($runtimeRoot);
    crudExpect(array_column($planA['files'], 'status') === ['create', 'create'], '新文件必须标记 create');
    crudExpect(isset($planA['files'][0]['hash'], $planA['files'][0]['diff']), '计划必须包含 hash 与 diff');
    crudReject(static fn () => $planner->plan($definition, ['../escape.txt' => 'x']), '项目目录');

    $forgedPlan = $planner->plan($definition, ['generated/forged.txt' => 'x']);
    $forgedPlan['confirmToken'][5] = $forgedPlan['confirmToken'][5] === 'a' ? 'b' : 'a';
    crudReject(static fn () => $writerFor()->write($forgedPlan, $forgedPlan['confirmToken']), '签名');
    $expiredPlan = $planner->plan($definition, ['generated/expired.txt' => 'x']);
    $now += 61;
    crudReject(static fn () => $writerFor()->write($expiredPlan, $expiredPlan['confirmToken']), '过期');
    $now -= 61;
    $changedPlan = $planner->plan($definition, ['generated/changed.txt' => 'original']);
    $changedPlan['files'][0]['content'] = 'tampered';
    $changedPlan['files'][0]['hash'] = hash('sha256', 'tampered');
    crudReject(static fn () => $writerFor()->write($changedPlan, $changedPlan['confirmToken']), 'planDigest');

    mkdir($root . '/generated', 0755, true);
    file_put_contents($root . '/generated/a.txt', "alpha\n");
    file_put_contents($root . '/generated/b.txt', "old\n");
    $conflictPlan = $planner->plan($definition, $files);
    crudExpect(array_column($conflictPlan['files'], 'status') === ['unchanged', 'conflict'], '必须区分 unchanged 与 conflict');
    crudReject(static fn () => $writerFor()->write($conflictPlan, $conflictPlan['confirmToken']), 'allowOverwrite');
    crudReject(static fn () => $writerFor()->write($conflictPlan, $conflictPlan['confirmToken'], ['generated/b.txt', 'outside.txt']), '计划外路径');
    file_put_contents($root . '/generated/b.txt', "changed-after-plan\n");
    crudReject(static fn () => $writerFor()->write($conflictPlan, $conflictPlan['confirmToken'], ['generated/b.txt']), 'hash');

    file_put_contents($root . '/generated/b.txt', "old\n");
    $writePlan = $planner->plan($definition, ['generated/b.txt' => "new\n", 'generated/c.txt' => "created\n"]);
    $calls = 0;
    $failingWriter = $writerFor(static function () use (&$calls): void {
        $calls++;
        if ($calls === 2) {
            throw new RuntimeException('模拟写入失败');
        }
    });
    crudReject(static fn () => $failingWriter->write($writePlan, $writePlan['confirmToken'], ['generated/b.txt']), '模拟写入失败');
    crudExpect(file_get_contents($root . '/generated/b.txt') === "old\n" && !is_file($root . '/generated/c.txt'), '失败必须回滚覆盖和新增文件');

    foreach (['fsync', 'verify'] as $postRenameFailure) {
        file_put_contents($root . '/generated/b.txt', "post-rename-old\n");
        $postRenamePlan = $planner->plan($definition, ['generated/b.txt' => "post-rename-new\n"]);
        putenv('FUNADMIN_CRUD_HELPER_TEST_FAIL_AFTER_RENAME=' . $postRenameFailure);
        crudReject(
            static fn () => $writerFor()->write($postRenamePlan, $postRenamePlan['confirmToken'], ['generated/b.txt']),
            '提交后'
        );
        putenv('FUNADMIN_CRUD_HELPER_TEST_FAIL_AFTER_RENAME');
        crudExpect(
            file_get_contents($root . '/generated/b.txt') === "post-rename-old\n",
            'rename 后 ' . $postRenameFailure . ' 失败也必须恢复当前文件'
        );
    }

    $structuredStage = $root . '/structured-stage.txt';
    $structuredTarget = $root . '/generated/structured-target.txt';
    file_put_contents($structuredStage, 'structured-new');
    putenv('FUNADMIN_CRUD_HELPER_TEST_FAIL_AFTER_RENAME=fsync');
    $structuredFailure = (new SafeCommit($root))->commit(
        $structuredStage,
        $structuredTarget,
        $root . '/structured-backup.txt',
        null,
        hash('sha256', 'structured-new')
    );
    putenv('FUNADMIN_CRUD_HELPER_TEST_FAIL_AFTER_RENAME');
    crudExpect(
        ($structuredFailure['ok'] ?? true) === false
        && ($structuredFailure['renamed'] ?? false) === true
        && ($structuredFailure['phase'] ?? '') === 'post_rename',
        'SafeCommit API 必须结构化区分 rename 后失败'
    );
    (new SafeCommit($root))->remove(
        $structuredTarget,
        hash('sha256', 'structured-new'),
        (string) ($structuredFailure['target_parent'] ?? '')
    );

    $successfulPlan = $planner->plan($definition, ['generated/b.txt' => "new\n"]);
    $result = $writerFor()->write($successfulPlan, $successfulPlan['confirmToken'], ['generated/b.txt']);
    crudExpect(file_get_contents($root . '/generated/b.txt') === "new\n" && $result['status'] === 'written', '精确授权后必须原子写入');
    crudReject(static fn () => $writerFor()->write($successfulPlan, $successfulPlan['confirmToken'], ['generated/b.txt']), '已使用');

    $toctouPlan = $planner->plan($definition, ['generated/b.txt' => "toctou\n"]);
    $toctouWriter = $writerFor(null, static function () use ($root): void {
        file_put_contents($root . '/generated/b.txt', "attacker\n");
    });
    crudReject(static fn () => $toctouWriter->write($toctouPlan, $toctouPlan['confirmToken'], ['generated/b.txt']), 'hash');
    crudExpect(file_get_contents($root . '/generated/b.txt') === "attacker\n", 'TOCTOU 拒绝不得覆盖并发变更');

    file_put_contents($root . '/generated/b.txt', "window-old\n");
    mkdir($root . '/attack-destination', 0755, true);
    $windowPlan = $planner->plan($definition, ['generated/b.txt' => "window-new\n"]);
    putenv('FUNADMIN_CRUD_HELPER_TEST_SWAP_PARENT=generated:attack-destination');
    crudReject(
        static fn () => $writerFor()->write($windowPlan, $windowPlan['confirmToken'], ['generated/b.txt']),
        '父目录绑定已变化'
    );
    putenv('FUNADMIN_CRUD_HELPER_TEST_SWAP_PARENT');
    $swappedParents = glob($root . '/.crud-helper-swap-*') ?: [];
    crudExpect(count($swappedParents) === 1, '测试钩子必须真实替换最终父目录绑定');
    crudExpect(!file_exists($root . '/generated/b.txt'), '攻击者替换后的目录不得收到提交内容');
    crudExpect(file_get_contents($swappedParents[0] . '/b.txt') === "window-old\n", '原目录 fd 指向的内容不得被覆盖');
    rename($root . '/generated', $root . '/attack-destination');
    rename($swappedParents[0], $root . '/generated');

    $symlinkPlan = $planner->plan($definition, ['late-link/child/file.txt' => 'safe']);
    $outside = $root . '-outside';
    mkdir($outside, 0755, true);
    $symlinkWriter = $writerFor(null, static function () use ($root, $outside): void {
        symlink($outside, $root . '/late-link');
    });
    crudReject(static fn () => $symlinkWriter->write($symlinkPlan, $symlinkPlan['confirmToken']), '符号链接');
    crudExpect(!is_file($outside . '/child/file.txt'), '替换前出现的符号链接祖先不得逃逸项目根');
    unlink($root . '/late-link');
    rmdir($outside);

    $directoryPlan = $planner->plan($definition, [
        'new-tree/deep/first.txt' => 'first',
        'new-tree/deep/second.txt' => 'second',
    ]);
    $directoryCalls = 0;
    $directoryWriter = $writerFor(static function () use (&$directoryCalls): void {
        if (++$directoryCalls === 2) {
            throw new RuntimeException('目录回滚触发');
        }
    });
    crudReject(static fn () => $directoryWriter->write($directoryPlan, $directoryPlan['confirmToken']), '目录回滚触发');
    crudExpect(!file_exists($root . '/new-tree'), '失败必须逆序清理本次新增目录');

    file_put_contents($root . '/generated/b.txt', "atomic-old\n");
    $atomicRestorePlan = $planner->plan($definition, ['generated/b.txt' => "atomic-new\n"]);
    $backupInode = null;
    $atomicRestoreWriter = $writerFor(static function () use ($root, &$backupInode): void {
        $backups = glob($root . '/.crud-write-*/backup/generated/b.txt') ?: [];
        crudExpect(count($backups) === 1, '原子恢复测试必须找到安全 staging 备份');
        $backupInode = fileinode($backups[0]);
        throw new RuntimeException('触发原子恢复');
    });
    crudReject(
        static fn () => $atomicRestoreWriter->write($atomicRestorePlan, $atomicRestorePlan['confirmToken'], ['generated/b.txt']),
        '触发原子恢复'
    );
    crudExpect(
        file_get_contents($root . '/generated/b.txt') === "atomic-old\n"
        && fileinode($root . '/generated/b.txt') === $backupInode,
        '恢复必须通过 rename 原子替换而非 copy 原地覆盖'
    );

    file_put_contents($root . '/generated/b.txt', "rollback-race-old\n");
    $rollbackRacePlan = $planner->plan($definition, ['generated/b.txt' => "rollback-race-new\n"]);
    $rollbackOutside = $root . '-rollback-outside';
    mkdir($rollbackOutside, 0755, true);
    file_put_contents($rollbackOutside . '/b.txt', "outside-safe\n");
    $rollbackRaceWriter = $writerFor(static function () use ($root, $rollbackOutside): void {
        unlink($root . '/generated/b.txt');
        symlink($rollbackOutside . '/b.txt', $root . '/generated/b.txt');
        throw new RuntimeException('触发符号链接回滚竞态');
    });
    crudReject(
        static fn () => $rollbackRaceWriter->write($rollbackRacePlan, $rollbackRacePlan['confirmToken'], ['generated/b.txt']),
        '回滚失败'
    );
    crudExpect(
        file_get_contents($rollbackOutside . '/b.txt') === "outside-safe\n"
        && (glob($root . '/.crud-write-*/backup/generated/b.txt') ?: []) !== [],
        '回滚期间目标符号链接必须拒绝且保留 staging 备份'
    );
    unlink($root . '/generated/b.txt');
    file_put_contents($root . '/generated/b.txt', "rollback-race-old\n");
    crudRemoveTree($rollbackOutside);
    foreach (glob($root . '/.crud-write-*') ?: [] as $retainedBackup) {
        crudRemoveTree($retainedBackup);
    }

    $parentRacePlan = $planner->plan($definition, ['generated/b.txt' => "parent-race-new\n"]);
    mkdir($root . '/rollback-attacker', 0755, true);
    file_put_contents($root . '/rollback-attacker/b.txt', "parent-race-new\n");
    $parentRaceWriter = $writerFor(static function () use ($root): void {
        rename($root . '/generated', $root . '/generated-held');
        rename($root . '/rollback-attacker', $root . '/generated');
        throw new RuntimeException('触发父目录替换回滚竞态');
    });
    crudReject(
        static fn () => $parentRaceWriter->write($parentRacePlan, $parentRacePlan['confirmToken'], ['generated/b.txt']),
        '回滚失败'
    );
    crudExpect(
        file_get_contents($root . '/generated/b.txt') === "parent-race-new\n"
        && (glob($root . '/.crud-write-*/backup/generated/b.txt') ?: []) !== [],
        '回滚期间父目录替换必须拒绝且保留 staging 备份'
    );
    crudRemoveTree($root . '/generated');
    rename($root . '/generated-held', $root . '/generated');
    foreach (glob($root . '/.crud-write-*') ?: [] as $retainedBackup) {
        crudRemoveTree($retainedBackup);
    }

    file_put_contents($root . '/generated/b.txt', "rollback-source\n");
    $rollbackFailurePlan = $planner->plan($definition, ['generated/b.txt' => "rollback-target\n"]);
    $rollbackFailureWriter = $writerFor(static function () use ($root): void {
        $backups = glob($root . '/.crud-write-*/backup/generated/b.txt') ?: [];
        if ($backups !== []) {
            unlink($backups[0]);
        }
        throw new RuntimeException('原始写入失败');
    });
    crudReject(static fn () => $rollbackFailureWriter->write($rollbackFailurePlan, $rollbackFailurePlan['confirmToken'], ['generated/b.txt']), '回滚失败');
    crudExpect((glob($root . '/.crud-write-*') ?: []) !== [], '回滚失败必须保留备份目录以供恢复');

    if (function_exists('pcntl_fork')) {
        $lockA = $planner->plan($definition, ['generated/lock-a.txt' => 'a']);
        $lockB = $planner->plan($definition, ['generated/lock-b.txt' => 'b']);
        $lockMarker = $root . '/lock-held';
        $pid = pcntl_fork();
        if ($pid === 0) {
            while (!is_file($lockMarker)) {
                usleep(10_000);
            }
            $started = microtime(true);
            $writerFor()->write($lockB, $lockB['confirmToken']);
            file_put_contents($root . '/lock-wait', (string) (microtime(true) - $started));
            exit(0);
        }
        $lockWriter = $writerFor(static function () use ($lockMarker): void {
            file_put_contents($lockMarker, 'held');
            usleep(500_000);
        });
        $lockWriter->write($lockA, $lockA['confirmToken']);
        pcntl_waitpid($pid, $status);
        crudExpect((float) file_get_contents($root . '/lock-wait') >= 0.35, '并发写入必须等待项目级排他锁');
    }

    mkdir($root . '/blocked-target', 0755, true);
    $blockedPlan = $planner->plan($definition, ['blocked-target' => 'cannot replace directory']);
    crudExpect($blockedPlan['files'][0]['status'] === 'blocked', '目录目标必须标记 blocked');

    $fixtureDefinition = CrudDefinition::fromArray(validDefinition([
        'paths' => [
            'backend' => 'fixture/backend/AuditLog.php',
            'frontend' => 'fixture/frontend/audit-log.vue',
            'database' => 'fixture/database/audit-log.sql',
        ],
    ]));
    $generatorPlan = (new CrudGenerator($root, dirname(__DIR__, 2) . '/app/common/crud/templates/v1', $tokens))->plan($fixtureDefinition);
    crudExpect(count($generatorPlan['files']) === 3, 'Core 必须通过后端、前端、数据库模板形成完整计划');
    $generatedByPath = array_column($generatorPlan['files'], 'content', 'path');
    crudExpect(str_contains($generatedByPath['fixture/frontend/audit-log.vue'], 'useCrud'), '前端 fixture 必须复用 useCrud 模式');

    $definitionFile = $root . '/definition.json';
    file_put_contents($definitionFile, json_encode($fixtureDefinition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    putenv('CRUD_CONFIRM_SECRET=m3-test-confirm-secret');
    $adapterResult = (new AdminWebCrudGenerator($root))->run('definition.json');
    crudExpect($adapterResult['dryRun'] === true && isset($adapterResult['sensitive']['confirmToken']), '服务层必须单独返回 sensitive.confirmToken');
    crudExpect(!isset($adapterResult['plan']['confirmToken']), '普通 plan 不得包含确认 token');
    crudExpect(!str_contains($adapterResult['output'], $adapterResult['sensitive']['confirmToken']), '普通摘要不得打印确认 token');
    crudReject(static fn () => (new AdminWebCrudGenerator($root))->run('definition.json', true, true), 'force');
    $adapterSource = file_get_contents(dirname(__DIR__, 2) . '/app/common/service/AdminWebCrudGenerator.php');
    crudExpect(!str_contains((string) $adapterSource, 'proc_open') && !str_contains((string) $adapterSource, 'crud-gen.mjs'), '兼容适配器不得再调用 Node Core');
    $commandSource = (string) file_get_contents(dirname(__DIR__, 2) . '/extend/fun/curd/AdminWebCrud.php');
    crudExpect(!str_contains($commandSource, "addOption('confirm-token'"), 'CLI 不得接受 argv confirm token');
    crudExpect(str_contains($commandSource, "php://stdin") || str_contains($commandSource, 'confirm-token-file'), 'CLI 写入 token 必须从 stdin 或 0600 文件读取');
    $mcpSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/common/service/McpService.php');
    crudExpect(str_contains($mcpSource, 'run($configPath, true, false)'), 'MCP CRUD 必须固定为只读 dry-run');
    $mcpMethod = substr($mcpSource, strpos($mcpSource, 'public function handleCurd'), 900);
    crudExpect(str_contains($mcpMethod, 'unset($result[\'sensitive\'])'), 'MCP 返回必须移除 sensitive token');

    $manifest = GenerationManifest::create($definition, 'fixture-v1', $successfulPlan, 'admin', 'written', [
        'password' => 'secret',
        'token' => 'secret-token',
        'safe' => 'kept',
    ]);
    $encodedManifest = json_encode($manifest->toArray(), JSON_THROW_ON_ERROR);
    crudExpect(!str_contains($encodedManifest, 'secret') && str_contains($encodedManifest, 'admin'), 'Manifest 必须排除 secret 并记录 operator');
    foreach (['definitionHash', 'templateVersion', 'files', 'operator', 'status'] as $key) {
        crudExpect(array_key_exists($key, $manifest->toArray()), 'Manifest 缺少字段：' . $key);
    }

    echo "CRUD core tests: PASS\n";
} finally {
    crudRemoveTree($root);
}
