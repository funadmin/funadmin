<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use support\annotation\route\Route as RouteAttribute;
use support\annotation\route\RouteGroup;
use Webman\Finder\ControllerFinder;
use Webman\Route;

final class BusinessModuleLayoutTest extends TestCase
{
    public function test_webman_console_discovers_all_business_module_commands(): void
    {
        [$status, $stdout, $stderr] = $this->runConsole(['list', '--format=json']);
        self::assertSame(0, $status, $stderr);
        $commands = array_column(json_decode($stdout, true, 512, JSON_THROW_ON_ERROR)['commands'], null, 'name');
        foreach ([
            'ai:change-set-recover', 'ai:outbox-dispatch', 'ai:sandbox-cleanup',
            'business:generation-recover', 'crud:generate', 'crud:inspect', 'crud:preview', 'crud:validate',
            'identity:cleanup', 'identity:health', 'identity:logout-deliveries:work', 'identity:signing-key',
            'mcp', 'plugin:activation-cache', 'plugin:crud-generate',
            'plugin:crud-preview', 'plugin:make', 'plugin:make-crud', 'plugin:package',
            'plugin:publication-recover', 'plugin:validate', 'upgrade:recover-stale',
        ] as $name) {
            self::assertArrayHasKey($name, $commands, '业务命令未接入 webman/console：' . $name);
            self::assertNotSame('', $commands[$name]['description']);
        }
        self::assertTrue($commands['identity:cleanup']['definition']['options']['dry-run']['accept_value'] === false);
        self::assertSame('1000', $commands['identity:cleanup']['definition']['options']['limit']['default']);
        self::assertTrue($commands['crud:generate']['definition']['arguments']['definition']['is_required']);
        self::assertArrayHasKey('confirm-token-file', $commands['crud:generate']['definition']['options']);
        self::assertArrayHasKey('start', $commands);
        self::assertArrayHasKey('make:command', $commands);
    }

    public function test_webman_console_renders_every_business_command_help_without_execution(): void
    {
        foreach (glob(__DIR__ . '/../app/admin/*/command/*.php') as $file) {
            $source = file_get_contents($file);
            if (!preg_match('/->setName\(\'([^\']+)\'\)/', $source, $matches)) {
                continue;
            }
            [$status, $stdout, $stderr] = $this->runConsole([$matches[1], '--help']);
            self::assertSame(0, $status, $matches[1] . ': ' . $stderr . $stdout);
            self::assertStringContainsString($matches[1], $stdout);
        }
    }

    public function test_webman_console_returns_a_readable_error_for_missing_definition(): void
    {
        [$status, $stdout, $stderr] = $this->runConsole(['crud:validate', '/nonexistent/funadmin-definition.json']);
        self::assertSame(1, $status);
        self::assertStringContainsString('CRUD Definition 文件不存在', $stdout . $stderr);
        self::assertStringNotContainsString('undefined method', $stdout . $stderr);
    }

    public function test_webman_plugin_make_preview_does_not_write_files(): void
    {
        $pluginPath = __DIR__ . '/../plugins/consolepreview';
        self::assertDirectoryDoesNotExist($pluginPath);
        [$status, $stdout, $stderr] = $this->runConsole(['plugin:make', 'consolepreview', '--preview', '--admin']);
        self::assertSame(0, $status, $stderr . $stdout);
        $plan = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($plan);
        self::assertNotEmpty($plan);
        self::assertDirectoryDoesNotExist($pluginPath);
    }

