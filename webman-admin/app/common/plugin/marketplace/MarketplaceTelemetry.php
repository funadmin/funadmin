<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace;

use app\common\plugin\marketplace\dto\MarketplaceProtocol;
use app\common\plugin\model\Plugin;
use GuzzleHttp\Client;

/**
 * 安装统计上报：仅在配置了自建市场地址时启用，仅上报来自云市场的插件；
 * 尽力而为、短超时、忽略一切失败，且不经过 NativeMarketplaceAdapter::call()，避免 401 登出市场账号。
 */
final class MarketplaceTelemetry
{
    public const EVENTS = ['install', 'update', 'uninstall', 'enable', 'disable'];

    public function __construct(
        private readonly ?NativeMarketplaceHttpTransport $transport,
        private readonly CloudAccountSession $session,
        private readonly string $platformVersion,
        private readonly string $phpVersion,
        private readonly mixed $siteId = null
    ) {
    }

    public static function create(): self
    {
        $marketplace = (array) config('plugins.marketplace');
        $url = trim((string) ($marketplace['url'] ?? ''));
        $enabled = $url !== '' && ($marketplace['telemetry'] ?? true) !== false;
        return new self(
            $enabled ? new NativeMarketplaceHttpTransport(new Client(), $url, 5, 3) : null,
            new CloudAccountSession(new ThinkSessionStore()),
            (string) config('funadmin.version'),
            PHP_VERSION
        );
    }

    /** 在生命周期操作成功后调用；插件记录用于判断来源与版本（卸载前读取）。 */
    public function report(string $event, ?array $plugin, string $fromVersion = ''): void
    {
        if ($this->transport === null || !in_array($event, self::EVENTS, true) || $plugin === null || ($plugin['source'] ?? '') !== 'cloud') {
            return;
        }
        try {
            ($this->transport)('/api/v3/telemetry/installs', [
                'site_id' => is_callable($this->siteId) ? ($this->siteId)() : MarketplaceSiteIdentity::id(),
                'manifest_schema' => MarketplaceProtocol::MANIFEST_SCHEMA,
                'package_format' => MarketplaceProtocol::PACKAGE_FORMAT,
                'platform_version' => $this->platformVersion,
                'php_version' => $this->phpVersion,
                'events' => [[
                    'code' => (string) ($plugin['code'] ?? ''),
                    'event' => $event,
                    'version' => (string) ($plugin['version'] ?? ''),
                    'from_version' => $fromVersion,
                    'occurred_at' => date(DATE_ATOM),
                ]],
            ], $this->session->token());
        } catch (\Throwable) {
            // 统计失败不影响插件生命周期操作。
        }
    }

    public static function pluginRecord(string $code): ?array
    {
        $record = Plugin::withTrashed()->where('code', $code)->field('code,version,source')->find();
        return $record ? $record->toArray() : null;
    }
}
