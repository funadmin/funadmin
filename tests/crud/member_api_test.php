<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/topthink/framework/src/helper.php';

use app\common\crud\CrudDefinition;
use app\common\crud\DefinitionValidator;
use app\common\crud\PluginCrudTarget;
use app\common\crud\ProductionTemplateContext;
use app\common\crud\TemplateRenderer;
use app\common\plugin\sdk\PluginScaffolder;

function memberApiExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function memberApiReject(callable $callback, string $contains): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        memberApiExpect(str_contains($exception->getMessage(), $contains), '异常不匹配：' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $contains);
}

function memberApiTemplates(): array
{
    return [
        'migration' => 'database/migration.sql.tpl', 'model' => 'admin/model.php.tpl',
        'validate' => 'admin/validate.php.tpl', 'service' => 'admin/service.php.tpl',
        'controller' => 'admin/controller.php.tpl', 'permissionMigration' => 'database/permissions.sql.tpl',
        'api' => 'frontend/api.ts.tpl', 'view' => 'frontend/index.vue.tpl',
        'form' => 'frontend/form.vue.tpl', 'detail' => 'frontend/detail.vue.tpl',
        'phpTest' => 'tests/php-test.php.tpl', 'vitestTest' => 'tests/vitest-test.ts.tpl',
        'langMigration' => 'database/lang.sql.tpl', 'langZh' => 'admin/lang-zh.php.tpl', 'langEn' => 'admin/lang-en.php.tpl',
        'memberApiController' => 'admin/member-controller.php.tpl',
    ];
}

