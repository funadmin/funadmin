<?php

declare(strict_types=1);

namespace app\admin\plugin\service;

use app\common\crud\PathGuard;
use app\common\plugin\model\Plugin;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use app\common\service\database\MigrationService;
use RuntimeException;

/**
 * plugin:migrate --adopt：插件 migration 的效果已经存在（例如旧版生命周期钩子里的运行时 DDL 先建好了列）时，
 * 核对当前结构后只登记 system_migration，不执行 SQL。核对与登记由 MigrationService::adoptVersion 完成，
 * 登记的 checksum 与正常执行时相同；不改插件生命周期状态，随后的更新/迁移会跳过已登记版本。
 */
final class PluginMigrationAdoptionService
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
    }

    public function adopt(string $code, string $version, bool $dryRun = false): array
    {
        PluginScaffolder::assertValidName($code);
        $version = str_ends_with($version, '.sql') ? substr($version, 0, -4) : $version;
        $record = Plugin::where('code', $code)->first();
        if ($record === null) throw new RuntimeException('插件尚未安装：' . $code);
        $manifest = Manifest::fromDirectory(($this->projectRoot ?? base_path()) . '/plugins/' . $code);
        $path = $manifest->toArray()['migrations']['path'] ?? null;
        if (!is_string($path) || $path === '') throw new RuntimeException('插件没有声明 migrations.path：' . $code);
        $directory = PathGuard::resolve($manifest->directory(), $path, '插件迁移');
        $scope = 'plugin:' . strtolower($code);
        $migrations = MigrationService::instance();
        if ($dryRun) return $migrations->adoptVersion($directory, $version, $scope, true);
        $lock = PluginInfrastructureService::lifecycleLock($this->projectRoot)->acquire($code);
        try {
            return $migrations->adoptVersion($directory, $version, $scope, false, static function (array $report) use ($code, $record, $manifest): void {
                (new PluginOperationAuditService())->maintenance($code, 'migration_adopt', 'adopt-' . bin2hex(random_bytes(12)),
                    (string) $record->version, $manifest->version(), [
                        'scope' => $report['scope'], 'version' => $report['version'], 'checksum' => $report['checksum'],
                        'effects' => array_map(static fn (array $effect): string => $effect['kind'] . ' ' . $effect['target'] . '：' . $effect['detail'], $report['effects']),
                    ]);
            });
        } finally {
            $lock->release();
        }
    }
}
