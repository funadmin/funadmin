<?php

declare(strict_types=1);

namespace app\market\service;

use app\market\service\payment\AlipayChannel;
use app\market\service\payment\MockChannel;
use app\market\service\payment\PaymentChannel;
use app\market\service\payment\PaymentConfig;
use app\market\service\payment\PaymentResult;
use app\market\service\payment\WechatChannel;
use RuntimeException;
use think\facade\Db;

/**
 * 订单与支付入账。入账以订单行锁保证幂等：同一订单无论收到多少次回调或查询结果，只开通一次授权。
 */
final class MarketOrderService
{
    public const PLANS = ['perpetual' => '买断（永久授权）', 'yearly' => '按年（授权一年）'];
    public const CHANNELS = ['alipay' => '支付宝', 'wechat' => '微信支付', 'mock' => '模拟支付', 'manual' => '人工确认'];
    private const PAY_WINDOW = 1800;

    public static function channel(string $channel): PaymentChannel
    {
        return match ($channel) {
            'alipay' => new AlipayChannel(PaymentConfig::channel('alipay')),
            'wechat' => new WechatChannel(PaymentConfig::channel('wechat')),
            'mock' => new MockChannel(),
            default => throw new RuntimeException('不支持的支付渠道'),
        };
    }

    /** 插件可购买的方案与价格（分）。 */
    public static function plans(array $plugin): array
    {
        if (($plugin['license_type'] ?? 'free') !== 'grant') {
            return [];
        }
        $plans = [];
        if ((int) $plugin['price_perpetual'] > 0) {
            $plans['perpetual'] = (int) $plugin['price_perpetual'];
        }
        if ((int) $plugin['price_yearly'] > 0) {
            $plans['yearly'] = (int) $plugin['price_yearly'];
        }
        return $plans;
    }