    private function runConsole(array $arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../webman', '--no-ansi', '--no-interaction', ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__)
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $stdout, $stderr];
    }

    public function test_running_http_controllers_live_inside_their_business_modules(): void
    {
        $controllers = [
            'authentication' => ['AdminAuth', 'ApiAuth'],
            'authorization' => ['Management'],
        ];
        foreach ($controllers as $module => $names) {
            foreach ($names as $name) {
                $class = 'app\\admin\\' . $module . '\\controller\\' . $name;
                self::assertTrue(class_exists($class), '控制器应归入业务模块：' . $class);
                self::assertSame(
                    realpath(__DIR__ . '/../app/admin/' . $module . '/controller/' . $name . '.php'),
                    (new ReflectionClass($class))->getFileName()
                );
                self::assertFalse(class_exists('app\\admin\\controller\\' . $module . '\\' . $name));
                self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/' . $module . '/' . $name . '.php');
            }
        }
    }

    public function test_ai_commands_live_inside_the_ai_module_without_changing_command_names(): void
    {
        foreach ([
            'AiChangeSetRecover' => 'ai:change-set-recover',
            'AiOutboxDispatch' => 'ai:outbox-dispatch',
            'AiSandboxCleanup' => 'ai:sandbox-cleanup',
        ] as $class => $command) {
            $file = __DIR__ . '/../app/admin/ai/command/' . $class . '.php';
            self::assertFileExists($file);
            $tokens = token_get_all(file_get_contents($file), TOKEN_PARSE);
            $namespaces = array_values(array_filter($tokens, static fn ($token): bool => is_array($token) && $token[0] === T_NAME_QUALIFIED));
            self::assertSame('app\\admin\\ai\\command', $namespaces[0][1]);
            self::assertStringContainsString("->setName('" . $command . "')", file_get_contents($file));
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/command/' . $class . '.php');
        }
    }

    public function test_identity_commands_live_inside_the_identity_module_without_changing_options(): void
    {
        foreach ([
            'IdentityCleanup' => ['identity:cleanup', 'tenant', 'limit', 'audit-retention-days', 'dry-run'],
            'IdentityHealth' => ['identity:health', 'tenant', 'secret-warning-days'],
            'IdentityLogoutDeliveriesWork' => ['identity:logout-deliveries:work', 'once', 'tenant', 'limit', 'dry-run'],
            'IdentitySigningKey' => ['identity:signing-key', 'tenant', 'action', 'limit', 'publish-window', 'dry-run'],
        ] as $class => $contracts) {
            $file = __DIR__ . '/../app/admin/identity/command/' . $class . '.php';
            self::assertFileExists($file);
            $source = file_get_contents($file);
            $tokens = token_get_all($source, TOKEN_PARSE);
            $namespaces = array_values(array_filter($tokens, static fn ($token): bool => is_array($token) && $token[0] === T_NAME_QUALIFIED));
            self::assertSame('app\\admin\\identity\\command', $namespaces[0][1]);
            $command = array_shift($contracts);
            self::assertStringContainsString("->setName('" . $command . "')", $source);
            foreach ($contracts as $option) {
                self::assertStringContainsString("->addOption('" . $option . "'", $source);
            }
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/command/' . $class . '.php');
        }
    }

    public function test_development_commands_and_their_shared_trait_have_one_module(): void
    {
        foreach ([
            'CrudGenerate' => 'crud:generate',
            'CrudInspect' => 'crud:inspect',
            'CrudPreview' => 'crud:preview',
            'CrudValidate' => 'crud:validate',
            'BusinessGenerationRecover' => 'business:generation-recover',
            'CrudCommandSupport' => null,
        ] as $class => $command) {
            $file = __DIR__ . '/../app/admin/development/command/' . $class . '.php';
            self::assertFileExists($file);
            $source = file_get_contents($file);
            token_get_all($source, TOKEN_PARSE);
            self::assertStringContainsString('namespace app\\admin\\development\\command;', $source);
            if ($command !== null) {
                self::assertStringContainsString("->setName('" . $command . "')", $source);
            }
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/command/' . $class . '.php');
        }
        self::assertTrue(trait_exists('app\\admin\\development\\command\\CrudCommandSupport'));
    }

    public function test_plugin_commands_import_the_relocated_shared_trait(): void
    {
        foreach (['PluginCrudGenerate', 'PluginCrudPreview'] as $class) {
            $file = __DIR__ . '/../app/admin/plugin/command/' . $class . '.php';
            self::assertFileExists($file);
            $source = file_get_contents($file);
            self::assertStringContainsString('use app\\admin\\development\\command\\CrudCommandSupport;', $source);
        }
    }

    public function test_remaining_commands_are_grouped_by_business_module(): void
    {
        foreach ([
            'plugin' => [
                'PluginActivationCacheRebuild' => 'plugin:activation-cache',
                'PluginCrudGenerate' => 'plugin:crud-generate',
                'PluginCrudPreview' => 'plugin:crud-preview',
                'PluginMake' => 'plugin:make',
                'PluginMakeCrud' => 'plugin:make-crud',
                'PluginPackage' => 'plugin:package',
                'PluginPublicationRecover' => 'plugin:publication-recover',
                'PluginValidate' => 'plugin:validate',
            ],
            'system' => ['UpgradeRecover' => 'upgrade:recover-stale'],
            'common' => ['McpServer' => 'mcp'],
        ] as $module => $commands) {
            foreach ($commands as $class => $command) {
                $file = __DIR__ . '/../app/admin/' . $module . '/command/' . $class . '.php';
                self::assertFileExists($file);
                $source = file_get_contents($file);
                token_get_all($source, TOKEN_PARSE);
                self::assertStringContainsString('namespace app\\admin\\' . $module . '\\command;', $source);
                self::assertStringContainsString("->setName('" . $command . "')", $source);
                self::assertFileDoesNotExist(__DIR__ . '/../app/admin/command/' . $class . '.php');
            }
        }
    }

    public function test_member_definitions_move_to_system_without_changing_compiled_protocols(): void
    {
        $options = ['groups' => [['id' => 2, 'name' => 'G']], 'levels' => [['id' => 3, 'name' => 'L']], 'tags' => [['id' => 4, 'name' => 'T']]];
        foreach ([
            'zh-cn' => ['MemberFormDefinition' => '9b380e66154489471c0d715d73077ad030fe80a67a8618e0ea539dc871598461', 'MemberPageDefinition' => '656d1262185d68761e11f2d8ae7db4e5b6b51307561d515fc1f578341a50ced5'],
            'en-us' => ['MemberFormDefinition' => '06e93976c5ee265d9c90229d5701543b5ded99f213e6a3652134446f2e811f69', 'MemberPageDefinition' => 'cc13346e8d4df7d76772e1f022498aa7a1f2446d9d882b8da072065c6f1dada7'],
        ] as $locale => $definitions) {
            foreach ($definitions as $name => $hash) {
                $class = 'app\\admin\\system\\service\\' . $name;
                self::assertTrue(class_exists($class));
                self::assertSame($hash, hash('sha256', json_encode($class::build($options, $locale), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
                self::assertFileDoesNotExist(__DIR__ . '/../app/admin/service/' . $name . '.php');
            }
        }
    }

    public function test_profile_controller_is_grouped_with_authentication_and_keeps_route_attributes(): void
    {
        $file = __DIR__ . '/../app/admin/authentication/controller/AdminProfile.php';
        self::assertFileExists($file);
        $source = file_get_contents($file);
        token_get_all($source, TOKEN_PARSE);
        self::assertStringContainsString('namespace app\\admin\\authentication\\controller;', $source);
        foreach (["#[Group('profile')]", "#[Get('')]", "#[Put('')]", "#[Post('password')]"] as $route) {
            self::assertStringContainsString($route, $source);
        }
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/authentication/AdminProfile.php');
    }

    public function test_identity_audit_and_oidc_controllers_keep_their_public_route_attributes(): void
    {
        foreach ([
            'IdentityAudit' => ["#[Group('identity/audit')]", "#[Get('')]"],
            'OidcSession' => ["#[Group('identity/oidc-sessions')]", "#[Post(':id/revoke')]", "#[Get('deliveries')]", "#[Post('deliveries/:id/retry')]"],
            'OidcSigningKey' => ["#[Group('identity/signing-keys')]", "#[Post('rotate')]"],
        ] as $class => $routes) {
            $file = __DIR__ . '/../app/admin/identity/controller/' . $class . '.php';
            self::assertFileExists($file);
            $source = file_get_contents($file);
            token_get_all($source, TOKEN_PARSE);
            self::assertStringContainsString('namespace app\\admin\\identity\\controller;', $source);
            foreach ($routes as $route) {
                self::assertStringContainsString($route, $source);
            }
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/identity/' . $class . '.php');
        }
    }

    public function test_remaining_identity_controllers_preserve_the_copied_business_implementation(): void
    {
        foreach ([
            'EnterpriseApplication' => 'identity/applications',
            'IdentityUser' => 'identity/users',
            'OAuthClient' => 'identity/oauth-clients',
            'ScopeClaim' => 'identity/scopes',
            'SsoConfiguration' => 'identity/sso',
        ] as $class => $group) {
            $file = __DIR__ . '/../app/admin/identity/controller/' . $class . '.php';
            self::assertFileExists($file);
            $source = file_get_contents($file);
            token_get_all($source, TOKEN_PARSE);
            self::assertStringContainsString('namespace app\\admin\\identity\\controller;', $source);
            self::assertStringContainsString("#[Group('" . $group . "')]", $source);
            self::assertStringContainsString('CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class', $source);
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/identity/' . $class . '.php');
        }
    }

    public function test_authorization_and_plugin_platform_controllers_have_explicit_module_ownership(): void
    {
        foreach ([
            'app\\admin\\authorization\\controller\\SystemAdmin' => 'authorization/controller/SystemAdmin.php',
            'app\\admin\\authorization\\controller\\SystemDepartment' => 'authorization/controller/SystemDepartment.php',
            'app\\admin\\plugin\\controller\\DevPlugin' => 'plugin/controller/DevPlugin.php',
            'app\\admin\\plugin\\controller\\SystemPlugin' => 'plugin/controller/SystemPlugin.php',
        ] as $class => $relativePath) {
            self::assertTrue(class_exists($class));
            self::assertSame(realpath(__DIR__ . '/../app/admin/' . $relativePath), (new ReflectionClass($class))->getFileName());
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/' . ($class === 'app\\admin\\authorization\\controller\\SystemAdmin' ? 'system/SystemAdmin.php' : ($class === 'app\\admin\\authorization\\controller\\SystemDepartment' ? 'system/SystemDepartment.php' : 'plugin/' . basename($relativePath))));
        }
    }

    public function test_member_association_models_live_in_system_with_unchanged_table_names(): void
    {
        foreach (['MemberGroupRelation' => 'member_group_relation', 'MemberTagRelation' => 'member_tag_relation'] as $name => $table) {
            $class = 'app\\admin\\system\\model\\' . $name;
            self::assertTrue(class_exists($class));
            self::assertSame($table, (new $class())->getTable());
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/' . $name . '.php');
            self::assertFalse(class_exists('app\\admin\\model\\' . $name));
        }
        $member = file_get_contents(__DIR__ . '/../app/admin/system/model/Member.php');
        foreach (['MemberGroupRelation', 'MemberTagRelation'] as $name) {
            self::assertStringContainsString('use app\\admin\\system\\model\\' . $name . ';', $member);
        }
    }

    public function test_member_group_and_level_models_keep_logical_tables_and_soft_delete_scope(): void
    {
        foreach (['MemberGroup' => 'member_groups', 'MemberLevel' => 'member_levels'] as $name => $table) {
            $class = 'app\\admin\\system\\model\\' . $name;
            self::assertTrue(class_exists($class));
            $model = new $class();
            self::assertSame($table, $model->getTable());
            self::assertStringContainsString('deleted_at', $model->newQuery()->toSql());
            self::assertStringNotContainsString('deleted_at', $model->newQuery()->withTrashed()->toSql());
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/' . $name . '.php');
            self::assertFalse(class_exists('app\\admin\\model\\' . $name));
        }
    }

    public function test_member_model_is_in_system_and_keeps_its_implicit_table_and_validation(): void
    {
        $class = 'app\\admin\\system\\model\\Member';
        self::assertTrue(class_exists($class));
        self::assertSame('members', (new $class())->getTable());
        self::assertSame('用户名长度必须为 2 至 80 个字符', $class::validateAttributes(['username' => 'a']));
        self::assertSame('用户名已存在', $class::duplicateError(new \RuntimeException('1062 Duplicate entry username')));
        self::assertNull($class::duplicateError(new \RuntimeException('not a duplicate')));
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/Member.php');
        self::assertFalse(class_exists('app\\admin\\model\\Member'));
    }

    public function test_log_attachment_and_department_models_have_explicit_module_ownership(): void
    {
        foreach ([
            'system' => ['AdminLog' => 'admin_log', 'AttachGroup' => 'attach_groups'],
            'authorization' => ['Department' => 'department'],
        ] as $module => $models) {
            foreach ($models as $name => $table) {
                $class = 'app\\admin\\' . $module . '\\model\\' . $name;
                self::assertTrue(class_exists($class));
                self::assertSame($table, (new $class())->getTable());
                self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/' . $name . '.php');
            }
        }
        $department = 'app\\admin\\authorization\\model\\Department';
        self::assertSame('部门名称不能为空且不能超过 100 个字符', $department::validateAttributes([]));
        self::assertSame('邮箱格式不正确', $department::validateAttributes(['name' => '部门', 'email' => 'bad']));
        self::assertNull($department::validateAttributes(['name' => '部门', 'email' => 'valid@example.test']));
    }

    public function test_member_tag_source_is_owned_by_system_without_adding_runtime_adapters(): void
    {
        $file = __DIR__ . '/../app/admin/system/model/MemberTag.php';
        self::assertFileExists($file);
        $source = file_get_contents($file);
        token_get_all($source, TOKEN_PARSE);
        self::assertStringContainsString('namespace app\\admin\\system\\model;', $source);
        self::assertStringContainsString("protected \$name = 'member_tag';", $source);
        self::assertStringContainsString('use think\\model\\concern\\SoftDelete;', $source);
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/MemberTag.php');
    }



    public function test_plugin_example_business_layers_live_under_business_code(): void
    {
        foreach ([
            'controller/Index.php' => 'app\\admin\\plugin\\business\\example\\controller\\Index',
            'controller/TestController.php' => 'app\\admin\\plugin\\business\\example\\controller\\TestController',
            'model/Test.php' => 'app\\admin\\plugin\\business\\example\\model\\Test',
            'service/TestService.php' => 'app\\admin\\plugin\\business\\example\\service\\TestService',
        ] as $relativePath => $class) {
            self::assertTrue(class_exists($class), '插件业务类应归入 plugin/business：' . $class);
            self::assertSame(realpath(__DIR__ . '/../app/admin/plugin/business/example/' . $relativePath), (new ReflectionClass($class))->getFileName());
        }
        $validate = file_get_contents(__DIR__ . '/../app/admin/plugin/business/example/validate/TestValidate.php');
        token_get_all($validate, TOKEN_PARSE);
        self::assertStringContainsString('namespace app\\admin\\plugin\\business\\example\\validate;', $validate);

        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/plugin/example/Index.php');
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/plugin/example/TestController.php');
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/plugin/example/Test.php');
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/service/plugin/example/TestService.php');
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/validate/plugin/example/TestValidate.php');
    }

    public function test_plugin_market_controllers_live_under_business_code(): void
    {
        foreach (['CategoryController', 'GrantController', 'OrderController', 'PluginController', 'SettingController', 'StatController'] as $class) {
            $fqcn = 'app\\admin\\plugin\\business\\market\\controller\\' . $class;
            self::assertTrue(class_exists($fqcn), '插件市场控制器应归入 plugin/business：' . $fqcn);
            self::assertSame(realpath(__DIR__ . '/../app/admin/plugin/business/market/controller/' . $class . '.php'), (new ReflectionClass($fqcn))->getFileName());
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/plugin/market/' . $class . '.php');
        }
    }

    public function test_authorization_registry_and_system_upgrade_services_have_module_ownership(): void
    {
        foreach ([
            'app\\admin\\authorization\\service\\ResourceRegistryService' => 'authorization/service/ResourceRegistryService.php',
            'app\\admin\\system\\service\\UpgradeDatabaseBackup' => 'system/service/UpgradeDatabaseBackup.php',
            'app\\admin\\system\\service\\UpgradeManifestVerifier' => 'system/service/UpgradeManifestVerifier.php',
            'app\\admin\\system\\service\\UpgradePackageVerifier' => 'system/service/UpgradePackageVerifier.php',
            'app\\admin\\system\\service\\UpgradeService' => 'system/service/UpgradeService.php',
        ] as $class => $relativePath) {
            self::assertTrue(class_exists($class), '服务应归入业务模块：' . $class);
            self::assertSame(realpath(__DIR__ . '/../app/admin/' . $relativePath), (new ReflectionClass($class))->getFileName());
        }
        foreach (['ResourceRegistryService', 'UpgradeDatabaseBackup', 'UpgradeManifestVerifier', 'UpgradePackageVerifier', 'UpgradeService'] as $name) {
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/service/' . $name . '.php');
        }
    }

    public function test_application_autoload_does_not_redirect_controllers_to_a_flat_directory(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('./app', $composer['autoload']['psr-4']['app\\']);
        self::assertArrayNotHasKey('app\\controller\\', $composer['autoload']['psr-4']);
    }

    public function test_running_controllers_are_discovered_with_native_route_attributes(): void
    {
        $discovered = array_map(static fn ($file): string => $file->meta()['class'], ControllerFinder::files());
        foreach ([
            'app\\admin\\authentication\\controller\\AdminAuth' => '/admin/auth',
            'app\\admin\\authentication\\controller\\ApiAuth' => '/api/auth',
            'app\\admin\\authorization\\controller\\Management' => '/admin/management',
        ] as $class => $prefix) {
            self::assertContains($class, $discovered);
            $reflection = new ReflectionClass($class);
            $groups = $reflection->getAttributes(RouteGroup::class);
            self::assertCount(1, $groups);
            self::assertSame($prefix, $groups[0]->newInstance()->prefix);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                self::assertCount(1, $method->getAttributes(RouteAttribute::class, \ReflectionAttribute::IS_INSTANCEOF));
            }
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_native_annotation_routes_preserve_urls_methods_and_parameter_constraints(): void
    {
        Route::load([config_path()]);
        $admin = 'app\\admin\\authentication\\controller\\AdminAuth';
        $api = 'app\\admin\\authentication\\controller\\ApiAuth';
        $management = 'app\\admin\\authorization\\controller\\Management';
        foreach ([
            ['GET', '/admin/auth/csrf', $admin, 'csrf', []],
            ['POST', '/admin/auth/login', $admin, 'login', []],
            ['GET', '/admin/auth/me', $admin, 'me', []],
            ['GET', '/admin/auth/menus', $admin, 'menus', []],
            ['POST', '/admin/auth/logout', $admin, 'logout', []],
            ['POST', '/api/auth/login', $api, 'login', []],
            ['GET', '/api/auth/me', $api, 'me', []],
            ['POST', '/api/auth/logout', $api, 'logout', []],
        ] as [$verb, $path, $controller, $action, $params]) {
            $matched = Route::dispatch($verb, $path);
            self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
            self::assertSame([$controller, $action], $matched[1]['callback']);
            self::assertSame($params, $matched[2]);
        }
        foreach (['users', 'roles', 'permissions', 'menus'] as $resource) {
            foreach (['GET' => 'listing', 'POST' => 'create', 'PUT' => 'update', 'DELETE' => 'remove'] as $verb => $action) {
                $hasId = in_array($verb, ['PUT', 'DELETE'], true);
                $path = '/admin/management/' . $resource . ($hasId ? '/42' : '');
                $matched = Route::dispatch($verb, $path);
                self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
                self::assertSame([$management, $action], $matched[1]['callback']);
                self::assertSame(['resource' => $resource] + ($hasId ? ['id' => '42'] : []), $matched[2]);
            }
        }
        self::assertSame(\FastRoute\Dispatcher::FOUND, Route::dispatch('GET', '/health')[0]);
        self::assertSame(\FastRoute\Dispatcher::METHOD_NOT_ALLOWED, Route::dispatch('GET', '/admin/auth/login')[0]);
        self::assertSame(\FastRoute\Dispatcher::METHOD_NOT_ALLOWED, Route::dispatch('POST', '/api/auth/me')[0]);
        foreach (['unknown/42', 'users/0', 'users/-1', 'users/abc', 'users/01'] as $invalid) {
            self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, Route::dispatch('PUT', '/admin/management/' . $invalid)[0]);
        }
        self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, Route::dispatch('GET', '/admin/identity/scopes')[0]);
        $routes = file_get_contents(__DIR__ . '/../config/route.php');
        self::assertStringContainsString('Route::disableDefaultRoute()', $routes);
        self::assertStringContainsString('Route::fallback(', $routes);
        self::assertStringNotContainsString('/admin/auth/', $routes);
        self::assertStringNotContainsString('/api/auth/', $routes);
        self::assertStringNotContainsString('/admin/management/', $routes);
        $businessRoutes = array_filter(Route::getRoutes(), static function ($route) use ($admin, $api, $management): bool {
            $callback = $route->getCallback();
            return is_array($callback) && in_array($callback[0], [$admin, $api, $management], true);
        });
        self::assertCount(12, $businessRoutes);
    }
}
