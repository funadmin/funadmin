<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\form\dataSource\FormDataSourceException;
use app\common\form\dataSource\FormDataSourceRegistry;
use app\common\form\validation\FormAsyncValidationException;
use app\common\form\validation\FormAsyncValidatorRegistry;
use app\console\form\service\FormDataService;

function dataExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$asyncRegistry = new FormAsyncValidatorRegistry([
    'unique' => static fn (mixed $value, array $values, array $options): bool|string => $value === 'used' ? '值已存在' : true,
]);
$service = new FormDataService($asyncRegistry);
$publishedHash = str_repeat('a', 64);
dataExpect($service->assertPublishedSchemaHash($publishedHash, $publishedHash) === $publishedHash, '提交必须接受与已发布快照一致的 schemaHash');
try {
    $service->assertPublishedSchemaHash(str_repeat('b', 64), $publishedHash);
    dataExpect(false, '过期 schemaHash 必须被拒绝');
} catch (InvalidArgumentException $exception) {
    dataExpect($exception->getMessage() === 'FORM_SCHEMA_CONFLICT', '过期 schemaHash 必须返回稳定冲突错误码');
}
$errors = $service->revalidateAsync([
    ['field_name' => 'email', 'validate_rules' => ['async' => ['key' => 'unique']]],
], ['email' => 'used']);
dataExpect($errors === [['path' => 'email', 'message' => '值已存在']], '服务端提交必须复验已注册异步 validator 并返回 fieldErrors');
$astAsyncErrors = $service->revalidateSchemaAsync([
    'nodes' => [[
        'id' => 'email', 'field' => 'email', 'validation' => [
            ['type' => 'async', 'validator' => ['key' => 'unique'], 'bail' => false],
            ['type' => 'async', 'validator' => ['key' => 'unique', 'params' => ['scope' => 'tenant']]],
        ], 'children' => [],
    ]],
], ['email' => 'used']);
dataExpect(count($astAsyncErrors) === 2 && array_column($astAsyncErrors, 'path') === ['email', 'email'], '服务端必须以已发布 AST 原序复验全部 async key');
$conditionalAsyncErrors = $service->revalidateSchemaAsync([
    'nodes' => [[
        'id' => 'email', 'field' => 'email', 'validation' => [
            ['type' => 'async', 'validator' => ['key' => 'unique'], 'when' => ['field' => 'mode', 'op' => 'eq', 'value' => 'strict']],
        ], 'children' => [],
    ]],
], ['email' => 'used', 'mode' => 'relaxed']);
dataExpect($conditionalAsyncErrors === [], '服务端 async 复验必须跳过 when 未命中的规则');
$optionalAsyncCalls = 0;
$optionalAsyncService = new FormDataService(new FormAsyncValidatorRegistry([
    'unique' => static function () use (&$optionalAsyncCalls): bool {
        $optionalAsyncCalls++;
        return true;
    },
]));
$optionalAsyncErrors = $optionalAsyncService->revalidateSchemaAsync([
    'nodes' => [[
        'id' => 'email', 'field' => 'email', 'validation' => [
            ['type' => 'async', 'validator' => ['key' => 'unique']],
        ], 'children' => [],
    ]],
], ['email' => '']);
dataExpect($optionalAsyncErrors === [] && $optionalAsyncCalls === 0, '服务端 async 复验必须跳过可选空值');
$bailAsyncErrors = $service->revalidateSchemaAsync([
    'nodes' => [[
        'id' => 'email', 'field' => 'email', 'validation' => [
            ['type' => 'async', 'validator' => ['key' => 'unique'], 'bail' => true],
            ['type' => 'async', 'validator' => ['key' => 'unique']],
        ], 'children' => [],
    ]],
], ['email' => 'used']);
dataExpect(count($bailAsyncErrors) === 1, '服务端 async 复验必须在失败规则 bail=true 时停止该字段后续规则');
$astDeclarations = $service->schemaAsyncDeclarations([
    'nodes' => [['id' => 'email', 'field' => 'email', 'validation' => [
        ['type' => 'async', 'validator' => ['key' => 'unique']],
        ['type' => 'async', 'validator' => ['key' => 'unique', 'params' => ['scope' => 'tenant']]],
    ], 'children' => []]],
], 'email', 'unique');
dataExpect(count($astDeclarations) === 2, '即时异步校验也必须从已发布 AST 解析并保留同 key 多规则');
try {
    $service->revalidateAsync([
        ['field_name' => 'email', 'validate_rules' => ['async' => ['key' => 'missing']]],
    ], ['email' => 'value']);
    dataExpect(false, '服务端必须拒绝未注册异步 validator key');
} catch (FormAsyncValidationException $exception) {
    dataExpect($exception->errorCode() === 'FORM_ASYNC_VALIDATOR_NOT_REGISTERED', '未知 validator 必须返回治理错误码');
}

