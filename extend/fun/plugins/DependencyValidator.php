<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/**
 * 校验运行环境与已安装插件版本约束。
 */
final class DependencyValidator
{
    public function __construct(
        private readonly string $funadminVersion,
        private readonly string $phpVersion
    ) {
    }

    public function assertSatisfied(Manifest $manifest, array $installed): void
    {
        $php = $manifest->requirement('php');
        if ($php !== null && !$this->matches($this->phpVersion, $php)) {
            throw new RuntimeException("PHP {$this->phpVersion} 不满足 {$php}");
        }
        $funadmin = $manifest->requirement('funadmin');
        if ($funadmin !== null && !$this->matches($this->funadminVersion, $funadmin)) {
            throw new RuntimeException("FunAdmin {$this->funadminVersion} 不满足 {$funadmin}");
        }
        foreach ($manifest->dependencies() as $name => $constraint) {
            $dependency = $installed[$name] ?? null;
            if (!is_array($dependency)) {
                throw new RuntimeException('依赖插件未安装：' . $name);
            }
            if (($dependency['lifecycle_state'] ?? '') !== 'enabled') {
                throw new RuntimeException('依赖插件未启用：' . $name);
            }
            $version = (string) ($dependency['version'] ?? '');
            if (!$this->matches($version, $constraint)) {
                throw new RuntimeException("依赖插件 {$name} 版本不满足 {$constraint}，当前 {$version}");
            }
        }
    }

    private function matches(string $version, string $constraint): bool
    {
        foreach (preg_split('/\s*,\s*|\s+/', trim($constraint)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            if (str_starts_with($part, '^')) {
                $minimum = substr($part, 1);
                $major = (int) explode('.', $minimum)[0];
                if (version_compare($version, $minimum, '<') || version_compare($version, ($major + 1) . '.0.0', '>=')) {
                    return false;
                }
                continue;
            }
            if (!preg_match('/^(>=|<=|>|<|=)?(.+)$/', $part, $matches)) {
                return false;
            }
            if (!version_compare($version, $matches[2], $matches[1] ?: '=')) {
                return false;
            }
        }
        return true;
    }
}