function memberApiData(array $overrides = [], ?array $memberApi = ['enabled' => true, 'ownerField' => 'member_id']): array
{
    $data = [
        'schemaVersion' => '1.0', 'connection' => 'mysql', 'module' => 'generated', 'entity' => 'address-book',
        'table' => 'address_book', 'title' => '收货地址', 'apiPrefix' => '/generated/address-book',
        'routePath' => '/generated/address-book', 'primaryKey' => 'id', 'timestamps' => true, 'softDeletes' => true,
        'target' => ['type' => 'core'],
        'generationTargets' => [
            'migration' => 'database/generated/address-book.sql',
            'model' => 'app/admin/model/generated/AddressBook.php',
            'validate' => 'app/admin/validate/generated/AddressBookValidate.php',
            'service' => 'app/admin/service/generated/AddressBookService.php',
            'controller' => 'app/admin/controller/generated/AddressBookController.php',
            'permissionMigration' => 'database/generated/address-book_permissions.sql',
            'api' => 'admin-web/src/api/generated/address-book.ts',
            'view' => 'admin-web/src/views/generated/address-book/index.vue',
            'form' => 'admin-web/src/views/generated/address-book/components/AddressBookForm.vue',
            'detail' => 'admin-web/src/views/generated/address-book/components/AddressBookDetail.vue',
            'phpTest' => 'tests/generated/AddressBookGeneratedTest.php',
            'vitestTest' => 'admin-web/tests/generated/address-book.spec.ts',
            'langMigration' => 'database/generated/address-book_lang.sql',
            'langZh' => 'app/admin/lang/zh-cn/address-book.php',
            'langEn' => 'app/admin/lang/en-us/address-book.php',
            'memberApiController' => 'app/api/controller/generated/AddressBookController.php',
        ],
        'permissionPrefix' => 'generated:address-book',
        'fields' => [
            ['name' => 'id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true, 'list' => true],
            ['name' => 'member_id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'required' => true, 'list' => true, 'form' => true, 'detail' => true],
            ['name' => 'name', 'dbType' => 'varchar(80)', 'nullable' => false, 'required' => true, 'list' => true, 'form' => true, 'detail' => true, 'search' => true, 'searchOperator' => 'like', 'sortable' => true],
        ],
        'relations' => [], 'optionsSource' => [],
        'templates' => memberApiTemplates(),
        'capabilities' => ['list' => true, 'search' => true, 'form' => true, 'detail' => true, 'create' => true, 'update' => true, 'delete' => true, 'import' => true, 'export' => true],
        'features' => ['batchDelete' => true, 'status' => false, 'detail' => true, 'import' => true, 'export' => true, 'upload' => false, 'dictionary' => false, 'referenceProtection' => false, 'formMode' => 'dialog', 'importLimit' => 100, 'exportLimit' => 100],
        'dataScope' => ['enabled' => false, 'field' => ''],
        'menu' => ['enabled' => true, 'parentId' => null, 'parentSourceName' => '', 'name' => '收货地址', 'icon' => 'i-ep-document', 'sortOrder' => 20, 'hidden' => false, 'keepAlive' => true, 'affix' => false, 'target' => '_self'],
        'permission' => ['enabled' => true, 'groupName' => '收货地址', 'actions' => []],
    ];
    if ($memberApi !== null) $data['memberApi'] = $memberApi;
    return array_replace_recursive($data, $overrides);
}

// 查询替身：记录查询构造调用，find 恒返回 null（不可见记录）。
class MemberApiQueryProbe
{
    public static array $calls = [];
    public static function __callStatic(string $name, array $arguments): self
    {
        self::$calls[] = [$name, $arguments];
        return new self();
    }
    public function find(): ?\think\Model { return null; }
    public function paginate(array $options): self { return $this; }
    public function items(): array { return []; }
    public function total(): int { return 0; }
    public function __call(string $name, array $arguments): self
    {
        self::$calls[] = [$name, $arguments];
        return $this;
    }
}

function memberApiOwnerFiltered(string $field, int $memberId): bool
{
    foreach (MemberApiQueryProbe::$calls as [$name, $arguments]) {
        if ($name === 'where' && $arguments === [$field, $memberId]) return true;
    }
    return false;
}

function memberApiEval(string $php): void
{
    eval(preg_replace('/^<\?php\s*/', '', $php));
}

$repository = dirname(__DIR__, 2);
$validator = new DefinitionValidator();
$renderer = new TemplateRenderer($repository . '/app/common/crud/templates/v1');

// 1. 校验
$validator->validate(CrudDefinition::fromArray(memberApiData()), $repository);
memberApiReject(fn () => $validator->validate(CrudDefinition::fromArray(memberApiData([], ['enabled' => true, 'ownerField' => 'owner_id'])), $repository), '必须引用已定义字段');
memberApiReject(fn () => $validator->validate(CrudDefinition::fromArray(memberApiData([], ['enabled' => true, 'ownerField' => 'id'])), $repository), '不能是主键');
memberApiReject(fn () => $validator->validate(CrudDefinition::fromArray(memberApiData([], ['enabled' => true, 'ownerField' => 'name'])), $repository), '必须是整数字段');
memberApiReject(fn () => $validator->validate(CrudDefinition::fromArray(memberApiData(['entity' => 'member'])), $repository), '冲突');
memberApiReject(fn () => $validator->validate(CrudDefinition::fromArray(memberApiData([], null)), $repository), '未启用 memberApi');
$withoutTarget = memberApiData();
unset($withoutTarget['generationTargets']['memberApiController']);
memberApiReject(fn () => $validator->validate(CrudDefinition::fromArray($withoutTarget), $repository), 'generationTargets 缺少制品：memberApiController');
$legacy = memberApiData([], null);
unset($legacy['generationTargets']['memberApiController'], $legacy['templates']['memberApiController']);
$validator->validate(CrudDefinition::fromArray($legacy), $repository);
memberApiExpect(!array_key_exists('memberApi', CrudDefinition::fromArray($legacy)->toArray()), '未启用时定义不得新增 memberApi 键（保持历史 definitionHash）');
memberApiExpect(ProductionTemplateContext::build(CrudDefinition::fromArray($legacy))['memberControllerContent'] === '', '未启用时不得生成会员控制器');

// 2. 核心生成内容
$core = ProductionTemplateContext::build(CrudDefinition::fromArray(memberApiData()));
$source = $renderer->render('admin/member-controller.php.tpl', $core);
memberApiExpect(str_contains($source, 'namespace app\\api\\controller\\generated;'), '核心会员控制器命名空间错误');
memberApiExpect(str_contains($source, "#[Group('v2/address-book')]"), '核心会员接口路由错误');
memberApiExpect(str_contains($source, 'protected array $middleware = [MApi::class];'), '会员接口必须要求会员登录');
memberApiExpect(str_contains($source, 'use app\\admin\\model\\generated\\AddressBook;') && str_contains($source, 'use app\\admin\\service\\generated\\AddressBookService;'), '核心会员控制器应复用后台生成的模型与服务');
foreach (["#[Delete('')]", "#[Post('restore')]", "#[Post('import')]", "#[Get('export')]", "':id/status'", "'options/:source'", "':id/destroy'"] as $forbidden) {
    memberApiExpect(!str_contains($source, $forbidden), '会员接口不得暴露：' . $forbidden);
}

// 3. 运行时：归属限制与归属写入
$namespaced = static fn (string $content, string $from, string $to): string => str_replace("namespace {$from};", "namespace {$to};", $content);
memberApiEval($namespaced($core['serviceContent'], 'app\\admin\\service\\generated', 'app\\admin\\service\\generated'));
memberApiEval($core['validateContent']);
memberApiEval($source);
$class = 'app\\api\\controller\\generated\\AddressBookController';
$reflection = new ReflectionClass($class);
foreach (['index', 'detail', 'create', 'update', 'remove'] as $action) memberApiExpect($reflection->getMethod($action)->isPublic(), '会员接口缺少：' . $action);
foreach (['status', 'restoreOne', 'destroyOne', 'recycle', 'restore', 'destroy', 'import', 'export'] as $action) {
    memberApiExpect(!$reflection->hasMethod($action) || !$reflection->getMethod($action)->isPublic(), '会员接口暴露管理能力：' . $action);
}
$controller = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('model')->setValue($controller, MemberApiQueryProbe::class);
$request = (new \think\Request())->withGet(['recycled' => 1])->withPost(['name' => '家', 'member_id' => 999]);
$request->member_id = 7;
$reflection->getProperty('request')->setValue($controller, $request);
foreach (['index' => [], 'detail' => [5], 'update' => [5], 'remove' => [5]] as $action => $arguments) {
    MemberApiQueryProbe::$calls = [];
    $code = $controller->{$action}(...$arguments)->getData()['code'];
    memberApiExpect(memberApiOwnerFiltered('member_id', 7), $action . ' 必须限定为当前会员');
    memberApiExpect(!array_intersect(['onlyTrashed', 'withTrashed'], array_column(MemberApiQueryProbe::$calls, 0)), $action . ' 不得查询回收站');
    memberApiExpect($action === 'index' ? $code === 200 : $code === 404, $action . ' 对不可见记录应返回 ' . ($action === 'index' ? 200 : 404));
}
$payload = $reflection->getMethod('payload');
$created = $payload->invoke($controller, null);
memberApiExpect($created['member_id'] === 7 && $created['name'] === '家', '新增必须强制写入当前会员且忽略伪造归属');
$existing = new class extends \think\Model { public function __construct() {} };
memberApiExpect($payload->invoke($controller, $existing)['member_id'] === 7, '修改不得转移归属');
$anonymous = (new \think\Request())->withPost([]);
$reflection->getProperty('request')->setValue($controller, $anonymous);
MemberApiQueryProbe::$calls = [];
$controller->index();
memberApiExpect(memberApiOwnerFiltered('member_id', -1), '缺少会员身份时必须不匹配任何记录');

// 4. 插件目标：application 层会员控制器 + 后台照常生成
$root = sys_get_temp_dir() . '/funadmin-member-api-' . bin2hex(random_bytes(5));
mkdir($root . '/plugins', 0755, true);
try {
    (new PluginScaffolder($root . '/plugins'))->scaffold('shop', '商城插件');
    $pluginData = memberApiData(['target' => ['type' => 'plugin', 'plugin' => 'shop', 'scope' => 'admin'], 'permissionPrefix' => 'shop:address-book']);
    unset($pluginData['generationTargets']);
    foreach (['permissionMigration', 'phpTest', 'vitestTest', 'langMigration', 'langZh', 'langEn'] as $coreOnly) unset($pluginData['templates'][$coreOnly]);
    $plugin = CrudDefinition::fromArray($pluginData);
    $validator->validate($plugin, $root);
    $files = (new PluginCrudTarget($root))->files($plugin, $renderer);
    $memberController = $files['plugins/shop/app/shop/controller/AddressBookController.php'] ?? '';
    memberApiExpect(str_contains($memberController, 'namespace app\\shop\\controller;') && str_contains($memberController, "#[Group('address-book')]"), '插件会员控制器命名空间或路由错误');
    memberApiExpect(str_contains($memberController, "->where('member_id', \$this->memberId())") && str_contains($memberController, "#[Post('')]"), '插件会员控制器必须可写且限定归属');
    memberApiExpect(str_contains($memberController, 'use app\\shop\\model\\AddressBook;'), '插件会员控制器应使用 application 层模型');
    foreach (['app/shop/model/AddressBook.php', 'app/shop/service/AddressBookService.php', 'app/shop/validate/AddressBookValidate.php', 'app/admin/controller/AddressBookController.php', 'admin-web/address-book/api.ts'] as $path) {
        memberApiExpect(isset($files['plugins/shop/' . $path]), '插件缺少生成文件：' . $path);
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}

echo "member api tests: PASS\n";
