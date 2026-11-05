<?php

declare(strict_types=1);

return [
    'relay' => static fn (mixed $value, array $context): mixed =>
        $context['hooks']->filter('plugin.hookdemo.decorate', $value),
];
