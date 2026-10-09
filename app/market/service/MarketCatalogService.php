<?php

declare(strict_types=1);

namespace app\market\service;

use think\facade\Db;

/**
 * /api/v3 目录与分发：只暴露已上架插件的已发布版本，兼容性按客户端上报的平台上下文计算。
 */
final class MarketCatalogService
{
    public function categories(): array
    {
        $rows = Db::name('market_category')->where('status', 1)->order(['sort' => 'desc', 'id' => 'asc'])->field('id,name')->select()->toArray();
        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']], $rows);
    }

    public function search(array $context, string $keyword, int $categoryId, int $page, int $limit): array
    {
        $keyword = trim($keyword);
        $build = static function () use ($keyword, $categoryId) {
            $query = Db::name('market_plugin')->where('status', 1)
                ->whereIn('id', static function ($published): void {
                    $published->name('market_version')->where('status', 'published')->field('plugin_id');
                });
            if ($keyword !== '') {
                $like = '%' . addcslashes($keyword, '%_\\') . '%';
                $query->where(static function ($where) use ($like): void {
                    $where->whereLike('code', $like)->whereOr('name', 'like', $like)->whereOr('description', 'like', $like);
                });
            }
            if ($categoryId > 0) {
                $query->where('category_id', $categoryId);
            }
            return $query;
        };
        $total = $build()->count();
        $rows = $build()->order(['sort' => 'desc', 'id' => 'desc'])->page($page, $limit)->select()->toArray();
        return [
            'items' => array_map(fn (array $plugin): array => $this->pluginPayload($plugin, $context), $rows),
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function detail(string $code, array $context): ?array
    {
        $plugin = $this->listedPlugin($code);
        return $plugin ? $this->pluginPayload($plugin, $context) : null;
    }

    public function versions(string $code, array $context): ?array
    {
        $plugin = $this->listedPlugin($code);
        return $plugin ? $this->versionPayloads($plugin, $context) : null;
    }

    /**
     * @param list<array{code:string, code_version:string, db_version:string, modified:bool}> $installed
     */
    public function checkUpdates(array $installed, array $context): array
    {
        $items = [];
        foreach ($installed as $entry) {
            $code = (string) $entry['code'];
            $current = (string) $entry['code_version'];
            $dbVersion = (string) $entry['db_version'];
            $modified = (bool) $entry['modified'];
            $plugin = $this->listedPlugin($code);
            $item = [
                'code' => $code,
                'installed_version' => $current,
                'latest_version' => $current,
                'update_available' => false,
                'compatible' => false,
                'database_compatible' => true,
                'requires_manual_merge' => false,
                'reason' => '',
            ];
            if ($plugin === null) {
                $items[] = ['reason' => '插件市场中不存在该插件'] + $item;
                continue;
            }
            $latest = null;
            $latestIncompatible = '';
            foreach ($this->versionPayloads($plugin, $context) as $version) {
                if (!$version['compatible']) {
                    $latestIncompatible = $latestIncompatible ?: $version['compatible_reason'];
                    continue;
                }
                if ($latest === null || version_compare($version['code_version'], $latest['code_version'], '>')) {
                    $latest = $version;
                }
            }
            if ($latest === null) {
                $items[] = ['reason' => $latestIncompatible !== '' ? $latestIncompatible : '没有与当前平台兼容的已发布版本'] + $item;
                continue;
            }
            $newer = version_compare($latest['code_version'], $current, '>');
            $databaseCompatible = $this->databaseCompatible($latest['database_capability'], $dbVersion);
            $reason = match (true) {
                !$newer => '已是最新版本',
                !$databaseCompatible => '新版本的数据库能力低于当前已迁移版本 ' . $dbVersion . '，不能直接升级',
                $modified => '本地插件文件已被修改，升级会覆盖修改，请先人工合并',
                default => '',
            };
            $items[] = [
                'latest_version' => $latest['code_version'],
                'update_available' => $newer,
                'compatible' => true,
                'database_compatible' => $databaseCompatible,
                'requires_manual_merge' => $newer && $modified,
                'reason' => $reason,
            ] + $item;
        }
        return $items;
    }

    /** @return array{code:string, code_version:string, authorized:bool, message:string} */
    public function authorize(int $memberId, string $code, string $version, string $dbVersion, array $context): array
    {
        [$authorized, $message] = $this->authorization($memberId, $code, $version, $dbVersion, $context);
        return ['code' => $code, 'code_version' => $version, 'authorized' => $authorized, 'message' => $message];
    }

    /** @return array{0: ?array, 1: string} 下载描述与失败原因 */
    public function download(int $memberId, string $code, string $version, string $dbVersion, array $context): array
    {
        [$authorized, $message, $row] = $this->authorization($memberId, $code, $version, $dbVersion, $context);
        if (!$authorized || $row === null) {
            return [null, $message];
        }
        $plugin = $this->listedPlugin($code);
        $payload = $this->versionPayload($plugin, $row, $context);
        $expires = time() + MarketSettings::intValue('download_ttl', 600, 60, 86400);
        $url = MarketSettings::publicUrl() . '/api/v3/packages/' . (int) $row['id'] . '/' . $memberId . '/' . $expires . '/' . $this->downloadSignature((int) $row['id'], $memberId, $expires);
        return [['url' => $url] + $payload, ''];
    }

    /** @return array{path:string, name:string, size:int}|null */
    public function resolvePackage(int $versionId, int $memberId, int $expires, string $signature): ?array
    {
        if ($expires < time() || !hash_equals($this->downloadSignature($versionId, $memberId, $expires), $signature)) {
            return null;
        }
        $row = Db::name('market_version')->alias('v')->join('market_plugin p', 'p.id = v.plugin_id')
            ->where('v.id', $versionId)->where('v.status', 'published')->where('p.status', 1)
            ->field('v.id,v.package_path,v.size,v.sha256,v.code_version,p.code')->find();
        if (!$row) {
            return null;
        }
        $path = MarketSettings::storagePath((string) $row['package_path']);
        if (!is_file($path) || filesize($path) !== (int) $row['size']) {
            return null;
        }
        Db::name('market_version')->where('id', $versionId)->inc('download_count')->update();
        Db::name('market_download')->insert([
            'version_id' => $versionId,
            'member_id' => $memberId,
            'ip' => substr((string) request()->ip(), 0, 45),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return ['path' => $path, 'name' => $row['code'] . '-' . $row['code_version'] . '.zip', 'size' => (int) $row['size']];
    }

    /** @return array{0:bool, 1:string, 2:?array} */
    private function authorization(int $memberId, string $code, string $version, string $dbVersion, array $context): array
    {
        $plugin = $this->listedPlugin($code);
        if ($plugin === null) {
            return [false, '插件不存在或已下架', null];
        }
        $row = Db::name('market_version')->where('plugin_id', $plugin['id'])->where('code_version', $version)->where('status', 'published')->find();
        if (!$row) {
            return [false, '该版本不存在或未发布', null];
        }
        $payload = $this->versionPayload($plugin, $row, $context);
        if (!$payload['compatible']) {
            return [false, $payload['compatible_reason'], null];
        }
        if (!$this->databaseCompatible((string) $row['database_capability'], $dbVersion)) {
            return [false, '该版本的数据库能力低于当前已迁移版本 ' . $dbVersion, null];
        }
        if ($plugin['license_type'] !== 'free' && !$this->hasGrant((int) $plugin['id'], $memberId)) {
            $message = MarketOrderService::plans($plugin) === []
                ? '当前账号未获得该插件授权，请联系市场管理员'
                : '该插件需要购买授权，请前往 ' . $this->storeUrl($plugin) . ' 购买后再安装';
            return [false, $message, null];
        }
        return [true, '', $row];
    }

    private function hasGrant(int $pluginId, int $memberId): bool
    {
        return Db::name('market_grant')->where('plugin_id', $pluginId)->where('member_id', $memberId)->where('status', 1)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->whereOr('expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->count() > 0;
    }

    /** 客户端禁止部署数据库能力低于当前已迁移版本的代码（DatabaseCapabilityGuard）。 */
    private function databaseCompatible(string $capability, string $dbVersion): bool
    {
        return $dbVersion === '' || strnatcmp($capability, $dbVersion) >= 0;
    }

    private function listedPlugin(string $code): ?array
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1) {
            return null;
        }
        $plugin = Db::name('market_plugin')->where('code', $code)->where('status', 1)->find();
        return $plugin ?: null;
    }

    private function pluginPayload(array $plugin, array $context): array
    {
        return [
            'id' => (int) $plugin['id'],
            'code' => (string) $plugin['code'],
            'name' => (string) $plugin['name'],
            'description' => (string) $plugin['description'],
            'author' => (string) $plugin['author'],
            'versions' => $this->versionPayloads($plugin, $context),
            'price_text' => $this->priceText($plugin),
            'store_url' => $this->storeUrl($plugin),
        ];
    }

    private function priceText(array $plugin): string
    {
        if ($plugin['license_type'] === 'free') {
            return '免费';
        }
        $plans = MarketOrderService::plans($plugin);
        $parts = [];
        if (isset($plans['perpetual'])) {
            $parts[] = '买断 ¥' . MarketStoreService::money($plans['perpetual']);
        }
        if (isset($plans['yearly'])) {
            $parts[] = '¥' . MarketStoreService::money($plans['yearly']) . '/年';
        }
        return $parts === [] ? '需授权' : implode(' · ', $parts);
    }

    private function storeUrl(array $plugin): string
    {
        return MarketSettings::publicUrl() . '/plugin/' . $plugin['code'];
    }

    private function versionPayloads(array $plugin, array $context): array
    {
        $rows = Db::name('market_version')->where('plugin_id', $plugin['id'])->where('status', 'published')->select()->toArray();
        usort($rows, static fn (array $left, array $right): int => version_compare((string) $right['code_version'], (string) $left['code_version']));
        return array_map(fn (array $row): array => $this->versionPayload($plugin, $row, $context), $rows);
    }

    private function versionPayload(array $plugin, array $row, array $context): array
    {
        $requires = json_decode((string) ($row['requires'] ?? ''), true);
        $requires = is_array($requires) ? $requires : [];
        [$compatible, $reason] = $this->compatibility($requires, $context);
        if (array_key_exists('plugins', $requires) && $requires['plugins'] === []) {
            $requires['plugins'] = (object) [];
        }
        return [
            'id' => (int) $row['id'],
            'code' => (string) $plugin['code'],
            'code_version' => (string) $row['code_version'],
            'changelog' => (string) ($row['changelog'] ?? ''),
            'compatible' => $compatible,
            'requires' => $requires === [] ? (object) [] : $requires,
            'compatible_range' => $this->compatibleRange($requires),
            'published_at' => (string) ($row['published_at'] ?? ''),
            'sha256' => (string) $row['sha256'],
            'signature' => (string) $row['signature'],
            'signature_algorithm' => MarketProtocol::SIGNATURE_ALGORITHM,
            'size' => (int) $row['size'],
            'manifest_schema' => MarketProtocol::MANIFEST_SCHEMA,
            'package_format' => MarketProtocol::PACKAGE_FORMAT,
            'tree_hash' => (string) $row['tree_hash'],
            'database_capability' => (string) $row['database_capability'],
            'applications' => ['app' => (bool) $row['app_enabled'], 'admin' => (bool) $row['admin_enabled']],
            'compatible_reason' => $reason,
        ];
    }

    /** @return array{0:bool, 1:string} */
    private function compatibility(array $requires, array $context): array
    {
        if ((int) ($context['manifest_schema'] ?? 0) !== MarketProtocol::MANIFEST_SCHEMA
            || (string) ($context['package_format'] ?? '') !== MarketProtocol::PACKAGE_FORMAT) {
            return [false, '客户端不支持 manifest schema 2 / ' . MarketProtocol::PACKAGE_FORMAT . ' 包格式'];
        }
        $platform = trim((string) ($context['platform_version'] ?? ''));
        $php = trim((string) ($context['php_version'] ?? ''));
        $funadminConstraint = trim((string) ($requires['funadmin'] ?? ''));
        $phpConstraint = trim((string) ($requires['php'] ?? ''));
        if ($funadminConstraint !== '' && ($platform === '' || !VersionConstraint::matches($platform, $funadminConstraint))) {
            return [false, 'FunAdmin ' . ($platform ?: '未知版本') . ' 不满足 ' . $funadminConstraint];
        }
        if ($phpConstraint !== '' && ($php === '' || !VersionConstraint::matches($php, $phpConstraint))) {
            return [false, 'PHP ' . ($php ?: '未知版本') . ' 不满足 ' . $phpConstraint];
        }
        return [true, ''];
    }

    private function compatibleRange(array $requires): string
    {
        $parts = [];
        if (trim((string) ($requires['funadmin'] ?? '')) !== '') {
            $parts[] = 'FunAdmin ' . trim((string) $requires['funadmin']);
        }
        if (trim((string) ($requires['php'] ?? '')) !== '') {
            $parts[] = 'PHP ' . trim((string) $requires['php']);
        }
        return implode('；', $parts);
    }

    private function downloadSignature(int $versionId, int $memberId, int $expires): string
    {
        return hash_hmac('sha256', $versionId . '|' . $memberId . '|' . $expires, MarketSigner::downloadSecret());
    }
}
