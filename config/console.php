<?php

return [
    'commands' => [
        'curd' => 'fun\curd\AdminWebCrud',
        'crud:inspect' => 'fun\command\CrudInspect',
        'crud:validate' => 'fun\command\CrudValidate',
        'crud:preview' => 'fun\command\CrudPreview',
        'crud:generate' => 'fun\command\CrudGenerate',
        'menu' => 'fun\curd\Menu',
        'plugin' => 'fun\curd\Plugin',
        'install' => 'fun\curd\Install',
        'mcp' => 'fun\mcp\McpServer',
    ],
];
