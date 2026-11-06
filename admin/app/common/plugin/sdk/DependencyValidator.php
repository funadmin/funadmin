<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/**
 * 校验运行环境与已安装插件版本约束。
 */
final class DependencyValidator
{
    public function __construct(
        private readonly string $funadminVersion,
        private readonly string $phpVersion,
        private readonly HostPackageRepository $hostPackages = new HostPackageRepository()
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
        foreach ($manifest->dependencies() as $code => $constraint) {
            $dependency = $installed[$code] ?? null;
            if (!is_array($dependency)) {
                throw new RuntimeException('依赖插件未安装：' . $code);
            }
            if ((int) ($dependency['needs_reinstall'] ?? 0) !== 0) {
                throw new RuntimeException('依赖插件需要重新安装：' . $code);
            }
            if (($dependency['lifecycle_state'] ?? '') !== 'enabled') {
                throw new RuntimeException('依赖插件未启用：' . $code);
            }
            $version = (string) ($dependency['version'] ?? '');
            if (!$this->matches($version, $constraint)) {
                throw new RuntimeException("依赖插件 {$code} 版本不满足 {$constraint}，当前 {$version}");
            }
        }
    }

    /** Separate from generic lifecycle checks so removal never depends on Composer. */
    public function assertComposerSatisfied(Manifest $manifest): void
    {
        $requirements = $manifest->composerRequirements();
        if ($requirements === []) { return; }
        $packages = $this->hostPackages->packages();
        $errors = []; $arguments = [];
        foreach ($requirements as $name => $constraint) {
            // Limit the existing constraint grammar explicitly; never pass arbitrary strings to version_compare.
            if (!preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/D', $name)
                || !preg_match('/^(?:\^|>=|<=|>|<|=)?v?\d+(?:\.\d+)*(?:(?:\s*,\s*|\s+)(?:\^|>=|<=|>|<|=)?v?\d+(?:\.\d+)*)*$/D', $constraint)) {
                throw new RuntimeException('不支持的 Composer 包名或版本约束：' . $name . ' ' . $constraint);
            }
            $version = $packages[$name] ?? null;
            $normalized = $version === null ? '' : ltrim($version, 'v');
            $normalizedConstraint = preg_replace('/(?<=[\^<>= ,])v(?=\d)|^v(?=\d)/', '', $constraint);
            if ($version === null || !preg_match('/^\d+(?:\.\d+)*$/D', $normalized) || !$this->matchesComposer($normalized, $normalizedConstraint)) {
                $errors[] = $name . ' 需要 ' . $constraint . '，当前 ' . ($version ?? '未安装');
                $arguments[] = '"' . $name . ':' . $constraint . '"';
            }
        }
        if ($errors !== []) {
            throw new RuntimeException('宿主 Composer 依赖不满足：' . implode('；', $errors)
                . '。请在宿主项目根目录执行：composer require ' . implode(' ', $arguments) . '，然后重启服务');
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
        $visit = function (string $code) use (&$visit, &$visiting, &$visited, &$sorted, $manifests): void {
            if (isset($visiting[$code])) {
                throw new RuntimeException('检测到插件循环依赖：' . $code);
            }
            if (isset($visited[$code]) || !isset($manifests[$code])) {
                return;
            }
            $visiting[$code] = true;
            $dependencies = array_keys($manifests[$code]->dependencies());
            sort($dependencies);
            foreach ($dependencies as $dependency) {
                $visit((string) $dependency);
            }
            unset($visiting[$code]);
            $visited[$code] = true;
            $sorted[$code] = $manifests[$code];
        };
        foreach (array_keys($manifests) as $code) {
            $visit((string) $code);
        }
        return $sorted;
    }

    public function assertNoEnabledDependents(string $code, array $manifests, array $installed): void
    {
        foreach ($manifests as $dependent => $manifest) {
            if ($dependent === $code || ($installed[$dependent]['lifecycle_state'] ?? '') !== 'enabled') {
                continue;
            }
            if (array_key_exists($code, $manifest->dependencies())) {
                throw new RuntimeException("存在已启用的反向依赖：{$dependent} -> {$code}");
            }
        }
    }

    /** Composer caret excludes the next breaking 0.x release, unlike legacy plugin constraints. */
    private function matchesComposer(string $version, string $constraint): bool
    {
        foreach (preg_split('/\s*,\s*|\s+/', trim($constraint)) ?: [] as $part) {
            if (str_starts_with($part, '^')) {
                $minimum = substr($part, 1);
                $parts = array_map('intval', explode('.', $minimum));
                $upper = $parts;
                $index = 0;
                while ($index < count($parts) - 1 && $parts[$index] === 0) { $index++; }
                $upper[$index]++;
                for ($i = $index + 1; $i < count($upper); $i++) { $upper[$i] = 0; }
                if (version_compare($version, $minimum, '<') || version_compare($version, implode('.', $upper), '>=')) { return false; }
            } elseif (!$this->matches($version, $part)) { return false; }
        }
        return true;
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
