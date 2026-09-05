<?php

declare(strict_types=1);

return [
    'confirm_secret' => getenv('CRUD_CONFIRM_SECRET') ?: '',
    'confirm_ttl' => 300,
    // 仅暴露已在 database.connections 中配置的连接名，API/CLI 均不接受 DSN。
    'connections' => ['mysql'],
];