    public function create(int $memberId, string $code, string $plan, string $channel): array
    {
        $plugin = Db::name('market_plugin')->where('code', $code)->where('status', 1)->find();
        if (!$plugin) {
            throw new RuntimeException('插件不存在或已下架');
        }
        $plans = self::plans($plugin);
        if (!isset($plans[$plan])) {
            throw new RuntimeException('该插件不提供所选购买方案');
        }
        if (!in_array($channel, PaymentConfig::enabledChannels(), true)) {
            throw new RuntimeException('所选支付方式暂不可用');
        }
        $grant = $this->activeGrant((int) $plugin['id'], $memberId);
        if ($grant !== null && $grant['expires_at'] === null) {
            throw new RuntimeException('你已永久拥有该插件，无需再次购买');
        }
        $this->closeExpired();
        $reusable = Db::name('market_order')
            ->where('member_id', $memberId)->where('plugin_id', $plugin['id'])->where('plan', $plan)->where('channel', $channel)
            ->where('status', 'pending')->where('amount', $plans[$plan])->where('expire_at', '>', date('Y-m-d H:i:s', time() + 300))
            ->order('id', 'desc')->find();
        if ($reusable) {
            return $reusable;
        }
        $now = time();
        $order = [
            'order_no' => date('YmdHis', $now) . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT) . str_pad((string) ($memberId % 10000), 4, '0', STR_PAD_LEFT),
            'member_id' => $memberId,
            'plugin_id' => (int) $plugin['id'],
            'plan' => $plan,
            'amount' => $plans[$plan],
            'subject' => mb_substr($plugin['name'] . ' - ' . self::PLANS[$plan], 0, 120),
            'status' => 'pending',
            'channel' => $channel,
            'expire_at' => date('Y-m-d H:i:s', $now + self::PAY_WINDOW),
            'client_ip' => substr((string) request()->ip(), 0, 45),
            'created_at' => date('Y-m-d H:i:s', $now),
            'updated_at' => date('Y-m-d H:i:s', $now),
        ];
        $order['id'] = (int) Db::name('market_order')->insertGetId($order);
        return $order;
    }

    public function find(string $orderNo, ?int $memberId = null): ?array
    {
        if (preg_match('/^\d{20,32}$/', $orderNo) !== 1) {
            return null;
        }
        $query = Db::name('market_order')->alias('o')->join('market_plugin p', 'p.id = o.plugin_id')
            ->where('o.order_no', $orderNo)
            ->field('o.*, p.code as plugin_code, p.name as plugin_name, p.cover as plugin_cover');
        if ($memberId !== null) {
            $query->where('o.member_id', $memberId);
        }
        $order = $query->find();
        return $order ?: null;
    }

    /** 调起支付渠道；返回跳转地址或二维码内容。 */
    public function pay(array $order): array
    {
        if ($order['status'] !== 'pending') {
            throw new RuntimeException('订单不是待支付状态');
        }
        if (strtotime((string) $order['expire_at']) <= time()) {
            $this->close((string) $order['order_no'], '支付超时');
            throw new RuntimeException('订单已超时关闭，请重新下单');
        }
        $base = MarketSettings::publicUrl();
        return self::channel((string) $order['channel'])->create(
            $order,
            $base . '/order/' . $order['order_no'],
            $base . '/pay/notify/' . $order['channel']
        );
    }

    /** 渠道回调或主动查询得到的支付结果入账。 */
    public function settle(PaymentResult $result, string $channel, string $event): bool
    {
        $this->log($result->orderNo, $channel, $event, true, $result->paid ? 'paid' : 'unpaid', $result->raw);
        if (!$result->paid) {
            return false;
        }
        return $this->markPaid($result->orderNo, $channel, $result->tradeNo, $result->amount);
    }

    public function markPaid(string $orderNo, string $channel, string $tradeNo, int $amount, string $remark = ''): bool
    {
        return Db::transaction(function () use ($orderNo, $channel, $tradeNo, $amount, $remark): bool {
            $order = Db::name('market_order')->where('order_no', $orderNo)->lock(true)->find();
            if (!$order) {
                throw new RuntimeException('订单不存在');
            }
            if ($order['status'] === 'paid') {
                return true;
            }
            if ($channel !== 'manual' && $order['channel'] !== $channel) {
                throw new RuntimeException('支付渠道与订单不一致');
            }
            if ($amount !== (int) $order['amount']) {
                throw new RuntimeException('支付金额与订单金额不一致');
            }
            $grantId = $this->grant((int) $order['plugin_id'], (int) $order['member_id'], (string) $order['plan'], (int) $order['id']);
            Db::name('market_order')->where('id', $order['id'])->update([
                'status' => 'paid',
                'channel' => $channel === 'manual' ? 'manual' : $order['channel'],
                'trade_no' => $tradeNo !== '' ? mb_substr($tradeNo, 0, 64) : null,
                'paid_amount' => $amount,
                'paid_at' => date('Y-m-d H:i:s'),
                'grant_id' => $grantId,
                'remark' => mb_substr($remark, 0, 255),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        });
    }

    /** 用户回到收银台或轮询时，异步通知可能尚未到达，主动向渠道查询补偿。 */
    public function sync(array $order): array
    {
        if ($order['status'] === 'pending' && in_array($order['channel'], ['alipay', 'wechat'], true)) {
            try {
                $result = self::channel((string) $order['channel'])->query($order);
                if ($result !== null && $result->orderNo === $order['order_no']) {
                    $this->settle($result, (string) $order['channel'], 'query');
                }
            } catch (\Throwable $exception) {
                $this->log((string) $order['order_no'], (string) $order['channel'], 'query', false, mb_substr($exception->getMessage(), 0, 250), []);
            }
        }
        if ($order['status'] === 'pending' && strtotime((string) $order['expire_at']) <= time()) {
            $this->close((string) $order['order_no'], '支付超时');
        }
        return $this->find((string) $order['order_no']) ?? $order;
    }

    public function close(string $orderNo, string $reason): void
    {
        Db::name('market_order')->where('order_no', $orderNo)->where('status', 'pending')
            ->update(['status' => 'closed', 'remark' => mb_substr($reason, 0, 255), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function closeExpired(): void
    {
        Db::name('market_order')->where('status', 'pending')->where('expire_at', '<', date('Y-m-d H:i:s', time() - 600))
            ->update(['status' => 'closed', 'remark' => '支付超时', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function log(string $orderNo, string $channel, string $event, bool $verified, string $result, array $payload): void
    {
        Db::name('market_payment_log')->insert([
            'order_no' => mb_substr($orderNo, 0, 32),
            'channel' => mb_substr($channel, 0, 16),
            'event' => $event,
            'verified' => (int) $verified,
            'result' => mb_substr($result, 0, 255),
            'payload' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function activeGrant(int $pluginId, int $memberId): ?array
    {
        $grant = Db::name('market_grant')->where('plugin_id', $pluginId)->where('member_id', $memberId)->where('status', 1)->find();
        if (!$grant || ($grant['expires_at'] !== null && strtotime((string) $grant['expires_at']) <= time())) {
            return null;
        }
        return $grant;
    }

    /** 买断设为永久；按年从当前有效期（未过期时）顺延一年。 */
    private function grant(int $pluginId, int $memberId, string $plan, int $orderId): int
    {
        $existing = Db::name('market_grant')->where('plugin_id', $pluginId)->where('member_id', $memberId)->lock(true)->find();
        if ($plan === 'perpetual') {
            $expiresAt = null;
        } else {
            $base = time();
            if ($existing && (int) $existing['status'] === 1) {
                if ($existing['expires_at'] === null) {
                    return (int) $existing['id'];
                }
                $base = max($base, (int) strtotime((string) $existing['expires_at']));
            }
            $expiresAt = date('Y-m-d H:i:s', (int) strtotime('+1 year', $base));
        }
        $data = [
            'expires_at' => $expiresAt,
            'status' => 1,
            'source' => 'order',
            'plan' => $plan,
            'order_id' => $orderId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            Db::name('market_grant')->where('id', $existing['id'])->update($data);
            return (int) $existing['id'];
        }
        return (int) Db::name('market_grant')->insertGetId($data + [
            'plugin_id' => $pluginId,
            'member_id' => $memberId,
            'remark' => '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
