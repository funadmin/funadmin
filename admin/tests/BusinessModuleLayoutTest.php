<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
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
    public function test_common_services_are_grouped_by_responsibility(): void
    {
        $groups = [
            'authentication' => ['BearerTokenExtractor', 'TokenService'],
            'cache' => ['DbCacheService', 'PredisService'],
            'database' => ['MigrationService', 'DdlEncodingGuard'],
            'install' => ['InstallSupport'],
            'member' => ['MemberAuthService', 'MemberInput'],
            'storage' => ['UploadService', 'OssService'],
            'mcp' => ['McpService'],
        ];
        foreach ($groups as $group => $names) {
            foreach ($names as $name) {
                $file = __DIR__ . '/../app/common/service/' . $group . '/' . $name . '.php';
                self::assertFileExists($file);
                $source = file_get_contents($file);
                token_get_all($source, TOKEN_PARSE);
                self::assertStringContainsString('namespace app\\common\\service\\' . $group . ';', $source);
                self::assertFileDoesNotExist(__DIR__ . '/../app/common/service/' . $name . '.php');
                self::assertFalse(class_exists('app\\common\\service\\' . $name));
                if (!in_array($name, ['PredisService', 'UploadService', 'OssService'], true)) {
                    $class = 'app\\common\\service\\' . $group . '\\' . $name;
                    self::assertTrue(class_exists($class), $class);
                    self::assertSame(realpath($file), (new ReflectionClass($class))->getFileName());
                }
            }
        }
        $rootFiles = array_map('basename', glob(__DIR__ . '/../app/common/service/*.php') ?: []);
        self::assertSame(['AbstractService.php'], $rootFiles);
    }

    public function test_admin_log_service_is_owned_by_system(): void
    {
        $class = 'app\\admin\\system\\service\\AdminLogService';
        self::assertTrue(class_exists($class));
        self::assertSame(realpath(__DIR__ . '/../app/admin/system/service/AdminLogService.php'), (new ReflectionClass($class))->getFileName());
        self::assertFileDoesNotExist(__DIR__ . '/../app/common/service/AdminLogService.php');
        self::assertFalse(class_exists('app\\common\\service\\AdminLogService'));
        self::assertStringContainsString('use ' . $class . ';', file_get_contents(__DIR__ . '/../app/admin/middleware/SystemLogMiddleware.php'));
    }

    public function test_webman_console_discovers_all_business_module_commands(): void
    {
        [$status, $stdout, $stderr] = $this->runConsole(['list', '--format=json']);
        self::assertSame(0, $status, $stderr);
        $commands = array_column(json_decode($stdout, true, 512, JSON_THROW_ON_ERROR)['commands'], null, 'name');
        foreach ([
            'ai:change-set-recover', 'ai:outbox-dispatch', 'ai:sandbox-cleanup',
            'business:generation-recover', 'crud:generate', 'crud:inspect', 'crud:preview', 'crud:validate',
            'mcp', 'plugin:activation-cache', 'plugin:crud-generate',
            'plugin:crud-preview', 'plugin:make', 'plugin:make-crud', 'plugin:package',
            'plugin:publication-recover', 'plugin:validate', 'upgrade:recover-stale',
        ] as $name) {
            self::assertArrayHasKey($name, $commands, '业务命令未接入 webman/console：' . $name);
            self::assertNotSame('', $commands[$name]['description']);
        }
        foreach (['identity:cleanup', 'identity:health', 'identity:logout-deliveries:work', 'identity:signing-key'] as $removed) {
            self::assertArrayNotHasKey($removed, $commands, '身份中心已移除：' . $removed);
        }
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
            'authorization' => ['SystemAdmin', 'SystemRole', 'SystemPermission', 'SystemMenu'],
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
            'zh-cn' => ['MemberFormDefinition' => '9b380e66154489471c0d715d73077ad030fe80a67a8618e0ea539dc871598461', 'MemberPageDefinition' => '001fd5a08c0e1199bd53e6d7f3f28cf7067733fd897d19881052a96d215b266c'],
            'en-us' => ['MemberFormDefinition' => '06e93976c5ee265d9c90229d5701543b5ded99f213e6a3652134446f2e811f69', 'MemberPageDefinition' => 'a3be6efe0458697e0e98d5d10cfbbaf8a1d2f9caa91bd42656fde61d897284d6'],
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
        foreach (["#[RouteGroup('/admin/profile')]", "#[Get('')]", "#[Put('')]", "#[Post('/password')]"] as $route) {
            self::assertStringContainsString($route, $source);
        }
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/authentication/AdminProfile.php');
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
        foreach (['MemberGroup' => 'member_group', 'MemberLevel' => 'member_level'] as $name => $table) {
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
        self::assertSame('member', (new $class())->getTable());
        self::assertSame('用户名长度必须为 2 至 80 个字符', $class::validateAttributes(['username' => 'a']));
        self::assertSame('用户名已存在', $class::duplicateError(new \RuntimeException('1062 Duplicate entry username')));
        self::assertNull($class::duplicateError(new \RuntimeException('not a duplicate')));
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/Member.php');
        self::assertFalse(class_exists('app\\admin\\model\\Member'));
    }

    public function test_log_attachment_and_department_models_have_explicit_module_ownership(): void
    {
        foreach ([
            'system' => ['AdminLog' => 'admin_log', 'AttachGroup' => 'attach_group'],
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
        self::assertStringContainsString("protected \$table = 'member_tag';", $source);
        self::assertStringContainsString('use app\\common\\model\\concern\\LaravelSoftDelete;', $source);
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/MemberTag.php');
    }



    public function test_plugin_example_business_layers_live_under_business_code(): void
    {
        foreach ([
            'controller/Index.php' => 'app\\admin\\plugins\\example\\controller\\Index',
            'controller/TestController.php' => 'app\\admin\\plugins\\example\\controller\\TestController',
            'model/Test.php' => 'app\\admin\\plugins\\example\\model\\Test',
            'service/TestService.php' => 'app\\admin\\plugins\\example\\service\\TestService',
        ] as $relativePath => $class) {
            self::assertTrue(class_exists($class), '插件业务类应归入 plugins/：' . $class);
            self::assertSame(realpath(__DIR__ . '/../app/admin/plugins/example/' . $relativePath), (new ReflectionClass($class))->getFileName());
        }
        $validate = file_get_contents(__DIR__ . '/../app/admin/plugins/example/validate/TestValidate.php');
        token_get_all($validate, TOKEN_PARSE);
        self::assertStringContainsString('namespace app\\admin\\plugins\\example\\validate;', $validate);

        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/plugin/example/Index.php');
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/plugin/example/TestController.php');
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/model/plugin/example/Test.php');
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/service/plugin/example/TestService.php');
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/validate/plugin/example/TestValidate.php');
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

    #[DataProvider('remainingControllerProvider')]
    public function test_remaining_system_and_feature_controllers_have_module_ownership(string $module, string $name): void
    {
        $fqcn = 'app\\admin\\' . $module . '\\controller\\' . $name;
        self::assertTrue(class_exists($fqcn), '控制器应归入模块：' . $fqcn);
        self::assertSame(realpath(__DIR__ . '/../app/admin/' . $module . '/controller/' . $name . '.php'), (new ReflectionClass($fqcn))->getFileName());
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/controller/' . $module . '/' . $name . '.php');
    }

    public static function remainingControllerProvider(): iterable
    {
        foreach ([
            'system' => ['AdminUpload', 'SystemAttachment', 'SystemAttachmentGroup', 'SystemBlacklist', 'SystemConfig', 'SystemDict', 'SystemLanguage', 'SystemMember', 'SystemMemberGroup', 'SystemMemberLevel', 'SystemOperationLog', 'SystemStorage', 'SystemUpgrade'],
            'ai' => ['Ai', 'Profiles'],
            'form' => ['Data'],
            'development' => ['Business'],
        ] as $module => $classes) {
            foreach ($classes as $name) {
                yield $module . '/' . $name => [$module, $name];
            }
        }
    }

    public function test_shared_request_pagination_and_tree_traits_are_owned_by_admin_common(): void
    {
        foreach (['AdminCrudRequest', 'AdminPagination', 'AdminTree'] as $name) {
            $class = 'app\\admin\\common\\traits\\' . $name;
            self::assertTrue(trait_exists($class), '后台公共 Trait 应归入 common：' . $class);
            self::assertSame(realpath(__DIR__ . '/../app/admin/common/traits/' . $name . '.php'), (new ReflectionClass($class))->getFileName());
            self::assertFalse(trait_exists('app\\admin\\traits\\' . $name));
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/traits/' . $name . '.php');
        }
        $traits = class_uses('app\\common\\controller\\AdminController');
        foreach (['AdminCrudRequest', 'AdminPagination', 'AdminTree'] as $name) {
            self::assertContains('app\\admin\\common\\traits\\' . $name, $traits);
        }
        $template = file_get_contents(__DIR__ . '/../app/common/crud/ProductionTemplateContext.php');
        foreach (['AdminCrudRequest', 'AdminPagination'] as $name) {
            self::assertStringContainsString('app\\\\admin\\\\common\\\\traits\\\\' . $name, $template);
            self::assertStringNotContainsString('app\\\\admin\\\\traits\\\\' . $name, $template);
        }
    }

    public function test_shared_format_scope_and_json_traits_are_owned_by_their_architecture_layers(): void
    {
        foreach (['AdminDataScope', 'AdminJsonResponse'] as $name) {
            $class = 'app\\admin\\common\\traits\\' . $name;
            self::assertTrue(trait_exists($class), '后台公共 Trait 应归入 admin/common：' . $class);
            self::assertSame(realpath(__DIR__ . '/../app/admin/common/traits/' . $name . '.php'), (new ReflectionClass($class))->getFileName());
            self::assertFalse(trait_exists('app\\admin\\traits\\' . $name));
            self::assertFileDoesNotExist(__DIR__ . '/../app/admin/traits/' . $name . '.php');
            self::assertContains($class, class_uses('app\\common\\controller\\AdminController'));
        }

        $formatTrait = 'app\\common\\traits\\DataFormat';
        self::assertTrue(trait_exists($formatTrait), '跨应用数据格式化 Trait 应归入 common：' . $formatTrait);
        self::assertSame(realpath(__DIR__ . '/../app/common/traits/DataFormat.php'), (new ReflectionClass($formatTrait))->getFileName());
        self::assertFalse(trait_exists('app\\admin\\common\\traits\\AdminDataFormat'));
        self::assertFileDoesNotExist(__DIR__ . '/../app/admin/common/traits/AdminDataFormat.php');

        foreach ([
            'app\\admin\\authorization\\model\\AuthGroup',
            'app\\admin\\authorization\\model\\AdminMenu',
            'app\\admin\\authorization\\model\\Department',
            'app\\admin\\authorization\\model\\Permission',
            'app\\admin\\system\\model\\Member',
            'app\\admin\\system\\model\\AdminLog',
            'app\\common\\model\\ConfigGroup',
            'app\\common\\model\\Blacklist',
            'app\\common\\model\\Language',
        ] as $class) {
            self::assertContains($formatTrait, class_uses($class), $class);
        }

        foreach (glob(__DIR__ . '/../app/common/model/*.php') ?: [] as $file) {
            $source = file_get_contents($file);
            token_get_all($source, TOKEN_PARSE);
            self::assertStringNotContainsString('app\\admin\\', $source, '公共模型不得依赖 admin：' . $file);
        }
    }

    public function test_backend_application_imports_resolve_to_declared_classes(): void
    {
        $root = dirname(__DIR__);
        $sources = [];
        $declared = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $source = file_get_contents($file->getPathname());
            $relative = substr($file->getPathname(), strlen($root) + 1);
            $sources[$relative] = $source;
            if (!preg_match('/^namespace ([^;]+);/m', $source, $namespace)) continue;
            preg_match_all('/^(?:(?:final|abstract|readonly)\\s+)*(?:class|interface|trait|enum)\\s+(\\w+)/m', $source, $classes);
            foreach ($classes[1] as $class) $declared[$namespace[1] . '\\' . $class] = true;
        }
        $missing = [];
        foreach ($sources as $file => $source) {
            if (!str_starts_with($file, 'app/admin/') && !str_starts_with($file, 'app/common/crud/')) continue;
            if (str_starts_with($file, 'app/admin/controller/generated/')) continue;
            preg_match_all('/^use (app\\\\[^;\\s]+)(?:\\s+as\\s+\\w+)?;/m', $source, $imports);
            foreach ($imports[1] as $class) {
                if (!isset($declared[$class])) $missing[] = $file . ': ' . $class;
            }
        }
        self::assertSame([], $missing, '后台静态导入不得指向不存在的应用类；受管文件冲突须保留并报告');
    }

    public function test_generated_entity_paths_compile_matching_namespaces_without_rewriting_snapshots(): void
    {
        $data = [
            'schemaVersion' => '1.0', 'entity' => 'layout-example', 'table' => 'layout_example', 'title' => '目录契约',
            'apiPrefix' => '/generated/layout-example', 'routePath' => '/generated/layout-example',
            'permissionPrefix' => 'generated:layout-example', 'timestamps' => false, 'softDeletes' => false,
            'fields' => [['name' => 'id', 'label' => 'ID', 'dbType' => 'bigint', 'nullable' => false, 'primary' => true]],
            'relations' => [], 'optionsSource' => [],
            'features' => ['importLimit' => 100, 'exportLimit' => 100, 'formMode' => 'dialog'],
            'dataScope' => ['enabled' => false],
            'menu' => ['enabled' => false], 'permission' => ['enabled' => false],
            'generationTargets' => [
                'model' => 'app/admin/generated/layout_example/model/LayoutExample.php',
                'validate' => 'app/admin/generated/layout_example/validate/LayoutExampleValidate.php',
                'service' => 'app/admin/generated/layout_example/service/LayoutExampleService.php',
                'controller' => 'app/admin/generated/layout_example/controller/LayoutExampleController.php',
            ],
        ];
        $definition = \app\common\crud\CrudDefinition::fromArray($data);
        $hash = $definition->hash();
        $context = \app\common\crud\ProductionTemplateContext::build($definition);
        foreach (['model', 'validate', 'service', 'controller'] as $layer) {
            self::assertStringContainsString('namespace app\\admin\\generated\\layout_example\\' . $layer . ';', $context[$layer . 'Content']);
            token_get_all($context[$layer . 'Content'], TOKEN_PARSE);
        }
        self::assertStringContainsString("#[Group('/admin/generated/layout-example')]", $context['controllerContent']);
        self::assertSame('/generated/layout-example', $context['apiPrefix']);
        self::assertSame('generated:layout-example', $context['permissionPrefix']);
        self::assertStringContainsString('use app\\admin\\generated\\layout_example\\model\\LayoutExample;', $context['serviceContent']);
        self::assertStringContainsString('use app\\admin\\generated\\layout_example\\service\\LayoutExampleService;', $context['controllerContent']);
        self::assertSame($hash, $definition->hash());
        $custom = \app\common\crud\ProductionTemplateContext::build($definition, ['modelNamespace' => 'app\\custom\\model']);
        self::assertStringContainsString('namespace app\\custom\\model;', $custom['modelContent']);
        foreach (['model', 'validate', 'service', 'controller'] as $layer) {
            $data['generationTargets'][$layer] = 'app/admin/' . $layer . '/generated/LayoutExample.php';
        }
        $legacy = \app\common\crud\ProductionTemplateContext::build(\app\common\crud\CrudDefinition::fromArray($data));
        self::assertStringContainsString('namespace app\\admin\\model\\generated;', $legacy['modelContent']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_generated_entity_layout_writes_loadable_files_and_protects_local_changes(): void
    {
        $root = sys_get_temp_dir() . '/funadmin-layout-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        $loader = new \Composer\Autoload\ClassLoader();
        $loader->addPsr4('app\\admin\\generated\\layout_isolated\\', $root . '/app/admin/generated/layout_isolated');
        $loader->register(true);
        try {
            $data = \Tests\Console\CrudDefinitionFixture::make('layout-isolated')->toArray();
            $classes = ['model' => 'LayoutIsolated', 'validate' => 'LayoutIsolatedValidate', 'service' => 'LayoutIsolatedService', 'controller' => 'LayoutIsolatedController'];
            foreach ($classes as $layer => $class) {
                $data['generationTargets'][$layer] = 'app/admin/generated/layout_isolated/' . $layer . '/' . $class . '.php';
            }
            $definition = \app\common\crud\CrudDefinition::fromArray($data);
            $generator = new \app\common\crud\CrudGenerator($root, tokens: new \app\common\crud\ConfirmationToken($root, 'isolated-layout-test-secret'));
            $plan = $generator->plan($definition);
            self::assertDirectoryDoesNotExist($root . '/app');
            self::assertSame('written', $generator->generate($definition, $plan['confirmToken'])['write']['status']);
            foreach ($classes as $layer => $class) {
                $file = $root . '/' . $data['generationTargets'][$layer];
                self::assertFileExists($file);
                self::assertTrue(class_exists('app\\admin\\generated\\layout_isolated\\' . $layer . '\\' . $class));
                self::assertDirectoryDoesNotExist($root . '/app/admin/' . $layer);
            }
            $controller = 'app\\admin\\generated\\layout_isolated\\controller\\LayoutIsolatedController';
            $groups = (new ReflectionClass($controller))->getAttributes(RouteGroup::class);
            self::assertSame('/admin/generated/layout-isolated', $groups[0]->newInstance()->prefix);
            try {
                $generator->generate($definition, $plan['confirmToken']);
                self::fail('已使用的确认 token 必须拒绝');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('token', $error->getMessage());
            }
            $next = $generator->plan($definition);
            $model = $root . '/' . $data['generationTargets']['model'];
            file_put_contents($model, 'local-change');
            try {
                $generator->generate($definition, $next['confirmToken']);
                self::fail('目标漂移必须阻断');
            } catch (\RuntimeException $error) {
                self::assertSame('local-change', file_get_contents($model));
            }
        } finally {
            $loader->unregister();
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }

    public function test_new_core_input_uses_entity_layout_while_saved_snapshots_keep_their_paths(): void
    {
        $data = \Tests\Console\CrudDefinitionFixture::make('layout-input')->toArray();
        $snapshot = \app\common\crud\CrudDefinition::fromArray($data);
        $hash = $snapshot->hash();
        $input = \app\common\crud\CrudDefinition::fromInput($data);
        foreach (['model' => 'LayoutInput', 'validate' => 'LayoutInputValidate', 'service' => 'LayoutInputService', 'controller' => 'LayoutInputController'] as $layer => $class) {
            self::assertSame('app/admin/generated/layout_input/' . $layer . '/' . $class . '.php', $input->get('generationTargets')[$layer]);
        }
        self::assertSame($data['apiPrefix'], $input->get('apiPrefix'));
        self::assertSame($data['permissionPrefix'], $input->get('permissionPrefix'));
        self::assertSame($hash, \app\common\crud\CrudDefinition::fromArray($snapshot->toArray())->hash());
        self::assertSame($data['generationTargets']['model'], $snapshot->get('generationTargets')['model']);
        unset($data['generationTargets']['model']);
        self::assertSame('app/admin/generated/layout_input/model/LayoutInput.php', \app\common\crud\CrudDefinition::fromInput($data)->get('generationTargets')['model']);
        $data['generationTargets']['model'] = 'app/custom/model/LayoutInput.php';
        self::assertSame('app/custom/model/LayoutInput.php', \app\common\crud\CrudDefinition::fromInput($data)->get('generationTargets')['model']);
        $data['formSchema'] = ['list' => []];
        self::assertSame($data['generationTargets']['controller'], \app\common\crud\CrudDefinition::fromInput($data)->get('generationTargets')['controller']);
    }

    public function test_new_entity_layout_rejects_existing_legacy_artifacts_before_preview_and_write(): void
    {
        $root = sys_get_temp_dir() . '/funadmin-layout-conflict-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        try {
            $data = \Tests\Console\CrudDefinitionFixture::make('layout-conflict')->toArray();
            $definition = \app\common\crud\CrudDefinition::fromInput($data);
            $generator = new \app\common\crud\CrudGenerator($root, tokens: new \app\common\crud\ConfirmationToken($root, 'layout-conflict-test-secret'));
            foreach (['model', 'validate', 'service', 'controller'] as $layer) {
                $legacy = $root . '/' . $data['generationTargets'][$layer];
                mkdir(dirname($legacy), 0700, true);
                file_put_contents($legacy, 'managed-original');
                $rejected = false;
                try { $generator->plan($definition); }
                catch (\InvalidArgumentException $error) {
                    $rejected = true;
                    self::assertStringContainsString('GENERATION_LAYOUT_CONFLICT', $error->getMessage());
                }
                self::assertTrue($rejected, '旧生成制品存在时必须阻止新布局：' . $layer);
                self::assertSame('managed-original', file_get_contents($legacy));
                self::assertDirectoryDoesNotExist($root . '/app/admin/generated');
                unlink($legacy);
            }
            $plan = $generator->plan($definition);
            $legacy = $root . '/' . $data['generationTargets']['model'];
            file_put_contents($legacy, 'created-after-preview');
            $rejected = false;
            try { $generator->generate($definition, $plan['confirmToken']); }
            catch (\InvalidArgumentException $error) {
                $rejected = true;
                self::assertStringContainsString('GENERATION_LAYOUT_CONFLICT', $error->getMessage());
            }
            self::assertTrue($rejected, '预览后出现旧制品也必须阻止写入');
            self::assertSame('created-after-preview', file_get_contents($legacy));
            self::assertDirectoryDoesNotExist($root . '/app/admin/generated');
            unlink($legacy);
            foreach (['model', 'validate', 'service', 'controller'] as $layer) {
                $baselines = [['path' => $data['generationTargets'][$layer], 'baseContent' => 'registered-original']];
                foreach (['planManaged', 'renderManagedBundle'] as $method) {
                    $rejected = false;
                    try { $generator->$method($definition, $baselines); }
                    catch (\InvalidArgumentException $error) {
                        $rejected = true;
                        self::assertStringContainsString('GENERATION_LAYOUT_CONFLICT', $error->getMessage());
                    }
                    self::assertTrue($rejected, '历史 baseline 不得被新布局绕过：' . $method . '/' . $layer);
                    self::assertSame('registered-original', $baselines[0]['baseContent']);
                }
            }
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }

    public function test_form_crud_factory_uses_entity_layout_for_new_generated_targets(): void
    {
        $factory = new \app\admin\development\service\FormCrudDefinitionFactory();
        $method = (new ReflectionClass($factory))->getMethod('targets');
        $targets = $method->invoke($factory, 'layout-factory', 'LayoutFactory', false);
        self::assertSame('app/admin/generated/layout_factory/model/LayoutFactory.php', $targets['model']);
        self::assertSame('app/admin/generated/layout_factory/validate/LayoutFactoryValidate.php', $targets['validate']);
        self::assertSame('app/admin/generated/layout_factory/service/LayoutFactoryService.php', $targets['service']);
        self::assertSame('app/admin/generated/layout_factory/controller/LayoutFactoryController.php', $targets['controller']);
        $memberTargets = $method->invoke($factory, 'layout-factory', 'LayoutFactory', true);
        self::assertSame('app/api/controller/generated/LayoutFactoryController.php', $memberTargets['memberApiController']);
        self::assertSame('app/admin/generated/layout_factory/controller/LayoutFactoryController.php', $memberTargets['controller']);
    }

    public function test_plugin_center_uses_webman_database_and_cache_facades(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/admin/plugin/service/PluginCenterService.php');
        self::assertStringContainsString('use support\\Db;', $source);
        self::assertStringContainsString('use support\\Cache;', $source);
        self::assertStringNotContainsString('use think\\facade\\Db;', $source);
        self::assertStringNotContainsString('use think\\facade\\Cache;', $source);
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
                self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, $matched[0]);
            }
        }
        self::assertSame(\FastRoute\Dispatcher::FOUND, Route::dispatch('GET', '/health')[0]);
        self::assertSame(\FastRoute\Dispatcher::METHOD_NOT_ALLOWED, Route::dispatch('GET', '/admin/auth/login')[0]);
        self::assertSame(\FastRoute\Dispatcher::METHOD_NOT_ALLOWED, Route::dispatch('POST', '/api/auth/me')[0]);
        foreach (['unknown/42', 'users/0', 'users/-1', 'users/abc', 'users/01'] as $invalid) {
            self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, Route::dispatch('PUT', '/admin/management/' . $invalid)[0]);
        }
        self::assertSame(\FastRoute\Dispatcher::NOT_FOUND, Route::dispatch('GET', '/admin/identity/scopes')[0], '身份中心已移除');
        $routes = file_get_contents(__DIR__ . '/../config/route.php');
        self::assertStringContainsString('Route::disableDefaultRoute()', $routes);
        self::assertStringContainsString('Route::fallback(', $routes);
        self::assertStringNotContainsString('/admin/auth/', $routes);
        self::assertStringNotContainsString('/api/auth/', $routes);
        self::assertStringNotContainsString('/admin/management/', $routes);
        $businessRoutes = array_filter(Route::getRoutes(), static function ($route) use ($admin, $api): bool {
            $callback = $route->getCallback();
            return is_array($callback) && in_array($callback[0], [$admin, $api], true);
        });
        self::assertCount(8, $businessRoutes);
    }
}