$page = $service->paginateOptions([
    ['label' => '张三', 'value' => 1],
    ['label' => '李四', 'value' => 2],
    ['label' => '张五', 'value' => 3],
], '张', 2, 1);
dataExpect($page === ['options' => [['label' => '张五', 'value' => 3]], 'total' => 2], '异步选项必须支持搜索、分页和总数');
dataExpect(
    $service->normalizeOptionsResult([['label' => '甲', 'value' => 1]]) === ['list' => [['label' => '甲', 'value' => 1]], 'options' => [['label' => '甲', 'value' => 1]], 'total' => 1],
    '普通 options 必须同时返回 list/total 与兼容 options'
);
dataExpect(
    $service->normalizeOptionsResult(['list' => [['label' => '乙', 'value' => 2]], 'total' => 8]) === ['list' => [['label' => '乙', 'value' => 2]], 'options' => [['label' => '乙', 'value' => 2]], 'total' => 8],
    '已分页结果必须兼容映射 options'
);

$endpointRegistry = FormDataSourceRegistry::core([
    'member.options' => [
        'permission' => 'system:member:list',
        'parameters' => ['keyword', 'department_id'],
        'timeoutMs' => 100,
        'maxResults' => 20,
        'handler' => static fn (array $arguments): array => [[
            'label' => (string) ($arguments['keyword'] ?? ''),
            'value' => (int) ($arguments['department_id'] ?? 0),
        ]],
    ],
]);
$endpointService = new FormDataService(
    $asyncRegistry,
    $endpointRegistry,
    static fn (string $permission): bool => $permission === 'system:member:list'
);
$endpointOptions = $endpointService->executeOptionsSource([
    'mode' => 'endpoint',
    'endpoint' => 'member.options',
    'params' => ['keyword' => '$search', 'department_id' => '$context.department_id', 'url' => 'http://evil'],
    'response' => ['items' => '$', 'label' => 'label', 'value' => 'value'],
], ['search' => '张', 'context' => ['department_id' => 7]]);
dataExpect($endpointOptions === [['label' => '张', 'value' => 7]], 'FormDataService 必须实际执行已注册 endpoint key');
try {
    $endpointService->executeOptionsSource(['kind' => 'endpoint', 'endpoint' => 'http://127.0.0.1/private']);
    dataExpect(false, 'FormDataService 不得执行客户端 URL');
} catch (FormDataSourceException $exception) {
    dataExpect($exception->errorCode() === 'FORM_DATA_SOURCE_ENDPOINT_NOT_ALLOWED', 'URL 必须按 endpoint 治理错误拒绝');
}

$primaryMethod = new ReflectionMethod($service, 'primaryKey');
$primaryMethod->setAccessible(true);
$primary = $primaryMethod->invoke($service, ['legacy_id' => ['type' => 'varchar(36)', 'primary' => true]]);
dataExpect($primary === ['name' => 'legacy_id', 'type' => 'string'], '运行时服务必须识别 adopted 表真实字符串主键');

