<?php

return [
    'commands' => [
        'crud:inspect' => 'app\console\command\CrudInspect',
        'crud:validate' => 'app\console\command\CrudValidate',
        'crud:preview' => 'app\console\command\CrudPreview',
        'crud:generate' => 'app\console\command\CrudGenerate',
        'business:generation-recover' => 'app\console\command\BusinessGenerationRecover',
        'plugin:activation-cache' => 'app\console\command\PluginActivationCacheRebuild',
        'plugin:make' => 'app\console\command\PluginMake',
        'plugin:make-crud' => 'app\console\command\PluginMakeCrud',
        'plugin:crud-preview' => 'app\console\command\PluginCrudPreview',
        'plugin:crud-generate' => 'app\console\command\PluginCrudGenerate',
        'plugin:validate' => 'app\console\command\PluginValidate',
        'plugin:package' => 'app\console\command\PluginPackage',
        'plugin:publication-recover' => 'app\console\command\PluginPublicationRecover',
        'maintenance:contract-migrate' => 'app\console\command\MaintenanceContractMigrate',
        'upgrade:recover-stale' => 'app\console\command\UpgradeRecover',
        'ai:sandbox-cleanup' => 'app\console\command\AiSandboxCleanup',
        'ai:outbox-dispatch' => 'app\console\command\AiOutboxDispatch',
        'ai:change-set-recover' => 'app\console\command\AiChangeSetRecover',
        'identity:logout-deliveries:work' => 'app\console\command\IdentityLogoutDeliveriesWork',
        'identity:cleanup' => 'app\console\command\IdentityCleanup',
        'identity:health' => 'app\console\command\IdentityHealth',
        'identity:signing-key' => 'app\console\command\IdentitySigningKey',
        'mcp' => 'app\console\command\McpServer',
    ],
];
