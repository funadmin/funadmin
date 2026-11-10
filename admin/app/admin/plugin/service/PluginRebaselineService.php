<?php

declare(strict_types=1);

namespace app\admin\plugin\service;

use app\common\plugin\model\Plugin;
use app\common\plugin\model\PluginResource;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use RuntimeException;
use support\Db;

/**
 * 重建插件发布登记（plugin_resource）基线，并提供开发环境的源码同步。
 *
 * 登记行由发布器自身的 rebaseline() 生成，与真实发布使用同一套路径映射、sha256 与 tree hash。
 * 默认拒绝在已安装文件与插件源不一致时改写登记，只有显式 --force 才以所选一侧为准。
 */
final class PluginRebaselineService
{
    public const FROM_INSTALLED = 'installed';
    public const FROM_SOURCE = 'source';
    private const MUTABLE_STATES = ['disabled', 'enabled', 'failed'];

    public function __construct(
        private readonly ?string $projectRoot = null,
        private readonly ?PluginInfrastructureService $infrastructure = null
    ) {
    }

    /** 只读计算：差异、将写入的登记行与相对当前登记的变更，不加锁也不写库。 */
    public function plan(string $code, string $from): array
    {
        $this->installedRecord($code);
        return $this->compute($code, $from, 'rebaseline-' . bin2hex(random_bytes(12)))
            + ['staleJournals' => $this->staleTokens($code)];
    }

    /** 在生命周期锁与发布锁内重建登记；不一致时除非 $force 否则拒绝。 */
    public function apply(string $code, string $from, bool $force = false): array
    {
        $lock = PluginInfrastructureService::lifecycleLock($this->projectRoot)->acquire($code);
        try {
            return $this->rebaselineLocked($code, $from, $force, 'rebaseline');
        } finally {
            $lock->release();
        }
    }

    /**
     * 仅限开发环境：把插件源完整发布到已安装位置（经 publication journal，可回滚），然后按源重建登记。
     * 已安装位置的手工改动会被源码覆盖，这正是 sync 的语义。
     */
    public function sync(string $code): array
    {
        if (!self::isDevelopmentEnvironment()) {
            throw new RuntimeException('plugin:sync 仅限开发环境（需要 APP_DEBUG=true 或 APP_ENV=dev）');
        }
        $lock = PluginInfrastructureService::lifecycleLock($this->projectRoot)->acquire($code);
        try {
            // 先以当前磁盘为基线接管（含漂移），发布时才不会被冲突检测拦下，且漂移文件仍可随 journal 回滚。
            $this->rebaselineLocked($code, self::FROM_INSTALLED, true, 'sync');
            $token = 'sync-' . bin2hex(random_bytes(12));
            $infrastructure = $this->infrastructure();
            $infrastructure->publishResources($this->manifest($code), $token);
            $infrastructure->completePublishedResources($token);
            $result = $this->rebaselineLocked($code, self::FROM_SOURCE, false, 'sync');
        } finally {
            $lock->release();
        }
        (new PluginService($this->projectRoot))->refreshActivationCache();
        return $result;
    }

    public static function isDevelopmentEnvironment(): bool
    {
        $environment = strtolower(trim((string) (getenv('APP_ENV') ?: '')));
        return config('app.debug') === true || in_array($environment, ['dev', 'development', 'local'], true);
    }

    private function rebaselineLocked(string $code, string $from, bool $force, string $operation): array
    {
        $record = $this->installedRecord($code);
        $this->assertNoStalePublication($code);
        return $this->infrastructure()->withPublicationLock(function () use ($code, $from, $force, $operation, $record): array {
            $token = $operation . '-' . bin2hex(random_bytes(12));
            $plan = $this->compute($code, $from, $token);
            if ($plan['differences'] !== [] && !$force) {
                throw new RuntimeException(
                    '已安装文件与插件源不一致，拒绝 rebaseline（确认以 ' . $from . ' 为准请加 --force）：'
                    . implode(', ', $plan['differences'])
                );
            }
            Db::transaction(function () use ($code, $plan, $operation, $from, $force, $token, $record): void {
                $infrastructure = $this->infrastructure();
                $infrastructure->appPublisher()->replaceRegistry($code, $plan['native']);
                $infrastructure->publisher()->replaceRegistry($code, $plan['files']);
                (new PluginOperationAuditService())->maintenance(
                    $code,
                    $operation,
                    $token,
                    (string) $record->version,
                    $plan['version'],
                    [
                        'from' => $from,
                        'force' => $force,
                        'differences' => $plan['differences'],
                        'changes' => $plan['changes'],
                    ]
                );
            });
            unset($plan['native'], $plan['files']);
            return ['applied' => true] + $plan;
        });
    }