// 规则构建：必填/数字/开关/长度/范围/正则。
$built = $service->buildRules([
    ['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(100)', 'form_required' => 1, 'validate_rules' => ['minlen' => 2, 'maxlen' => 20]],
    ['field_name' => 'amount', 'label' => '金额', 'type' => 'number', 'column_type' => 'decimal(10,2)', 'form_required' => 0, 'validate_rules' => ['min' => 0, 'max' => 999]],
    ['field_name' => 'enabled', 'label' => '启用', 'type' => 'switch', 'column_type' => 'tinyint(1)', 'form_required' => 0, 'validate_rules' => null],
    ['field_name' => 'code', 'label' => '编码', 'type' => 'input', 'column_type' => 'varchar(20)', 'form_required' => 0, 'validate_rules' => ['pattern' => '^[A-Z]+$']],
    ['field_name' => 'birthday', 'label' => '生日', 'type' => 'date', 'column_type' => 'date', 'form_required' => 0, 'validate_rules' => null],
    ['field_name' => 'visited_at', 'label' => '访问时间', 'type' => 'date', 'column_type' => 'datetime', 'form_required' => 0, 'validate_rules' => null],
]);
dataExpect(str_contains($built['rules']['title'], 'require'), '必填字段必须生成 require 规则');
dataExpect(str_contains($built['rules']['title'], 'length:2,20'), '长度规则必须生成');
dataExpect(str_contains($built['rules']['amount'], 'number'), '数字字段必须生成 number 规则');
dataExpect(str_contains($built['rules']['amount'], 'egt:0') && str_contains($built['rules']['amount'], 'elt:999'), '数值范围规则必须生成');
dataExpect($built['rules']['enabled'] === 'in:0,1', '开关字段必须生成 in:0,1 规则');
dataExpect(str_contains($built['rules']['code'], 'regex:^[A-Z]+$'), '正则规则必须生成');
dataExpect($built['rules']['birthday'] === 'dateFormat:Y-m-d', 'date 必须只校验 Y-m-d');
dataExpect($built['rules']['visited_at'] === 'dateFormat:Y-m-d H:i:s', 'datetime 必须只校验 Y-m-d H:i:s');
dataExpect(($built['messages']['title.require'] ?? '') === '标题不能为空', '必填消息必须带字段标签');

