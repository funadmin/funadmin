<?php

return [
    'commands' => [
        'crud:inspect' => 'fun\command\CrudInspect',
        'crud:validate' => 'fun\command\CrudValidate',
        'crud:preview' => 'fun\command\CrudPreview',
        'crud:generate' => 'fun\command\CrudGenerate',
        'plugin:runtime-cache' => 'fun\command\PluginRuntimeCacheRebuild',
        'upgrade:recover-stale' => 'fun\command\UpgradeRecover',
        'mcp' => 'fun\mcp\McpServer',
    ],
];
