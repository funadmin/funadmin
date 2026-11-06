<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/** Reads JSON only: inspecting a staged package must not execute its PHP. */
final class ComposerConflictDetector
{
    public function __construct(private readonly HostPackageRepository $host = new HostPackageRepository()) {}

    public static function bundledPackages(string $directory): array
    {
        $file = rtrim($directory, '/\\') . '/vendor/composer/installed.json';
        if (!is_file($file)) { return []; }
        try { $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new RuntimeException('插件 Composer 包元数据无效：' . $file, 0, $e); }
        if (!is_array($data)) { throw new RuntimeException('插件 Composer 包元数据必须为对象或数组'); }
        $packages = $data['packages'] ?? $data;
        if (!is_array($packages) || !array_is_list($packages)) { throw new RuntimeException('插件 Composer packages 必须为数组'); }
        $result = [];
        foreach ($packages as $package) {
            if (!is_array($package) || !is_string($package['name'] ?? null) || !is_string($package['version'] ?? null)) {
                throw new RuntimeException('插件 Composer 包缺少 name/version');
            }
            $result[$package['name']] = $package['version'];
        }
        return $result;
    }

    /** @param array<string,string> $otherPluginDirectories */
    public function detect(string $directory, array $otherPluginDirectories = []): array
    {
        $own = self::bundledPackages($directory);
        if ($own === []) { return []; }
        $sources = ['host' => $this->host->packages()];
        foreach ($otherPluginDirectories as $code => $path) {
            if (basename($directory) !== (string) $code) { $sources['plugin:' . $code] = self::bundledPackages($path); }
        }
        $conflicts = [];
        foreach ($own as $name => $version) {
            foreach ($sources as $source => $packages) {
                if (!isset($packages[$name])) { continue; }
                $other = $packages[$name];
                $a = self::compatibilityLine($version); $b = self::compatibilityLine($other);
                if ($a !== null && $a === $b) { continue; }
                $conflicts[] = ['package' => $name, 'pluginVersion' => $version, 'otherVersion' => $other, 'source' => $source,
                    'reason' => $a === null || $b === null ? '开发或预发布版本无法可靠判断兼容性' : '版本兼容范围不同'];
            }
        }
        usort($conflicts, static fn ($a, $b) => [$a['package'], $a['source']] <=> [$b['package'], $b['source']]);
        return $conflicts;
    }

    public static function fingerprint(array $conflicts): string
    {
        $rows = array_map(static fn ($c) => array_intersect_key($c, array_flip(['package', 'source', 'pluginVersion', 'otherVersion'])), $conflicts);
        $rows = array_map(static fn ($c) => [$c['package'], $c['source'], $c['pluginVersion'], $c['otherVersion']], $rows);
        sort($rows);
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private static function compatibilityLine(string $version): ?string
    {
        if (!preg_match('/^v?(\d+)\.(\d+)(?:\.\d+)*$/D', $version, $m)) { return null; }
        return $m[1] === '0' ? '0.' . $m[2] : $m[1];
    }
}
