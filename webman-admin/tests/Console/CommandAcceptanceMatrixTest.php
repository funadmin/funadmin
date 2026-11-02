<?php

declare(strict_types=1);

namespace Tests\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;

/** 矩阵只记录可追溯证据；是否通过仍由对应 SQLite/MySQL 执行测试决定。 */
final class CommandAcceptanceMatrixTest extends TestCase
{
    public static function matrix(): array
    {
        return [
            'crud:inspect' => ['development', 'CrudInspect', MySqlCrudInspectTest::class, 'test_inspect_command_reads_schema_using_whitelisted_named_connection', 'test_inspect_command_reads_schema_using_whitelisted_named_connection'],
            'crud:validate' => ['development', 'CrudValidate', CrudGenerationExecutionTest::class, 'test_cli_validate_preview_generate_and_token_file_permissions', 'test_cli_validate_preview_generate_and_token_file_permissions'],
            'crud:preview' => ['development', 'CrudPreview', CrudGenerationExecutionTest::class, 'test_cli_validate_preview_generate_and_token_file_permissions', 'test_cli_validate_preview_generate_and_token_file_permissions'],
            'crud:generate' => ['development', 'CrudGenerate', CrudGenerationExecutionTest::class, 'test_cli_validate_preview_generate_and_token_file_permissions', 'test_cli_validate_preview_generate_and_token_file_permissions'],
            'business:generation-recover' => ['development', 'BusinessGenerationRecover', BusinessGenerationRecoveryTest::class, 'test_cli_inspection_and_rollback_of_uncommitted_files_are_idempotent', 'test_drift_requires_manual_recovery_without_overwriting_human_edit'],
            'plugin:activation-cache' => ['plugin', 'PluginActivationCacheRebuild', PluginActivationExecutionTest::class, 'test_command_compiles_database_states_including_deleted_records_to_trusted_snapshot', 'test_command_compiles_database_states_including_deleted_records_to_trusted_snapshot'],
            'plugin:crud-generate' => ['plugin', 'PluginCrudGenerate', PluginCrudExecutionTest::class, 'test_preview_generate_cli_enforces_token_permissions_and_replay', 'test_preview_generate_cli_enforces_token_permissions_and_replay'],
            'plugin:crud-preview' => ['plugin', 'PluginCrudPreview', PluginCrudExecutionTest::class, 'test_preview_generate_cli_enforces_token_permissions_and_replay', 'test_preview_generate_cli_enforces_token_permissions_and_replay'],
            'plugin:make' => ['plugin', 'PluginMake', PluginScaffoldExecutionTest::class, 'test_make_preview_is_read_only_and_command_writes_only_to_explicit_project', 'test_make_preview_is_read_only_and_command_writes_only_to_explicit_project'],
            'plugin:make-crud' => ['plugin', 'PluginMakeCrud', MySqlPluginCommandTest::class, 'test_make_crud_infers_real_schema_in_temporary_plugin_without_mutating_table', 'test_make_crud_infers_real_schema_in_temporary_plugin_without_mutating_table'],
            'plugin:package' => ['plugin', 'PluginPackage', PluginPackageExecutionTest::class, 'test_package_cli_round_trips_stage_and_is_reproducible', 'test_signed_archive_verifies_and_tampering_is_rejected_before_extraction'],
            'plugin:publication-recover' => ['plugin', 'PluginPublicationRecover', PluginPublicationExecutionTest::class, 'test_cli_inspects_and_recovers_real_file_publication_and_registry_idempotently', 'test_cli_inspects_and_recovers_real_file_publication_and_registry_idempotently'],
            'plugin:validate' => ['plugin', 'PluginValidate', PluginScaffoldExecutionTest::class, 'test_validate_reads_logical_plugin_registry_and_rejects_missing_dependency', 'test_validate_reads_logical_plugin_registry_and_rejects_missing_dependency'],
            'identity:cleanup' => ['identity', 'IdentityCleanup', MySqlIdentityCommandTest::class, 'test_all_four_identity_commands_execute_against_real_mysql', 'test_all_four_identity_commands_execute_against_real_mysql'],
            'identity:health' => ['identity', 'IdentityHealth', MySqlIdentityCommandTest::class, 'test_all_four_identity_commands_execute_against_real_mysql', 'test_all_four_identity_commands_execute_against_real_mysql'],
            'identity:logout-deliveries:work' => ['identity', 'IdentityLogoutDeliveriesWork', MySqlIdentityCommandTest::class, 'test_all_four_identity_commands_execute_against_real_mysql', 'test_all_four_identity_commands_execute_against_real_mysql'],
            'identity:signing-key' => ['identity', 'IdentitySigningKey', MySqlIdentityCommandTest::class, 'test_all_four_identity_commands_execute_against_real_mysql', 'test_all_four_identity_commands_execute_against_real_mysql'],
            'ai:change-set-recover' => ['ai', 'AiChangeSetRecover', AiChangeSetRecoveryTest::class, 'test_recovery_command_rolls_back_proven_changes_and_is_idempotent', 'test_recovery_command_refuses_unknown_external_writes'],
            'ai:outbox-dispatch' => ['ai', 'AiOutboxDispatch', MySqlAiQueueTest::class, 'test_real_outbox_redis_transport_consumer_and_retry', [AiOutboxExecutionTest::class, 'test_command_returns_nonzero_when_outbox_storage_is_unavailable']],
            'ai:sandbox-cleanup' => ['ai', 'AiSandboxCleanup', AiSandboxCleanupTest::class, 'test_cleanup_command_only_claims_expired_terminal_sandboxes', 'test_cleanup_failure_returns_nonzero_and_preserves_foreign_container'],
            'mcp' => ['common', 'McpServer', McpStdioProtocolTest::class, 'test_stdio_handshake_tools_resources_calls_and_eof_are_clean', 'test_command_rejects_unknown_action_with_nonzero_exit'],
            'upgrade:recover-stale' => ['system', 'UpgradeRecover', UpgradeRecoveryExecutionTest::class, 'test_pre_deploy_stale_task_releases_slot_and_repeat_is_noop', 'test_missing_post_deploy_backup_requires_manual_recovery_without_releasing_slot'],
        ];
    }

