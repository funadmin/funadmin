<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\DefinitionValidator;
use app\common\service\MigrationService;
use Ramsey\Uuid\Uuid;
use think\App;
use think\event\RouteLoaded;
use think\facade\Db;
use think\facade\Session;

function m5Expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function m5Data(think\Response $response): array
{
    $payload = $response->getData();
    if (is_string($payload)) {
        $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }
    m5Expect(is_array($payload), '响应必须可解析为数组');
    return $payload;
}

function m5Request(App $app, array $get = [], array $post = []): void
{
    $app->request->withGet($get)->withPost($post)->withRoute([]);
}

function m5RemoveTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

/**
 * 当前生成器面向带 array 类型的 Validate 基类；本仓库锁定的 think-validate 仍是无类型属性。
 * 仅在运行时 fixture 中移除子类属性类型，保留生成规则和所有真实控制器验证行为。
 */
function m5PrepareValidatorFixture(string $path): void
{
    $parentRule = new ReflectionProperty(think\Validate::class, 'rule');
    if ($parentRule->hasType()) {
        return;
    }
    $source = (string) file_get_contents($path);
    $output = preg_replace('/protected\\s+array\\s+\\$rule\\s*=/', 'protected $rule =', $source, 1, $count);
    m5Expect($count === 1 && is_string($output), 'Validate 兼容 fixture 必须识别生成的 typed rule 属性');
    file_put_contents($path, $output);
}

