<?php

declare(strict_types=1);

namespace app\market\service\payment;

use think\Request;
use think\Response;

interface PaymentChannel
{
    /**
     * 发起支付。
     *
     * @param array{order_no:string, amount:int, subject:string, expire_at:string} $order
     * @return array{type:'redirect', url:string}|array{type:'qrcode', code_url:string}|array{type:'mock'}
     */
    public function create(array $order, string $returnUrl, string $notifyUrl): array;

    /** 验签并解析异步通知；验签失败或不是支付成功通知时返回 null。 */
    public function parseNotify(Request $request): ?PaymentResult;

    public function notifyAck(bool $success, string $message = ''): Response;

    /** 主动查询支付结果（用户返回页面或轮询时补偿异步通知）。 */
    public function query(array $order): ?PaymentResult;
}
