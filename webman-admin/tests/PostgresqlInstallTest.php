<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use app\common\service\install\InstallSupport;
use PHPUnit\Framework\TestCase;
use support\Db;

final class PostgresqlInstallTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL 测试启动器');
        self::assertMatchesRegularExpression('/\Awebman_pg_test_[a-f0-9]{6,12}\z/D', Db::connection()->getDatabaseName());
    }

    public function test_native_installation_resets_seed_sequences_and_creates_attachment_index(): void
    {
        $schema = 'install_' . bin2hex(random_bytes(5));
        $db = [
            'driver' => 'pgsql', 'schema' => $schema, 'sslmode' => 'disable',
            'host' => getenv('DB_HOST'), 'port' => '5432', 'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'), 'password' => getenv('DB_PASSWORD'), 'prefix' => 'custom_',
        ];
        InstallSupport::prepareDatabase($db, '5.7');
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $previous = $manager->getDefaultConnection();
        $name = 'pg_install_' . bin2hex(random_bytes(5));
        $config = \Illuminate\Container\Container::getInstance()['config'];
        $connections = $config['database.connections'];
        $connections[$name] = InstallSupport::databaseConfig($db, config('database'))['connections']['pgsql'];
        $config['database.connections'] = $connections;
        $manager->setDefaultConnection($name);
        try {
            $admin = ['username' => 'installer', 'password' => 'safe-install-123', 'repassword' => 'safe-install-123', 'email' => 'admin@example.com'];
            InstallSupport::installSchemaAndAdmin(base_path('database/migrations'), $admin);
            self::assertTrue(password_verify($admin['password'], Db::table('admin')->value('password')));
            foreach (['member_group', 'member_level', 'attach_group'] as $table) {
                self::assertGreaterThan(1, Db::table($table)->insertGetId(['name' => '自动编号']));
            }
            self::assertGreaterThan(1, Db::table('admin')->insertGetId(['username' => 'another', 'password' => 'hashed']));
            self::assertGreaterThan(1, Db::table('auth_group')->insertGetId(['name' => '测试角色', 'code' => 'test_role']));
            self::assertTrue(Db::schema()->hasIndex('attach', ['driver', 'storage_key']));
            foreach (['form', 'form_field', 'form_schema_version', 'form_publish_attempt', 'form_list_action_execution', 'business_module', 'crud_generation', 'generated_file_baseline', 'ai_conversation', 'ai_conversation_group', 'ai_message', 'ai_task', 'ai_task_event', 'ai_outbox', 'ai_tool_call', 'ai_approval', 'ai_change_set', 'ai_configuration_profile', 'ai_attachment', 'ai_stream_nonce', 'identity_tenant', 'identity_user', 'identity_admin_link', 'identity_credential', 'enterprise_application', 'oauth_client', 'scope', 'client_secret', 'client_grant', 'client_scope', 'redirect_uri', 'oauth_authorization', 'oauth_authorization_code', 'oauth_token', 'oidc_session', 'oidc_client_session', 'oidc_signing_key', 'backchannel_logout_delivery'] as $table) self::assertTrue(Db::schema()->hasTable($table), '缺少完整模块结构：' . $table);
            self::assertTrue(Db::schema()->hasForeignKey('oauth_token', ['tenant_id', 'client_id']));
            self::assertTrue(Db::schema()->hasForeignKey('ai_message', ['conversation_id']));
            self::assertTrue(Db::table('permission')->where('code', 'development.business:modules')->exists());
            self::assertTrue(Db::table('permission')->where('code', 'development.ai:conversationindex')->exists());
            self::assertTrue(Db::table('permission')->where('code', 'identity.enterpriseapplication:index')->exists());
            self::assertTrue(Db::table('admin_menu')->where('source_name', 'ai_development')->exists());
            self::assertTrue(Db::table('admin_menu')->where('source_name', 'business_development')->exists());
            $pdo = Db::connection()->getPdo();
            foreach ([
                'INSERT INTO custom_ai_message(conversation_id,sequence,role,content) VALUES (999999,1,\'user\',\'{}\')' => '23503',
                'INSERT INTO custom_application_assignment(tenant_id,application_id,subject_type,subject_id) VALUES (1,999999,\'all\',1)' => '23514',
            ] as $sql => $expected) {
                $pdo->beginTransaction();
                try { $pdo->exec($sql); self::fail('无效结构数据必须拒绝'); }
                catch (\PDOException $error) { self::assertSame($expected, $error->getCode()); }
                finally { $pdo->rollBack(); }
            }
            $store = new \app\admin\ai\repository\DatabaseAiConversationStore();
            $group = $store->createConversationGroup(['admin_id' => 1, 'name' => 'PG 分组']);
            $conversation = $store->createConversation(['admin_id' => 1, 'uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'group_id' => $group['id'], 'title' => 'PG 对话']);
            self::assertSame('PG 对话', $conversation['title']);
            $message = $store->appendMessage($conversation['id'], ['role' => 'user', 'content' => ['text' => '中文消息']]);
            self::assertSame(['text' => '中文消息'], $message['content']);
            $operations = new \app\common\service\identity\IdentityOperationsService();
            self::assertTrue($operations->health(1)['healthy']);
            $identityUser = (new \app\common\service\identity\IdentityUserService())->create(1, 'admin', 1, ['username' => 'installer', 'status' => 1]);
            $identityUser = (new \app\common\service\identity\IdentityUserService())->update(1, $identityUser->id, ['username' => 'installer', 'display_name' => '已更新', 'status' => 1]);
            self::assertSame('已更新', $identityUser->fresh()->display_name);
            $credential = new \app\common\service\identity\IdentityCredentialService();
            $credential->syncHash(1, $identityUser->id, password_hash('identity-test-123', PASSWORD_DEFAULT));
            self::assertTrue($credential->verifyAndUpgrade(1, $identityUser->id, 'identity-test-123'));
            $catalog = new \app\common\service\identity\ApplicationCatalogService();
            $application = $catalog->save(1, ['code'=>'pg_app','name'=>'PG 应用','runtime_type'=>'standalone','launch_url'=>'https://example.com/app'], null);
            $clients = new \app\common\service\identity\OAuthClientService();
            $client = $clients->save(1, $application['id'], ['name'=>'PG Client','clientType'=>'public','grants'=>['authorization_code'],'scopes'=>['openid']]);
            $redirects = new \app\common\service\identity\RedirectUriService();
            self::assertCount(1, $redirects->replace(1, $client['id'], [['uri'=>'https://example.com/callback']]));
            self::assertTrue($redirects->matches(1, $client['id'], 'https://example.com/callback', 'authorization_callback'));
            self::assertSame(['openid'], $clients->detail(1, $client['id'])['scopes']);
            self::assertSame('locked', $operations->withLock(1, 'test', static fn () => 'locked'));
            $form = \app\admin\form\model\Form::create(['form_key' => 'pg_definition', 'name' => 'PG 表单', 'table_name' => 'custom_pg_definition', 'connection' => $name]);
            $builder = \app\common\form\builder\Form::make('pg_definition')->table('custom_pg_definition', $name);
            $builder->input('title', '标题');
            $compiled = $builder->compile();
            $repository = new \app\admin\form\repository\FormSchemaRepository();
            $version = $repository->saveVersion($form->id, $compiled->document(), 'designer', 'admin:1');
            self::assertSame($compiled->hash(), $version->schema_hash);
            self::assertSame($compiled->hash(), $repository->draft($form->id)->hash());
            self::assertSame([], (new MigrationService())->run());
        } finally {
            while (Db::connection()->transactionLevel() > 0) Db::connection()->rollBack();
            Db::statement('DROP SCHEMA "' . $schema . '" CASCADE');
            $manager->setDefaultConnection($previous);
            $manager->purge($name);
        }
    }
}
