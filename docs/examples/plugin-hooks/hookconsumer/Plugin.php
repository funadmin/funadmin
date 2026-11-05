<?php

declare(strict_types=1);

namespace plugins\hookconsumer;

final class Plugin extends \app\common\plugin\sdk\Plugin
{
    public function install(): bool { return true; }
    public function uninstall(): bool { return true; }
    public function enabled(): bool { return true; }
    public function disabled(): bool { return true; }
}
