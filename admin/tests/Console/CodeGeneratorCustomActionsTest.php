<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\service\FormCrudDefinitionFactory;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\ProductionTemplateContext;
use app\common\form\action\FormActionNotImplementedException;
use app\common\form\action\ListActionStore;
use app\common\form\action\ListButtonExecutor;
use app\common\form\registry\FormRegistryFactory;
use app\common\form\schema\FormSchema;
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
final class CodeGeneratorCustomActionsTest extends TestCase
{
    private const ACTIONS = 'app/admin/business/shop_order/action/ShopOrderActions.php';
    private const CONFIG = 'app/admin/business/shop_order/config/actions.php';

    private string $root = '';
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $file) if (is_file($file)) unlink($file);
        if ($this->root !== '' && is_dir($this->root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($this->root);
        }
        Context::destroy();
    }

    public function test_generation_adds_actions_class_and_registry_config(): void
    {
        $definition = $this->definition('shop_order', self::actions());
        self::assertSame([self::ACTIONS, self::CONFIG], [$definition->get('generationTargets')['customActions'], $definition->get('generationTargets')['customActionsConfig']]);
        $files = array_column($this->generator()->plan($definition)['files'], 'content', 'path');
        self::assertArrayHasKey(self::ACTIONS, $files);
        self::assertArrayHasKey(self::CONFIG, $files);
        token_get_all($files[self::ACTIONS], TOKEN_PARSE);
        token_get_all($files[self::CONFIG], TOKEN_PARSE);
        self::assertStringContainsString('namespace app\\admin\\business\\shop_order\\action;', $files[self::ACTIONS]);
        self::assertStringContainsString('public function approve(array $parameters, array $context): array', $files[self::ACTIONS]);
        self::assertStringContainsString('public function markPaid(array $parameters, array $context): array', $files[self::ACTIONS]);

        $registry = $this->loadGenerated($files)['registry'];
        self::assertSame(['business.shop-order.approve', 'business.shop-order.mark_paid'], array_keys($registry));
        $approve = $registry['business.shop-order.approve'];
        self::assertSame([
            'permission' => 'business:shop-order:action-approve', 'capabilityVersion' => '1', 'locations' => ['row'], 'targets' => ['record'],
            'parameters' => ['id', 'note'], 'parameterTypes' => ['id' => 'integer', 'note' => 'string'],
            'effect' => 'write', 'batch' => false, 'requiresConfirmation' => true, 'resultContract' => 'json', 'timeoutMs' => 3000, 'receivesContext' => true,
        ], array_diff_key($approve, ['handler' => true]));
        self::assertSame(['toolbar'], $registry['business.shop-order.mark_paid']['locations']);
        self::assertSame(['selection'], $registry['business.shop-order.mark_paid']['targets']);
        self::assertSame('business:shop-order:action-mark-paid', $registry['business.shop-order.mark_paid']['permission']);
        self::assertSame('read', $registry['business.shop-order.mark_paid']['effect']);
        try {
            ($approve['handler'])(['id' => 1, 'note' => '同意'], ['location' => 'row', 'ids' => [1], 'id' => 1]);
            self::fail('方法桩必须提示未实现');
        } catch (FormActionNotImplementedException $error) {
            self::assertSame('未实现：审核', $error->getMessage());
        }

        // managed 规划按生成目标登记 artifactType，参与基线与冲突跟踪。
        $managed = array_column($this->generator()->planManaged($definition, [])['files'], null, 'path');
        self::assertSame(['customActions', 'create'], [$managed[self::ACTIONS]['artifactType'], $managed[self::ACTIONS]['status']]);
        self::assertSame(['customActionsConfig', 'create'], [$managed[self::CONFIG]['artifactType'], $managed[self::CONFIG]['status']]);
    }

    public function test_permission_migration_registers_one_row_per_custom_action(): void
    {
        $definition = $this->definition('shop_order', self::actions());
        $actions = array_column($definition->toArray()['permission']['actions'], null, 'codeSuffix');
        self::assertSame(['action' => 'action-approve', 'codeSuffix' => 'action-approve', 'label' => '审核'], $actions['action-approve']);
        self::assertSame('标记已付', $actions['action-mark-paid']['label']);
        $sql = array_column($this->generator()->plan($definition)['files'], 'content', 'path')['database/business/shop-order_permissions.sql'];
        self::assertStringContainsString("SELECT @permission_group_id, 'admin', 'business:shop-order:action-approve', 'business/shop-order', 'action-approve', '审核', 'route', 1, 0, 'generated', 'shop-order'", $sql);
        self::assertStringContainsString("'business:shop-order:action-mark-paid', 'business/shop-order', 'action-mark-paid', '标记已付'", $sql);
        self::assertMatchesRegularExpression("/`code` NOT IN \\([^)]*'business:shop-order:action-approve'[^)]*\\)/", $sql);
    }

    public function test_empty_custom_actions_keep_definition_and_files_identical(): void
    {
        $plain = $this->definition('shop_order', null);
        $empty = $this->definition('shop_order', []);
        self::assertSame($plain->hash(), $empty->hash());
        $hashes = static fn (array $plan): array => array_column($plan['files'], 'hash', 'path');
        self::assertSame($hashes($this->generator()->plan($plain)), $hashes($this->generator()->plan($empty)));
        self::assertArrayNotHasKey('customActions', $plain->get('generationTargets'));
        self::assertArrayNotHasKey('customActions', $plain->get('templates'));
    }

    public function test_plugin_target_rejects_custom_actions(): void
    {
        (new PluginScaffolder($this->root() . '/plugins'))->scaffold('shopkit', '订单插件', false, true, true);
        $factory = new FormCrudDefinitionFactory();
        $definition = $factory->forBusinessTarget($this->definition('shop_order', self::actions()), ['type' => 'plugin', 'pluginCode' => 'shopkit']);
        $this->expectExceptionObject(new \InvalidArgumentException('插件目标暂不支持自定义动作，请改为生成到核心目标或移除 list.customActions'));
        $this->generator()->plan($definition);
    }

    public function test_definition_validator_requires_matching_custom_action_artifacts(): void
    {
        $data = $this->definition('shop_order', self::actions())->toArray();
        unset($data['generationTargets']['customActionsConfig']);
        try {
            $this->generator()->plan(CrudDefinition::fromArray($data));
            self::fail('缺少注册清单目标必须拒绝');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('customActionsConfig', $error->getMessage());
        }
        $data = $this->definition('shop_order', null)->toArray();
        $data['generationTargets']['customActions'] = self::ACTIONS;
        try {
            $this->generator()->plan(CrudDefinition::fromArray($data));
            self::fail('未声明自定义动作时不得生成动作类');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('customActions', $error->getMessage());
        }
    }

    public function test_registry_factory_merges_generated_action_configs_and_rejects_duplicates(): void
    {
        $generated = $this->root() . '/generated';
        $write = static function (string $dir, string $content) use ($generated): void {
            if (!is_dir($generated . '/' . $dir . '/config')) mkdir($generated . '/' . $dir . '/config', 0700, true);
            file_put_contents($generated . '/' . $dir . '/config/actions.php', $content);
        };
        $definition = static fn (string $permission): string => "['handler' => static fn (array \$p): array => ['ok' => true], 'permission' => '{$permission}', 'locations' => ['row'], 'targets' => ['record'], 'effect' => 'read']";
        $write('shop_order', "<?php\nreturn ['business.shop-order.approve' => " . $definition('business:shop-order:action-approve') . "];\n");
        $write('shop_goods', "<?php\nreturn ['business.shop-goods.publish' => " . $definition('business:shop-goods:action-publish') . "];\n");
        $config = ['actions' => ['system.ping' => ['handler' => static fn (): array => [], 'permission' => 'system:ping:run']]];

        $definitions = (new FormRegistryFactory($config, null, $generated))->actions()->definitions();
        $keys = array_keys($definitions);
        sort($keys);
        self::assertSame(['business.shop-goods.publish', 'business.shop-order.approve', 'system.ping'], $keys);
        self::assertSame('business:shop-order:action-approve', $definitions['business.shop-order.approve']['permission']);
        // 不存在的生成目录不影响核心配置。
        self::assertSame(['system.ping'], array_keys((new FormRegistryFactory($config, null, $generated . '/missing'))->actions()->definitions()));

        $conflict = ['actions' => ['business.shop-order.approve' => $config['actions']['system.ping']]];
        try {
            (new FormRegistryFactory($conflict, null, $generated))->actions();
            self::fail('注册键冲突必须报错');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('business.shop-order.approve', $error->getMessage());
            self::assertStringContainsString('config/form.php', $error->getMessage());
            self::assertStringContainsString('shop_order/config/actions.php', $error->getMessage());
        }

        // 生成清单只能登记本模块前缀的键，不能借名覆盖其他模块或核心动作。
        $write('shop_goods', "<?php\nreturn ['business.shop-order.reject' => " . $definition('business:shop-order:action-reject') . "];\n");
        try {
            (new FormRegistryFactory([], null, $generated))->actions();
            self::fail('越界注册键必须报错');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('business.shop-order.reject', $error->getMessage());
            self::assertStringContainsString('shop_goods/config/actions.php', $error->getMessage());
        }
    }

    public function test_generated_list_action_reaches_method_stub_through_registered_action_chain(): void
    {
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $button = ['id' => 'approve-row', 'label' => '审核', 'permission' => 'business:console-order:action-approve',
            'action' => ['type' => 'registered', 'key' => 'business.console-order.approve', 'capabilityVersion' => '1'],
            'params' => ['id' => ['source' => 'row', 'field' => 'id'], 'note' => ['source' => 'literal', 'value' => '同意']]];
        $schema = $this->schema('console_order', self::actions(), $connection, $prefix . 'console_order', ['row' => [$button]]);
        $definition = (new FormCrudDefinitionFactory())->createFromSchema($schema, []);
        $context = ProductionTemplateContext::build($definition);
        $files = [];
        foreach ($definition->get('generationTargets') as $type => $path) {
            if (in_array($type, ['model', 'validate', 'service', 'controller', 'customActions', 'customActionsConfig'], true)) {
                $files[$path] = $context[['model' => 'modelContent', 'validate' => 'validateContent', 'service' => 'serviceContent', 'controller' => 'controllerContent', 'customActions' => 'customActionsContent', 'customActionsConfig' => 'customActionsConfigContent'][$type]];
            }
        }
        BusinessGenerationFixture::create();
        Db::schema()->table('form', static function (Blueprint $table): void {
            $table->string('form_key'); $table->integer('status')->default(1); $table->string('name')->default('');
            $table->string('table_name')->default(''); $table->string('connection')->default(''); $table->string('source_type')->default('');
        });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->string('schema_hash'); $table->json('schema_document');
        });
        Db::schema()->create('console_order', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->timestamps(); $table->softDeletes();
        });
        try {
            Db::table('form')->insert(['id' => 1, 'form_key' => 'console_order']);
            Db::table('business_module')->insert(['form_id' => 1, 'code' => 'console_order', 'lifecycle_status' => 'published', 'published_schema_hash' => $schema->hash(), 'published_schema_version' => 1, 'metadata' => '{}']);
            Db::table('form_schema_version')->insert(['form_id' => 1, 'version' => 1, 'schema_hash' => $schema->hash(), 'schema_document' => $schema->canonicalJson()]);
            $generated = $this->root() . '/generated';
            foreach ($files as $path => $content) {
                if (str_ends_with($path, '/config/actions.php')) {
                    $target = $generated . '/' . substr($path, strlen('app/admin/business/'));
                    mkdir(dirname($target), 0700, true);
                    file_put_contents($target, $content);
                    continue;
                }
                $file = tempnam(sys_get_temp_dir(), 'funadmin-custom-action-'); $this->temporary[] = $file;
                file_put_contents($file, $content); require $file;
            }
            $record = Db::table('console_order')->insertGetId(['title' => '待审核']);
            $request = new Request("POST /admin/business/console-order/list-action HTTP/1.1\r\n\r\n");
            $request->context['admin'] = ['id' => 7];
            Context::set(\Webman\Http\Request::class, $request);

            $registry = (new FormRegistryFactory([], null, $generated))->actions();
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE list_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, scope VARCHAR(64) NOT NULL, digest VARCHAR(64), state VARCHAR(20), result TEXT, deleted_at DATETIME NULL, deleted_token INTEGER NOT NULL DEFAULT 0, UNIQUE (scope, deleted_token))');
            $executor = new ListButtonExecutor($registry, bin2hex(random_bytes(32)), new ListActionStore($pdo, 'list_actions'));
            $binding = ['formKey' => 'console_order', 'schemaHash' => $schema->hash(), 'route' => 'business:console-order', 'table' => $prefix . 'console_order', 'connection' => $connection];
            $granted = [];
            $service = new \app\admin\form\service\FormDataService(permissionChecker: static function (string $permission) use (&$granted): bool {
                $granted[] = $permission;
                return $permission !== 'business:console-order:action-approve' || in_array('action', $GLOBALS['customActionGrant'] ?? [], true);
            }, productionBinding: $binding);
            $payload = ['buttonId' => 'approve-row', 'location' => 'row', 'schemaHash' => $schema->hash(), 'ids' => [$record], 'idempotencyKey' => 'first'];
            $controller = new \app\admin\business\console_order\controller\ConsoleOrderController();
            $failure = new \ReflectionMethod($controller, 'listActionFailure');

            // 无动作权限：目录不列出、执行被拒并映射为 403。
            self::assertArrayNotHasKey('business.console-order.approve', (array) $service->listActionCatalog('console_order', 'row', $registry)['actions']);
            try {
                $service->executeListButton('console_order', $payload, $executor);
                self::fail('无动作权限必须拒绝');
            } catch (\InvalidArgumentException $error) {
                self::assertSame('FORM_ACTION_FORBIDDEN', $error->getMessage());
                self::assertSame(403, json_decode($failure->invoke($controller, $error)->rawBody(), true)['code']);
            }
            self::assertContains('business:console-order:action-approve', $granted);

            // 已授权：仍需二次确认，确认后调用到方法桩，入口返回「未实现：审核」而非服务器错误。
            $GLOBALS['customActionGrant'] = ['action'];
            self::assertArrayHasKey('business.console-order.approve', (array) $service->listActionCatalog('console_order', 'row', $registry)['actions']);
            $confirmation = $service->executeListButton('console_order', $payload, $executor);
            self::assertSame('confirmation_required', $confirmation['status']);
            try {
                $service->executeListButton('console_order', $payload + ['confirmation' => $confirmation['confirmation']], $executor);
                self::fail('方法桩必须提示未实现');
            } catch (FormActionNotImplementedException $error) {
                $reply = json_decode($failure->invoke($controller, $error)->rawBody(), true);
                self::assertSame([422, '未实现：审核', 'FORM_ACTION_NOT_IMPLEMENTED'], [$reply['code'], $reply['msg'], $reply['data']['code'] ?? null]);
            }

            // 用户实现方法后（以同一份注册定义替换处理器模拟），同一条注册链返回处理结果。
            $definitions = require $generated . '/console_order/config/actions.php';
            $definitions['business.console-order.approve']['handler'] = static fn (array $parameters): array => ['approved' => $parameters['id'], 'note' => $parameters['note']];
            $executor = new ListButtonExecutor(new \app\common\form\action\FormActionRegistry($definitions), bin2hex(random_bytes(32)), new ListActionStore($pdo, 'list_actions'));
            $payload['idempotencyKey'] = 'second';
            $confirmation = $service->executeListButton('console_order', $payload, $executor);
            self::assertSame(['status' => 'success', 'result' => ['approved' => $record, 'note' => '同意']],
                $service->executeListButton('console_order', $payload + ['confirmation' => $confirmation['confirmation']], $executor));
        } finally {
            unset($GLOBALS['customActionGrant']);
            Db::schema()->dropIfExists('console_order');
            Db::schema()->dropIfExists('form_schema_version');
            BusinessGenerationFixture::drop();
        }
    }

    public function test_method_stub_receives_record_id_or_selected_ids_in_context(): void
    {
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        $buttons = [
            'row' => [['id' => 'approve-row', 'label' => '审核', 'permission' => 'business:console-order:action-approve',
                'action' => ['type' => 'registered', 'key' => 'business.console-order.approve', 'capabilityVersion' => '1'],
                'params' => ['id' => ['source' => 'row', 'field' => 'id'], 'note' => ['source' => 'literal', 'value' => '同意']]]],
            'toolbar' => [['id' => 'mark-paid', 'label' => '标记已付', 'permission' => 'business:console-order:action-mark-paid',
                'action' => ['type' => 'registered', 'key' => 'business.console-order.mark_paid', 'capabilityVersion' => '1']]],
        ];
        $schema = $this->schema('console_order', self::actions(), $connection, $prefix . 'console_order', $buttons);
        $definition = (new FormCrudDefinitionFactory())->createFromSchema($schema, []);
        $context = ProductionTemplateContext::build($definition);
        // 以生成的动作类为模板：方法体改为回显 context，模拟用户实现。
        $class = str_replace(
            ["throw new \\app\\common\\form\\action\\FormActionNotImplementedException('审核');", "throw new \\app\\common\\form\\action\\FormActionNotImplementedException('标记已付');"],
            ["return ['context' => \$context, 'parameters' => \$parameters];", "return ['context' => \$context];"],
            $context['customActionsContent']
        );
        self::assertStringContainsString("@param array{key: string, location: 'row'|'toolbar', ids: list<int|string>, id?: int|string} \$context", $context['customActionsContent']);
        $this->setUpPublishedModule($schema);
        try {
            $generated = $this->root() . '/generated';
            mkdir($generated . '/console_order/config', 0700, true);
            file_put_contents($generated . '/console_order/config/actions.php', $context['customActionsConfigContent']);
            foreach ([$class, $context['modelContent']] as $source) {
                $file = tempnam(sys_get_temp_dir(), 'funadmin-custom-context-'); $this->temporary[] = $file;
                file_put_contents($file, $source); require $file;
            }
            $first = Db::table('console_order')->insertGetId(['title' => '一']);
            $second = Db::table('console_order')->insertGetId(['title' => '二']);
            $request = new Request("POST /admin/business/console-order/list-action HTTP/1.1\r\n\r\n");
            $request->context['admin'] = ['id' => 7];
            Context::set(\Webman\Http\Request::class, $request);
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE list_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, scope VARCHAR(64) NOT NULL, digest VARCHAR(64), state VARCHAR(20), result TEXT, deleted_at DATETIME NULL, deleted_token INTEGER NOT NULL DEFAULT 0, UNIQUE (scope, deleted_token))');
            $executor = new ListButtonExecutor((new FormRegistryFactory([], null, $generated))->actions(), bin2hex(random_bytes(32)), new ListActionStore($pdo, 'list_actions'));
            $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (): bool => true, productionBinding: [
                'formKey' => 'console_order', 'schemaHash' => $schema->hash(), 'route' => 'business:console-order', 'table' => $prefix . 'console_order', 'connection' => $connection]);

            $row = ['buttonId' => 'approve-row', 'location' => 'row', 'schemaHash' => $schema->hash(), 'ids' => [$second], 'idempotencyKey' => 'row-1'];
            $confirmation = $service->executeListButton('console_order', $row, $executor);
            $result = $service->executeListButton('console_order', $row + ['confirmation' => $confirmation['confirmation']], $executor)['result'];
            self::assertSame(['key' => 'business.console-order.approve', 'location' => 'row', 'ids' => [$second], 'id' => $second], $result['context']);
            self::assertSame(['id' => $second, 'note' => '同意'], $result['parameters']);

            $toolbar = ['buttonId' => 'mark-paid', 'location' => 'toolbar', 'schemaHash' => $schema->hash(), 'ids' => [$first, $second], 'idempotencyKey' => 'bar-1'];
            $result = $service->executeListButton('console_order', $toolbar, $executor)['result'];
            self::assertSame(['key' => 'business.console-order.mark_paid', 'location' => 'toolbar', 'ids' => [$first, $second]], $result['context']);
        } finally {
            $this->tearDownPublishedModule();
        }
    }

    /** 处理器明确声明「未成功」（FormActionUserException / 未实现）时释放幂等占位，同一请求可原样重试；其他异常保持 unknown。 */
    public function test_explicit_failure_releases_idempotency_slot_while_other_errors_stay_unknown(): void
    {
        $prefix = Db::connection()->getTablePrefix();
        $connection = (string) config('database.default');
        $button = ['id' => 'approve-row', 'label' => '审核', 'permission' => 'business:console-order:action-approve',
            'action' => ['type' => 'registered', 'key' => 'business.console-order.approve', 'capabilityVersion' => '1'],
            'params' => ['id' => ['source' => 'row', 'field' => 'id'], 'note' => ['source' => 'literal', 'value' => '同意']]];
        $schema = $this->schema('console_order', self::actions(), $connection, $prefix . 'console_order', ['row' => [$button]]);
        $context = ProductionTemplateContext::build((new FormCrudDefinitionFactory())->createFromSchema($schema, []));
        $this->setUpPublishedModule($schema);
        try {
            foreach ([$context['customActionsContent'], $context['modelContent']] as $source) {
                $file = tempnam(sys_get_temp_dir(), 'funadmin-custom-retry-'); $this->temporary[] = $file;
                file_put_contents($file, $source); require $file;
            }
            $config = tempnam(sys_get_temp_dir(), 'funadmin-custom-retry-config-'); $this->temporary[] = $config;
            file_put_contents($config, $context['customActionsConfigContent']);
            $definitions = require $config;
            $record = Db::table('console_order')->insertGetId(['title' => '待审核']);
            $request = new Request("POST /admin/business/console-order/list-action HTTP/1.1\r\n\r\n");
            $request->context['admin'] = ['id' => 7];
            Context::set(\Webman\Http\Request::class, $request);
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec('CREATE TABLE list_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, scope VARCHAR(64) NOT NULL, digest VARCHAR(64), state VARCHAR(20), result TEXT, deleted_at DATETIME NULL, deleted_token INTEGER NOT NULL DEFAULT 0, UNIQUE (scope, deleted_token))');
            $secret = bin2hex(random_bytes(32));
            $service = new \app\admin\form\service\FormDataService(permissionChecker: static fn (): bool => true, productionBinding: [
                'formKey' => 'console_order', 'schemaHash' => $schema->hash(), 'route' => 'business:console-order', 'table' => $prefix . 'console_order', 'connection' => $connection]);
            $run = function (callable $handler, string $nonce) use ($definitions, $secret, $pdo, $service, $schema, $record): callable {
                $definitions['business.console-order.approve']['handler'] = $handler;
                $executor = new ListButtonExecutor(new \app\common\form\action\FormActionRegistry($definitions), $secret, new ListActionStore($pdo, 'list_actions'));
                $payload = ['buttonId' => 'approve-row', 'location' => 'row', 'schemaHash' => $schema->hash(), 'ids' => [$record], 'idempotencyKey' => $nonce];
                $payload['confirmation'] = $service->executeListButton('console_order', $payload, $executor)['confirmation'];
                return static fn (): array => $service->executeListButton('console_order', $payload, $executor);
            };

            // 用户异常：第一次拒绝，同一 idempotencyKey 原样重试会再次执行处理器。
            $calls = 0;
            $send = $run(static function (array $parameters) use (&$calls): array {
                if (++$calls === 1) throw new \app\common\form\action\FormActionUserException('订单已锁定，请稍后重试');
                return ['approved' => $parameters['id']];
            }, 'user-retry');
            try { $send(); self::fail('必须抛出用户异常'); } catch (\app\common\form\action\FormActionUserException) {}
            self::assertSame(['status' => 'success', 'result' => ['approved' => $record]], $send());
            self::assertSame(2, $calls);
            // 成功后重放返回已记录结果，不再执行。
            self::assertSame(['status' => 'success', 'result' => ['approved' => $record]], $send());
            self::assertSame(2, $calls);

            // 未实现的方法桩同样视为「未发生」：重试再次到达方法桩，而非 FORM_LIST_RESULT_UNKNOWN。
            $stub = $definitions['business.console-order.approve']['handler'];
            $send = $run($stub, 'stub-retry');
            foreach ([1, 2] as $_) {
                try { $send(); self::fail('方法桩必须提示未实现'); } catch (FormActionNotImplementedException) {}
            }

            // 其他异常可能已有副作用：保持 unknown，禁止重试。
            $calls = 0;
            $send = $run(static function () use (&$calls): array { $calls++; throw new \RuntimeException('写入中断'); }, 'runtime-retry');
            try { $send(); self::fail('必须抛出运行时异常'); } catch (\RuntimeException $error) { self::assertSame('写入中断', $error->getMessage()); }
            try { $send(); self::fail('未知结果不得重试'); } catch (\InvalidArgumentException $error) { self::assertSame('FORM_LIST_RESULT_UNKNOWN', $error->getMessage()); }
            self::assertSame(1, $calls);
        } finally {
            $this->tearDownPublishedModule();
        }
    }

    public function test_hand_edited_actions_class_survives_regeneration(): void
    {
        $definition = $this->definition('shop_order', self::actions());
        $content = $this->generator()->renderManagedBundle($definition)[self::ACTIONS];
        $absolute = $this->root() . '/' . self::ACTIONS;
        mkdir(dirname($absolute), 0700, true);
        $edited = str_replace("throw new \\app\\common\\form\\action\\FormActionNotImplementedException('审核');", "return ['approved' => \$parameters['id']];", $content);
        self::assertNotSame($content, $edited);
        file_put_contents($absolute, $edited);
        $baselines = [['path' => self::ACTIONS, 'artifactType' => 'customActions', 'baseHash' => hash('sha256', $content), 'baseContent' => $content, 'contentKind' => 'text']];

        $same = array_column($this->generator()->planManaged($definition, $baselines)['files'], null, 'path')[self::ACTIONS];
        self::assertSame('keep-local', $same['status']);

        $more = [...self::actions(), ['key' => 'reject', 'label' => '驳回', 'locations' => ['row'], 'batch' => false, 'requiresConfirmation' => true, 'effect' => 'write', 'parameters' => []]];
        $plan = $this->generator()->planManaged($this->definition('shop_order', $more), $baselines);
        $file = array_column($plan['files'], null, 'path')[self::ACTIONS];
        self::assertSame('auto-merged', $file['status']);
        self::assertFalse($plan['blocked']);
        self::assertStringContainsString("return ['approved' => \$parameters['id']];", $file['content']);
        self::assertStringContainsString('public function reject(array $parameters, array $context): array', $file['content']);
        token_get_all($file['content'], TOKEN_PARSE);
    }

    public function test_button_missing_a_bound_parameter_reports_the_button_and_parameter(): void
    {
        $button = ['id' => 'approve-row', 'label' => '审核按钮', 'permission' => 'business:shop-order:action-approve',
            'action' => ['type' => 'registered', 'key' => 'business.shop-order.approve', 'capabilityVersion' => '1'],
            'params' => ['id' => ['source' => 'row', 'field' => 'id']]];
        $definition = (new FormCrudDefinitionFactory())->createFromSchema($this->schema('shop_order', self::actions(), buttons: ['row' => [$button]]), []);
        try {
            (new \app\common\crud\DefinitionValidator($this->dependencyChecker(static fn (): bool => true)))->validate($definition, $this->root());
            self::fail('按钮缺少动作参数绑定必须拒绝');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('审核按钮', $error->getMessage());
            self::assertStringContainsString('note', $error->getMessage());
            self::assertStringNotContainsString('尚未接通安全宿主适配', $error->getMessage());
        }
    }

    public function test_registry_errors_are_not_hidden_behind_the_generic_adapter_message(): void
    {
        $button = ['id' => 'approve-row', 'label' => '审核', 'permission' => 'business:shop-order:action-approve',
            'action' => ['type' => 'registered', 'key' => 'business.shop-order.approve', 'capabilityVersion' => '1'],
            'params' => ['id' => ['source' => 'row', 'field' => 'id'], 'note' => ['source' => 'literal', 'value' => '同意']]];
        $definition = (new FormCrudDefinitionFactory())->createFromSchema($this->schema('shop_order', self::actions(), buttons: ['row' => [$button]]), []);
        $conflict = static fn (): bool => throw new \InvalidArgumentException('表单动作注册键冲突：business.shop-order.approve 同时声明于 config/form.php 与 app/admin/business/shop_order/config/actions.php');
        try {
            (new \app\common\crud\DefinitionValidator($this->dependencyChecker($conflict)))->validate($definition, $this->root());
            self::fail('注册表错误必须阻断生成');
        } catch (\InvalidArgumentException $error) {
            self::assertStringContainsString('表单动作注册键冲突：business.shop-order.approve', $error->getMessage());
        }
    }

    public function test_generated_config_declares_timeout_and_stub_documents_user_messages(): void
    {
        $context = ProductionTemplateContext::build($this->definition('shop_order', self::actions()));
        self::assertSame(2, substr_count($context['customActionsConfigContent'], "'timeoutMs' => 3000,"));
        self::assertStringContainsString('FormActionUserException', $context['customActionsContent']);
        self::assertStringContainsString("'message'", $context['customActionsContent']);
        self::assertStringContainsString('timeoutMs', $context['customActionsContent']);
        // 用户异常须在做出任何更改之前抛出：入口把它视为「未发生」并允许原样重试。
        self::assertStringContainsString('在做出任何更改之前', $context['customActionsContent']);
    }

    public function test_user_exception_returns_422_with_handler_message(): void
    {
        $controller = new class extends \app\common\controller\AdminController {
            public function failure(\Throwable $error): array { return json_decode($this->listActionFailure($error)->rawBody(), true); }
        };
        Context::set(\Webman\Http\Request::class, new Request("POST /admin/business/shop-order/list-action HTTP/1.1\r\n\r\n"));
        $reply = $controller->failure(new \app\common\form\action\FormActionUserException('订单已关闭，不能审核'));
        self::assertSame([422, '订单已关闭，不能审核', 'FORM_ACTION_USER_ERROR'], [$reply['code'], $reply['msg'], $reply['data']['code'] ?? null]);
        // 其他运行时异常仍不泄露文本。
        self::assertSame('服务器内部错误', $controller->failure(new \RuntimeException('secret'))['msg']);
    }

    private function dependencyChecker(callable $ready): \app\common\form\dependency\FormSchemaDependencyChecker
    {
        return new \app\common\form\dependency\FormSchemaDependencyChecker(new \app\common\form\action\FormActionRegistry(),
            \app\common\form\dataSource\FormDataSourceRegistry::core([]), new \app\common\form\validation\FormAsyncValidatorRegistry([]),
            new \app\common\form\component\PluginFormComponentRegistry(), $ready);
    }

    public static function actions(): array
    {
        return [
            ['key' => 'approve', 'label' => '审核', 'locations' => ['row'], 'batch' => false, 'requiresConfirmation' => true, 'effect' => 'write',
                'parameters' => [['name' => 'id', 'type' => 'integer'], ['name' => 'note', 'type' => 'string']]],
            ['key' => 'mark_paid', 'label' => '标记已付', 'locations' => ['toolbar'], 'batch' => true, 'requiresConfirmation' => false, 'effect' => 'read', 'parameters' => []],
        ];
    }

    /** 已发布的业务模块快照（与生成控制器 productionBinding 对应）。 */
    private function setUpPublishedModule(FormSchema $schema): void
    {
        BusinessGenerationFixture::create();
        Db::schema()->table('form', static function (Blueprint $table): void {
            $table->string('form_key'); $table->integer('status')->default(1); $table->string('name')->default('');
            $table->string('table_name')->default(''); $table->string('connection')->default(''); $table->string('source_type')->default('');
        });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->string('schema_hash'); $table->json('schema_document');
        });
        Db::schema()->create('console_order', static function (Blueprint $table): void {
            $table->id(); $table->string('title'); $table->timestamps(); $table->softDeletes();
        });
        Db::table('form')->insert(['id' => 1, 'form_key' => 'console_order']);
        Db::table('business_module')->insert(['form_id' => 1, 'code' => 'console_order', 'lifecycle_status' => 'published', 'published_schema_hash' => $schema->hash(), 'published_schema_version' => 1, 'metadata' => '{}']);
        Db::table('form_schema_version')->insert(['form_id' => 1, 'version' => 1, 'schema_hash' => $schema->hash(), 'schema_document' => $schema->canonicalJson()]);
    }

    private function tearDownPublishedModule(): void
    {
        Db::schema()->dropIfExists('console_order');
        Db::schema()->dropIfExists('form_schema_version');
        BusinessGenerationFixture::drop();
    }

    /** 渲染出的动作类与注册清单按生成内容加载，返回注册定义。 */
    private function loadGenerated(array $files): array
    {
        $class = tempnam(sys_get_temp_dir(), 'funadmin-actions-'); $this->temporary[] = $class;
        file_put_contents($class, $files[self::ACTIONS]); require $class;
        $config = tempnam(sys_get_temp_dir(), 'funadmin-actions-config-'); $this->temporary[] = $config;
        file_put_contents($config, $files[self::CONFIG]);
        return ['registry' => require $config];
    }

    private function definition(string $key, ?array $actions): CrudDefinition
    {
        return (new FormCrudDefinitionFactory())->createFromSchema($this->schema($key, $actions), []);
    }

    private function schema(string $key, ?array $actions, string $connection = 'mysql', ?string $table = null, array $buttons = []): FormSchema
    {
        $list = ['tools' => ['refresh' => true]] + ($actions === null ? [] : ['customActions' => $actions]) + ($buttons === [] ? [] : ['buttons' => $buttons]);
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
            'schemaVersion' => 2, 'key' => $key, 'title' => '订单',
            'database' => ['connection' => $connection, 'table' => $table ?? 'fun_' . $key, 'source' => 'created'],
            'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                'database' => ['columnType' => 'varchar(120)', 'nullable' => true], 'list' => ['show' => true], 'children' => []]],
            'list' => $list,
        ]);
    }

    private function root(): string
    {
        if ($this->root === '') {
            $this->root = sys_get_temp_dir() . '/funadmin-custom-actions-' . bin2hex(random_bytes(8));
            mkdir($this->root, 0700, true);
        }
        return $this->root;
    }

    private function generator(): CrudGenerator
    {
        return new CrudGenerator($this->root(), tokens: new ConfirmationToken($this->root(), 'custom-action-test-secret'));
    }
}
