<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\repository\GeneratedFileBaselineRepository;
use app\admin\development\service\FormCrudDefinitionFactory;
use app\admin\development\service\GenerationTransactionService;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\ProductionTemplateContext;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use app\common\plugin\sdk\PluginScaffolder;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class CodeGeneratorRelationsTest extends TestCase
{
    /** 修改关联生成前记录的无关联模块基准（definitionHash 与全部生成文件 sha256）。 */
    private const PLAIN_BASELINE = [
        'core' => [
            'definitionHash' => 'a07a1978d3f33bbb5613816efb7dbfc350de42b05ad5717c19267b9a48ccb82c',
            'files' => [
                'app/admin/business/shop_plain/controller/ShopPlainController.php' => 'd5bdfe07b1fcf59b23783a47bd38d138e6a15862985bec2227ae29da5e5e7672',
                'app/admin/business/shop_plain/lang/en-us.php' => 'cf6264058cd19ba878b3828997d7bd5f0a93e6307ad7e9a8790311790c9acee4',
                'app/admin/business/shop_plain/lang/zh-cn.php' => 'a08ac305ea247e7492bd78090c40caa66e875223c91dbee3cbfafc74fb86a692',
                'app/admin/business/shop_plain/model/ShopPlain.php' => '3501b9f3424727574ad0c8de8ca7d21313d0e53532ae0998170cddccd2a92f53',
                'app/admin/business/shop_plain/service/ShopPlainService.php' => '2b6e8997d05a318f97d6f741981a4b604a772db263ebf69a20eda4655731b39e',
                'app/admin/business/shop_plain/validate/ShopPlainValidate.php' => '18c8a08e79c4a9d675982e9935a0feec256547c4457d2fd76904d106c43c471d',
                'database/business/shop-plain.sql' => '80a91eed629a3e12a9c4594c411b32872fb8da9de6d2fb84c67f8c646ac001d5',
                'database/business/shop-plain_lang.sql' => 'a30cacf99776130affbf47e2ce85ff5ab3d0cb95b1a9ad7542b0f231670f6218',
                'database/business/shop-plain_permissions.sql' => '4ce2e1f59708b205f2e50df50fadbf5e4a6d62c11cb99c4facd5d9a729bdf768',
                'tests/business/ShopPlainGeneratedTest.php' => '6e65441cd0b2fc2b778732cd320c8808c8221821750b147e9dcbbf35d8c069e1',
                'web/src/admin/api/business/shop-plain.ts' => 'be5314e795a3d07aa720bd9a7372c24d4bb0730653814caa231187e87efdf6d7',
                'web/src/admin/views/business/shop-plain/components/ShopPlainDetail.vue' => 'beb70112a10ab27ccab812822e4807507515403cf2791946444fc8e964acee6c',
                'web/src/admin/views/business/shop-plain/components/ShopPlainForm.vue' => '9957f5c04f51f87b1458c9968f410176d0fe45c2ba2e26cb7f79936a57c1c5a9',
                'web/src/admin/views/business/shop-plain/index.vue' => 'f1c0628836ba60c6d4c457d8d19078b92714dc3f7e1eab924b8b0a4e4ff861f6',
                'web/tests/business/shop-plain.spec.ts' => '50089bc2daf01a7d382586b734cd5a2f86f8a95d35b31e62085c56ddd45b6486',
            ],
        ],
        'plugin' => [
            'definitionHash' => 'a75fb744e94ec91a812164be753df213a21468629d1fe3d2d862755dd08322c5',
            'files' => [
                'plugins/shopkit/app/admin/plugins/shopkit/controller/ShopPlainController.php' => '6f43e2318ad4976cee5a929ea898111b4cb942ca69f846c89be54a7fa34e5c27',
                'plugins/shopkit/app/admin/plugins/shopkit/model/ShopPlain.php' => '735cbf06dc88f65462099000fa3e0f4536f4f0550e04d89ac3be3867c044e193',
                'plugins/shopkit/app/admin/plugins/shopkit/service/ShopPlainService.php' => '943bbd4e32e383f35c3cc0661679aaf43e37827dd4378ef69621ecae89161e0e',
                'plugins/shopkit/app/admin/plugins/shopkit/validate/ShopPlainValidate.php' => 'b981d6cb4d36860cfd2adecb15dd763d4cecb911b7a7ea85f0dc17aa943ef0cc',
                'plugins/shopkit/database/migrations/001_create_shop_plain.sql' => '59b9f7bceea3862970fa8159613ab9a35c4ac2b3a59ecfd19f6aad96ffd6b49e',
                'plugins/shopkit/plugin.json' => 'cc10c9096e8733b808c245c7f4fc2e7d452add5bbab2a32da831ea5e959df9c9',
                'plugins/shopkit/web/src/admin/plugins/shopkit/shop-plain/api.ts' => '87354cfa671278907fb83577be0c410f36c0885d53a8aa9cf9d958cb8812d220',
                'plugins/shopkit/web/src/admin/plugins/shopkit/shop-plain/components/ShopPlainDetail.vue' => '7669711049fd28699c321cfb2bde96ecb2eb1100b017e4aab8931c7874654c76',
                'plugins/shopkit/web/src/admin/plugins/shopkit/shop-plain/components/ShopPlainForm.vue' => '7fcb0f8da03cc7270993fb40c326205cd71470bfa4a9f4b3c445be65ba2e9860',
                'plugins/shopkit/web/src/admin/plugins/shopkit/shop-plain/index.vue' => 'd705b454841835ee5f76132a32034f3074ecd41c779653dbb923ed68a285a680',
            ],
        ],
    ];

    private const RELATION_NS = 'app\\admin\\business\\shop_goods\\model\\relation';

    private string $root = '';

    protected function tearDown(): void
    {
        if ($this->root !== '' && is_dir($this->root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        Context::destroy();
    }

    public function test_schema_relations_map_to_definition(): void
    {
        $relations = $this->goodsDefinition()->toArray()['relations'];
        $byName = array_column($relations, null, 'name');

        self::assertSame([
            'name' => 'detail', 'type' => 'hasOne', 'field' => 'id', 'target' => 'ShopGoodsDetail', 'targetField' => 'goods_id',
            'modelOnly' => true, 'targetTable' => 'fun_shop_goods_detail', 'modelClass' => self::RELATION_NS . '\\ShopGoodsDetail',
        ], $byName['detail']);
        self::assertSame([
            'name' => 'tags', 'type' => 'belongsToMany', 'field' => 'id', 'target' => 'ShopTag', 'targetField' => 'id',
            'pivotTable' => 'fun_shop_goods_tag', 'pivotLocalKey' => 'goods_id', 'pivotTargetKey' => 'tag_id',
            'modelOnly' => true, 'targetTable' => 'fun_shop_tag', 'modelClass' => self::RELATION_NS . '\\ShopTag',
        ], $byName['tags']);
        self::assertSame(['hasMany', 'id', 'goods_id'], [$byName['skus']['type'], $byName['skus']['field'], $byName['skus']['targetField']]);
        self::assertSame(self::RELATION_NS . '\\ShopCategory', $byName['category']['modelClass']);
    }

    public function test_has_one_and_has_many_use_eloquent_argument_order(): void
    {
        $model = ProductionTemplateContext::build($this->goodsDefinition())['modelContent'];

        self::assertStringContainsString('->hasMany(\\' . self::RELATION_NS . "\\ShopGoodsSku::class, 'goods_id', 'id')", $model);
        self::assertStringContainsString('->hasOne(\\' . self::RELATION_NS . "\\ShopGoodsDetail::class, 'goods_id', 'id')", $model);
        self::assertStringContainsString('->belongsTo(\\' . self::RELATION_NS . "\\ShopCategory::class, 'category_id', 'id')->select(['id', 'name']);", $model);
        self::assertStringContainsString('->belongsToMany(\\' . self::RELATION_NS . "\\ShopTag::class, 'shop_goods_tag', 'goods_id', 'tag_id', 'id', 'id')", $model);
        token_get_all($model, TOKEN_PARSE);
    }

    public function test_relation_targets_get_their_own_model_file(): void
    {
        $definition = $this->goodsDefinition();
        $plan = $this->generator()->plan($definition);
        $files = array_column($plan['files'], 'content', 'path');
        $base = 'app/admin/business/shop_goods/model/relation/';

        foreach (['ShopCategory' => 'shop_category', 'ShopGoodsSku' => 'shop_goods_sku', 'ShopGoodsDetail' => 'shop_goods_detail', 'ShopTag' => 'shop_tag'] as $class => $table) {
            self::assertArrayHasKey($base . $class . '.php', $files);
            $content = $files[$base . $class . '.php'];
            self::assertStringContainsString('namespace ' . self::RELATION_NS . ';', $content);
            self::assertStringContainsString("final class {$class} extends \\app\\common\\model\\BaseModel", $content);
            self::assertStringContainsString("protected \$table = '{$table}';", $content);
            self::assertStringContainsString("protected \$connection = 'mysql';", $content);
            self::assertStringContainsString("protected \$primaryKey = 'id';", $content);
            self::assertStringContainsString('public $timestamps = false;', $content);
            token_get_all($content, TOKEN_PARSE);
        }
        // 白名单只含生成代码读取的列：belongsTo 值/显示字段、子表单子字段与外键、纯模型关联仅连接键。
        $visible = static function (string $content): array {
            preg_match('/protected \$visible = (.*?);\n/s', $content, $match);
            return eval('return ' . $match[1] . ';');
        };
        self::assertSame(['id', 'name'], $visible($files[$base . 'ShopCategory.php']));
        self::assertSame(['goods_id', 'id', 'sku_name'], $visible($files[$base . 'ShopGoodsSku.php']));
        self::assertSame(['goods_id'], $visible($files[$base . 'ShopGoodsDetail.php']));
        self::assertSame(['id'], $visible($files[$base . 'ShopTag.php']));
        self::assertCount(4, array_filter(array_keys($files), static fn (string $path): bool => str_starts_with($path, $base)));
        $service = $files['app/admin/business/shop_goods/service/ShopGoodsService.php'];
        self::assertStringContainsString('\\' . self::RELATION_NS . "\\ShopCategory::orderBy('id', 'asc')", $service);
        // 纯模型关联不预加载、不进入输出。
        preg_match('/WITH_RELATIONS = (.*?);\n/s', $service, $with);
        self::assertSame(['category', 'skus'], eval('return ' . $with[1] . ';'));
        $controller = $files['app/admin/business/shop_goods/controller/ShopGoodsController.php'];
        self::assertStringContainsString("'category' => \$model->category?->toArray()", $controller);
        self::assertStringNotContainsString('$model->detail', $controller);
        self::assertStringNotContainsString('$model->tags', $controller);

        // managed 规划按生成目标登记 artifactType，参与基线与冲突跟踪。
        $managed = array_column($this->generator()->planManaged($definition, [])['files'], null, 'path');
        self::assertSame('relationModel:ShopCategory', $managed[$base . 'ShopCategory.php']['artifactType']);
        self::assertSame('create', $managed[$base . 'ShopCategory.php']['status']);
    }

    public function test_plugin_target_generates_relation_models_in_plugin_namespace(): void
    {
        $factory = new FormCrudDefinitionFactory();
        $definition = $factory->forBusinessTarget($this->goodsDefinition(), ['type' => 'plugin', 'pluginCode' => 'shopkit']);
        $byName = array_column($definition->toArray()['relations'], null, 'name');
        self::assertSame('app\\admin\\plugins\\shopkit\\model\\relation\\ShopCategory', $byName['category']['modelClass']);
        $root = $this->root();
        (new PluginScaffolder($root . '/plugins'))->scaffold('shopkit', '关联插件', false, true, true);
        $files = array_column($this->generator()->plan($definition)['files'], 'content', 'path');
        $path = 'plugins/shopkit/app/admin/plugins/shopkit/model/relation/ShopCategory.php';
        self::assertArrayHasKey($path, $files);
        self::assertStringContainsString('namespace app\\admin\\plugins\\shopkit\\model\\relation;', $files[$path]);
        self::assertStringContainsString("->belongsTo(\\app\\admin\\plugins\\shopkit\\model\\relation\\ShopCategory::class, 'category_id', 'id')", $files['plugins/shopkit/app/admin/plugins/shopkit/model/ShopGoods.php']);

        $state = new class {
            public function loadBaselines(int $id): array { return []; }
        };
        $transactions = new GenerationTransactionService($root, new ConfirmationToken($root, 'relation-plugin-secret'),
            new GeneratedFileBaselineRepository($root, $state), $state, null, static fn (): array => []);
        $assert = new \ReflectionMethod($transactions, 'assertPluginPaths');
        $assert->invoke($transactions, $definition->get('target'), [['path' => $path, 'status' => 'create']], [$path]);
        $this->addToAssertionCount(1);
    }

    public function test_core_relation_removed_plans_delete_of_relation_model(): void
    {
        $path = 'app/admin/business/shop_goods/model/relation/ShopCategory.php';
        $type = 'relationModel:ShopCategory';
        $baselines = $this->localRelationBaseline($this->renderedRelation($this->categoryDefinition(true), $path), $path, $type);
        $files = array_column($this->generator()->planManaged($this->categoryDefinition(false), $baselines)['files'], null, 'path');
        self::assertSame('delete', $files[$path]['status']);
        self::assertSame($type, $files[$path]['artifactType']);

        file_put_contents($this->root() . '/' . $path, file_get_contents($this->root() . '/' . $path) . "// 本地修改\n");
        $plan = $this->generator()->planManaged($this->categoryDefinition(false), $baselines);
        self::assertSame('conflict', array_column($plan['files'], null, 'path')[$path]['status']);
        self::assertTrue($plan['blocked']);
    }

    public function test_plugin_relation_removed_plans_delete_of_relation_model(): void
    {
        $root = $this->root();
        (new PluginScaffolder($root . '/plugins'))->scaffold('shopkit', '关联插件', false, true, true);
        $path = 'plugins/shopkit/app/admin/plugins/shopkit/model/relation/ShopCategory.php';
        $factory = new FormCrudDefinitionFactory();
        $with = $factory->forBusinessTarget($this->categoryDefinition(true), ['type' => 'plugin', 'pluginCode' => 'shopkit']);
        $without = $factory->forBusinessTarget($this->categoryDefinition(false), ['type' => 'plugin', 'pluginCode' => 'shopkit']);
        $baselines = $this->localRelationBaseline($this->generator()->renderManagedBundle($with)[$path], $path, 'source');

        $plan = $this->generator()->planManaged($without, $baselines);
        $file = array_column($plan['files'], null, 'path')[$path];
        self::assertSame(['delete', 'source'], [$file['status'], $file['artifactType']]);
        self::assertContains($path, $plan['allowedPaths']);
        $state = new class {
            public function loadBaselines(int $id): array { return []; }
        };
        $transactions = new GenerationTransactionService($root, new ConfirmationToken($root, 'relation-plugin-secret'),
            new GeneratedFileBaselineRepository($root, $state), $state, null, static fn (): array => []);
        (new \ReflectionMethod($transactions, 'assertPluginPaths'))->invoke($transactions, $without->get('target'), $plan['files'], $plan['allowedPaths']);

        // 本地手改的孤立关联模型必须冲突阻断，不能静默删除。
        file_put_contents($root . '/' . $path, file_get_contents($root . '/' . $path) . "// 本地修改\n");
        $blocked = $this->generator()->planManaged($without, $baselines);
        self::assertSame('conflict', array_column($blocked['files'], null, 'path')[$path]['status']);
        self::assertTrue($blocked['blocked']);
        self::assertFileExists($root . '/' . $path);

        // 其它不再生成的基线路径仍被拒绝。
        try {
            $this->generator()->planManaged($without, [...$baselines, ['path' => 'plugins/shopkit/app/admin/plugins/shopkit/model/Other.php', 'artifactType' => 'source', 'baseHash' => str_repeat('a', 64)]]);
            self::fail('非关联模型的孤立基线必须拒绝');
        } catch (\InvalidArgumentException $error) {
            self::assertSame('BUSINESS_ARTIFACT_PATH_FORBIDDEN', $error->getMessage());
        }
    }

    public function test_self_relation_reuses_own_model(): void
    {
        $definition = $this->definition('shop_goods', [
            $this->node('title', 'input', 'varchar(120)'),
            $this->node('parent_id', 'select', 'bigint', ['database' => ['columnType' => 'bigint', 'nullable' => true,
                'relation' => ['type' => 'belongs_to', 'table' => 'fun_shop_goods', 'labelField' => 'title', 'valueField' => 'id', 'multiple' => false, 'onDelete' => 'restrict']]]),
        ]);
        $relation = $definition->toArray()['relations'][0];
        self::assertSame('app\\admin\\business\\shop_goods\\model\\ShopGoods', $relation['modelClass']);
        self::assertArrayNotHasKey('targetTable', $relation);
        $files = array_column($this->generator()->plan($definition)['files'], 'content', 'path');
        self::assertSame([], array_filter(array_keys($files), static fn (string $path): bool => str_contains($path, '/model/relation/')));
        self::assertStringContainsString("->belongsTo(\\app\\admin\\business\\shop_goods\\model\\ShopGoods::class, 'parent_id', 'id')", $files['app/admin/business/shop_goods/model/ShopGoods.php']);
        self::assertStringContainsString("\\app\\admin\\business\\shop_goods\\model\\ShopGoods::orderBy('id', 'asc')", $files['app/admin/business/shop_goods/service/ShopGoodsService.php']);
    }

    public function test_belongs_to_system_table_generates_relation_model(): void
    {
        $connection = (string) config('database.default');
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $prefix = Db::connection()->getTablePrefix();
        $definition = $this->definition('shop_order', [
            $this->node('title', 'input', 'varchar(120)'),
            $this->node('admin_id', 'select', 'bigint', ['database' => ['columnType' => 'bigint', 'nullable' => true,
                'relation' => ['type' => 'belongs_to', 'table' => $prefix . 'admin', 'labelField' => 'nickname', 'valueField' => 'id', 'multiple' => false, 'onDelete' => 'restrict']]]),
        ], [
            ['type' => 'hasOne', 'name' => 'invoice', 'table' => $prefix . 'shop_invoice', 'localField' => 'id', 'foreignField' => 'order_id'],
        ], $connection);
        $context = ProductionTemplateContext::build($definition);
        self::assertSame(['Admin', 'ShopInvoice'], array_keys($context['relationModels']));
        self::assertStringContainsString("protected \$table = 'admin';", $context['relationModels']['Admin']);
        $files = array_column($this->generator()->plan($definition)['files'], 'path');
        self::assertContains('app/admin/business/shop_order/model/relation/Admin.php', $files);

        Db::schema()->create('admin', static function (Blueprint $table): void { $table->id(); $table->string('nickname'); $table->string('password'); $table->string('token'); });
        Db::schema()->create('shop_invoice', static function (Blueprint $table): void { $table->id(); $table->unsignedBigInteger('order_id'); $table->string('secret'); });
        Db::schema()->create('shop_order', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->unsignedBigInteger('admin_id')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        $temporary = [];
        try {
            foreach ([$context['relationModels']['Admin'], $context['relationModels']['ShopInvoice'], $context['modelContent'], $context['serviceContent'], $context['validateContent'], $context['controllerContent']] as $source) {
                $file = tempnam(sys_get_temp_dir(), 'funadmin-relation-model-'); $temporary[] = $file;
                file_put_contents($file, $source); require $file;
            }
            Db::table('admin')->insert(['id' => 7, 'nickname' => '超级管理员', 'password' => 'hash-secret', 'token' => 'token-secret']);
            Db::table('shop_order')->insert(['title' => '订单一', 'admin_id' => 7, 'created_at' => '2026-11-07 00:00:00', 'updated_at' => '2026-11-07 00:00:00']);
            Db::table('shop_invoice')->insert(['order_id' => 1, 'secret' => 'invoice-secret']);
            Db::connection()->enableQueryLog();
            $request = new Request("GET /admin/business/shop-order HTTP/1.1\r\n\r\n");
            $request->context['admin'] = ['id' => 1];
            Context::set(\Webman\Http\Request::class, $request);
            $controller = new \app\admin\business\shop_order\controller\ShopOrderController();
            $listing = json_decode($controller->index()->rawBody(), true);
            self::assertSame(200, $listing['code'], (string) ($listing['msg'] ?? ''));
            self::assertSame(1, $listing['data']['total']);
            self::assertSame(['id' => 7, 'nickname' => '超级管理员'], $listing['data']['list'][0]['admin']);
            self::assertArrayNotHasKey('invoice', $listing['data']['list'][0]);
            foreach (['hash-secret', 'token-secret', 'invoice-secret', 'password', 'token'] as $secret) {
                self::assertStringNotContainsString($secret, json_encode($listing, JSON_UNESCAPED_UNICODE));
            }
            $queries = implode("\n", array_column(Db::connection()->getQueryLog(), 'query'));
            self::assertStringNotContainsString('shop_invoice', $queries);
            self::assertMatchesRegularExpression('/select "id", "nickname" from "fun_admin"/', $queries);
            self::assertStringNotContainsString('select * from "fun_admin"', $queries);
            // 预加载路径同样只取白名单列。
            $eager = \app\admin\business\shop_order\model\ShopOrder::with(\app\admin\business\shop_order\service\ShopOrderService::WITH_RELATIONS)->first();
            self::assertSame(['id' => 7, 'nickname' => '超级管理员'], $eager->admin->getAttributes());
            self::assertSame(['admin'], \app\admin\business\shop_order\service\ShopOrderService::WITH_RELATIONS);
            $detail = json_decode($controller->detail(1)->rawBody(), true);
            self::assertSame(['id' => 7, 'nickname' => '超级管理员'], $detail['data']['admin'] ?? null, (string) json_encode($detail, JSON_UNESCAPED_UNICODE));
            self::assertArrayNotHasKey('invoice', $detail['data']);
            // 模型方法仍然可用。
            self::assertSame('invoice-secret', \app\admin\business\shop_order\model\ShopOrder::query()->first()->invoice->getAttribute('secret'));
            $service = new \app\admin\business\shop_order\service\ShopOrderService();
            self::assertSame([['label' => '超级管理员', 'value' => 7]], $service->options('admin_id_options'));
        } finally {
            foreach ($temporary as $file) unlink($file);
            Db::schema()->dropIfExists('shop_order');
            Db::schema()->dropIfExists('admin');
            Db::schema()->dropIfExists('shop_invoice');
        }
    }

    public function test_modules_without_relations_are_byte_identical(): void
    {
        $root = $this->root();
        (new PluginScaffolder($root . '/plugins'))->scaffold('shopkit', '基准插件', false, true, true);
        $factory = new FormCrudDefinitionFactory();
        $schema = $this->schema('shop_plain', [
            $this->node('title', 'input', 'varchar(120)', ['list' => ['show' => true, 'filter' => 'like']]),
            $this->node('stock', 'number', 'int'),
            $this->node('remark', 'textarea', 'text'),
        ], [], 'mysql', '无关联模块');
        foreach (['core' => ['type' => 'core'], 'plugin' => ['type' => 'plugin', 'pluginCode' => 'shopkit']] as $name => $target) {
            $definition = $factory->forBusinessTarget($factory->createFromSchema($schema, []), $target);
            $files = [];
            foreach ($this->generator()->plan($definition)['files'] as $file) $files[$file['path']] = $file['hash'];
            ksort($files);
            self::assertSame(self::PLAIN_BASELINE[$name], ['definitionHash' => $definition->hash(), 'files' => $files], $name);
        }
    }

    public function test_field_access_travels_in_access_key_and_marks_field_sensitive(): void
    {
        $definition = $this->definition('shop_secure', [
            $this->node('title', 'input', 'varchar(120)', ['list' => ['show' => true, 'filter' => 'like']]),
            $this->node('secret', 'input', 'varchar(120)', ['access' => ['read' => ['shop:secret:read']], 'list' => ['show' => true, 'filter' => 'like']]),
        ]);
        $fields = array_column($definition->toArray()['fields'], null, 'name');
        self::assertSame(['read' => ['shop:secret:read']], $fields['secret']['access']);
        self::assertSame([], $fields['title']['access']);
        self::assertSame([], $fields['secret']['controlProps']);
        $bundle = $this->generator()->renderManagedBundle($definition);
        $controller = (string) current(array_filter($bundle, static fn (string $path): bool => str_ends_with($path, 'Controller.php'), ARRAY_FILTER_USE_KEY));
        self::assertStringContainsString("'title' =>", $controller);
        self::assertStringNotContainsString("'secret' =>", $controller);
    }

    public function test_definition_has_no_layout_schema(): void
    {
        $definition = (new FormCrudDefinitionFactory())->createFromSchema($this->schema('shop_plain', [
            $this->node('title', 'input', 'varchar(120)', ['list' => ['show' => true, 'filter' => 'like']]),
            $this->node('stock', 'number', 'int'),
            $this->node('remark', 'textarea', 'text'),
        ], [], 'mysql', '无关联模块'), []);

        self::assertArrayNotHasKey('layoutSchema', $definition->toArray());
    }

    private function categoryDefinition(bool $relation): CrudDefinition
    {
        $database = ['columnType' => 'bigint', 'nullable' => true] + ($relation ? ['relation' => [
            'type' => 'belongs_to', 'table' => 'fun_shop_category', 'labelField' => 'name', 'valueField' => 'id', 'multiple' => false, 'onDelete' => 'restrict',
        ]] : []);
        return $this->definition('shop_goods', [
            $this->node('title', 'input', 'varchar(120)'),
            $this->node('category_id', $relation ? 'select' : 'number', 'bigint', ['database' => $database]),
        ]);
    }

    private function renderedRelation(CrudDefinition $definition, string $path): string
    {
        return $this->generator()->renderManagedBundle($definition)[$path];
    }

    /** 模拟此前已生成：本地文件与基线均为当时的关联模型内容。 */
    private function localRelationBaseline(string $content, string $path, string $artifactType): array
    {
        $absolute = $this->root() . '/' . $path;
        if (!is_dir(dirname($absolute))) mkdir(dirname($absolute), 0700, true);
        file_put_contents($absolute, $content);
        return [['path' => $path, 'artifactType' => $artifactType, 'baseHash' => hash('sha256', $content), 'baseContent' => $content, 'contentKind' => 'text']];
    }

    private function goodsDefinition(): CrudDefinition
    {
        return $this->definition('shop_goods', [
            $this->node('title', 'input', 'varchar(120)'),
            $this->node('category_id', 'select', 'bigint', ['database' => ['columnType' => 'bigint', 'nullable' => true,
                'relation' => ['type' => 'belongs_to', 'table' => 'fun_shop_category', 'labelField' => 'name', 'valueField' => 'id', 'multiple' => false, 'onDelete' => 'restrict']]]),
            [
                'id' => 'skus_node', 'kind' => 'field', 'type' => 'subform', 'field' => 'skus', 'title' => '规格',
                'database' => ['relation' => ['type' => 'has_many', 'table' => 'fun_shop_goods_sku', 'valueField' => 'goods_id']],
                'children' => [$this->node('sku_name', 'input', 'varchar(60)')],
            ],
        ], [
            ['type' => 'hasOne', 'name' => 'detail', 'table' => 'fun_shop_goods_detail', 'localField' => 'id', 'foreignField' => 'goods_id'],
            ['type' => 'belongsToMany', 'name' => 'tags', 'table' => 'fun_shop_tag', 'localField' => 'id', 'foreignField' => 'id',
                'pivot' => ['table' => 'fun_shop_goods_tag', 'localKey' => 'goods_id', 'foreignKey' => 'tag_id']],
        ]);
    }

    private function definition(string $key, array $nodes, array $relations = [], string $connection = 'mysql'): CrudDefinition
    {
        return (new FormCrudDefinitionFactory())->createFromSchema($this->schema($key, $nodes, $relations, $connection), []);
    }

    private function schema(string $key, array $nodes, array $relations, string $connection, string $title = '关联模块'): \app\common\form\schema\FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
            'schemaVersion' => 2, 'key' => $key, 'title' => $title,
            'database' => ['connection' => $connection, 'table' => 'fun_' . $key, 'source' => 'created'],
            'nodes' => $nodes,
        ] + ($relations === [] ? [] : ['relations' => $relations]));
    }

    private function node(string $field, string $type, string $column, array $extra = []): array
    {
        return array_replace(['id' => $field . '_node', 'kind' => 'field', 'type' => $type, 'field' => $field, 'title' => $field,
            'database' => ['columnType' => $column, 'nullable' => true], 'list' => ['show' => true], 'children' => []], $extra);
    }

    private function root(): string
    {
        if ($this->root === '') {
            $this->root = sys_get_temp_dir() . '/funadmin-relations-' . bin2hex(random_bytes(8));
            mkdir($this->root, 0700, true);
        }
        return $this->root;
    }

    private function generator(): CrudGenerator
    {
        return new CrudGenerator($this->root(), tokens: new ConfirmationToken($this->root(), 'relation-test-secret'));
    }
}
