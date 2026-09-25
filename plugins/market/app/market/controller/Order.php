<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketOrderService;
use app\market\service\MarketStoreService;
use app\market\service\payment\PaymentConfig;
use think\annotation\route\Get;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\facade\Cache;
use think\Response;

/** 下单与收银台。 */
final class Order extends StoreController
{
    #[Post('order', ['complete_match' => true])]
    public function create(): Response
    {
        if ($redirect = $this->requireMember()) {
            return $redirect;
        }
        $code = (string) $this->request->post('code', '');
        $back = '/plugin/' . (preg_match('/^[a-z][a-z0-9]*$/', $code) === 1 ? $code : '');
        if (!$this->checkCsrf()) {
            $this->flash('页面已过期，请刷新后重试', true);
            return $this->to($back);
        }
        try {
            $order = (new MarketOrderService())->create((int) $this->member['id'], $code, (string) $this->request->post('plan', ''), (string) $this->request->post('channel', ''));
            return $this->to('/order/' . $order['order_no']);
        } catch (\RuntimeException $exception) {
            $this->flash($exception->getMessage(), true);
            return $this->to($back);
        }
    }

    #[Get('order/:no', ['complete_match' => true])]
    #[Pattern('no', '\d{20,32}')]
    public function show(string $no): Response
    {
        if ($redirect = $this->requireMember()) {
            return $redirect;
        }
        $service = new MarketOrderService();
        $order = $service->find($no, (int) $this->member['id']);
        if ($order === null) {
            return $this->errorPage('订单不存在');
        }
        $order = $service->sync($order);
        $payment = null;
        $error = '';
        if ($order['status'] === 'pending' && $order['channel'] !== 'alipay') {
            try {
                $payment = $this->payment($order);
            } catch (\RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }
        return $this->render('store/order', [
            'title' => '订单支付',
            'order' => $order + [
                'amount_text' => MarketStoreService::money((int) $order['amount']),
                'plan_text' => MarketOrderService::PLANS[$order['plan']] ?? $order['plan'],
                'channel_text' => MarketOrderService::CHANNELS[$order['channel']] ?? $order['channel'],
            ],
            'payment' => $payment,
            'paymentError' => $error,
            'mockAllowed' => $order['channel'] === 'mock' && in_array('mock', PaymentConfig::enabledChannels(), true),
        ]);
    }

    /** 支付宝电脑网站支付：生成带签名的跳转地址后 302 到支付宝收银台。 */
    #[Get('order/:no/pay', ['complete_match' => true])]
    #[Pattern('no', '\d{20,32}')]
    public function pay(string $no): Response
    {
        if ($redirect = $this->requireMember()) {
            return $redirect;
        }
        $order = (new MarketOrderService())->find($no, (int) $this->member['id']);
        if ($order === null || $order['status'] !== 'pending' || $order['channel'] !== 'alipay') {
            return $this->to('/order/' . $no);
        }
        try {
            $payment = (new MarketOrderService())->pay($order);
            return redirect($payment['url']);
        } catch (\RuntimeException $exception) {
            $this->flash($exception->getMessage(), true);
            return $this->to('/order/' . $no);
        }
    }

    #[Get('order/:no/status', ['complete_match' => true])]
    #[Pattern('no', '\d{20,32}')]
    public function status(string $no): Response
    {
        if ($this->member === null) {
            return json(['status' => 'unauthorized'], 401);
        }
        $service = new MarketOrderService();
        $order = $service->find($no, (int) $this->member['id']);
        if ($order === null) {
            return json(['status' => 'missing'], 404);
        }
        return json(['status' => $service->sync($order)['status']])->header(['Cache-Control' => 'no-store']);
    }

    #[Post('order/:no/mock', ['complete_match' => true])]
    #[Pattern('no', '\d{20,32}')]
    public function mock(string $no): Response
    {
        if ($redirect = $this->requireMember()) {
            return $redirect;
        }
        $service = new MarketOrderService();
        $order = $service->find($no, (int) $this->member['id']);
        if ($order === null || !$this->checkCsrf() || $order['channel'] !== 'mock' || !in_array('mock', PaymentConfig::enabledChannels(), true)) {
            return $this->errorPage('模拟支付不可用', 403);
        }
        $service->log($no, 'mock', 'notify', true, 'mock paid', ['member_id' => $this->member['id']]);
        $service->markPaid($no, 'mock', 'MOCK' . $no, (int) $order['amount']);
        return $this->to('/order/' . $no);
    }

    #[Post('order/:no/cancel', ['complete_match' => true])]
    #[Pattern('no', '\d{20,32}')]
    public function cancel(string $no): Response
    {
        if ($redirect = $this->requireMember()) {
            return $redirect;
        }
        $service = new MarketOrderService();
        if ($this->checkCsrf() && $service->find($no, (int) $this->member['id']) !== null) {
            $service->close($no, '用户取消');
            $this->flash('订单已取消');
        }
        return $this->to('/user?tab=orders');
    }

    /** 微信二维码在有效期内复用，避免刷新页面反复调用下单接口。 */
    private function payment(array $order): array
    {
        if ($order['channel'] === 'mock') {
            return ['type' => 'mock'];
        }
        $cacheKey = 'market:payment:' . $order['order_no'];
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $payment = (new MarketOrderService())->pay($order);
        Cache::set($cacheKey, $payment, max(60, strtotime((string) $order['expire_at']) - time()));
        return $payment;
    }
}
