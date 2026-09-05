<?php

return [
    'commands' => [
        'crud' => 'fun\crud\AdminWebCrud',
        'crud:inspect' => 'fun\command\CrudInspect',
        'crud:validate' => 'fun\command\CrudValidate',
        'crud:preview' => 'fun\command\CrudPreview',
        'crud:generate' => 'fun\command\CrudGenerate',
        'menu' => 'fun\crud\Menu',
        'plugin' => 'fun\crud\Plugin',
        'install' => 'fun\crud\Install',
        'mcp' => 'fun\mcp\McpServer',
    ],
];
