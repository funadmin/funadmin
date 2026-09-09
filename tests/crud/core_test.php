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
use app\console\service\PermissionResource;

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
        if ($item->isLink() || $item->isFile()) {
            unlink($item->getPathname());
        } else {
            rmdir($item->getPathname());
        }
    }
    rmdir($path);
}

function crudRun(array $command, ?string $workingDirectory = null): string
{
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory, null, ['bypass_shell' => true]);
    crudExpect(is_resource($process), '无法启动验证进程：' . implode(' ', $command));
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    crudExpect($code === 0, "验证命令失败：" . implode(' ', $command) . "\n{$stdout}{$stderr}");
    return (string) $stdout . (string) $stderr;
}

function validDefinition(array $overrides = []): array
{
    return array_replace_recursive([
        'schemaVersion' => '1.0',
        'name' => 'audit-log',
        'table' => 'fun_audit_log',
        'title' => '审计日志',
        'paths' => [
            'migration' => 'database/generated/audit_log.sql',
            'model' => 'app/console/model/AuditLog.php',
            'validate' => 'app/console/validate/AuditLogValidate.php',
            'service' => 'app/console/service/AuditLogService.php',
            'controller' => 'app/console/controller/generated/AuditLogController.php',
            'permissionMigration' => 'database/generated/audit_log_permissions.sql',
            'api' => 'admin-web/src/api/generated/audit-log.ts',
            'view' => 'admin-web/src/views/generated/audit-log/index.vue',
            'form' => 'admin-web/src/views/generated/audit-log/components/AuditLogForm.vue',
            'detail' => 'admin-web/src/views/generated/audit-log/components/AuditLogDetail.vue',
        ],
        'apiPrefix' => '/system/audit-log',
        'permissionPrefix' => 'system:audit-log',
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true],
            ['name' => 'status', 'dbType' => 'tinyint(1)', 'nullable' => false, 'comment' => '状态:0=禁用,1=启用', 'list' => true, 'search' => true, 'searchOperator' => 'eq', 'form' => true, 'detail' => true],
        ],
        'relations' => [],
        'optionsSource' => [],
        'templates' => [
            'migration' => 'database/migration.sql.tpl', 'model' => 'console/model.php.tpl',
            'validate' => 'console/validate.php.tpl', 'service' => 'console/service.php.tpl',
            'controller' => 'console/controller.php.tpl',
            'permissionMigration' => 'database/permissions.sql.tpl',
            'api' => 'frontend/api.ts.tpl', 'view' => 'frontend/index.vue.tpl',
            'form' => 'frontend/form.vue.tpl', 'detail' => 'frontend/detail.vue.tpl',
        ],
        'capabilities' => ['list' => true, 'search' => true, 'form' => true, 'detail' => true, 'create' => true, 'update' => true, 'delete' => true, 'import' => true, 'export' => true],
        'features' => ['batchDelete' => true, 'status' => true, 'detail' => true, 'import' => true, 'export' => true, 'upload' => true, 'dictionary' => true, 'referenceProtection' => true, 'formMode' => 'dialog', 'importLimit' => 10000, 'exportLimit' => 10000],
        'dataScope' => ['enabled' => false, 'field' => ''],
    ], $overrides);
}

$root = sys_get_temp_dir() . '/funadmin-crud-core-' . bin2hex(random_bytes(5));
mkdir($root, 0755, true);

