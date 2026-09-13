<?php

declare(strict_types=1);

namespace app\console\development\service;

use app\common\crud\PathGuard;
use app\common\plugin\model\Plugin;
use app\common\plugin\sdk\Manifest;
use app\console\authorization\service\AdminAuthorizationService;
use app\console\plugin\service\DevPluginService;
use Closure;
use InvalidArgumentException;

/** 业务目标与表归属的只读边界；生成事务须在插件生命周期锁内再次调用。 */
final class BusinessTargetService
{
    private readonly Closure $authorized;
    private readonly Closure $states;
    private readonly Closure $options;
    private readonly Closure $owners;
    private readonly Closure $tableExists;

    public function __construct(
        private readonly string $root,
        private readonly string $defaultConnection = 'mysql',
        ?callable $authorized = null,
        ?callable $states = null,
        ?callable $options = null,
        ?callable $owners = null,
        ?callable $tableExists = null
    ) {
        $this->authorized = Closure::fromCallable($authorized ?? static fn (): bool => (new AdminAuthorizationService())->nodeAccess('console/development.devplugin/options'));
        $this->states = Closure::fromCallable($states ?? static function (): array {
            $result = [];
            foreach (Plugin::whereNull('deleted_at')->select() as $record) $result[(string) $record->code] = $record->toArray();
            return $result;
        });
        $this->options = Closure::fromCallable($options ?? fn (): array => (new DevPluginService($this->root))->options(true));
        $this->owners = Closure::fromCallable($owners ?? fn (): array => $this->tableOwners());
        $this->tableExists = Closure::fromCallable($tableExists ?? function (string $table): bool {
            foreach ((new DevCrudService($this->root, [$this->defaultConnection]))->tables($this->defaultConnection) as $row) {
                if ($row['name'] === $table) return true;
            }
            return false;
        });
    }

    public function candidates(): array
    {
        $items = [['type' => 'core', 'pluginCode' => null, 'name' => '核心后台', 'scope' => 'console', 'available' => true, 'reason' => null]];
        if (($this->authorized)()) {
            $states = ($this->states)();
            foreach (($this->options)() as $option) {
                $code = (string) ($option['code'] ?? '');
                if (preg_match('/^[a-z][a-z0-9]*$/D', $code) !== 1) continue;
                $reason = $this->unavailableReason($option, $states[$code] ?? []);
                $items[] = ['type' => 'plugin', 'pluginCode' => $code, 'name' => $option['name'], 'scope' => 'console',
                    'available' => $reason === null, 'reason' => $reason];
            }
        }
        return ['list' => $items, 'defaultConnection' => $this->defaultConnection, 'migrationPath' => 'database/migrations'];
    }

    /** 恢复只复用开发授权，不以新生成的可用状态阻断失败事务回滚。 */
    public function assertRecovery(array $target): void
    {
        if (($target['type'] ?? 'core') !== 'plugin') return;
        if (!($this->authorized)()) throw new InvalidArgumentException('BUSINESS_TARGET_FORBIDDEN');
        if (preg_match('/^[a-z][a-z0-9]*$/D', (string) ($target['plugin'] ?? '')) !== 1
            || ($target['scope'] ?? '') !== 'console') {
            throw new InvalidArgumentException('BUSINESS_TARGET_UNAVAILABLE');
        }
    }

    public function assertSelection(array $target, string $connection, string $table, bool $generated = false): void
    {
        if (($target['type'] ?? 'core') !== 'plugin') return;
        if (!($this->authorized)()) throw new InvalidArgumentException('BUSINESS_TARGET_FORBIDDEN');
        if ($connection !== $this->defaultConnection) throw new InvalidArgumentException('BUSINESS_DEFAULT_CONNECTION_ONLY');
        $code = (string) $target['pluginCode'];
        $candidates = array_column($this->candidates()['list'], null, 'pluginCode');
        if (($candidates[$code]['available'] ?? false) !== true) throw new InvalidArgumentException('BUSINESS_TARGET_UNAVAILABLE');
        $owners = ($this->owners)();
        if (isset($owners[$table]) && $owners[$table] !== $code) throw new InvalidArgumentException('BUSINESS_TABLE_FORBIDDEN');
        if (($target['tableStrategy'] ?? '') === 'owned') {
            $prefix = (string) \think\facade\Config::get('database.connections.' . $connection . '.prefix', '');
            if (!str_starts_with($table, $prefix . $code . '_')) throw new InvalidArgumentException('BUSINESS_TABLE_PREFIX_REQUIRED');
            if (!$generated && ($this->tableExists)($table)) throw new InvalidArgumentException('BUSINESS_TABLE_ALREADY_EXISTS');
        } elseif (($target['tableStrategy'] ?? '') === 'external') {
            if (isset($owners[$table])) throw new InvalidArgumentException('BUSINESS_TABLE_FORBIDDEN');
            if (!($this->tableExists)($table)) throw new InvalidArgumentException('BUSINESS_EXTERNAL_TABLE_MISSING');
        } else {
            throw new InvalidArgumentException('BUSINESS_TABLE_STRATEGY_INVALID');
        }
    }

