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
