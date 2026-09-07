<?php

declare(strict_types=1);

namespace plugins\example\listener;

final class ExampleListener
{
    public static bool $triggered = false;

    public function handle(): void
    {
        self::$triggered = true;
    }
}