    /** 原因只使用固定机器码和文案，绝不透传异常、磁盘路径或生命周期令牌。 */
    private function unavailableReason(array $option, array $state): ?array
    {
        $code = match (true) {
            !empty($state['recovery_token']) => 'RECOVERY_LOCKED',
            !empty($state['operation_token']) => 'OPERATION_LOCKED',
            !in_array($state['lifecycle_state'] ?? 'discovered', ['discovered', 'enabled', 'disabled'], true) => 'LIFECYCLE_BLOCKED',
            !in_array('console', $option['scopes'] ?? [], true) => 'CONSOLE_MISSING',
            default => $this->writeBlockReason($option),
        };
        if ($code === null) return null;
        $message = match ($code) {
            'RECOVERY_LOCKED' => '插件存在待恢复操作，请先完成恢复',
            'OPERATION_LOCKED' => '插件正在发布或执行生命周期操作，请稍后重试',
            'LIFECYCLE_BLOCKED' => '插件当前生命周期状态不允许业务开发',
            'CONSOLE_MISSING' => '插件缺少 console 后台目录',
            'ADMIN_WEB_MISSING' => '插件缺少标准 admin-web 前端目录',
            'MIGRATION_PATH_UNSUPPORTED' => '插件未使用标准数据库迁移目录',
            'READ_ONLY' => '插件目录或必要文件不可写',
            default => '插件配置不可用，请检查本地插件',
        };
        return ['code' => 'BUSINESS_TARGET_' . $code, 'message' => $message];
    }

    private function writeBlockReason(array $option): ?string
    {
        if (isset($option['businessWritable'])) return $option['businessWritable'] === true ? null : 'READ_ONLY';
        try {
            $directory = PathGuard::resolve($this->root, 'plugins/' . $option['code'], '插件目录');
            $data = Manifest::fromDirectory($directory)->toArray();
            if (($data['adminWeb']['source'] ?? '') !== 'admin-web' || !is_dir($directory . '/admin-web')) return 'ADMIN_WEB_MISSING';
            if (($data['migrations']['path'] ?? 'database/migrations') !== 'database/migrations') return 'MIGRATION_PATH_UNSUPPORTED';
            foreach ([$directory, $directory . '/plugin.json', $directory . '/app/console', $directory . '/admin-web'] as $path) {
                clearstatcache(true, $path);
                if (!is_writable($path)) return 'READ_ONLY';
            }
            return null;
        } catch (\Throwable) {
            return 'CONFIGURATION_INVALID';
        }
    }

    /** 以已有核心及插件迁移声明建立拒绝表；未知或冲突归属不授予所有权。 */
    private function tableOwners(): array
    {
        $owners = [];
        $files = glob($this->root . '/database/migrations/*.sql') ?: [];
        foreach (glob($this->root . '/plugins/*/database/migrations/*.sql') ?: [] as $file) $files[] = $file;
        foreach (glob($this->root . '/plugins/*/migrations/*.sql') ?: [] as $file) $files[] = $file;
        foreach ($files as $file) {
            if (is_link($file)) throw new InvalidArgumentException('BUSINESS_TABLE_FORBIDDEN');
            $owner = preg_match('#/plugins/([^/]+)/#', $file, $match) ? $match[1] : 'core';
            preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z][a-z0-9_]*)`?/i', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $table) $owners[$table] = isset($owners[$table]) && $owners[$table] !== $owner ? 'conflict' : $owner;
        }
        return $owners;
    }
}
