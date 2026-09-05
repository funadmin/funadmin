<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 强制生命周期阶段顺序，并将每一步同步给持久化适配器。 */
final class LifecycleProgress
{
    private const PROGRESS = [
        'validate' => 5,
        'deploy' => 20,
        'hooks' => 35,
        'migration' => 55,
        'resources' => 75,
        'permissions' => 90,
        'complete' => 100,
    ];

    private int $position = -1;

    public function __construct(private readonly mixed $record)
    {
    }

    public function advance(string $stage): void
    {
        $stages = array_keys(self::PROGRESS);
        $position = array_search($stage, $stages, true);
        if ($position === false || $position !== $this->position + 1) {
            throw new RuntimeException('插件生命周期阶段顺序非法：' . $stage);
        }
        $this->position = $position;
        ($this->record)($stage, self::PROGRESS[$stage], null);
    }

    public function fail(string $stage, ?string $recoveryPath = null): void
    {
        if (!array_key_exists($stage, self::PROGRESS)) {
            throw new RuntimeException('未知插件生命周期阶段：' . $stage);
        }
        $progress = $this->position < 0 ? 0 : self::PROGRESS[array_keys(self::PROGRESS)[$this->position]];
        ($this->record)($stage, $progress, $recoveryPath);
    }
}
