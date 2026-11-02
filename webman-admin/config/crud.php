<?php

declare(strict_types=1);

return [
    'connections' => array_values(array_filter(array_map('trim', explode(',', getenv('CRUD_CONNECTIONS') ?: 'mysql')))),
    'confirm_secret' => getenv('CRUD_CONFIRM_SECRET') ?: '',
];