    public static function dependencies(): array
    {
        return [
            'development' => [
                'services' => ['app/admin/development/service/DevCrudService.php', 'app/admin/development/service/GenerationTransactionService.php', 'app/admin/development/service/GenerationResourceTransaction.php', 'app/common/crud/CrudGenerator.php', 'app/common/crud/ProductionTemplateContext.php', 'app/common/crud/CrudResourceInstaller.php', 'app/common/crud/LangPackTranslator.php', 'app/common/plugin/sdk/PluginRoute.php', 'app/common/plugin/sdk/Service.php', 'app/common/functions/plugin.php', 'app/common/service/database/MigrationService.php'],
                'configuration' => ['crud.connections', 'crud.confirm_secret', 'database.connections', 'config/bootstrap.php', 'config/autoload.php'],
                'storage' => ['business_module', 'crud_generation', 'generated_file_baseline', 'form', 'form_schema_version', 'system_migration'],
                'paths' => ['runtime/private/business-development', 'runtime/cache/crud-confirm-nonces', 'app/admin/generated', 'database/generated'],
                'external' => ['mysql'],
            ],
            'plugin' => [
                'services' => ['app/admin/plugin/service/PluginService.php', 'app/admin/plugin/service/PluginInfrastructureService.php', 'app/admin/plugin/service/PluginAppPublicationService.php', 'app/admin/plugin/service/PluginPackageService.php', 'app/common/plugin/sdk/PluginEntryFactory.php', 'app/common/plugin/sdk/PluginActivationCompiler.php', 'app/common/plugin/sdk/PluginScaffolder.php', 'app/common/plugin/sdk/PluginRoute.php', 'app/common/plugin/sdk/Service.php', 'app/common/functions/plugin.php'],
                'configuration' => ['plugins.marketplace.public_key', 'funadmin.version', 'crud.connections', 'database.connections', 'config/bootstrap.php', 'config/autoload.php'],
                'storage' => ['plugin', 'plugin_resource', 'system_migration', 'permission', 'admin_menu'],
                'paths' => ['plugins', 'runtime/plugins', 'public/plugin-assets', 'app/admin/controller/plugin'],
                'external' => ['mysql', 'sodium', 'zip'],
            ],
            'identity' => [
                'services' => ['app/common/service/identity/IdentityOperationsService.php', 'app/common/service/identity/SigningKeyService.php', 'app/identity/service/BackchannelLogoutWorker.php', 'app/identity/service/BackchannelLogoutDispatcher.php', 'app/identity/service/LogoutTokenService.php'],
                'configuration' => ['identity.issuer', 'database.connections'],
                'storage' => ['identity_tenant', 'oauth_authorization_code', 'oauth_token', 'oidc_session', 'identity_audit_log', 'oidc_signing_key', 'backchannel_logout_delivery', 'identity_sso_config', 'client_secret'],
                'paths' => ['runtime/secret/oidc'],
                'external' => ['mysql', 'openssl', 'curl', 'controlled TLS receiver'],
            ],
            'ai' => [
                'services' => ['app/admin/ai/job/AiAgentJob.php', 'app/admin/ai/controller/Ai.php', 'app/admin/ai/service/AiConversationService.php', 'app/admin/ai/service/AiProviderSettingsService.php', 'app/admin/ai/queue/AiAgentConsumer.php', 'app/admin/ai/service/AiOutboxDispatcher.php', 'app/admin/ai/service/AgentSandboxManager.php', 'app/admin/ai/service/AiChangeSetTransactionService.php', 'app/admin/ai/service/AiConfigurationProfileService.php', 'app/admin/ai/service/AiAttachmentService.php', 'app/admin/ai/infrastructure/ContainerAiToolExecutor.php', 'app/common/crud/LangPackTranslator.php'],
                'configuration' => ['ai.provider', 'ai.encryption_key', 'ai.storage', 'ai.sandbox', 'ai.tools.allowlist', 'plugin.webman.redis-queue.redis.ai-agent'],
                'storage' => ['ai_conversation', 'ai_message', 'ai_task', 'ai_task_event', 'ai_outbox', 'ai_change_set', 'ai_tool_call', 'ai_approval', 'ai_configuration_profile', 'ai_attachment'],
                'paths' => ['runtime/private/ai', 'config/plugin/webman/redis-queue'],
                'external' => ['mysql', 'redis', 'docker', 'curl', 'gd', 'local fake provider'],
            ],
            'common' => [
                'services' => ['app/common/service/mcp/McpService.php'],
                'configuration' => ['database.default', 'app.default_timezone', 'crud.connections'],
                'storage' => ['admin'], 'paths' => ['public/uploads'], 'external' => ['mcp/sdk STDIO'],
            ],
            'system' => [
                'services' => ['app/admin/system/service/UpgradeService.php', 'app/admin/system/service/UpgradeDatabaseBackup.php', 'app/common/service/database/MigrationService.php'],
                'configuration' => ['database.default', 'database.connections'],
                'storage' => ['upgrade_task', 'system_migration'], 'paths' => ['runtime/upgrade'], 'external' => ['mysql', 'PDO'],
            ],
        ];
    }

