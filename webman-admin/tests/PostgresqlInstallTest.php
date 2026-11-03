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

    public function test_cli_installs_from_standard_input_without_exposing_credentials(): void
    {
        $root = sys_get_temp_dir() . '/pg-cli-' . bin2hex(random_bytes(6));
        $schema = 'cli_' . bin2hex(random_bytes(5));
        mkdir($root . '/runtime', 0700, true);
        mkdir($root . '/public', 0700);
        symlink(base_path('database'), $root . '/database');
        symlink(config_path(), $root . '/config');
        copy(base_path('.env.example'), $root . '/.env.example');
        $input = ['db' => [
            'driver' => 'pgsql', 'schema' => $schema, 'sslmode' => 'disable',
            'host' => getenv('DB_HOST'), 'port' => '5432', 'database' => getenv('DB_DATABASE'),
            'username' => getenv('DB_USERNAME'), 'password' => getenv('DB_PASSWORD'), 'prefix' => '',
        ], 'admin' => ['username' => 'cli_admin', 'password' => 'cli-safe-password-123', 'repassword' => 'cli-safe-password-123', 'email' => 'cli@example.com']];
        $invoke = static function (string $command, string $stdin = '') use ($root): array {
            $code = 'define("BASE_PATH", ' . var_export($root, true) . '); require ' . var_export(base_path('bin/console.php'), true) . ';';
            $process = proc_open([PHP_BINARY, '-r', $code, $command], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            return [proc_close($process), $output];
        };
        try {
            [$status, $output] = $invoke('install', json_encode($input, JSON_THROW_ON_ERROR));
            self::assertSame(0, $status, $output);
            self::assertFileExists($root . '/public/install.lock');
            self::assertSame('pgsql', \Dotenv\Dotenv::parse(file_get_contents($root . '/.env'))['DB_CONNECTION']);
            self::assertStringNotContainsString($input['admin']['password'], $output);
            [$status, $output] = $invoke('install', json_encode($input, JSON_THROW_ON_ERROR));
            self::assertSame(1, $status);
            self::assertStringContainsString('系统已安装', $output);
            [$status, $output] = $invoke('migrate');
            self::assertSame(0, $status, $output);
            self::assertStringContainsString('最新版本', $output);
            [$status, $output] = $invoke('auth:prune');
            self::assertSame(0, $status, $output);
        } finally {
            Db::statement('DROP SCHEMA IF EXISTS "' . $schema . '" CASCADE');
            foreach (['.env', '.env.example', 'public/install.lock', 'runtime/install.mutex', 'database', 'config'] as $file) if (is_file($root . '/' . $file) || is_link($root . '/' . $file)) unlink($root . '/' . $file);
            rmdir($root . '/runtime'); rmdir($root . '/public'); rmdir($root);
        }
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
            foreach (['market_category', 'market_plugin', 'market_version', 'market_grant', 'market_token', 'market_order', 'market_payment_log', 'market_site', 'market_site_plugin', 'market_install_event', 'market_download'] as $table) self::assertTrue(Db::schema()->hasTable($table), '缺少市场运行时结构：' . $table);
            foreach (['nickname', 'motto', 'real_name', 'birthday', 'scores'] as $column) self::assertTrue(Db::schema()->hasColumn('member', $column), '会员资料列缺失：' . $column);
            $request = new \support\Request("POST /api/v3/auth/register HTTP/1.1\r\n\r\n");
            $request->connection = (new \ReflectionClass(\Workerman\Connection\TcpConnection::class))->newInstanceWithoutConstructor();
            $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
            \support\Context::set(\Webman\Http\Request::class, $request);
            $accounts = new \app\market\service\MarketAccountService();
            $memberId = $accounts->register(['username' => 'pgbuyer', 'email' => 'pgbuyer@example.com', 'password' => 'buyer-password-123', 'password_confirm' => 'buyer-password-123'], '127.0.0.' . random_int(10, 200));
            $tokens = (new \app\market\service\MarketTokenService())->login('pgbuyer', 'buyer-password-123');
            self::assertSame($memberId, (new \app\market\service\MarketTokenService())->memberId($tokens['access_token']));
            self::assertSame([], (new \app\market\service\MarketCatalogService())->categories());
            $member = \app\common\model\Member::findOrFail($memberId);
            $identityId = (int) Db::table('identity_member_link')->where('member_id', $memberId)->value('user_id');
            self::assertSame((string) $member->last_login, Db::table('identity_user')->where('id', $identityId)->value('last_login_at'));
            \support\Context::destroy();
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
            $listRequest = new \support\Request("GET /admin/development/business/modules?page=1&pageSize=20 HTTP/1.1\r\n\r\n");
            \support\Context::set(\Webman\Http\Request::class, $listRequest);
            $listing = (new \app\admin\development\service\BusinessModuleService())->listing(1, 20, '', '', '');
            \support\Context::destroy();
            self::assertSame(0, $listing['total']);
            self::assertSame([], $listing['list']);
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
            Db::table('identity_sso_config')->insert(['tenant_id' => 1, 'enabled' => 1, 'provider_mode' => 'identity_provider']);
            $query = http_build_query(['client_id' => $client['client_id'], 'redirect_uri' => 'https://example.com/callback', 'response_type' => 'code', 'scope' => 'openid', 'nonce' => 'nonce-123', 'state' => 'state-123', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('a', 43), true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256']);
            $response = (new \app\identity\controller\OAuth())->authorize(new \support\Request("GET /identity/authorize?$query HTTP/1.1\r\n\r\n"));
            self::assertSame(200, $response->getStatusCode(), $response->rawBody());
            self::assertArrayHasKey('transaction_id', json_decode($response->rawBody(), true));
            $transaction = json_decode($response->rawBody(), true)['transaction_id'];
            $request = new \support\Request("GET /identity/interaction?transaction_id=$transaction HTTP/1.1\r\n\r\n");
            $request->context['session'] = new \Workerman\Protocols\Http\Session(bin2hex(random_bytes(16)));
            \support\Context::set(\Webman\Http\Request::class, $request);
            self::assertSame(200, (new \app\identity\controller\Identity())->page($request)->getStatusCode());
            \support\Context::destroy();
            $catalog->publish(1, $application['id']);
            $decision = (new \app\identity\service\AuthorizationTransactionService())->decide($transaction, ['tenant_id' => 1, 'user_id' => $identityUser->id, 'auth_time' => time(), 'session_id' => str_repeat('s', 32)], true);
            self::assertTrue($decision['approved']);
            $record = (new \app\identity\service\AuthorizationTransactionService())->consumeCode($decision['code'], $client['id'], 'https://example.com/callback', str_repeat('a', 43));
            self::assertSame($identityUser->id, $record['user_id']);
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
