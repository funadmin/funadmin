<?php

declare(strict_types=1);

return [
    'connections_explicit' => (bool) getenv('CRUD_CONNECTIONS'),
    'connections' => array_values(array_filter(array_map('trim', explode(',', getenv('CRUD_CONNECTIONS') ?: (getenv('DB_CONNECTION') ?: 'mysql'))))),
    'confirm_secret' => getenv('CRUD_CONFIRM_SECRET') ?: '',
];
