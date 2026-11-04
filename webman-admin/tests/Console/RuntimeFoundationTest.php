<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\model\AiTask;
use app\common\model\identity\IdentityTenant;
use app\common\model\identity\OAuthToken;
use app\common\service\AbstractService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class RuntimeFoundationTest extends TestCase
{
    public function test_default_ai_provider_transport_disables_environment_proxies_and_keeps_body_middleware(): void
    {
        $sandbox = new \app\admin\ai\service\AgentSandboxManager(new \app\admin\ai\infrastructure\NativeDockerProcessRunner(), base_path(), sys_get_temp_dir() . '/unused-ai-default', []);
        $job = new \app\admin\ai\job\AiAgentJob(sandboxManager: $sandbox);
        $factory = (new \ReflectionProperty($job, 'orchestratorFactory'))->getValue($job);
        $orchestrator = $factory(['protocol' => 'openai-chat', 'base_url' => 'https://8.8.8.8/v1', 'api_key' => '', 'model' => 'local-test']);
        $gateway = (new \ReflectionProperty($orchestrator, 'provider'))->getValue($orchestrator);
        $client = (new \ReflectionProperty($gateway, 'client'))->getValue($gateway);
        self::assertSame('', $client->getConfig('proxy'), '环境代理不得绕过已校验的 DNS 固定地址');
        self::assertInstanceOf(\GuzzleHttp\HandlerStack::class, $client->getConfig('handler'));
        self::assertStringContainsString('prepare_body', (string) $client->getConfig('handler'));
        $handler = (new \ReflectionProperty($client->getConfig('handler'), 'handler'))->getValue($client->getConfig('handler'));
        self::assertInstanceOf(\GuzzleHttp\Handler\CurlHandler::class, $handler);
    }

    public function test_ai_and_crud_runtime_closures_do_not_reference_think_configuration_runtime(): void
    {
        foreach ([
            base_path('app/admin/ai/service/AiConversationService.php'),
            base_path('app/common/crud/LangPackTranslator.php'),
            base_path('app/admin/ai/service/AiProviderSettingsService.php'),
        ] as $file) {
            self::assertStringNotContainsString('think\\facade\\Config', (string) file_get_contents($file), $file);
        }
    }

    public function test_application_request_is_a_native_webman_request(): void
    {
        $request = new \app\Request("GET /probe?key=value HTTP/1.1\r\nHost: localhost\r\n\r\n");
        self::assertInstanceOf(\support\Request::class, $request);
        self::assertSame('value', $request->input('key'));
    }

    public function test_default_form_observability_writes_native_logs_without_sensitive_payload(): void
    {
        $handler = new \Monolog\Handler\TestHandler();
        $logger = \support\Log::channel();
        $logger->pushHandler($handler);
        try {
            $result = (new \app\common\form\observability\FormObservability())->measure(['formKey' => 'native', 'password' => 'must-not-be-logged'], static fn () => 'ok');
            self::assertSame('ok', $result);
            $records = $handler->getRecords();
            self::assertCount(1, $records);
            self::assertSame('form.event', $records[0]->message);
            self::assertSame('native', $records[0]->context['formKey']);
            self::assertArrayNotHasKey('password', $records[0]->context);
        } finally {
            $logger->popHandler();
        }
    }

    public function test_oss_hook_uses_native_events(): void
    {
        $id = \Webman\Event\Event::on('OssUpload', static fn (array $data): array => $data);
        try {
            self::assertSame(['osspath' => 'remote/key', 'localpath' => '/tmp/local', 'save' => false], (new \app\common\service\storage\OssService())->uploads('fixture', 'remote/key', '/tmp/local', false));
        } finally {
            \Webman\Event\Event::off('OssUpload', $id);
        }
    }

    public function test_file_helper_writes_source_without_template_cache_header(): void
    {
        $path = tempnam(runtime_path(), 'source-output-');
        try {
            \app\common\helper\FileHelper::filePutContents('<?php return 42;', $path, 0);
            self::assertSame('<?php return 42;', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function test_redis_service_accepts_native_connection_without_think_container(): void
    {
        $connection = new \Illuminate\Redis\Connections\PhpRedisConnection(new class {
            public function get(string $key): string { return 'native:' . $key; }
            public function ping(): string { return 'PONG'; }
        });
        $service = new \app\common\service\cache\PredisService($connection);
        self::assertSame('native:fixture', $service->get('fixture'));
        self::assertSame('PONG', $service->ping());
        unset($service);
        self::assertSame('PONG', $connection->ping());
    }

    public function test_retired_http_traits_and_model_authentication_are_not_runtime_entry_points(): void
    {
        self::assertFalse(trait_exists(\app\common\traits\Jump::class));
        self::assertFalse(trait_exists(\app\common\traits\Apis::class));
        foreach (['login', 'reg', 'sendEmail'] as $method) {
            self::assertFalse(method_exists(\app\common\model\Member::class, $method));
        }
        self::assertTrue(method_exists(\app\market\service\MarketAccountService::class, 'register'));
        self::assertTrue(method_exists(\app\common\service\member\MemberAuthService::class, 'authenticate'));
    }

    public function test_service_instance_uses_webman_container(): void
    {
        $service = ConsoleFoundationService::instance(label: 'console');
        self::assertInstanceOf(ConsoleFoundationService::class, $service);
        self::assertSame('console', $service->label);
    }

    public function test_tenant_queries_use_eloquent_and_reject_invalid_tenants(): void
    {
        self::assertInstanceOf(Builder::class, IdentityTenant::forTenant(7));
        self::assertInstanceOf(Builder::class, OAuthToken::forTenant(7));
        self::assertSame([7], OAuthToken::forTenant(7)->getBindings());
        self::assertStringContainsString('tenant_id', OAuthToken::forTenant(7)->toSql());
        $this->expectException(\InvalidArgumentException::class);
        OAuthToken::forTenant(0);
    }

    public function test_models_use_connection_prefix_without_changing_logical_tables(): void
    {
        $connection = Db::connection();
        self::assertSame('sqlite', $connection->getDriverName());
        self::assertSame(':memory:', $connection->getDatabaseName());
        $original = $connection->getTablePrefix();
        try {
            foreach (['fun_', 'custom_', ''] as $prefix) {
                $connection->setTablePrefix($prefix);
                foreach ([AiTask::class => 'ai_task', OAuthToken::class => 'oauth_token', IdentityTenant::class => 'identity_tenant'] as $class => $table) {
                    $model = new $class();
                    self::assertSame($table, $model->getTable());
                    self::assertStringContainsString('"' . $prefix . $table . '"', $model->newQuery()->toSql());
                }
            }
        } finally {
            $connection->setTablePrefix($original);
        }
    }

    public function test_ai_task_round_trips_json_and_respects_soft_deletes(): void
    {
        self::assertSame('sqlite', Db::connection()->getDriverName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->dropIfExists('ai_task');
        Db::schema()->create('ai_task', static function (Blueprint $table): void {
            $table->id();
            foreach (['input', 'output', 'error', 'usage', 'test_result'] as $column) {
                $table->json($column)->nullable();
            }
            $table->timestamps();
            $table->softDeletes();
        });
        try {
            $task = AiTask::create(['input' => ['admin_id' => 7], 'output' => ['messages' => []]]);
            self::assertSame(['admin_id' => 7], $task->fresh()->input);
            self::assertSame(['messages' => []], $task->fresh()->output);
            $task->delete();
            self::assertNull(AiTask::find($task->id));
            self::assertNotNull(AiTask::withTrashed()->find($task->id));
        } finally {
            Db::schema()->dropIfExists('ai_task');
        }
    }
}

final class ConsoleFoundationService extends AbstractService
{
    public function __construct(public readonly string $label = 'default')
    {
        parent::__construct();
    }
}
