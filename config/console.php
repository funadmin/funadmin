<?php

return [
    'commands' => [
        'curd' => 'fun\crud\AdminWebCrud',
        'crud:inspect' => 'fun\command\CrudInspect',
        'crud:validate' => 'fun\command\CrudValidate',
        'crud:preview' => 'fun\command\CrudPreview',
        'crud:generate' => 'fun\command\CrudGenerate',
        'mcp' => 'fun\mcp\McpServer',
    ],
];