// 载荷过滤：白名单＋更新禁改＋多选关联拼接。
$fields = [
    ['field_name' => 'title', 'form_readonly' => 0, 'relation_multiple' => 0],
    ['field_name' => 'locked', 'form_readonly' => 1, 'relation_multiple' => 0],
    ['field_name' => 'owners', 'form_readonly' => 0, 'relation_multiple' => 1],
];
$created = $service->filterPayload($fields, ['title' => 'a', 'locked' => 'x', 'owners' => [1, 2], 'evil' => 'drop'], false);
dataExpect($created === ['title' => 'a', 'owners' => '1,2'], '新增必须白名单过滤、默认剔除只读字段并拼接多选关联');
$updated = $service->filterPayload($fields, ['title' => 'b', 'locked' => 'y', 'owners' => [3]], true);
dataExpect($updated === ['title' => 'b', 'owners' => '3'], '更新必须剔除禁改字段');
$governedFields = [
    ['field_name' => 'title', 'type' => 'input'],
    ['field_name' => 'hidden', 'type' => 'hidden'],
    ['field_name' => 'disabled', 'type' => 'input', 'control_props' => ['disabled' => true]],
    ['field_name' => 'readonly', 'type' => 'readonly', 'form_readonly' => 1],
    ['field_name' => 'computed', 'type' => 'input', 'options_source' => ['kind' => 'computed']],
    ['field_name' => 'primary', 'type' => 'input', 'control_props' => ['primary' => true]],
    ['field_name' => 'system', 'type' => 'input', 'control_props' => ['system' => true]],
    ['field_name' => 'secret', 'type' => 'password', 'control_props' => ['sensitive' => true, 'writeOnly' => true]],
];
$governedData = array_fill_keys(array_column($governedFields, 'field_name'), 'value');
dataExpect(
    $service->filterPayload($governedFields, $governedData, false) === ['title' => 'value', 'secret' => 'value'],
    'hidden/disabled/readonly/computed/primary/system 默认不得提交，writeOnly 密码允许写入'
);
dataExpect(
    $service->filterPayload($governedFields, $governedData, false, ['readonly', 'computed']) === [
        'title' => 'value', 'readonly' => 'value', 'computed' => 'value', 'secret' => 'value',
    ],
    '显式 include 必须允许提交指定受治理字段'
);
dataExpect(
    $service->sanitizeRecord($governedFields, ['title' => 'ok', 'secret' => 'stored']) === ['title' => 'ok'],
    'password/sensitive/writeOnly 不得从 detail/list 回显'
);
$controllerSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/form/Data.php');
$serviceSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/form/service/FormDataService.php');
dataExpect(str_contains($serviceSource, 'FormSchemaVersion'), '运行态 meta 必须从不可变版本表读取已发布 schema');
dataExpect(str_contains($serviceSource, 'BusinessModule'), '运行态必须通过业务模块读取发布绑定');
dataExpect(str_contains($serviceSource, "where('version', \$publishedVersion)") && str_contains($serviceSource, "where('schema_hash', \$publishedHash)"), '运行态必须按业务模块 version/hash 双重锁定发布快照，禁止草稿污染');
dataExpect(str_contains($serviceSource, '077_business_development_center'), '业务模块迁移缺失必须给出清晰错误');
dataExpect(str_contains($serviceSource, "'schema' => \$published['schema']"), 'meta 必须返回已发布 canonical FormSchema v2');
dataExpect(str_contains($serviceSource, "assertAsyncValid(\$published['schema']"), '异步提交校验必须以已发布 AST 为最终裁决，禁止使用有损字段投影');
dataExpect(str_contains($serviceSource, "schemaAsyncDeclarations(\$published['schema']"), '即时异步校验必须以已发布 AST 约束注册 key');
dataExpect(str_contains($serviceSource, "'schemaHash' => \$published['schemaHash']"), 'meta 必须返回已发布 schemaHash');
dataExpect(str_contains($serviceSource, "'etag' =>"), 'meta 必须返回可用于 ETag 的稳定值');
dataExpect(str_contains($controllerSource, "header(['ETag'"), 'meta 必须同时发送 HTTP ETag 响应头');
dataExpect(str_contains($controllerSource, "post('schemaHash'"), 'create/update 必须读取客户端 schemaHash');
dataExpect(str_contains($controllerSource, "code: 409"), '过期 schemaHash 必须返回冲突状态');
dataExpect(str_contains($controllerSource, 'redactRequestPayload'), '写入请求必须在 SystemLog 记录前动态脱敏');
dataExpect(str_contains($controllerSource, 'withPost'), '控制器必须用脱敏载荷替换待记录 POST 数据');
$complex = $service->filterPayload(
    [['field_name' => 'choices', 'type' => 'checkbox', 'column_type' => 'json', 'form_readonly' => 0, 'relation_multiple' => 0]],
    ['choices' => [1, 2]],
    false
);
dataExpect($complex === ['choices' => '[1,2]'], 'JSON 类型控件必须编码后写入数据库');
$relations = $service->splitPayload([
    ['field_name' => 'title', 'relation_type' => 'none', 'form_readonly' => 0, 'relation_multiple' => 0],
    ['field_name' => 'items', 'type' => 'repeatable', 'relation_type' => 'has_many', 'form_readonly' => 0, 'relation_multiple' => 0],
], ['title' => '订单', 'items' => [['sku' => 'A']]], false);
dataExpect($relations['parent'] === ['title' => '订单'], 'has_many 集合不得写入父表字段');
dataExpect($relations['relations'] === ['items' => [['sku' => 'A']]], 'has_many 集合必须从父表载荷分离');
$missingRelation = $service->splitPayload([
    ['field_name' => 'items', 'type' => 'subform', 'relation_type' => 'has_many', 'form_readonly' => 0, 'relation_multiple' => 0],
], [], true);
dataExpect($missingRelation['relations'] === [], '更新未提交关系字段时必须保持子行不变');
try {
    $service->splitPayload([
        ['field_name' => 'items', 'type' => 'subform', 'relation_type' => 'has_many', 'form_readonly' => 0, 'relation_multiple' => 0],
    ], ['items' => 'invalid'], true);
    dataExpect(false, '关系载荷非数组必须拒绝');
} catch (InvalidArgumentException) {
}