    private function compute(string $code, string $from, string $token): array
    {
        if (!in_array($from, [self::FROM_INSTALLED, self::FROM_SOURCE], true)) {
            throw new RuntimeException('--from 只能是 installed 或 source');
        }
        $manifest = $this->manifest($code);
        $infrastructure = $this->infrastructure();
        $native = $infrastructure->appPublisher()->rebaseline($manifest, $from, $token);
        $files = $infrastructure->publisher()->rebaseline($manifest, $from, $token);
        $differences = array_values(array_unique(array_merge($native['differences'], $files['differences'])));
        sort($differences, SORT_STRING);
        $next = array_merge($native['records'], $files['records']);
        return [
            'code' => $code,
            'version' => $manifest->version(),
            'from' => $from,
            'differences' => $differences,
            'changes' => $this->changes(PluginResource::where('plugin_code', $code)->get()->toArray(), $next),
            'records' => count($next),
            'native' => $native['records'],
            'files' => $files['records'],
            'applied' => false,
        ];
    }

    /** @return array{added: list<string>, removed: list<string>, changed: list<string>, unchanged: int} */
    private function changes(array $current, array $next): array
    {
        $index = static function (array $rows): array {
            $indexed = [];
            foreach ($rows as $row) {
                $indexed[(string) $row['target_path']] = [
                    (string) ($row['resource_type'] ?? ''),
                    (string) ($row['publication_unit'] ?? ''),
                    (string) ($row['source_path'] ?? ''),
                    (string) ($row['sha256'] ?? ''),
                    (string) ($row['tree_hash'] ?? ''),
                ];
            }
            ksort($indexed, SORT_STRING);
            return $indexed;
        };
        $before = $index($current);
        $after = $index($next);
        $changed = [];
        $unchanged = 0;
        foreach (array_intersect_key($after, $before) as $path => $row) {
            if ($row === $before[$path]) {
                $unchanged++;
            } else {
                $changed[] = $path;
            }
        }
        return [
            'added' => array_keys(array_diff_key($after, $before)),
            'removed' => array_keys(array_diff_key($before, $after)),
            'changed' => $changed,
            'unchanged' => $unchanged,
        ];
    }

    private function installedRecord(string $code): Plugin
    {
        PluginScaffolder::assertValidName($code);
        $record = Plugin::where('code', $code)->first();
        if (!$record) {
            throw new RuntimeException('插件尚未安装：' . $code);
        }
        if ((string) ($record->operation_token ?? '') !== '') {
            throw new RuntimeException('插件正在执行生命周期操作：' . $code);
        }
        if (!in_array((string) $record->lifecycle_state, self::MUTABLE_STATES, true)) {
            throw new RuntimeException('插件处于 ' . $record->lifecycle_state . ' 状态，不能重建发布基线');
        }
        return $record;
    }

    private function assertNoStalePublication(string $code): void
    {
        $tokens = $this->staleTokens($code);
        if ($tokens !== []) {
            throw new RuntimeException('插件存在未完成 publication journal，请先执行 plugin:publication-recover <token>：' . implode(', ', $tokens));
        }
    }

    /** @return list<string> */
    private function staleTokens(string $code): array
    {
        return array_map(
            static fn (array $journal): string => (string) ($journal['token'] ?? ''),
            $this->infrastructure()->appPublisher()->stale($code)
        );
    }

    private function manifest(string $code): Manifest
    {
        return Manifest::fromDirectory(($this->projectRoot ?? base_path()) . '/plugins/' . $code);
    }

    private function infrastructure(): PluginInfrastructureService
    {
        return $this->infrastructure ?? ($this->projectRoot === null
            ? \support\Container::get(PluginInfrastructureService::class)
            : new PluginInfrastructureService(projectRoot: $this->projectRoot));
    }
}
