<?php

declare(strict_types=1);

return [
    'default' => [
        'handlers' => [[
            'class' => Monolog\Handler\StreamHandler::class,
            'constructor' => ['php://stderr', Monolog\Logger::WARNING],
        ]],
    ],
];