try {
    $definition = CrudDefinition::fromArray(validDefinition());
    (new DefinitionValidator())->validate($definition, $root);
    crudExpect($definition->schemaVersion() === '1.0', 'Definition 必须保留 schemaVersion');
    crudExpect($definition->get('connection') === 'mysql', '旧 Definition 缺省连接必须归一化为 mysql');
    crudExpect($definition->fields()[0]['name'] === 'id', 'Definition 必须保留字段');
    $generatedContext = \app\common\crud\ProductionTemplateContext::build($definition);
    $generatedView = (string) ($generatedContext['viewContent'] ?? '');
    crudExpect(str_contains($generatedView, 'handleSelectionChange = (rows: Record<string, unknown>[])'), 'audit-log index.vue selection 回调必须使用结构化通用行类型');
    crudExpect(str_contains($generatedView, 'onSelectionChange(rows as unknown as AuditLogModel[])'), 'selection 回调必须在 useCrud 边界安全转换为生成模型类型');
    crudExpect(!str_contains($generatedView, 'rows: unknown[]'), 'audit-log index.vue 不得生成裸 unknown[] 参数');

    $definitionSchema = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/app/common/crud/schema/crud-definition-v1.schema.json'),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    $strictSnakeCase = '^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$';
    crudExpect($definitionSchema['properties']['table']['pattern'] === $strictSnakeCase, 'Schema table 必须严格 snake_case');
    crudExpect($definitionSchema['properties']['dataScope']['properties']['field']['pattern'] === "{$strictSnakeCase}|^$", 'Schema dataScope.field 必须严格 snake_case');
    foreach (['name', 'optionsSource'] as $fieldIdentifier) {
        crudExpect($definitionSchema['$defs']['field']['properties'][$fieldIdentifier]['pattern'] === $strictSnakeCase, 'Schema 字段标识符必须严格 snake_case');
    }
    foreach (['name', 'field', 'targetField', 'pivotTable', 'pivotLocalKey', 'pivotTargetKey', 'optionsSource'] as $relationIdentifier) {
        crudExpect($definitionSchema['$defs']['relation']['properties'][$relationIdentifier]['pattern'] === $strictSnakeCase, 'Schema 关系标识符必须严格 snake_case');
    }
    crudExpect(
        $definitionSchema['$defs']['relation']['allOf'][0]['then']['required'] === ['pivotTable', 'pivotLocalKey', 'pivotTargetKey'],
        'Schema belongsToMany 必须要求完整 pivot 标识符'
    );
    crudExpect(!in_array('route', $definitionSchema['$defs']['artifactNames']['enum'], true), 'Schema 不得声明独立 route 制品');
    crudExpect(!in_array('route', $definitionSchema['$defs']['artifacts']['required'], true), 'Schema 不得要求独立 route 路径');
    crudExpect(count($definitionSchema['$defs']['templates']['required']) === 12, 'Schema 必须要求 12 个生成模板');

    foreach ([
        ['data' => ['schemaVersion' => '9.9'], 'message' => 'schemaVersion'],
        ['data' => ['name' => '../escape'], 'message' => 'entity'],
        ['data' => ['paths' => ['controller' => '../outside.php']], 'message' => '项目目录'],
        ['data' => ['apiPrefix' => '/system/x;rm', 'routePath' => '/system/x;rm'], 'message' => 'apiPrefix'],
        ['data' => ['permissionPrefix' => 'system:x $(id)'], 'message' => '权限'],
        ['data' => ['command' => 'rm -rf /'], 'message' => '未知字段'],
        ['data' => ['templates' => ['controller' => '/tmp/evil.tpl']], 'message' => '模板'],
        ['data' => ['title' => "坏标题\0"], 'message' => '文本'],
        ['data' => ['fields' => [['name' => 'missing', 'dbType' => 'varchar(20)', 'nullable' => 'no']]], 'message' => 'nullable'],
        ['data' => ['fields' => [['name' => 'id', 'dbType' => 'bigint); DROP TABLE users; --', 'nullable' => false, 'primary' => true]]], 'message' => '字段类型'],
        ['data' => ['table' => '_audit_log'], 'message' => 'table'],
        ['data' => ['fields' => [['name' => 'audit__id', 'dbType' => 'bigint', 'nullable' => false, 'primary' => true]]], 'message' => 'primaryKey'],
        ['data' => ['fields' => [['name' => 'id', 'dbType' => 'bigint', 'nullable' => false, 'script' => 'evil']]], 'message' => '字段包含未知属性'],
        ['data' => ['relations' => [['name' => 'owner', 'type' => 'belongsTo']]], 'message' => '关系'],
        ['data' => ['relations' => [['name' => 'owner', 'type' => 'belongsTo', 'field' => 'owner_id', 'target' => 'Owner', 'targetField' => 'id', 'command' => 'evil']]], 'message' => '关系包含未知属性'],
        ['data' => ['fields' => [
            ['name' => 'id', 'dbType' => 'bigint', 'nullable' => false, 'primary' => true],
            ['name' => 'owner_id', 'dbType' => 'bigint', 'nullable' => false, 'relation' => "owner']; phpinfo(); //", 'references' => 'Owner.id'],
        ]], 'message' => '字段 relation'],
        ['data' => ['fields' => [
            ['name' => 'id', 'dbType' => 'bigint', 'nullable' => false, 'primary' => true],
            ['name' => 'owner_id', 'dbType' => 'bigint', 'nullable' => false, 'relation' => 'owner', 'references' => "Owner.id']; phpinfo(); //"],
        ]], 'message' => '字段 references'],
        ['data' => ['fields' => [
            ['name' => 'id', 'dbType' => 'bigint', 'nullable' => false, 'primary' => true],
            ['name' => 'owner_id', 'dbType' => 'bigint', 'nullable' => false, 'relation' => 'owner', 'references' => 'Owner.owner__id'],
        ]], 'message' => '字段 references'],
        ['data' => ['relations' => [[
            'name' => 'status__items', 'type' => 'belongsTo', 'field' => 'status', 'target' => 'Status',
            'targetField' => 'id',
        ]]], 'message' => '关系名'],
        ['data' => ['relations' => [[
            'name' => 'statuses', 'type' => 'belongsToMany', 'field' => 'status', 'target' => 'Status',
            'targetField' => 'id', 'pivotTable' => 'FunAuditStatus',
            'pivotLocalKey' => 'audit_id', 'pivotTargetKey' => 'status_id',
        ]]], 'message' => 'pivotTable'],
        ['data' => ['relations' => [[
            'name' => 'statuses', 'type' => 'belongsToMany', 'field' => 'status', 'target' => 'Status',
            'targetField' => 'id', 'pivotTable' => "fun_audit_status`; phpinfo(); //",
            'pivotLocalKey' => 'audit_id', 'pivotTargetKey' => 'status_id',
        ]]], 'message' => 'pivotTable'],
        ['data' => ['relations' => [[
            'name' => 'statuses', 'type' => 'belongsToMany', 'field' => 'status', 'target' => 'Status',
            'targetField' => 'id', 'pivotTable' => 'fun_audit_status',
            'pivotLocalKey' => "audit_id'); phpinfo(); //", 'pivotTargetKey' => 'status_id',
        ]]], 'message' => 'pivotLocalKey'],
        ['data' => ['relations' => [[
            'name' => 'statuses', 'type' => 'belongsToMany', 'field' => 'status', 'target' => 'Status',
            'targetField' => 'id', 'pivotTable' => 'fun_audit_status',
            'pivotLocalKey' => 'audit_id', 'pivotTargetKey' => "status_id'); phpinfo(); //",
        ]]], 'message' => 'pivotTargetKey'],
        ['data' => ['optionsSource' => [['name' => 'unsafe', 'type' => 'endpoint', 'endpoint' => '/safe', 'labelField' => "name']; phpinfo(); //", 'valueField' => 'id']]], 'message' => 'optionsSource labelField'],
        ['data' => ['optionsSource' => [['name' => 'unsafe', 'type' => 'dictionary', 'dictionary' => "code'); phpinfo(); //", 'labelField' => 'label', 'valueField' => 'value']]], 'message' => 'dictionary'],
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
    mkdir($root . '/templates/console', 0755, true);
    file_put_contents($root . '/templates/console/fixture.tpl', "{{title}}|{{phpClass}}\n");
    crudExpect($renderer->render('console/fixture.tpl', ['title' => '<日志>', 'phpClass' => 'AuditLog']) === '&lt;日志&gt;|AuditLog' . "\n", '模板文本必须按上下文转义');
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

    $nonWritableScope = validDefinition([
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true],
            ['name' => 'department_id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'writable' => false],
        ],
        'features' => ['status' => false],
        'dataScope' => ['enabled' => true, 'field' => 'department_id', 'resolver' => 'adminDepartmentIds'],
    ]);
    crudReject(
        static fn () => (new DefinitionValidator())->validate(CrudDefinition::fromArray($nonWritableScope), $root),
        'dataScope.field 必须可写'
    );
    $optionalScope = validDefinition([
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true],
            ['name' => 'department_id', 'dbType' => 'bigint unsigned', 'nullable' => true, 'writable' => true, 'required' => false],
        ],
        'features' => ['status' => false],
        'dataScope' => ['enabled' => true, 'field' => 'department_id', 'resolver' => 'adminDepartmentIds'],
    ]);
    crudReject(
        static fn () => (new DefinitionValidator())->validate(CrudDefinition::fromArray($optionalScope), $root),
        'dataScope.field 必须必填'
    );

    $fixtureDefinition = CrudDefinition::fromArray(validDefinition([
        'paths' => array_map(static fn (string $path): string => 'fixture/' . $path, validDefinition()['paths']),
        'fields' => [
            ['name' => 'uuid', 'dbType' => 'varchar(36)', 'nullable' => false, 'primary' => true, 'required' => true, 'format' => 'uuid', 'list' => true, 'detail' => true],
            ['name' => 'name', 'label' => '</script><script>alert(1)</script>', 'dbType' => 'varchar(80)', 'nullable' => false, 'required' => true, 'maxLength' => 80, 'unique' => true, 'search' => true, 'searchOperator' => 'like', 'sortable' => true, 'list' => true, 'form' => true, 'detail' => true],
            ['name' => 'amount', 'dbType' => 'decimal(12,2)', 'nullable' => false, 'valueType' => 'decimal', 'min' => 0, 'max' => 9999999999.99, 'list' => true, 'form' => true, 'detail' => true],
            ['name' => 'department_id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'relation' => 'department', 'references' => 'Department.id', 'optionsSource' => 'department_options', 'form' => true],
            ['name' => 'status', 'dbType' => 'tinyint(1)', 'nullable' => false, 'enum' => [0, 1], 'search' => true, 'searchOperator' => 'eq', 'list' => true, 'form' => true],
            ['name' => 'created_at', 'dbType' => 'datetime', 'nullable' => true, 'managed' => true, 'writable' => false, 'search' => true, 'searchOperator' => 'range', 'detail' => true],
        ],
        'relations' => [['name' => 'department', 'type' => 'belongsTo', 'field' => 'department_id', 'target' => 'Department', 'targetField' => 'id', 'optionsSource' => 'department_options', 'with' => true]],
        'optionsSource' => [
            ['name' => 'department_options', 'type' => 'relation', 'labelField' => 'name', 'valueField' => 'id'],
            ['name' => 'remote_options', 'type' => 'endpoint', 'endpoint' => '/system/remote/options', 'labelField' => 'title', 'valueField' => 'code'],
        ],
        'dataScope' => ['enabled' => true, 'field' => 'department_id', 'resolver' => 'adminDepartmentIds'],
    ]));
    $generator = new CrudGenerator($root, dirname(__DIR__, 2) . '/app/common/crud/templates/v1', $tokens);
    $generatorPlan = $generator->plan($fixtureDefinition);
    crudExpect(count($generatorPlan['files']) === 12, 'M5 必须生成 12 个非路由制品');
    crudExpect(
        array_filter($generatorPlan['files'], static fn (array $file): bool => str_contains($file['path'], '/route/')) === [],
        '生成计划不得包含任何路由文件'
    );
    $stablePlan = $generator->plan($fixtureDefinition);
    crudExpect($generatorPlan['planDigest'] === $stablePlan['planDigest'], 'M5 相同 Definition 必须稳定生成');
    $generatedByPath = array_column($generatorPlan['files'], 'content', 'path');
    crudExpect(isset($generatedByPath['tests/generated/AuditLogGeneratedTest.php']), 'M5 必须生成 PHP 测试制品');
    crudExpect(isset($generatedByPath['admin-web/tests/generated/audit-log.spec.ts']), 'M5 必须生成 Vitest 测试制品');
    $migrationPath = 'fixture/database/generated/audit_log.sql';
    crudExpect(str_contains($generatedByPath[$migrationPath], '`uk_fun_audit_log_name`'), '生成唯一索引名必须严格 snake_case');
    crudExpect(!str_contains($generatedByPath[$migrationPath], '`uk_audit-log_name`'), '生成 SQL 标识符不得包含模块 slug 连字符');
    $controllerPath = 'fixture/app/console/controller/generated/AuditLogController.php';
    crudExpect(str_contains($generatedByPath[$controllerPath], "#[Group('system/audit-log')]"), '控制器必须声明 Attribute 路由组');
    foreach (['detail', 'update', 'status', 'remove', 'restore', 'destroy'] as $idAction) {
        crudExpect(
            preg_match("/#\\[[^\\n]+':id[^\\n]*\\]\\n    #\\[Pattern\\('id', '\\[A-Za-z0-9_-\\]\\+'\\)\\]\\n    public function {$idAction}\\(/", $generatedByPath[$controllerPath]) === 1,
            "{$idAction} 的 :id 路由必须紧邻统一 Pattern"
        );
    }
    $validateSource = $generatedByPath['fixture/app/console/validate/AuditLogValidate.php'];
    crudExpect(str_contains($validateSource, 'forUpdate(') && str_contains($validateSource, 'unique:'), '唯一校验 update 必须排除参数化主键');
    crudExpect(
        str_contains($generatedByPath[$controllerPath], "protected function primaryKeyType(): string { return 'uuid'; }")
        && str_contains($generatedByPath[$controllerPath], "protected function primaryKeyPattern(): ?string { return '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD'; }"),
        '生成控制器必须按 Definition 主键类型声明严格 UUID 归一化规则'
    );
    $generatedService = $generatedByPath['fixture/app/console/service/AuditLogService.php'];
    crudExpect(str_contains($generatedService, "whereIn('department_id'"), '必须生成 dataScope 约束');
    crudExpect(
        str_contains($generatedService, 'public function options(string $source, ?array $departmentIds = null): array')
        && str_contains($generatedService, "whereIn('id', \$departmentIds ?: [0])"),
        'relation field 等于 dataScope.field 时 options 必须按允许 IDs 限制目标 valueField'
    );
    crudExpect(
        str_contains($generatedByPath[$controllerPath], "\$scope = (new DataScopeService())->resolve();")
        && str_contains($generatedByPath[$controllerPath], "->options(\$source, \$scope['all'] ? null : \$scope['departmentIds'])"),
        'options controller 必须将与写入校验相同的当前数据范围传入 service'
    );
    crudExpect(str_contains($generatedByPath['fixture/app/console/model/AuditLog.php'], 'function department()'), '必须生成模型关系');
    crudExpect(str_contains($generatedByPath['fixture/admin-web/src/api/generated/audit-log.ts'], 'amount: string'), '金额前端类型必须为 string');
    crudExpect(str_contains($generatedByPath['fixture/admin-web/src/views/generated/audit-log/index.vue'], 'useCrud'), '前端列表必须复用 useCrud');
    crudExpect(str_contains($generatedByPath['fixture/admin-web/src/views/generated/audit-log/index.vue'], 'SearchForm') && str_contains($generatedByPath['fixture/admin-web/src/views/generated/audit-log/index.vue'], 'DataTableShell'), '列表必须复用 SearchForm/DataTableShell');
    $permissionMigration = $generatedByPath['fixture/database/generated/audit_log_permissions.sql'];
    crudExpect(str_contains($permissionMigration, 'Generated forward permission/menu migration'), '权限菜单必须为独立 forward migration');
    crudExpect(str_contains($permissionMigration, 'fun_admin_menu'), '权限迁移必须同时注册菜单');
    $runtimePermission = PermissionResource::fromParts('console', 'generated\\AuditLogController', 'detail');
    crudExpect(
        $runtimePermission === ['obj' => 'console/generated.auditlogcontroller', 'act' => 'detail', 'code' => 'console/generated.auditlogcontroller:detail']
        && str_contains($permissionMigration, "'console/generated.auditlogcontroller', 'detail'")
        && str_contains($permissionMigration, "'console/generated.auditlogcontroller', 'index'"),
        '权限迁移 controller/action 必须与 PermissionResource 运行时资源一致'
    );
    crudExpect(
        str_contains($permissionMigration, "'system:audit-log:options', 'console/generated.auditlogcontroller', 'options'"),
        '启用 options 时必须生成与前端 code、运行时 obj/act 一致的权限'
    );
    crudExpect(str_contains($generatedByPath[$controllerPath], "options/:source"), '关系与字典必须生成受控 options endpoint');
    $generatedApi = $generatedByPath['fixture/admin-web/src/api/generated/audit-log.ts'];
    crudExpect(str_contains($generatedApi, '/system/remote/options') && str_contains($generatedApi, 'item.title') && str_contains($generatedApi, 'item.code'), 'endpoint optionsSource 必须生成同源可运行客户端并映射 label/value');
    $generatedView = $generatedByPath['fixture/admin-web/src/views/generated/audit-log/index.vue'];
    crudExpect(!str_contains($generatedView, '</script><script>'), 'Definition 文本不得闭合 Vue script 上下文');
    foreach (['回收站', '恢复', '永久删除', '导入', '导出', 'status'] as $capability) {
        crudExpect(str_contains($generatedView, $capability), '生成列表缺少完整能力：' . $capability);
    }
    crudExpect(str_contains($generatedByPath[$controllerPath], 'DataScopeService'), 'dataScope 必须进入控制器真实查询链');
    crudExpect(str_contains($generatedByPath[$controllerPath], 'baseQuery as private crudUnscopedBaseQuery'), 'dataScope 必须复用 Trait 基础查询');
    crudExpect(str_contains($generatedByPath[$controllerPath], 'protected function baseQuery(bool $onlyTrashed, bool $withTrashed)'), 'dataScope 必须覆盖所有详情与写操作的基础查询');
    crudExpect(str_contains($generatedByPath[$controllerPath], "return '无权写入指定数据范围';"), 'dataScope 必须拒绝创建、更新和导入到未授权部门');
    crudExpect(
        str_contains($generatedByPath[$controllerPath], "\$model === null && !array_key_exists('department_id', \$data)")
        && str_contains($generatedByPath[$controllerPath], "return '数据范围字段 department_id 必填';"),
        '非全范围创建与导入必须强制提交 dataScope 字段'
    );
    crudExpect(
        str_contains($generatedByPath[$controllerPath], "\$scopeValue = \$data['department_id'] ?? \$model?->department_id;")
        && str_contains($generatedByPath[$controllerPath], "in_array((int) \$scopeValue"),
        '更新必须校验提交值或模型原值仍位于授权 dataScope IDs'
    );
    $limitedDefinition = CrudDefinition::fromArray(validDefinition([
        'paths' => array_map(static fn (string $path): string => 'limited/' . $path, validDefinition()['paths']),
        'capabilities' => [
            'list' => true, 'search' => true, 'form' => true, 'detail' => false,
            'create' => false, 'update' => false, 'delete' => false, 'import' => false, 'export' => false,
        ],
    ]));
    $limitedPlan = $generator->plan($limitedDefinition);
    $limitedByPath = array_column($limitedPlan['files'], 'content', 'path');
    $limitedController = $limitedByPath['limited/app/console/controller/generated/AuditLogController.php'];
    foreach (['detail', 'create', 'update', 'status', 'recycle', 'restore', 'destroy', 'import', 'export'] as $disabledAction) {
        crudExpect(!str_contains($limitedController, "public function {$disabledAction}("), 'capability=false 不得暴露控制器动作：' . $disabledAction);
    }
    crudExpect(str_contains($limitedController, 'public function index()'), 'list capability 必须保留 index 动作');
    $limitedPermission = $limitedByPath['limited/database/generated/audit_log_permissions.sql'];
    crudExpect(str_contains($limitedPermission, "'console/generated.auditlogcontroller', 'index'"), 'list 权限必须映射运行时 index action');
    foreach (['detail', 'create', 'update', 'status', 'recycle', 'restore', 'destroy', 'import', 'export'] as $disabledAction) {
        crudExpect(!str_contains($limitedPermission, "'console/generated.auditlogcontroller', '{$disabledAction}'"), 'capability=false 不得生成权限动作：' . $disabledAction);
    }
    $limitedApi = $limitedByPath['limited/admin-web/src/api/generated/audit-log.ts'];
    crudExpect(str_contains($limitedApi, '  list:'), '启用的 list API 必须保留');
    foreach (['detail', 'create', 'update', 'removeMany', 'restore', 'forceDelete', 'status', 'importRows', 'exportRows'] as $disabledMethod) {
        crudExpect(!str_contains($limitedApi, "  {$disabledMethod}: ("), 'capability=false 不得生成前端 API：' . $disabledMethod);
    }
    $limitedView = $limitedByPath['limited/admin-web/src/views/generated/audit-log/index.vue'];
    foreach (['新增', '批量删除', '回收站', '恢复', '永久删除', '导入', '导出', 'onAdd', 'onEdit', 'onOpenDrawer', 'changeStatus', 'importCsv', 'exportRows'] as $disabledUi) {
        crudExpect(!str_contains($limitedView, $disabledUi), 'capability=false 不得生成页面按钮或调用：' . $disabledUi);
    }
    $batchDisabledDefinition = CrudDefinition::fromArray(validDefinition([
        'paths' => array_map(static fn (string $path): string => 'batch-disabled/' . $path, validDefinition()['paths']),
        'features' => ['batchDelete' => false],
    ]));
    $batchDisabledPlan = $generator->plan($batchDisabledDefinition);
    $batchDisabledByPath = array_column($batchDisabledPlan['files'], 'content', 'path');
    $batchDisabledController = $batchDisabledByPath['batch-disabled/app/console/controller/generated/AuditLogController.php'];
    crudExpect(!str_contains($batchDisabledController, 'public function recycle()'), 'batchDelete=false 不得生成批量 recycle 路由');
    crudExpect(
        str_contains($batchDisabledController, "#[Delete(':id')]")
        && str_contains($batchDisabledController, 'public function remove(int|string $id)')
        && str_contains($batchDisabledController, "#[Post(':id/restore')]")
        && str_contains($batchDisabledController, "#[Delete(':id/destroy')]"),
        'batchDelete=false 时必须仅保留明确的 soft-delete 单记录动作'
    );
    $batchDisabledApi = $batchDisabledByPath['batch-disabled/admin-web/src/api/generated/audit-log.ts'];
    foreach (['removeMany', 'restoreMany', 'forceDeleteMany'] as $batchMethod) {
        crudExpect(!str_contains($batchDisabledApi, "  {$batchMethod}:"), 'batchDelete=false 不得生成批量 API：' . $batchMethod);
    }
    crudExpect(
        str_contains($batchDisabledApi, '  remove:')
        && str_contains($batchDisabledApi, '  restore:')
        && str_contains($batchDisabledApi, '  forceDelete:'),
        'batchDelete=false 时单记录删除、恢复与永久删除 API 必须保留'
    );
    $batchDisabledView = $batchDisabledByPath['batch-disabled/admin-web/src/views/generated/audit-log/index.vue'];
    foreach (['批量删除', '批量恢复', '批量永久删除', 'onBatchDelete', 'selection', 'type="selection"'] as $batchUi) {
        crudExpect(!str_contains($batchDisabledView, $batchUi), 'batchDelete=false 不得生成批量 UI：' . $batchUi);
    }
    crudExpect(
        str_contains($batchDisabledView, '@click="removeRow(scope.row as AuditLogModel)"')
        && str_contains($batchDisabledView, 'await auditLogApi.remove(row.id)'),
        'batchDelete=false 时列表单删按钮和调用必须可用'
    );
    $batchDisabledPermission = $batchDisabledByPath['batch-disabled/database/generated/audit_log_permissions.sql'];
    crudExpect(
        str_contains($batchDisabledPermission, "'system:audit-log:delete', 'console/generated.auditlogcontroller', 'remove'")
        && !str_contains($batchDisabledPermission, "'console/generated.auditlogcontroller', 'recycle'"),
        'batchDelete=false 必须仅生成单删运行时权限资源'
    );

    $featureDisabledDefinition = CrudDefinition::fromArray(validDefinition([
        'paths' => array_map(static fn (string $path): string => 'feature-disabled/' . $path, validDefinition()['paths']),
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true],
            ['name' => 'dictionary_value', 'dbType' => 'varchar(30)', 'nullable' => true, 'form' => true, 'optionsSource' => 'dictionary_options'],
            ['name' => 'owner_id', 'dbType' => 'bigint unsigned', 'nullable' => true, 'form' => true, 'relation' => 'owner', 'references' => 'Admin.id', 'optionsSource' => 'owner_options'],
            ['name' => 'remote_value', 'dbType' => 'varchar(30)', 'nullable' => true, 'form' => true, 'optionsSource' => 'remote_options'],
            ['name' => 'attachment', 'dbType' => 'varchar(255)', 'nullable' => true, 'form' => true, 'upload' => true],
        ],
        'relations' => [[
            'name' => 'owner', 'type' => 'belongsTo', 'field' => 'owner_id', 'target' => 'Admin',
            'targetField' => 'id', 'optionsSource' => 'owner_options', 'with' => true,
        ]],
        'optionsSource' => [
            ['name' => 'dictionary_options', 'type' => 'dictionary', 'dictionary' => 'audit_status', 'labelField' => 'label', 'valueField' => 'value'],
            ['name' => 'owner_options', 'type' => 'relation', 'labelField' => 'username', 'valueField' => 'id'],
            ['name' => 'remote_options', 'type' => 'endpoint', 'endpoint' => '/system/remote/options', 'labelField' => 'title', 'valueField' => 'code'],
        ],
        'features' => ['status' => false, 'upload' => false, 'dictionary' => false],
    ]));
    $featureDisabledPlan = $generator->plan($featureDisabledDefinition);
    $featureDisabledByPath = array_column($featureDisabledPlan['files'], 'content', 'path');
    $featureDisabledService = $featureDisabledByPath['feature-disabled/app/console/service/AuditLogService.php'];
    crudExpect(
        !str_contains($featureDisabledService, 'dictionaryOptions')
        && !str_contains($featureDisabledService, 'dictionary_options')
        && str_contains($featureDisabledService, "'owner_options' =>"),
        'dictionary=false 仅禁用字典 optionsSource，relation options 必须保留'
    );
    $featureDisabledController = $featureDisabledByPath['feature-disabled/app/console/controller/generated/AuditLogController.php'];
    crudExpect(str_contains($featureDisabledController, 'public function options('), 'dictionary=false 不得影响 relation options 控制器');
    $featureDisabledApi = $featureDisabledByPath['feature-disabled/admin-web/src/api/generated/audit-log.ts'];
    crudExpect(
        str_contains($featureDisabledApi, '  options:') && str_contains($featureDisabledApi, '/system/remote/options'),
        'dictionary=false 不得影响 relation/endpoint options API'
    );
    $featureDisabledForm = $featureDisabledByPath['feature-disabled/admin-web/src/views/generated/audit-log/components/AuditLogForm.vue'];
    crudExpect(
        str_contains($featureDisabledForm, '<el-input v-model="form.dictionaryValue" />')
        && !str_contains($featureDisabledForm, 'dictionary_options')
        && str_contains($featureDisabledForm, 'optionLists.owner_options')
        && str_contains($featureDisabledForm, 'optionLists.remote_options'),
        'dictionary=false 时字典字段必须退化输入，relation/endpoint 选项仍保留'
    );
    crudExpect(
        str_contains($featureDisabledForm, '<el-input v-model="form.attachment" />')
        && !str_contains($featureDisabledForm, '<Upload')
        && !str_contains($featureDisabledForm, "import Upload from"),
        'upload=false 时上传字段必须退化为普通输入且不引入 Upload'
    );

    $dictionaryOnlyDisabledDefinition = CrudDefinition::fromArray(validDefinition([
        'paths' => array_map(static fn (string $path): string => 'dictionary-disabled/' . $path, validDefinition()['paths']),
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true],
            ['name' => 'dictionary_value', 'dbType' => 'varchar(30)', 'nullable' => true, 'form' => true, 'optionsSource' => 'dictionary_options'],
        ],
        'optionsSource' => [[
            'name' => 'dictionary_options', 'type' => 'dictionary', 'dictionary' => 'audit_status',
            'labelField' => 'label', 'valueField' => 'value',
        ]],
        'features' => ['status' => false, 'dictionary' => false],
    ]));
    $dictionaryOnlyDisabledPlan = $generator->plan($dictionaryOnlyDisabledDefinition);
    $dictionaryOnlyDisabledByPath = array_column($dictionaryOnlyDisabledPlan['files'], 'content', 'path');
    $dictionaryOnlyDisabledForm = $dictionaryOnlyDisabledByPath['dictionary-disabled/admin-web/src/views/generated/audit-log/components/AuditLogForm.vue'];
    crudExpect(
        !str_contains($dictionaryOnlyDisabledForm, 'loadOptions()')
        && !str_contains($dictionaryOnlyDisabledForm, 'dictionary_options'),
        '禁用唯一字典 optionsSource 时表单不得调用未生成的 loadOptions'
    );

    $generated = $generator->generate($fixtureDefinition, $generatorPlan['confirmToken'], [], 'm5-test');
    crudExpect(($generated['write']['status'] ?? '') === 'written', 'M5 preview 后必须真实生成到临时项目');
    foreach (array_keys($generatedByPath) as $relativePath) {
        $absolutePath = $root . '/' . $relativePath;
        crudExpect(is_file($absolutePath), '缺少实际生成文件：' . $relativePath);
        if (str_ends_with($relativePath, '.php')) {
            $lint = crudRun([PHP_BINARY, '-l', $absolutePath]);
            crudExpect(str_contains($lint, 'No syntax errors detected'), '生成 PHP 文件 lint 失败：' . $relativePath);
        }
    }
    $temporaryWeb = $root . '/fixture/admin-web';
    symlink(dirname(__DIR__, 2) . '/admin-web/node_modules', $temporaryWeb . '/node_modules');
    mkdir($temporaryWeb . '/src/utils/http', 0755, true);
    file_put_contents($temporaryWeb . '/src/utils/http/index.ts', <<<'TS'
const call = async <T = unknown>(..._args: any[]): Promise<T> => undefined as T;
export default { get: call, post: call, put: call, delete: call };
TS
    );
    file_put_contents($temporaryWeb . '/generated-support.d.ts', <<<'TS'
declare module '@/composables/useCrud' {
  export function useCrud<T extends Record<string, any>, Q extends Record<string, any>, ID>(options: any): {
    loading: any; list: any; total: any; query: Q; selection: any; dialogVisible: any; drawerVisible: any;
    current: any; loadData: () => Promise<void>; onSearch: () => void; onReset: () => void; onAdd: () => void;
    onEdit: (row: T) => void; onOpenDrawer: (row: T) => void; onBatchDelete: () => Promise<void>;
    onSelectionChange: (rows: T[]) => void;
  };
}
declare module '@/utils/csv' {
  export interface CsvColumn<T = any> { key: keyof T & string; label?: string }
  export function parseCsv<T extends Record<string, any>>(text: string, columns: CsvColumn<T>[]): T[];
  export function readFileAsText(file: File): Promise<string>;
  export function toCsv<T extends Record<string, any>>(rows: T[], columns: CsvColumn<T>[]): string;
  export function downloadCsv(filename: string, content: string): void;
}
TS
    );
    $temporaryTsconfig = [
        'extends' => dirname(__DIR__, 2) . '/admin-web/tsconfig.json',
        'compilerOptions' => [
            'baseUrl' => $temporaryWeb,
            'paths' => [
                '@/api/generated/*' => ['src/api/generated/*'],
                '@/views/generated/*' => ['src/views/generated/*'],
                '@/*' => ['src/*'],
            ],
            'types' => ['node', 'vite/client', 'element-plus/global'],
        ],
        'include' => [
            'src/**/*.ts', 'src/**/*.vue', 'tests/**/*.ts', 'generated-support.d.ts',
            dirname(__DIR__, 2) . '/admin-web/src/types/**/*.d.ts',
            dirname(__DIR__, 2) . '/admin-web/auto-imports.d.ts',
        ],
    ];
    file_put_contents($temporaryWeb . '/tsconfig.json', json_encode($temporaryTsconfig, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    crudRun([dirname(__DIR__, 2) . '/admin-web/node_modules/.bin/vue-tsc', '--noEmit', '-p', $temporaryWeb . '/tsconfig.json'], $temporaryWeb);
    mkdir($temporaryWeb . '/tests', 0755, true);
    file_put_contents($temporaryWeb . '/tests/generated.spec.ts', <<<'TS'
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

describe('generated CRUD artifacts', () => {
  it('keeps generated API and Vue components executable by the frontend toolchain', () => {
    const api = readFileSync(resolve('src/api/generated/audit-log.ts'), 'utf8');
    const view = readFileSync(resolve('src/views/generated/audit-log/index.vue'), 'utf8');
    expect(api).toContain('/system/audit-log');
    expect(view).toContain('useCrud');
  });
});
TS
    );
    crudRun([dirname(__DIR__, 2) . '/admin-web/node_modules/.bin/vitest', 'run', '--root', $temporaryWeb, 'tests/generated.spec.ts'], $temporaryWeb);
    crudExpect(!is_file(dirname(__DIR__, 2) . '/app/common/service/AdminWebCrudGenerator.php'), '旧 AdminWebCrudGenerator 兼容适配器不得恢复');
    crudExpect(!is_file(dirname(__DIR__, 2) . '/extend/fun/crud/AdminWebCrud.php'), '旧 CRUD CLI 兼容入口不得恢复');
    $generateSource = (string) file_get_contents(dirname(__DIR__, 2) . '/extend/fun/command/CrudGenerate.php');
    $supportSource = (string) file_get_contents(dirname(__DIR__, 2) . '/extend/fun/command/CrudCommandSupport.php');
    crudExpect(!str_contains($generateSource, "getOption('confirm-token')"), 'crud:generate 不得接受 argv confirm token');
    crudExpect(str_contains($supportSource, 'stream_get_contents(STDIN)') && str_contains($supportSource, '0600'), 'crud:generate token 必须从 stdin 或 0600 文件读取');
    $mcpSource = (string) file_get_contents(dirname(__DIR__, 2) . '/app/common/service/McpService.php');
    $mcpMethod = substr($mcpSource, strpos($mcpSource, 'public function handleCrud'), 2600);
    crudExpect(str_contains($mcpMethod, '->plan('), 'MCP CRUD 必须使用只读生成预览');
    crudExpect(!str_contains($mcpMethod, '->generate('), 'MCP CRUD 不得执行生成写入');
    crudExpect(str_contains($mcpMethod, 'unset($plan[\'confirmToken\'])'), 'MCP 返回必须移除确认 token');
    crudExpect(str_contains($mcpMethod, "'dryRun' => true"), 'MCP CRUD 必须明确返回 dry-run 状态');

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
