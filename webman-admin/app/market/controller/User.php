<?php

declare(strict_types=1);

namespace app\market\controller;

use app\market\service\MarketAccountService;
use app\market\service\MarketOrderService;
use app\market\service\MarketSettings;
use app\market\service\MarketSigner;
use app\market\service\MarketStoreService;
use think\annotation\route\Get;
use think\annotation\route\Post;
use think\facade\Db;
use think\Response;

/** 用户主页：我的插件、我的订单、账号设置与客户端接入说明。 */
final class User extends StoreController
{
    private const TABS = ['plugins', 'orders', 'settings', 'client'];

    #[Get('user', ['complete_match' => true])]
    public function index(): Response
    {
        if ($redirect = $this->requireMember()) {
            return $redirect;
        }
        $tab = (string) $this->request->get('tab', 'plugins');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'plugins';
        $memberId = (int) $this->member['id'];
        (new MarketOrderService())->closeExpired();
        $grants = Db::name('market_grant')->alias('g')->join('market_plugin p', 'p.id = g.plugin_id')
            ->where('g.member_id', $memberId)->where('g.status', 1)
            ->field('g.expires_at, g.source, g.plan, g.created_at, p.code, p.name, p.cover, p.price_yearly, p.license_type, p.status as plugin_status')
            ->order('g.updated_at', 'desc')->select()->toArray();
        $now = time();
        foreach ($grants as &$grant) {
            $grant['cover'] = MarketStoreService::safeImage((string) $grant['cover']);
            $grant['initial'] = mb_strtoupper(mb_substr((string) $grant['name'], 0, 1));
            $grant['expired'] = $grant['expires_at'] !== null && strtotime((string) $grant['expires_at']) <= $now;
            $grant['renewable'] = $grant['expires_at'] !== null && (int) $grant['price_yearly'] > 0 && (int) $grant['plugin_status'] === 1;
        }
        unset($grant);
        $orders = Db::name('market_order')->alias('o')->join('market_plugin p', 'p.id = o.plugin_id')
            ->where('o.member_id', $memberId)
            ->field('o.order_no, o.plan, o.amount, o.status, o.channel, o.paid_at, o.created_at, o.expire_at, p.name, p.code')
            ->order('o.id', 'desc')->limit(50)->select()->toArray();
        foreach ($orders as &$order) {
            $order['amount_text'] = MarketStoreService::money((int) $order['amount']);
            $order['plan_text'] = MarketOrderService::PLANS[$order['plan']] ?? $order['plan'];
            $order['channel_text'] = MarketOrderService::CHANNELS[$order['channel']] ?? $order['channel'];
        }
        unset($order);
        $stats = [
            'plugins' => count(array_filter($grants, static fn (array $grant): bool => !$grant['expired'])),
            'orders' => count(array_filter($orders, static fn (array $order): bool => $order['status'] === 'paid')),
            'spent' => MarketStoreService::money((int) Db::name('market_order')->where('member_id', $memberId)->where('status', 'paid')->sum('paid_amount')),
        ];
        return $this->render('store/user', [
            'title' => '我的主页',
            'tab' => $tab,
            'grants' => $grants,
            'orders' => $orders,
            'stats' => $stats,
            'marketUrl' => MarketSettings::publicUrl(),
            'publicKey' => MarketSigner::publicKey(),
        ]);
    }

    #[Post('user/profile', ['complete_match' => true])]
    public function profile(): Response
    {
        if ($redirect = $this->requireMember()) {
            return $redirect;
        }
        if (!$this->checkCsrf()) {
            $this->flash('页面已过期，请刷新后重试', true);
            return $this->to('/user?tab=settings');
        }
        try {
            (new MarketAccountService())->updateProfile((int) $this->member['id'], (array) $this->request->post());
            $this->flash('资料已保存');
        } catch (\RuntimeException $exception) {
            $this->flash($exception->getMessage(), true);
        }
        return $this->to('/user?tab=settings');
    }

    #[Post('user/password', ['complete_match' => true])]
    public function password(): Response
    {
        if ($redirect = $this->requireMember()) {
            return $redirect;
        }
        if (!$this->checkCsrf()) {
            $this->flash('页面已过期，请刷新后重试', true);
            return $this->to('/user?tab=settings');
        }
        try {
            (new MarketAccountService())->changePassword(
                (int) $this->member['id'],
                (string) $this->request->post('current', ''),
                (string) $this->request->post('password', ''),
                (string) $this->request->post('password_confirm', '')
            );
            $this->flash('密码已修改，客户端插件中心需要重新登录市场账号');
        } catch (\RuntimeException $exception) {
            $this->flash($exception->getMessage(), true);
        }
        return $this->to('/user?tab=settings');
    }
}
