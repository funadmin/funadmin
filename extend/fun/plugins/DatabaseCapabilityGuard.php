<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 防止部署的代码版本低于数据库已经执行的 migration 能力。 */
final class DatabaseCapabilityGuard
{
    public function __construct(private readonly mixed $versionCapability)
    {
    }

    public function assertDeployable(
        string $plugin,
        string $targetVersion,
        string $currentDbVersion,
        string $currentCodeVersion = '',
        string $packageCapability = ''
    ): void {
        if ($currentDbVersion === '') {
            return;
        }
        $targetCapability = $packageCapability;
        if ($targetCapability === '' || ($currentCodeVersion !== '' && version_compare($targetVersion, $currentCodeVersion, '<='))) {
            $targetCapability = ($this->versionCapability)($plugin, $targetVersion);
        }
        if ($targetCapability === null || $targetCapability === '') {
            throw new RuntimeException('目标版本缺少版本历史，无法证明数据库能力兼容');
        }
        if (strnatcmp((string) $targetCapability, $currentDbVersion) < 0) {
            throw new RuntimeException(sprintf(
                '目标版本数据库能力 %s 低于当前 %s，禁止降级部署',
                $targetCapability,
                $currentDbVersion
            ));
        }
    }
}
