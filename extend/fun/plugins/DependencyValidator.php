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

    public function assertAcyclic(array $manifests): void
    {
        $this->topologicalSort($manifests);
    }

    /**
     * 依赖优先的稳定拓扑排序；同层插件按名称排序，确保启动顺序可预测。
     *
     * @return array<string, Manifest>
     */
    public function topologicalSort(array $manifests): array
    {
        ksort($manifests);
        $visiting = [];
        $visited = [];
        $sorted = [];
        $visit = function (string $name) use (&$visit, &$visiting, &$visited, &$sorted, $manifests): void {
            if (isset($visiting[$name])) {
                throw new RuntimeException('检测到插件循环依赖：' . $name);
            }
            if (isset($visited[$name]) || !isset($manifests[$name])) {
                return;
            }
            $visiting[$name] = true;
            $dependencies = array_keys($manifests[$name]->dependencies());
            sort($dependencies);
            foreach ($dependencies as $dependency) {
                $visit((string) $dependency);
            }
            unset($visiting[$name]);
            $visited[$name] = true;
            $sorted[$name] = $manifests[$name];
        };
        foreach (array_keys($manifests) as $name) {
            $visit((string) $name);
        }
        return $sorted;
    }

    public function assertNoEnabledDependents(string $name, array $manifests, array $installed): void
    {
        foreach ($manifests as $dependent => $manifest) {
            if ($dependent === $name || ($installed[$dependent]['lifecycle_state'] ?? '') !== 'enabled') {
                continue;
            }
            if (array_key_exists($name, $manifest->dependencies())) {
                throw new RuntimeException("存在已启用的反向依赖：{$dependent} -> {$name}");
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
