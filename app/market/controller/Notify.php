<?php

declare(strict_types=1);

namespace app\market\controller;

use app\BaseController;
use app\market\service\MarketOrderService;
use app\market\service\payment\PaymentConfig;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\Response;

/** 支付渠道异步通知：不走会话与 CSRF，完全依赖渠道签名验证。 */
final class Notify extends BaseController
{
    #[Post('pay/notify/:channel', ['complete_match' => true])]
    #[Pattern('channel', 'alipay|wechat')]
    public function handle(string $channel): Response
    {
        $service = new MarketOrderService();
        $gateway = MarketOrderService::channel($channel);
        if (!PaymentConfig::channel($channel)['enabled']) {
            return $gateway->notifyAck(false, 'channel disabled');
        }
        try {
            $result = $gateway->parseNotify($this->request);
        } catch (\Throwable $exception) {
            $result = null;
        }
        if ($result === null) {
            $service->log('', $channel, 'notify', false, 'signature invalid', ['body' => mb_substr((string) $this->request->getInput(), 0, 4000)]);
            return $gateway->notifyAck(false, 'invalid signature');
        }
        try {
            $service->settle($result, $channel, 'notify');
        } catch (\RuntimeException $exception) {
            $service->log($result->orderNo, $channel, 'notify', true, mb_substr('settle failed: ' . $exception->getMessage(), 0, 250), []);
            return $gateway->notifyAck(false, $exception->getMessage());
        }
        return $gateway->notifyAck(true);
    }
}