    public function test_all_live_commands_have_traceable_normal_and_failure_execution_evidence(): void
    {
        self::assertCount(22, self::matrix());
        $discovered = [];
        foreach (glob(app_path('admin/*/command/*.php')) as $file) {
            $class = 'app\\admin\\' . basename(dirname($file, 2)) . '\\command\\' . basename($file, '.php');
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getAttributes(AsCommand::class) as $attribute) $discovered[$attribute->newInstance()->name] = $class;
        }
        foreach (self::matrix() as $name => [$module, $command, $test, $normal, $failure]) {
            self::assertSame('app\\admin\\' . $module . '\\command\\' . $command, $discovered[$name] ?? null);
            foreach ([[$test, $normal], is_array($failure) ? $failure : [$test, $failure]] as [$testClass, $method]) {
                self::assertTrue((new \ReflectionMethod($testClass, $method))->isPublic(), $name . ' 缺少执行证据');
            }
            self::assertArrayHasKey($module, self::dependencies());
        }
        self::assertSame([], array_diff(array_keys($discovered), array_keys(self::matrix())));
        self::assertArrayNotHasKey('maintenance:contract-migrate', $discovered, '用户确认退役的命令不得恢复');
        self::assertFileDoesNotExist(app_path('admin/common/command/MaintenanceContractMigrate.php'));
        foreach (self::dependencies() as $module) {
            foreach ($module['services'] as $file) self::assertFileExists(base_path($file));
            foreach (['configuration', 'storage', 'paths', 'external'] as $section) self::assertNotEmpty($module[$section]);
        }
    }
}
