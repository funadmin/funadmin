<?php

declare(strict_types=1);

namespace app\market\service\payment;

use app\market\service\MarketSettings;
use RuntimeException;

/**
 * 支付渠道配置。私钥与 APIv3 密钥属于机密，保存在插件私有存储（0600），不进入插件目录与历史归档。
 */
final class PaymentConfig
{
    private const FILE = 'keys/payment.json';

    /** 机密字段：后台读取时只返回「是否已设置」，保存时留空表示保持原值。 */
    public const SECRETS = [
        'alipay' => ['private_key'],
        'wechat' => ['private_key', 'api_v3_key'],
    ];

    private const DEFAULTS = [
        'alipay' => [
            'enabled' => false,
            'sandbox' => false,
            'app_id' => '',
            'private_key' => '',
            'alipay_public_key' => '',
        ],
        'wechat' => [
            'enabled' => false,
            'mch_id' => '',
            'app_id' => '',
            'serial_no' => '',
            'private_key' => '',
            'api_v3_key' => '',
            'platform_public_key_id' => '',
            'platform_public_key' => '',
        ],
        'mock' => [
            'enabled' => false,
        ],
    ];

    public static function all(): array
    {
        $file = MarketSettings::storagePath(self::FILE);
        $stored = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $stored = is_array($stored) ? $stored : [];
        $config = [];
        foreach (self::DEFAULTS as $channel => $defaults) {
            $config[$channel] = array_replace($defaults, array_intersect_key((array) ($stored[$channel] ?? []), $defaults));
        }
        return $config;
    }

    public static function channel(string $channel): array
    {
        return self::all()[$channel] ?? throw new RuntimeException('未知支付渠道');
    }

    /** 可供前台选择的渠道；模拟支付仅在调试模式且显式开启时出现。 */
    public static function enabledChannels(): array
    {
        $config = self::all();
        $channels = [];
        if ($config['alipay']['enabled'] && AlipayChannel::configured($config['alipay'])) {
            $channels[] = 'alipay';
        }
        if ($config['wechat']['enabled'] && WechatChannel::configured($config['wechat'])) {
            $channels[] = 'wechat';
        }
        if ($config['mock']['enabled'] && self::mockAllowed()) {
            $channels[] = 'mock';
        }
        return $channels;
    }

    public static function mockAllowed(): bool
    {
        return (bool) env('APP_DEBUG', false);
    }

    /** 后台展示用：机密字段替换为是否已设置。 */
    public static function masked(): array
    {
        $config = self::all();
        foreach (self::SECRETS as $channel => $fields) {
            foreach ($fields as $field) {
                $config[$channel][$field . '_set'] = trim((string) $config[$channel][$field]) !== '';
                unset($config[$channel][$field]);
            }
        }
        $config['mock']['allowed'] = self::mockAllowed();
        return $config;
    }

    public static function save(array $input): void
    {
        $current = self::all();
        foreach (self::DEFAULTS as $channel => $defaults) {
            $incoming = (array) ($input[$channel] ?? []);
            foreach ($defaults as $field => $default) {
                if (!array_key_exists($field, $incoming)) {
                    continue;
                }
                $value = $incoming[$field];
                if (is_bool($default)) {
                    $current[$channel][$field] = (bool) $value;
                    continue;
                }
                $value = trim((string) $value);
                if (in_array($field, self::SECRETS[$channel] ?? [], true) && $value === '') {
                    continue;
                }
                $current[$channel][$field] = $value;
            }
        }
        if ($current['wechat']['api_v3_key'] !== '' && strlen($current['wechat']['api_v3_key']) !== 32) {
            throw new RuntimeException('微信支付 APIv3 密钥必须是 32 位');
        }
        foreach (['alipay' => ['private_key', 'alipay_public_key'], 'wechat' => ['private_key', 'platform_public_key']] as $channel => $fields) {
            foreach ($fields as $field) {
                if ($current[$channel][$field] !== '' && PemKey::load($current[$channel][$field], str_contains($field, 'public')) === null) {
                    throw new RuntimeException(($channel === 'alipay' ? '支付宝' : '微信支付') . ' ' . $field . ' 不是有效的 RSA 密钥');
                }
            }
        }
        $file = MarketSettings::storagePath(self::FILE);
        MarketSettings::ensureDirectory(dirname($file), 0700);
        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, json_encode($current, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            throw new RuntimeException('无法写入支付配置');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('无法保存支付配置');
        }
    }
}
