<?php

declare(strict_types=1);

namespace app\market\service\payment;

use think\Request;
use think\Response;

/** 开发联调用的模拟支付：仅在 APP_DEBUG 且后台显式开启时可用，由买家在收银台点击确认。 */
final class MockChannel implements PaymentChannel
{
    public function create(array $order, string $returnUrl, string $notifyUrl): array
    {
        return ['type' => 'mock'];
    }

    public function parseNotify(Request $request): ?PaymentResult
    {
        return null;
    }

    public function notifyAck(bool $success, string $message = ''): Response
    {
        return response('', 404);
    }

    public function query(array $order): ?PaymentResult
    {
        return null;
    }
}
