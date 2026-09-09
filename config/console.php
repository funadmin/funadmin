<?php

return [
    'commands' => [
        'crud:inspect' => 'fun\command\CrudInspect',
        'crud:validate' => 'fun\command\CrudValidate',
        'crud:preview' => 'fun\command\CrudPreview',
        'crud:generate' => 'fun\command\CrudGenerate',
        'business:generation-recover' => 'fun\command\BusinessGenerationRecover',
        'plugin:activation-cache' => 'fun\command\PluginActivationCacheRebuild',
        'plugin:make' => 'fun\command\PluginMake',
        'plugin:make-crud' => 'fun\command\PluginMakeCrud',
        'plugin:crud-preview' => 'fun\command\PluginCrudPreview',
        'plugin:crud-generate' => 'fun\command\PluginCrudGenerate',
        'plugin:validate' => 'fun\command\PluginValidate',
        'plugin:package' => 'fun\command\PluginPackage',
        'plugin:publication-recover' => 'fun\command\PluginPublicationRecover',
        'maintenance:contract-migrate' => 'fun\command\MaintenanceContractMigrate',
        'upgrade:recover-stale' => 'fun\command\UpgradeRecover',
        'mcp' => 'fun\mcp\McpServer',
    ],
];
