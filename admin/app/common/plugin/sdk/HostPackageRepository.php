<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/** Host-only metadata: InstalledVersions aggregates plugin vendor directories. */
final class HostPackageRepository
{
    public function __construct(private readonly ?string $installedFile = null) {}

    /** @return array<string, string> */
    public function packages(): array
    {
        $file = $this->installedFile ?? base_path('vendor/composer/installed.php');
        if (!is_file($file)) {
            throw new RuntimeException('宿主 Composer 包元数据不存在，请先安装宿主依赖');
        }
        $data = (static fn (string $path): mixed => require $path)($file);
        $packages = [];
        foreach ((array) ($data['versions'] ?? []) as $name => $package) {
            if (isset($package['pretty_version'])) {
                $packages[$name] = (string) $package['pretty_version'];
            }
        }
        return $packages;
    }
}
