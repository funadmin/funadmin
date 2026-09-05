<?php

declare(strict_types=1);

return [
    'confirm_secret' => getenv('CRUD_CONFIRM_SECRET') ?: '',
    'confirm_ttl' => 300,
];