// 057 迁移：守卫式权限插入。
$migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/057_form_data_permissions.sql');
dataExpect(str_contains($migration, 'console/formdata:create'), '057 必须包含 formdata 路由权限');
dataExpect(str_contains($migration, 'WHERE NOT EXISTS'), '057 权限组必须守卫式');
dataExpect(str_contains($migration, 'INSERT IGNORE INTO `fun_permission`'), '057 路由权限必须 IGNORE 守卫');

// 在独立进程中替换持久化边界，真实执行服务授权分支，禁止连接数据库。
class FormDataMemoryModel
{
    public function __construct(private array $data = []) {}
    public function __get(string $key): mixed { return $this->data[$key] ?? null; }
    public function __set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function toArray(): array { return $this->data; }
    public static function where(mixed ...$args): FormDataMemoryQuery
    {
        return (new FormDataMemoryQuery(static::class))->where(...$args);
    }
}
class FormDataMemoryForm extends FormDataMemoryModel {}
class FormDataMemoryField extends FormDataMemoryModel {}
class FormDataMemoryModule extends FormDataMemoryModel {}
class FormDataMemoryVersion extends FormDataMemoryModel {}
class FormDataMemoryScope
{
    public static array $scope = ['all' => false, 'departmentIds' => [7, 8]];
    public function resolve(): array { return self::$scope; }
}
class FormDataMemoryRepository
{
    public function compile(array $document): object
    {
        return new class($document) {
            public function __construct(private array $document) {}
            public function hash(): string { return str_repeat('a', 64); }
            public function document(): array { return $this->document; }
            public function fieldProjection(): array { return $this->document['fields']; }
        };
    }
}
class FormDataMemoryDb
{
    public static array $tables = [];
    public static array $schemas = [];
    public static function connect(string $connection): self { return new self(); }
    public function getFields(string $table): array { return self::$schemas[$table]; }
    public function table(string $table): FormDataMemoryQuery { return new FormDataMemoryQuery($table); }
}
class FormDataMemoryQuery
{
    private array $predicates = [];
    private ?array $selection = null;
    private int $offset = 0;
    private ?int $limit = null;
    public function __construct(private string $table) {}
    private function columnName(string $name): string { return basename(str_replace('.', '/', $name)); }
    public function where(mixed $field, mixed $value = null): self
    {
        if ($field instanceof Closure) { $field($this); return $this; }
        $name = $this->columnName($field);
        $this->predicates[] = static fn (array $row): bool => ($row[$name] ?? null) == $value;
        return $this;
    }
    public function whereOr(string $field, mixed $value): self { return $this; }
    public function whereIn(string $field, array $values): self
    {
        $name = $this->columnName($field);
        $this->predicates[] = static fn (array $row): bool => in_array($row[$name] ?? null, $values);
        return $this;
    }
    public function whereNull(string $field): self { return $this->where($field, null); }
    public function field(array $fields): self { $this->selection = array_map($this->columnName(...), $fields); return $this; }
    public function lock(bool $lock): self { return $this; }
    public function order(string $field, string $order): self { return $this; }
    public function page(int $page, int $size): self { $this->offset = ($page - 1) * $size; $this->limit = $size; return $this; }
    private function matches(array $row): bool
    {
        foreach ($this->predicates as $predicate) if (!$predicate($row)) return false;
        return true;
    }
    private function rows(): array { return array_values(array_filter(FormDataMemoryDb::$tables[$this->table], $this->matches(...))); }
    public function count(): int { return count($this->rows()); }
    public function column(string $name): array { return array_column($this->rows(), $name); }
    public function find(): mixed
    {
        $row = $this->rows()[0] ?? null;
        return $row !== null && is_a($this->table, FormDataMemoryModel::class, true) ? new ($this->table)($row) : $row;
    }
    public function select(): \think\Collection
    {
        $rows = array_slice($this->rows(), $this->offset, $this->limit);
        if ($this->selection !== null) $rows = array_map(fn (array $row): array => array_intersect_key($row, array_flip($this->selection)), $rows);
        return new \think\Collection($rows);
    }
    public function insert(array $payload): void
    {
        foreach (FormDataMemoryDb::$tables[$this->table] as $row) {
            if (isset($payload['id']) && (string) $payload['id'] === (string) $row['id']) throw new InvalidArgumentException('子表主键冲突');
        }
        $payload['id'] ??= count(FormDataMemoryDb::$tables[$this->table]) + 10;
        FormDataMemoryDb::$tables[$this->table][] = $payload;
    }
    public function update(array $payload): void
    {
        foreach (FormDataMemoryDb::$tables[$this->table] as &$row) if ($this->matches($row)) $row = array_replace($row, $payload);
    }
    public function delete(): void
    {
        FormDataMemoryDb::$tables[$this->table] = array_values(array_filter(FormDataMemoryDb::$tables[$this->table], fn (array $row): bool => !$this->matches($row)));
    }
}
foreach ([
    FormDataMemoryForm::class => 'app\\console\\form\\model\\Form',
    FormDataMemoryField::class => 'app\\console\\form\\model\\FormField',
    FormDataMemoryModule::class => 'app\\console\\development\\model\\BusinessModule',
    FormDataMemoryVersion::class => 'app\\console\\form\\model\\FormSchemaVersion',
    FormDataMemoryRepository::class => 'app\\console\\form\\repository\\FormSchemaRepository',
    FormDataMemoryScope::class => 'app\\console\\authorization\\service\\DataScopeService',
    FormDataMemoryDb::class => 'think\\facade\\Db',
] as $fake => $real) {
    dataExpect(!class_exists($real, false), '内存替身必须先于持久化类加载：' . $real);
    class_alias($fake, $real);
}

