<?php

declare(strict_types=1);

return [
    'listen' => [
        'ExamplePluginEvent' => [\plugins\example\listener\ExampleListener::class],
    ],
];
