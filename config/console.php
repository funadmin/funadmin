<?php

return [
    'commands' => [
        'crud:inspect' => 'app\admin\command\CrudInspect',
        'crud:validate' => 'app\admin\command\CrudValidate',
        'crud:preview' => 'app\admin\command\CrudPreview',
        'crud:generate' => 'app\admin\command\CrudGenerate',
        'business:generation-recover' => 'app\admin\command\BusinessGenerationRecover',
        'plugin:activation-cache' => 'app\admin\command\PluginActivationCacheRebuild',
        'plugin:make' => 'app\admin\command\PluginMake',
        'plugin:make-crud' => 'app\admin\command\PluginMakeCrud',
        'plugin:crud-preview' => 'app\admin\command\PluginCrudPreview',
        'plugin:crud-generate' => 'app\admin\command\PluginCrudGenerate',
        'plugin:validate' => 'app\admin\command\PluginValidate',
        'plugin:package' => 'app\admin\command\PluginPackage',
        'plugin:publication-recover' => 'app\admin\command\PluginPublicationRecover',
        'maintenance:contract-migrate' => 'app\admin\command\MaintenanceContractMigrate',
        'upgrade:recover-stale' => 'app\admin\command\UpgradeRecover',
        'ai:sandbox-cleanup' => 'app\admin\command\AiSandboxCleanup',
        'ai:outbox-dispatch' => 'app\admin\command\AiOutboxDispatch',
        'ai:change-set-recover' => 'app\admin\command\AiChangeSetRecover',
        'identity:logout-deliveries:work' => 'app\admin\command\IdentityLogoutDeliveriesWork',
        'identity:cleanup' => 'app\admin\command\IdentityCleanup',
        'identity:health' => 'app\admin\command\IdentityHealth',
        'identity:signing-key' => 'app\admin\command\IdentitySigningKey',
        'mcp' => 'app\admin\command\McpServer',
    ],
];
