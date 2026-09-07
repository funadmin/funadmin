<?php

declare(strict_types=1);

namespace plugins\example\service;

use think\Service;

final class ExampleServiceProvider extends Service
{
    public function register(): void
    {
        $this->app->instance('plugin.example.binding', (object) ['value' => 'example-bound']);
    }
}