$relationField = ['field_name' => 'items', 'type' => 'subform', 'relation_type' => 'has_many', 'relation_table' => 'child', 'relation_value_field' => 'parent_id'];
$resetMemory = static function (array $access = [], bool $soft = true, bool $stringPrimary = false) use ($relationField): void {
    FormDataMemoryScope::$scope = ['all' => false, 'departmentIds' => [7, 8]];
    $parentFields = [array_replace($relationField, ['control_props' => ['schemaAccess' => $access]])];
    $childFields = [['field_name' => 'name', 'type' => 'input'], ['field_name' => 'dept_id', 'type' => 'number']];
    FormDataMemoryDb::$schemas = [
        'parent' => ['id' => ['primary' => true, 'type' => 'int']],
        'child' => ['id' => ['primary' => true, 'type' => $stringPrimary ? 'varchar(36)' : 'int'], 'parent_id' => [], 'dept_id' => [], 'name' => []] + ($soft ? ['deleted_at' => []] : []),
    ];
    FormDataMemoryDb::$tables = [
        FormDataMemoryForm::class => [
            ['id' => 1, 'form_key' => 'parent', 'table_name' => 'parent', 'connection' => 'memory', 'status' => 1],
            ['id' => 2, 'form_key' => 'child', 'table_name' => 'child', 'connection' => 'memory', 'status' => 1],
        ],
        FormDataMemoryModule::class => array_map(static fn (int $id): array => [
            'form_id' => $id, 'lifecycle_status' => 'published', 'published_schema_hash' => str_repeat('a', 64), 'published_schema_version' => 1,
            'metadata' => ['publishConfig' => ['dataScopeEnabled' => $id === 2, 'dataScopeField' => 'dept_id']],
        ], [1, 2]),
        FormDataMemoryVersion::class => [
            ['form_id' => 1, 'version' => 1, 'schema_hash' => str_repeat('a', 64), 'schema_document' => ['fields' => $parentFields]],
            ['form_id' => 2, 'version' => 1, 'schema_hash' => str_repeat('a', 64), 'schema_document' => ['fields' => $childFields]],
        ],
        'parent' => [['id' => 1]],
        'child' => [
            ['id' => 1, 'parent_id' => 1, 'dept_id' => 7, 'name' => '可见', 'deleted_at' => null],
            ['id' => 2, 'parent_id' => 1, 'dept_id' => 9, 'name' => '不可见', 'deleted_at' => null],
            ['id' => 3, 'parent_id' => 2, 'dept_id' => 7, 'name' => '其他父行', 'deleted_at' => null],
            ['id' => 4, 'parent_id' => 1, 'dept_id' => 7, 'name' => '已删除', 'deleted_at' => '2026-01-01'],
        ],
    ];
};
$sync = static function (array $rows) use ($service, $relationField): void {
    (new ReflectionMethod($service, 'syncRelations'))->invoke($service, new FormDataMemoryDb(), new FormDataMemoryForm(['connection' => 'memory']), [new FormDataMemoryField($relationField)], 1, ['items' => $rows], true);
};
$reject = static function (callable $operation): void {
    try { $operation(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException('越权操作未被拒绝');
};
$failures = [];
$cases = 0;
$check = static function (string $name, callable $operation) use (&$failures, &$cases, $resetMemory): void {
    $cases++;
    $resetMemory();
    try { $operation(); } catch (Throwable $exception) { $failures[] = $name . '：' . $exception->getMessage(); }
};
foreach ([
    '无写权限' => ['control_props' => ['schemaAccess' => ['write' => ['items.write']]]],
    '禁止提交' => ['control_props' => ['schemaAccess' => ['include' => 'never']]],
    '只读' => ['form_readonly' => 1],
    '禁用' => ['control_props' => ['disabled' => true]],
    '计算值' => ['control_props' => ['computed' => true]],
] as $name => $overrides) {
    $check($name, static function () use ($service, $relationField, $overrides): void {
        foreach ([false, true] as $update) dataExpect($service->splitPayload([array_replace($relationField, $overrides)], ['items' => []], $update)['relations'] === [], '未授权关系不得进入同步');
    });
}
$check('include 不得越过写权限', static function () use ($service, $relationField): void {
    $field = array_replace($relationField, ['control_props' => ['schemaAccess' => ['write' => ['items.write'], 'include' => 'always']]]);
    dataExpect($service->splitPayload([$field], ['items' => []], true, ['items'])['relations'] === [], 'include 不能提权');
});
$check('授权及显式提交兼容', static function () use ($relationField): void {
    $allowed = new FormDataService(permissionChecker: static fn (string $permission): bool => $permission === 'items.write');
    $field = array_replace($relationField, ['form_readonly' => 1, 'control_props' => ['schemaAccess' => ['write' => ['items.write']]]]);
    dataExpect($allowed->splitPayload([$field], ['items' => []], true, ['items'])['relations'] === ['items' => []], '合法 include 应允许提交');
});
$check('详情父关系 read', static function () use ($service, $resetMemory): void {
    $resetMemory(['read' => ['items.read']]);
    dataExpect($service->detail('parent', 1)['children'] === [], '详情不得泄漏未授权关系');
});
$check('分页父关系 read', static function () use ($service, $resetMemory, $reject): void {
    $resetMemory(['read' => ['items.read']]);
    $reject(fn () => $service->sub('parent', 'items', 1, 1, 20));
});
$check('父记录不存在', static function () use ($service, $reject): void { $reject(fn () => $service->sub('parent', 'items', 99, 1, 20)); });
$check('详情及分页子表范围', static function () use ($service): void {
    foreach ([$service->detail('parent', 1)['children']['items'], $service->sub('parent', 'items', 1, 1, 20)] as $result) {
        dataExpect($result['total'] === 1 && array_column($result['list'], 'id') === [1], '子表列表和 total 必须只统计可见活动行');
    }
});
foreach ([true, false] as $soft) $check($soft ? '软删除范围' : '硬删除范围', static function () use ($resetMemory, $sync, $soft): void {
    $resetMemory([], $soft);
    $sync([]);
    $rows = array_column(FormDataMemoryDb::$tables['child'], null, 'id');
    dataExpect(isset($rows[2], $rows[3]) && $rows[2]['deleted_at'] === null && $rows[3]['deleted_at'] === null, '清空关系必须保留不可见行及其他父行');
    dataExpect($soft ? $rows[1]['deleted_at'] !== null : !isset($rows[1]), '可见行应正常删除');
});
foreach ([2, 3, 4] as $id) $check('拒绝不可见或异属或已删子行 ' . $id, static function () use ($sync, $reject, $id): void { $reject(fn () => $sync([['id' => $id, 'name' => '非法']])); });
$check('更新省略部门', static function () use ($sync): void {
    $sync([['id' => 1, 'name' => '修改']]);
    $rows = array_column(FormDataMemoryDb::$tables['child'], null, 'id');
    dataExpect($rows[1]['name'] === '修改' && $rows[1]['dept_id'] === 7 && $rows[2]['deleted_at'] === null, '更新应保留部门和不可见子行');
});
foreach ([[['name' => '新增', 'dept_id' => 9]], [['name' => '缺少部门']], [['id' => 1, 'dept_id' => 9]]] as $index => $rows) {
    $check('写范围拒绝 ' . $index, static function () use ($sync, $reject, $rows): void { $reject(fn () => $sync($rows)); });
}
$check('合法部门变更及新增', static function () use ($sync): void {
    $sync([['id' => 1, 'dept_id' => 8], ['name' => '新增', 'dept_id' => 7]]);
    dataExpect(FormDataMemoryDb::$tables['child'][0]['dept_id'] === 8 && count(FormDataMemoryDb::$tables['child']) === 5, '合法范围内写入必须保留');
});
$check('空数据范围', static function () use ($service, $sync): void {
    FormDataMemoryScope::$scope['departmentIds'] = [];
    dataExpect($service->sub('parent', 'items', 1, 1, 20)['total'] === 0, '空范围不得读取子行');
    $before = FormDataMemoryDb::$tables['child'];
    $sync([]);
    dataExpect(FormDataMemoryDb::$tables['child'] === $before, '空范围不得删除任何行');
});
$check('全量数据范围', static function () use ($service): void {
    FormDataMemoryScope::$scope['all'] = true;
    dataExpect($service->sub('parent', 'items', 1, 1, 20)['total'] === 2, '全量范围仍须保持父归属与软删过滤');
});
$check('字符串主键创建及不可见冲突', static function () use ($resetMemory, $sync, $reject): void {
    $resetMemory([], true, true);
    $reject(fn () => $sync([['id' => '2', 'dept_id' => 7]]));
    $sync([['id' => 'NEW', 'dept_id' => 7, 'name' => '新行']]);
    dataExpect(in_array('NEW', array_column(FormDataMemoryDb::$tables['child'], 'id'), true), '新的字符串主键仍可插入');
});
$check('数据范围配置失效必须关闭访问', static function () use ($service, $sync, $reject): void {
    FormDataMemoryDb::$tables[FormDataMemoryModule::class][1]['metadata']['publishConfig']['dataScopeField'] = 'missing_department';
    $reject(fn () => $service->sub('parent', 'items', 1, 1, 20));
    $reject(fn () => $sync([]));
});
$check('重复子行拒绝', static function () use ($sync, $reject): void {
    $reject(fn () => $sync([['id' => 1], ['id' => 1]]));
});
$check('子表字段读取权限', static function () use ($service): void {
    FormDataMemoryDb::$tables[FormDataMemoryVersion::class][1]['schema_document']['fields'][0]['control_props']['schemaAccess']['read'] = ['child.name.read'];
    $result = $service->sub('parent', 'items', 1, 1, 20);
    dataExpect(!array_key_exists('name', $result['list'][0]), '子表不可读字段不得返回');
});
if ($failures !== []) throw new RuntimeException(implode("\n", $failures));
echo "form data contract tests: PASS; relation authorization cases: {$cases}\n";
