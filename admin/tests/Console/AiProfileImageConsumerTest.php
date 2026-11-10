<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\job\AiAgentJob;
use app\admin\ai\repository\DatabaseAiAttachmentRepository;
use app\admin\ai\repository\DatabaseAiConversationStore;
use app\admin\ai\repository\DatabaseAiProfileRepository;
use app\admin\ai\service\AgentSandboxManager;
use app\admin\ai\service\AiAgentOrchestrator;
use app\admin\ai\service\AiAttachmentService;
use app\admin\ai\service\AiAttachmentStorage;
use app\admin\ai\service\AiConfigurationProfileService;
use app\admin\ai\service\AiProfileSecret;
use app\common\ai\provider\OpenAiCompatibleGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

class AiProfileImageConsumerTest extends AiSandboxConsumerExecutionTest
{
    private string $privateRoot;

    protected function setUp(): void
    {
        parent::setUp();
        // macOS 的临时目录位于 /var -> /private/var 符号链接下，附件存储会拒绝符号链接路径。
        $this->privateRoot = realpath(sys_get_temp_dir()) . '/funadmin-ai-image-' . bin2hex(random_bytes(8));
        mkdir($this->privateRoot . '/project', 0700, true);
        file_put_contents($this->privateRoot . '/project/example.txt', 'snapshot');
        Db::schema()->table('ai_task', static function (Blueprint $table): void { $table->string('model')->nullable(); $table->string('provider')->nullable(); });
        Db::schema()->create('ai_configuration_profile', static function (Blueprint $table): void {
            $table->id(); $table->integer('admin_id'); $table->string('name'); $table->json('configuration');
            $table->text('secret_ciphertext')->nullable(); $table->boolean('is_default')->default(false); $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('ai_attachment', static function (Blueprint $table): void {
            $table->id(); $table->integer('admin_id'); $table->integer('conversation_id'); $table->integer('message_id')->nullable();
            foreach (['kind', 'name', 'mime', 'sha256', 'storage_path'] as $name) $table->string($name);
            $table->integer('size'); $table->integer('width')->nullable(); $table->integer('height')->nullable();
            $table->dateTime('expires_at'); $table->timestamps(); $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        foreach (['ai_configuration_profile', 'ai_attachment'] as $table) Db::schema()->dropIfExists($table);
        parent::tearDown();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->privateRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->privateRoot);
    }

    public function test_default_job_assembly_resolves_frozen_profile_current_key_and_private_image_before_provider_request(): void
    {
        $profiles = new AiConfigurationProfileService(new DatabaseAiProfileRepository(), new AiProfileSecret(\Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString()));
        $profile = $profiles->create(7, ['name' => '图片配置', 'provider' => 'local', 'protocol' => 'openai-chat', 'base_url' => 'https://8.8.8.8/v1', 'model' => 'image-model', 'api_key' => 'original-key',
            'max_input_tokens' => 100000, 'max_output_tokens' => 100, 'context_window' => 200000,
            'model_capabilities' => [['model' => 'image-model', 'image_input' => true, 'context_window' => 200000, 'max_output_tokens' => 1000]]]);
        $snapshot = $profiles->snapshot(7, $profile['id']);
        $profiles->update(7, $profile['id'], ['model' => 'new-model', 'api_key' => 'rotated-key']);
        $attachments = new AiAttachmentService(new DatabaseAiAttachmentRepository(), new AiAttachmentStorage($this->privateRoot . '/attachments'));
        $image = imagecreatetruecolor(2, 2); ob_start(); imagepng($image); $png = ob_get_clean();
        $uploaded = $attachments->upload(1, 7, 'input.png', $png);
        $store = new DatabaseAiConversationStore();
        $message = $attachments->append(1, 7, [['type' => 'text', 'text' => '描述图片'], ['type' => 'attachment', 'attachment_id' => $uploaded['id']]], $store);
        $history = $attachments->modelHistory(1, 7, $message);
        $input = ['admin_id' => 7, 'profile_snapshot' => $snapshot, 'messages' => [['role' => 'user', 'content' => $history]]];
        \app\admin\ai\model\AiTask::query()->findOrFail(1)->fill(['input' => $input, 'model' => 'image-model', 'provider' => 'local'])->save();
        $handler = new MockHandler([new Response(200, [], json_encode(['model' => 'image-model', 'choices' => [['message' => ['content' => '图片已解析'], 'finish_reason' => 'stop']], 'usage' => ['total_tokens' => 3]], JSON_THROW_ON_ERROR))]);
        $tools = new class implements \app\admin\ai\contract\AiToolExecutor { public function execute(array $call): array { throw new \RuntimeException('不应调用工具'); } };
        $runner = new SandboxProcessFixture($this->privateRoot);
        $sandbox = new AgentSandboxManager($runner, $this->privateRoot . '/project', $this->privateRoot . '/private', ['image' => 'test@sha256:' . str_repeat('a', 64)]);
        $factory = static fn (array $config): AiAgentOrchestrator => new AiAgentOrchestrator(new OpenAiCompatibleGateway(new Client(['handler' => HandlerStack::create($handler)]), $config), $tools);
        $job = new AiAgentJob($store, sandboxManager: $sandbox, orchestratorFactory: $factory, profiles: $profiles, attachments: $attachments);
        $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']);
        self::assertSame('succeeded', Db::table('ai_task')->value('status'));
        self::assertSame('cleaned', Db::table('ai_task')->value('sandbox_status'));
        $request = $handler->getLastRequest();
        self::assertSame('Bearer rotated-key', $request->getHeaderLine('Authorization'));
        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('image-model', $payload['model']);
        self::assertStringStartsWith('data:image/png;base64,', $payload['messages'][0]['content'][1]['image_url']['url']);
        self::assertStringNotContainsString('rotated-key', json_encode($store->task(1), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('base64,', json_encode($store->events(1, 0, 100), JSON_THROW_ON_ERROR));
    }
}
