<?php

return [
    'commands' => [
        'crud:inspect' => 'fun\command\CrudInspect',
        'crud:validate' => 'fun\command\CrudValidate',
        'crud:preview' => 'fun\command\CrudPreview',
        'crud:generate' => 'fun\command\CrudGenerate',
        'upgrade:recover-stale' => 'fun\command\UpgradeRecover',
        'mcp' => 'fun\mcp\McpServer',
    ],
];
