<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BusinessModuleLayoutTest extends TestCase
{
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
            'common' => ['MaintenanceContractMigrate' => 'maintenance:contract-migrate', 'McpServer' => 'mcp'],
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

    public function test_application_autoload_does_not_redirect_controllers_to_a_flat_directory(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('./app', $composer['autoload']['psr-4']['app\\']);
        self::assertArrayNotHasKey('app\\controller\\', $composer['autoload']['psr-4']);
    }

    public function test_explicit_routes_reference_the_module_controllers_without_changing_urls(): void
    {
        $routes = file_get_contents(__DIR__ . '/../config/route.php');
        foreach ([
            'use app\\admin\\authentication\\controller\\AdminAuth;',
            'use app\\admin\\authentication\\controller\\ApiAuth;',
            'use app\\admin\\authorization\\controller\\Management;',
            "Route::post('/api/auth/login', [ApiAuth::class, 'login'])",
            "Route::post('/admin/auth/login', [AdminAuth::class, 'login'])",
            "Route::get('/admin/auth/menus', [AdminAuth::class, 'menus'])",
            "Route::disableDefaultRoute()",
        ] as $contract) {
            self::assertStringContainsString($contract, $routes);
        }
    }
}
