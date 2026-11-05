<?php

declare(strict_types=1);

return [
    'bundled' => ['market', 'example'],
    'admin_web_path' => getenv('PLUGIN_ADMIN_WEB_PATH') ?: dirname(base_path()) . '/admin-web',
    'marketplace' => [
        'url' => getenv('PLUGIN_MARKETPLACE_URL') ?: '',
        'public_key' => getenv('PLUGIN_MARKETPLACE_PUBLIC_KEY') ?: '',
        'unsigned_policy' => 'reject_unsigned',
        'request_timeout' => 30,
        'connect_timeout' => 10,
        'telemetry' => false,
    ],
];