$projectRoot = dirname(__DIR__, 2);
$fixtureRoot = sys_get_temp_dir() . '/funadmin-m5-fixture-' . bin2hex(random_bytes(5));
$databaseName = 'funadmin_m5_test_' . bin2hex(random_bytes(5));
$app = new App($projectRoot . '/');
$app->http->name('console');
$app->setAppPath($projectRoot . '/app/console/');
$app->setNamespace('app\\console');
$app->initialize();
$mysql = config('database.connections.mysql');
$server = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=%s', $mysql['hostname'], $mysql['hostport'], $mysql['charset']),
    $mysql['username'],
    $mysql['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
m5Expect((bool) preg_match('/^funadmin_m5_test_[a-f0-9]+$/', $databaseName), '隔离数据库名不安全');
$server->exec("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

try {
    mkdir($fixtureRoot, 0755, true);
    $definition = CrudDefinition::fromArray([
        'schemaVersion' => '1.0',
        'name' => 'm5-record',
        'table' => 'fun_m5_record',
        'title' => 'M5 <记录> "安全"',
        'paths' => [
            'migration' => 'database/m5_record.sql',
            'model' => 'app/console/model/M5Record.php',
            'validate' => 'app/console/validate/M5RecordValidate.php',
            'service' => 'app/console/service/M5RecordService.php',
            'controller' => 'app/console/controller/generated/M5RecordController.php',
            'permissionMigration' => 'database/m5_record_permissions.sql',
            'api' => 'admin-web/src/api/generated/m5-record.ts',
            'view' => 'admin-web/src/views/generated/m5-record/index.vue',
            'form' => 'admin-web/src/views/generated/m5-record/components/M5RecordForm.vue',
            'detail' => 'admin-web/src/views/generated/m5-record/components/M5RecordDetail.vue',
        ],
        'apiPrefix' => '/generated/m5-record',
        'permissionPrefix' => 'generated:m5-record',
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true, 'detail' => true],
            ['name' => 'name', 'label' => '名称 <script>', 'dbType' => 'varchar(80)', 'nullable' => false, 'required' => true, 'maxLength' => 80, 'unique' => true, 'search' => true, 'searchOperator' => 'like', 'sortable' => true, 'list' => true, 'form' => true, 'detail' => true],
            ['name' => 'amount', 'dbType' => 'decimal(12,2)', 'nullable' => false, 'valueType' => 'decimal', 'min' => 0, 'list' => true, 'form' => true, 'detail' => true],
            ['name' => 'department_id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'relation' => 'department', 'references' => 'Department.id', 'optionsSource' => 'department_options', 'form' => true, 'detail' => true],
            ['name' => 'category', 'dbType' => 'varchar(30)', 'nullable' => false, 'optionsSource' => 'category_options', 'dictionary' => true, 'form' => true, 'detail' => true],
            ['name' => 'attachment_url', 'dbType' => 'varchar(255)', 'nullable' => true, 'upload' => true, 'form' => true, 'detail' => true],
            ['name' => 'status', 'dbType' => 'tinyint(1)', 'nullable' => false, 'default' => 1, 'enum' => [0, 1], 'search' => true, 'searchOperator' => 'eq', 'list' => true, 'form' => true, 'detail' => true],
            ['name' => 'created_at', 'dbType' => 'datetime', 'nullable' => true, 'managed' => true, 'writable' => false, 'search' => true, 'searchOperator' => 'range', 'detail' => true],
            ['name' => 'updated_at', 'dbType' => 'datetime', 'nullable' => true, 'managed' => true, 'writable' => false, 'detail' => true],
        ],
        'relations' => [[
            'name' => 'department', 'type' => 'belongsTo', 'field' => 'department_id', 'target' => 'Department',
            'targetField' => 'id', 'optionsSource' => 'department_options', 'with' => true,
        ]],
        'optionsSource' => [
            ['name' => 'department_options', 'type' => 'relation', 'labelField' => 'name', 'valueField' => 'id'],
            ['name' => 'category_options', 'type' => 'dictionary', 'dictionary' => 'm5_category', 'labelField' => 'label', 'valueField' => 'value'],
        ],
        'templates' => [
            'migration' => 'database/migration.sql.tpl', 'model' => 'console/model.php.tpl',
            'validate' => 'console/validate.php.tpl', 'service' => 'console/service.php.tpl',
            'controller' => 'console/controller.php.tpl',
            'permissionMigration' => 'database/permissions.sql.tpl',
            'api' => 'frontend/api.ts.tpl', 'view' => 'frontend/index.vue.tpl',
            'form' => 'frontend/form.vue.tpl', 'detail' => 'frontend/detail.vue.tpl',
        ],
        'metadata' => ['connection' => 'mysql'],
        'capabilities' => ['list' => true, 'search' => true, 'form' => true, 'detail' => true, 'create' => true, 'update' => true, 'delete' => true, 'import' => true, 'export' => true],
        'features' => ['softDelete' => true, 'batchDelete' => true, 'status' => true, 'detail' => true, 'import' => true, 'export' => true, 'upload' => true, 'dictionary' => true, 'referenceProtection' => true, 'formMode' => 'dialog', 'importLimit' => 2, 'exportLimit' => 2],
        'dataScope' => ['enabled' => true, 'field' => 'department_id', 'resolver' => 'adminDepartmentIds'],
    ]);

    $generator = new CrudGenerator($fixtureRoot, $projectRoot . '/app/common/crud/templates/v1', new ConfirmationToken($fixtureRoot, 'm5-runtime-secret'));
    $plan = $generator->plan($definition);
    $generated = $generator->generate($definition, $plan['confirmToken'], [], 'm5-runtime-test');
    m5Expect(($generated['write']['status'] ?? '') === 'written', 'fixture 必须真实生成');

    $uuidData = $definition->toArray();
    $uuidData['entity'] = 'm5-uuid-record';
    $uuidData['table'] = 'fun_m5_uuid_record';
    $uuidData['primaryKey'] = 'uuid';
    $uuidData['title'] = 'M5 UUID 记录';
    $uuidData['routePath'] = '/generated/m5-uuid-record';
    $uuidData['permissionPrefix'] = 'generated:m5-uuid-record';
    foreach ($uuidData['generationTargets'] as &$path) {
        $path = str_replace(['m5_record', 'M5Record', 'm5-record'], ['m5_uuid_record', 'M5UuidRecord', 'm5-uuid-record'], $path);
    }
    unset($path);
    $uuidData['fields'] = [
        ['name' => 'uuid', 'dbType' => 'varchar(36)', 'nullable' => false, 'primary' => true, 'required' => true, 'format' => 'uuid', 'detail' => true],
        ['name' => 'name', 'dbType' => 'varchar(80)', 'nullable' => false, 'required' => true, 'list' => true, 'form' => true, 'detail' => true],
        ['name' => 'status', 'dbType' => 'tinyint(1)', 'nullable' => false, 'default' => 1, 'list' => true, 'detail' => true],
        ['name' => 'created_at', 'dbType' => 'datetime', 'nullable' => true, 'managed' => true, 'writable' => false, 'detail' => true],
        ['name' => 'updated_at', 'dbType' => 'datetime', 'nullable' => true, 'managed' => true, 'writable' => false, 'detail' => true],
    ];
    $uuidData['relations'] = [];
    $uuidData['optionsSource'] = [];
    $uuidData['features']['dictionary'] = false;
    $uuidData['features']['referenceProtection'] = false;
    $uuidData['dataScope'] = ['enabled' => false, 'field' => ''];
    $uuidDefinition = CrudDefinition::fromArray($uuidData);
    $uuidPlan = $generator->plan($uuidDefinition);
    $uuidGenerated = $generator->generate($uuidDefinition, $uuidPlan['confirmToken'], [], 'm5-runtime-uuid-test');
    m5Expect(($uuidGenerated['write']['status'] ?? '') === 'written', 'UUID fixture 必须真实生成');

    $withoutStatusData = $uuidData;
    $withoutStatusData['entity'] = 'm5-without-status';
    $withoutStatusData['table'] = 'fun_m5_without_status';
    $withoutStatusData['title'] = 'M5 无状态记录';
    $withoutStatusData['routePath'] = '/generated/m5-without-status';
    $withoutStatusData['permissionPrefix'] = 'generated:m5-without-status';
    foreach ($withoutStatusData['generationTargets'] as &$path) {
        $path = str_replace(['m5_uuid_record', 'M5UuidRecord', 'm5-uuid-record'], ['m5_without_status', 'M5WithoutStatus', 'm5-without-status'], $path);
    }
    unset($path);
    $withoutStatusData['fields'] = array_values(array_filter(
        $withoutStatusData['fields'],
        static fn (array $field): bool => $field['name'] !== 'status'
    ));
    $withoutStatusData['features']['status'] = false;
    $withoutStatusDefinition = CrudDefinition::fromArray($withoutStatusData);
    $withoutStatusPlan = $generator->plan($withoutStatusDefinition);
    $withoutStatusGenerated = $generator->generate($withoutStatusDefinition, $withoutStatusPlan['confirmToken'], [], 'm5-runtime-without-status-test');
    m5Expect(($withoutStatusGenerated['write']['status'] ?? '') === 'written', '无 status fixture 必须真实生成');
    $withoutStatusController = file_get_contents($fixtureRoot . '/app/console/controller/generated/M5WithoutStatusController.php');
    $withoutStatusApi = file_get_contents($fixtureRoot . '/admin-web/src/api/generated/m5-without-status.ts');
    $withoutStatusView = file_get_contents($fixtureRoot . '/admin-web/src/views/generated/m5-without-status/index.vue');
    m5Expect(!str_contains($withoutStatusController, "#[Post(':id/status')]") && !str_contains($withoutStatusApi, 'status: (id:') && !str_contains($withoutStatusView, 'changeStatus'), '无 status 生成制品不得残留状态能力');

    $invalidStatusData = $withoutStatusData;
    $invalidStatusData['features']['status'] = true;
    try {
        (new DefinitionValidator())->validate(CrudDefinition::fromArray($invalidStatusData), $fixtureRoot);
        throw new RuntimeException('启用 status 时必须要求字段存在');
    } catch (InvalidArgumentException $exception) {
        m5Expect(str_contains($exception->getMessage(), 'status'), '缺少 status 字段必须给出明确错误');
    }
    foreach ([
        ['name' => 'status', 'dbType' => 'tinyint(1)', 'nullable' => false, 'writable' => false],
        ['name' => 'status', 'dbType' => 'varchar(20)', 'nullable' => false, 'writable' => true],
    ] as $invalidStatusField) {
        $invalidStatusData['fields'][] = $invalidStatusField;
        try {
            (new DefinitionValidator())->validate(CrudDefinition::fromArray($invalidStatusData), $fixtureRoot);
            throw new RuntimeException('启用 status 时必须拒绝不可写或类型不兼容字段');
        } catch (InvalidArgumentException $exception) {
            m5Expect(str_contains($exception->getMessage(), 'status'), '非法 status 字段必须给出明确错误');
        }
        array_pop($invalidStatusData['fields']);
    }

    $databaseConfig = config('database');
    $databaseConfig['connections']['mysql']['database'] = $databaseName;
    $app->config->set($databaseConfig, 'database');
    Db::connect('mysql')->execute('CREATE TABLE fun_department (id bigint unsigned NOT NULL AUTO_INCREMENT, name varchar(80) NOT NULL, status tinyint NOT NULL DEFAULT 1, created_at datetime NULL, updated_at datetime NULL, deleted_at datetime NULL, PRIMARY KEY(id)) ENGINE=InnoDB');
    Db::connect('mysql')->execute('CREATE TABLE fun_admin (id bigint unsigned NOT NULL AUTO_INCREMENT, username varchar(80) NOT NULL, dept_id bigint unsigned NOT NULL, status tinyint NOT NULL DEFAULT 1, created_at datetime NULL, updated_at datetime NULL, deleted_at datetime NULL, PRIMARY KEY(id)) ENGINE=InnoDB');
    Db::connect('mysql')->execute('CREATE TABLE fun_admin_department (admin_id bigint unsigned NOT NULL, dept_id bigint unsigned NOT NULL, PRIMARY KEY(admin_id, dept_id)) ENGINE=InnoDB');
    Db::connect('mysql')->execute('CREATE TABLE fun_auth_group (id bigint unsigned NOT NULL AUTO_INCREMENT, pid bigint unsigned NOT NULL DEFAULT 0, data_scope varchar(30) NOT NULL, status tinyint NOT NULL DEFAULT 1, created_at datetime NULL, updated_at datetime NULL, deleted_at datetime NULL, PRIMARY KEY(id)) ENGINE=InnoDB');
    Db::connect('mysql')->execute('CREATE TABLE fun_casbin_rule (id bigint unsigned NOT NULL AUTO_INCREMENT, ptype varchar(10) NOT NULL, v0 varchar(190) NOT NULL DEFAULT \'\', v1 varchar(190) NOT NULL DEFAULT \'\', v2 varchar(190) NOT NULL DEFAULT \'\', v3 varchar(190) NOT NULL DEFAULT \'\', v4 varchar(190) NOT NULL DEFAULT \'\', v5 varchar(190) NOT NULL DEFAULT \'\', rule_hash char(64) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uk_rule_hash(rule_hash)) ENGINE=InnoDB');
    Db::connect('mysql')->execute('CREATE TABLE fun_dict_type (id bigint unsigned NOT NULL AUTO_INCREMENT, code varchar(60) NOT NULL, name varchar(80) NOT NULL, status tinyint NOT NULL, sort_order int NOT NULL DEFAULT 0, created_at datetime NULL, updated_at datetime NULL, deleted_at datetime NULL, PRIMARY KEY(id), UNIQUE KEY uk_code(code)) ENGINE=InnoDB');
    Db::connect('mysql')->execute('CREATE TABLE fun_dict_item (id bigint unsigned NOT NULL AUTO_INCREMENT, type_id bigint unsigned NOT NULL, label varchar(80) NOT NULL, value varchar(80) NOT NULL, status tinyint NOT NULL, sort_order int NOT NULL DEFAULT 0, created_at datetime NULL, updated_at datetime NULL, deleted_at datetime NULL, PRIMARY KEY(id)) ENGINE=InnoDB');
    Db::connect('mysql')->execute('CREATE TABLE fun_admin_menu (id bigint unsigned NOT NULL AUTO_INCREMENT, pid bigint unsigned NOT NULL DEFAULT 0, permission_id bigint unsigned NOT NULL DEFAULT 0, app_name varchar(50) NOT NULL DEFAULT \'console\', name varchar(100) NOT NULL, href varchar(255) NOT NULL, query varchar(250) NOT NULL, target varchar(20) NOT NULL, icon varchar(100) NOT NULL, status tinyint NOT NULL, sort int NOT NULL DEFAULT 0, sort_order int NOT NULL, source_type varchar(20) NOT NULL, source_name varchar(100) NOT NULL, created_at datetime NULL, updated_at datetime NULL, deleted_at datetime NULL, PRIMARY KEY(id), UNIQUE KEY uk_menu_location(app_name,href,query)) ENGINE=InnoDB');
    Db::connect('mysql')->execute('CREATE TABLE fun_permission (id bigint unsigned NOT NULL AUTO_INCREMENT, pid bigint unsigned NOT NULL DEFAULT 0, app_name varchar(50) NOT NULL DEFAULT \'console\', code varchar(255) NULL, obj varchar(190) NOT NULL, act varchar(100) NOT NULL, name varchar(100) NOT NULL, resource_type varchar(20) NOT NULL, status tinyint NOT NULL, is_public tinyint NOT NULL, sort int NOT NULL DEFAULT 0, sort_order int NOT NULL, source_type varchar(20) NOT NULL, source_name varchar(100) NOT NULL, created_at datetime NULL, updated_at datetime NULL, deleted_at datetime NULL, PRIMARY KEY(id), UNIQUE KEY uk_permission_code(code)) ENGINE=InnoDB');

    $migrationService = new MigrationService();
    $statements = new ReflectionMethod($migrationService, 'statements');
    $statements->setAccessible(true);
    foreach ([$fixtureRoot . '/database/m5_record.sql', $fixtureRoot . '/database/m5_uuid_record.sql', $fixtureRoot . '/database/m5_without_status.sql'] as $migrationFile) {
        foreach ($statements->invoke($migrationService, file_get_contents($migrationFile)) as $statement) {
            Db::connect('mysql')->execute($statement);
        }
    }
    Db::connect('mysql')->execute('CREATE TABLE fun_m5_record_link (id bigint unsigned NOT NULL AUTO_INCREMENT, record_id bigint unsigned NOT NULL, PRIMARY KEY(id), CONSTRAINT fk_m5_record_link_record FOREIGN KEY(record_id) REFERENCES fun_m5_record(id)) ENGINE=InnoDB');
    Db::name('department')->insertAll([['id' => 1, 'name' => '研发'], ['id' => 2, 'name' => '财务']]);
    Db::name('admin')->insert(['id' => 2, 'username' => 'limited', 'dept_id' => 1, 'status' => 1]);
    Db::name('auth_group')->insert(['id' => 2, 'pid' => 0, 'data_scope' => 'dept', 'status' => 1]);
    $roleRule = ['g', 'admin:2', 'role:2', 'default'];
    Db::name('casbin_rule')->insert([
        'ptype' => 'g', 'v0' => 'admin:2', 'v1' => 'role:2', 'v2' => 'default',
        'rule_hash' => hash('sha256', implode("\x1f", $roleRule)),
    ]);
    $dictTypeId = Db::name('dict_type')->insertGetId(['code' => 'm5_category', 'name' => '分类', 'status' => 1]);
    Db::name('dict_item')->insert(['type_id' => $dictTypeId, 'label' => '甲类', 'value' => 'a', 'status' => 1, 'sort_order' => 10]);

    foreach (['M5RecordValidate.php', 'M5UuidRecordValidate.php', 'M5WithoutStatusValidate.php'] as $validatorFixture) {
        m5PrepareValidatorFixture($fixtureRoot . '/app/console/validate/' . $validatorFixture);
    }
    if (!class_exists('app\\console\\service\\DataScopeService')) {
        class_alias(\app\console\authorization\service\DataScopeService::class, 'app\\console\\service\\DataScopeService');
    }
    foreach (['model/M5Record.php', 'validate/M5RecordValidate.php', 'service/M5RecordService.php', 'controller/generated/M5RecordController.php', 'model/M5UuidRecord.php', 'validate/M5UuidRecordValidate.php', 'service/M5UuidRecordService.php', 'controller/generated/M5UuidRecordController.php', 'model/M5WithoutStatus.php', 'validate/M5WithoutStatusValidate.php', 'service/M5WithoutStatusService.php', 'controller/generated/M5WithoutStatusController.php'] as $file) {
        require_once $fixtureRoot . '/app/console/' . $file;
    }
    $modelClass = 'app\\console\\model\\M5Record';
    $modelName = new ReflectionProperty($modelClass, 'name');
    $modelName->setAccessible(true);
    m5Expect($modelName->getValue(new $modelClass()) === 'm5_record', '生成 model 必须去除数据库前缀');
    $validatorClass = 'app\\console\\validate\\M5RecordValidate';
    $serviceClass = 'app\\console\\service\\M5RecordService';
    $controllerClass = 'app\\console\\controller\\generated\\M5RecordController';

    $same = $modelClass::create(['name' => 'same', 'amount' => '1.00', 'department_id' => 1, 'category' => 'a', 'status' => 1]);
    $validator = (new $validatorClass())->forUpdate($same->id);
    m5Expect($validator->check(['name' => 'same', 'amount' => '2.00', 'department_id' => 1, 'category' => 'a', 'status' => 1]), 'unique update 必须排除当前 id');

    $service = new $serviceClass();
    $scoped = $service->query([2])->select()->toArray();
    m5Expect($scoped === [], 'dataScope 必须约束 service 查询');
    $relationOptions = $service->options('department_options');
    m5Expect($relationOptions[0] === ['label' => '研发', 'value' => 1], 'relation options 必须统一 label/value，实际：' . json_encode($relationOptions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    m5Expect($service->options('category_options')[0] === ['value' => 'a', 'label' => '甲类'], 'dictionary options 必须真实查询字典');

    Session::set('admin.id', 2);
    $limitedController = new $controllerClass($app);
    m5Request($app);
    $limitedOptions = m5Data($limitedController->options('department_options'));
    m5Expect($limitedOptions['data'] === [['label' => '研发', 'value' => 1]], '受限会话 options 不得枚举范围外 relation 值，实际：' . json_encode($limitedOptions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    m5Request($app, [], ['name' => 'denied', 'amount' => '10.25', 'department_id' => 2, 'category' => 'a', 'status' => 1]);
    m5Expect(m5Data($limitedController->create())['code'] === 422, '受限会话 options 与写入必须使用相同范围');

    $countBeforeRollback = $modelClass::count();
    try {
        $service->save(null, ['name' => 'rollback', 'amount' => '3.00', 'department_id' => 1, 'category' => 'a', 'status' => 1], static function (): void {
            throw new RuntimeException('relation failure');
        });
        throw new RuntimeException('事务失败必须抛出');
    } catch (RuntimeException $exception) {
        m5Expect($exception->getMessage() === 'relation failure', '必须透传事务失败');
    }
    m5Expect($modelClass::count() === $countBeforeRollback, 'service 事务失败必须回滚主记录');

    Session::set('admin.id', 1);
    $controller = new $controllerClass($app);
    m5Request($app, [], ['name' => 'created', 'amount' => '10.25', 'department_id' => 1, 'category' => 'a', 'attachment_url' => '/storage/a.png', 'status' => 1]);
    $created = m5Data($controller->create());
    m5Expect($created['code'] === 200 && ($created['data']['name'] ?? null) === 'created', 'controller create 必须可运行，实际：' . json_encode($created, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $id = (int) $created['data']['id'];

    m5Request($app, [], ['name' => 'created', 'amount' => '11.25', 'department_id' => 1, 'category' => 'a', 'status' => 1]);
    m5Expect(m5Data($controller->update($id))['code'] === 200, 'controller update 必须支持 unchanged unique');
    m5Request($app, ['page' => 1, 'pageSize' => 20, 'name' => 'creat'], []);
    $list = m5Data($controller->index());
    m5Expect($list['data']['total'] === 1 && $list['data']['list'][0]['department']['name'] === '研发', 'list 必须筛选并 eager load relation');
    m5Request($app);
    m5Expect(m5Data($controller->detail($id))['data']['department']['name'] === '研发', 'detail 必须 eager load relation');
    m5Request($app, [], ['status' => 0]);
    m5Expect(m5Data($controller->status($id))['data']['status'] === 0, 'status 必须更新');

    Db::name('m5_record_link')->insert(['record_id' => $id]);
    m5Request($app, ['ids' => [$id]], []);
    m5Expect(m5Data($controller->recycle())['code'] === 422, 'referenceProtection 必须阻止删除被外键引用的记录');
    Db::name('m5_record_link')->where('record_id', $id)->delete();
    m5Request($app, ['ids' => [$id]], []);
    m5Expect(m5Data($controller->recycle())['data']['removed'] === 1, 'delete/batch recycle 必须可运行');
    m5Request($app, ['recycled' => 1], []);
    m5Expect(m5Data($controller->index())['data']['total'] === 1, 'recycle list 必须可运行');
    m5Request($app, ['ids' => [$id]], []);
    m5Expect(m5Data($controller->restoreMany())['data']['restored'] === 1, '批量 restore 必须可运行');

    $beforeImport = $modelClass::count();
    m5Request($app, [], ['rows' => [
        ['name' => 'import-ok', 'amount' => '1.00', 'department_id' => 1, 'category' => 'a', 'status' => 1],
        ['name' => '', 'amount' => '2.00', 'department_id' => 1, 'category' => 'a', 'status' => 1],
    ]]);
    m5Expect(m5Data($controller->import())['code'] === 422 && $modelClass::count() === $beforeImport, 'import 任一行失败必须整批回滚');
    m5Request($app, [], ['rows' => [[], [], []]]);
    m5Expect(m5Data($controller->import())['code'] === 422, 'import 必须执行 Definition 上限');

    $modelClass::create(['name' => 'export-overflow', 'amount' => '1.00', 'department_id' => 1, 'category' => 'a', 'status' => 1]);
    m5Request($app);
    m5Expect(m5Data($controller->export())['code'] === 422, 'export 必须执行 Definition 上限');

    m5Request($app, ['ids' => [$id]], []);
    $controller->recycle();
    m5Request($app, ['ids' => [$id]], []);
    m5Expect(m5Data($controller->destroyMany())['data']['removed'] === 1 && $modelClass::withTrashed()->find($id) === null, '批量 forceDelete 必须永久删除');

    $uuidModelClass = 'app\\console\\model\\M5UuidRecord';
    $uuidControllerClass = 'app\\console\\controller\\generated\\M5UuidRecordController';
    $uuidController = new $uuidControllerClass($app);
    m5Request($app, [], ['uuid' => '550e8400-e29b-41d4-a716-446655440000', 'name' => 'uuid-created', 'status' => 1]);
    $uuidCreated = m5Data($uuidController->create());
    $createdUuid = (string) ($uuidCreated['data']['uuid'] ?? '');
    m5Expect($uuidCreated['code'] === 200 && Uuid::isValid($createdUuid) && $createdUuid !== '550e8400-e29b-41d4-a716-446655440000', 'controller create 必须忽略客户端主键并生成有效 UUID');
    m5Request($app, [], ['rows' => [
        ['uuid' => '550e8400-e29b-41d4-a716-446655440001', 'name' => 'uuid-import-a', 'status' => 1],
        ['uuid' => '550e8400-e29b-41d4-a716-446655440002', 'name' => 'uuid-import-b', 'status' => 1],
    ]]);
    $uuidImported = m5Data($uuidController->import());
    $importedUuids = $uuidModelClass::whereLike('name', 'uuid-import-%')->column('uuid');
    m5Expect($uuidImported['code'] === 200 && count($importedUuids) === 2 && count(array_unique($importedUuids)) === 2, 'controller import 必须生成唯一 UUID');
    foreach ($importedUuids as $importedUuid) {
        m5Expect(Uuid::isValid((string) $importedUuid) && !in_array($importedUuid, ['550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440002'], true), 'controller import 必须忽略客户端 UUID');
    }
    m5Request($app, [], ['name' => 'without-status']);
    $withoutStatusControllerClass = 'app\\console\\controller\\generated\\M5WithoutStatusController';
    $withoutStatusRuntimeController = new $withoutStatusControllerClass($app);
    m5Expect(m5Data($withoutStatusRuntimeController->create())['code'] === 200, '无 status controller create 必须可运行');

    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    $uuidModelClass::create(['uuid' => $uuid, 'name' => 'uuid-batch', 'status' => 1]);
    m5Request($app, ['ids' => [$uuid]], []);
    $uuidRecycle = m5Data($uuidController->recycle());
    m5Expect(($uuidRecycle['data']['removed'] ?? null) === 1, 'UUID 批量 delete 必须保留字符串主键，实际：' . json_encode($uuidRecycle, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    m5Request($app, ['ids' => [$uuid]], []);
    m5Expect(m5Data($uuidController->restoreMany())['data']['restored'] === 1, 'UUID 批量 restore 必须使用同一主键归一化');
    m5Request($app, ['ids' => [$uuid]], []);
    $uuidController->recycle();
    m5Request($app, ['ids' => [$uuid]], []);
    m5Expect(m5Data($uuidController->destroyMany())['data']['removed'] === 1 && $uuidModelClass::withTrashed()->where('uuid', $uuid)->find() === null, 'UUID 批量 forceDelete 必须使用同一主键归一化');
    m5Request($app, ['ids' => [$uuid, $uuid]], []);
    m5Expect(m5Data($uuidController->recycle())['code'] === 422, '批量动作必须拒绝重复主键');
    m5Request($app, ['ids' => ['']], []);
    m5Expect(m5Data($uuidController->recycle())['code'] === 422, '批量动作必须拒绝空主键');
    m5Request($app, ['ids' => ['not-a-uuid']], []);
    m5Expect(m5Data($uuidController->recycle())['code'] === 422, '批量动作必须拒绝异常主键');

    $controllerDirectory = $fixtureRoot . '/app/console/controller';
    $annotationConfig = config('annotation');
    $annotationConfig['route']['controllers'] = [$controllerDirectory => ['namespace' => 'app\\console\\controller']];
    $app->config->set($annotationConfig, 'annotation');
    $app->event->trigger(RouteLoaded::class);
    $routes = array_column($app->route->getRuleList(), 'rule');
    m5Expect(in_array('generated/m5-record', $routes, true) && in_array('generated/m5-record/<id>', $routes, true), '生成控制器必须被 Annotation route discovery 发现，实际：' . json_encode($routes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    m5Expect(in_array('generated/m5-record/options/<source>', $routes, true), 'options 动作必须被运行时 Annotation route discovery 发现');

    foreach ($statements->invoke($migrationService, file_get_contents($fixtureRoot . '/database/m5_record_permissions.sql')) as $statement) {
        Db::connect('mysql')->execute($statement);
    }
    m5Expect(Db::name('admin_menu')->where('source_type', 'generated')->where('source_name', 'm5-record')->count() === 1, 'permission migration 必须注册菜单');
    $codes = Db::name('permission')->where('source_type', 'generated')->where('source_name', 'm5-record')->column('code');
    foreach (['generated:m5-record:list', 'generated:m5-record:options', 'generated:m5-record:delete', 'generated:m5-record:destroy', 'generated:m5-record:import', 'generated:m5-record:export'] as $code) {
        m5Expect(in_array($code, $codes, true), 'permission migration 缺少权限：' . $code);
    }
    $optionsPermission = Db::name('permission')->where('code', 'generated:m5-record:options')->find();
    m5Expect(
        ($optionsPermission['obj'] ?? null) === 'console/generated.m5recordcontroller'
        && ($optionsPermission['act'] ?? null) === 'options',
        'options 权限必须与运行时 PermissionResource 的 obj/act 一致'
    );
    $menu = Db::name('admin_menu')->where('source_type', 'generated')->where('source_name', 'm5-record')->find();
    m5Expect(
        (int) ($menu['permission_id'] ?? 0) > 0
        && str_contains((string) ($menu['query'] ?? ''), 'permission=generated:m5-record:list'),
        '生成菜单必须绑定权限组并声明页面访问权限'
    );

    $form = file_get_contents($fixtureRoot . '/admin-web/src/views/generated/m5-record/components/M5RecordForm.vue');
    m5Expect(str_contains($form, "@/components/Upload/index.vue") && str_contains($form, 'category_options') && str_contains($form, 'type="file"') && !str_contains($form, '<script>'), 'dictionary/upload 必须生成匹配字段语义的可运行组件且模板文本已转义');
    $detail = file_get_contents($fixtureRoot . '/admin-web/src/views/generated/m5-record/components/M5RecordDetail.vue');
    m5Expect(str_contains($detail, 'v-text') && !str_contains($detail, 'v-html'), '详情模板不得产生 XSS sink');

    echo "M5 generated runtime tests: PASS\n";
} finally {
    $server->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
    m5RemoveTree($fixtureRoot);
}
